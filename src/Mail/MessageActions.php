<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\BlockedSender;
use App\Entity\Message;
use App\Entity\MessageUserState;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MessageEventType;
use App\Enum\MessageType;
use App\Repository\BlockedSenderRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Status changes on one or several messages (single toolbar actions, bulk actions, undo).
 * Shared changes are written to the message history; "Erledigt" (done for me) and Wiedervorlage are personal
 * (MessageUserState, Entscheidung 84) and stay out of it.
 */
final readonly class MessageActions
{
    public const array ACTIONS = ['done', 'done_all', 'reopen', 'trash', 'restore', 'read', 'unread', 'assign_me', 'unassign_me', 'project', 'snooze', 'unsnooze', 'spam', 'not_spam', 'block'];

    /** Action that reverts another one (for the undo toast). */
    public const array INVERSE = [
        'done' => 'reopen',
        'done_all' => 'reopen',
        'reopen' => 'done',
        'trash' => 'restore',
        'restore' => 'trash',
        'read' => 'unread',
        'unread' => 'read',
        'assign_me' => 'unassign_me',
        'unassign_me' => 'assign_me',
        'snooze' => 'unsnooze',
        'spam' => 'not_spam',
        'not_spam' => 'spam',
        'block' => 'not_spam',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ReadTracker $readTracker,
        private MessageRepository $messages,
        private ParticipantResolver $participants,
        private BlockedSenderRepository $blockedSenders,
    ) {
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<Message> the messages actually changed
     */
    public function apply(string $action, array $messages, User $user, ?int $projectId = null, ?\DateTimeImmutable $until = null): array
    {
        $changed = [];
        foreach ($this->expand($action, $messages, $user) as $message) {
            if ($this->applyOne($action, $message, $user, $projectId, $until)) {
                $changed[] = $message;
            }
        }
        $this->em->flush();

        return $changed;
    }

    /**
     * "Erledigt" closes the whole conversation; "Absender sperren" blocks the sender and catches all of their mails.
     *
     * @param list<Message> $messages
     *
     * @return list<Message>
     */
    private function expand(string $action, array $messages, User $user): array
    {
        if ('block' === $action) {
            return $this->block($messages, $user);
        }
        if ('done' !== $action && 'done_all' !== $action) {
            return $messages;
        }
        $all = [];
        foreach ($messages as $message) {
            $all[$message->getId() ?? spl_object_id($message)] = $message;
            foreach ($this->messages->findThread($message, $user) as $other) {
                if (!$other->isDone() && !$other->isTrashed()) {
                    $all[$other->getId() ?? spl_object_id($other)] = $other;
                }
            }
        }

        return array_values($all);
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<Message>
     */
    private function block(array $messages, User $user): array
    {
        $all = [];
        foreach ($messages as $message) {
            $organization = $message->getOrganization();
            $address = BlockedSender::normalize($message->getFromAddress());
            if (MessageType::Email !== $message->getType() || null === $organization || '' === $address) {
                continue;
            }
            if (null === $this->blockedSenders->findOneFor($organization, $address)) {
                $this->em->persist(new BlockedSender($organization, $address, $user));
                $this->em->flush();
            }
            $all[$message->getId() ?? spl_object_id($message)] = $message;
            foreach ($this->messages->findFromSender($user, $organization, $address) as $other) {
                $all[$other->getId() ?? spl_object_id($other)] = $other;
            }
        }

        return array_values($all);
    }

    private function applyOne(string $action, Message $message, User $user, ?int $projectId, ?\DateTimeImmutable $until): bool
    {
        switch ($action) {
            case 'spam':
            case 'block':
                if ($message->isSpam() || MessageType::Email !== $message->getType()) {
                    return false;
                }
                $message->setSpam(true)->log('block' === $action ? MessageEventType::Blocked : MessageEventType::Spam, $user, 'block' === $action ? $message->getFromAddress() : null);

                return true;
            case 'not_spam':
                // Trusting the mail again also lifts a block of its sender
                $organization = $message->getOrganization();
                if (null !== $organization && null !== $blocked = $this->blockedSenders->findOneFor($organization, $message->getFromAddress())) {
                    $this->em->remove($blocked);
                }
                if (!$message->isSpam()) {
                    return false;
                }
                $message->setSpam(false)->log(MessageEventType::NotSpam, $user);

                return true;
            case 'done':
                if ($message->isDone() || true === $this->state($message, $user, false)?->isDone()) {
                    return false;
                }
                $this->state($message, $user)->setDone(true);

                return true;
            case 'done_all':
                if ($message->isDone()) {
                    return false;
                }
                $message->markDone($user)->log(MessageEventType::Done, $user);

                return true;
            case 'reopen':
                // back into my inbox: lifts my own "done" and, if set, the one for everyone
                $state = $this->state($message, $user, false);
                $changed = null !== $state && $state->isDone();
                $state?->setDone(false);
                $this->cleanUp($state);
                if ($message->isDone()) {
                    $message->reopen()->log(MessageEventType::Reopened, $user);
                    $changed = true;
                }

                return $changed;
            case 'trash':
                if ($message->isTrashed()) {
                    return false;
                }
                $message->setTrashed(true)->log(MessageEventType::Trashed, $user);

                return true;
            case 'restore':
                if (!$message->isTrashed()) {
                    return false;
                }
                $message->setTrashed(false)->log(MessageEventType::Restored, $user);

                return true;
            case 'read':
                $this->readTracker->markRead($message, $user);

                return true;
            case 'unread':
                $this->readTracker->markUnread($message, $user);

                return true;
            case 'assign_me':
                if ($message->isAssignedTo($user)) {
                    return false;
                }
                $message->addAssignee($user)->log(MessageEventType::Assigned, $user, $user->getName());

                return true;
            case 'unassign_me':
                if (!$message->isAssignedTo($user)) {
                    return false;
                }
                $message->removeAssignee($user)->log(MessageEventType::Unassigned, $user, $user->getName());

                return true;
            case 'project':
                $project = $this->project($message, $projectId);
                if ($project === $message->getProject()) {
                    return false;
                }
                $message->setProject($project)->log(MessageEventType::Project, $user, $project?->getName());

                return true;
            case 'snooze':
                if (null === $until || $until <= new \DateTimeImmutable()) {
                    return false;
                }
                $this->state($message, $user)->setDone(false)->snooze($until);
                // it has to come back into the inbox then
                if ($message->isDone()) {
                    $message->reopen()->log(MessageEventType::Reopened, $user);
                }

                return true;
            case 'unsnooze':
                $state = $this->state($message, $user, false);
                if (null === $state?->getSnoozedUntil()) {
                    return false;
                }
                $state->snooze(null);
                $this->cleanUp($state);

                return true;
        }

        return false;
    }

    /**
     * @return ($create is true ? MessageUserState : ?MessageUserState)
     */
    private function state(Message $message, User $user, bool $create = true): ?MessageUserState
    {
        $state = $this->messages->userState($user, $message);
        if (null === $state && $create) {
            $state = new MessageUserState($message, $user);
            $this->em->persist($state);
            $this->em->flush();
        }

        return $state;
    }

    private function cleanUp(?MessageUserState $state): void
    {
        if (null !== $state && $state->isEmpty()) {
            $this->em->remove($state);
            $this->em->flush();
        }
    }

    /**
     * Only projects of the message's organization (or the user's personal ones) are allowed.
     */
    private function project(Message $message, ?int $projectId): ?Project
    {
        foreach ($this->participants->projects($message) as $candidate) {
            if ($candidate->getId() === $projectId) {
                return $candidate;
            }
        }

        return null;
    }
}
