<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MessageEventType;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Status changes on one or several messages (single toolbar actions, bulk actions, undo).
 * Every change is written to the message history.
 */
final readonly class MessageActions
{
    public const array ACTIONS = ['done', 'reopen', 'trash', 'restore', 'read', 'unread', 'assign_me', 'unassign_me', 'project', 'snooze', 'unsnooze'];

    /** Action that reverts another one (for the undo toast). */
    public const array INVERSE = [
        'done' => 'reopen',
        'reopen' => 'done',
        'trash' => 'restore',
        'restore' => 'trash',
        'read' => 'unread',
        'unread' => 'read',
        'assign_me' => 'unassign_me',
        'unassign_me' => 'assign_me',
        'snooze' => 'unsnooze',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ReadTracker $readTracker,
        private MessageRepository $messages,
        private ParticipantResolver $participants,
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
     * "Erledigt" closes the whole conversation.
     *
     * @param list<Message> $messages
     *
     * @return list<Message>
     */
    private function expand(string $action, array $messages, User $user): array
    {
        if ('done' !== $action) {
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

    private function applyOne(string $action, Message $message, User $user, ?int $projectId, ?\DateTimeImmutable $until): bool
    {
        switch ($action) {
            case 'done':
                if ($message->isDone()) {
                    return false;
                }
                $message->markDone($user)->log(MessageEventType::Done, $user);

                return true;
            case 'reopen':
                if (!$message->isDone()) {
                    return false;
                }
                $message->reopen()->log(MessageEventType::Reopened, $user);

                return true;
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
                $message->snooze($until)->log(MessageEventType::Snoozed, $user, $until->format('d.m.Y H:i'));

                return true;
            case 'unsnooze':
                if (null === $message->getSnoozedUntil()) {
                    return false;
                }
                $message->snooze(null)->log(MessageEventType::Snoozed, $user);

                return true;
        }

        return false;
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
