<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationEmail;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Creates notifications and delivers them (instant email, web push) once they are stored.
 *
 * Callers create notifications before their flush; delivery happens on kernel/console terminate
 * ({@see NotificationDeliverySubscriber}), so nothing is sent for changes that failed to save.
 */
final class NotificationCenter
{
    /** @var list<Notification> */
    private array $pending = [];

    /** @var array<string, true> recipient|url pairs already notified in this request */
    private array $notified = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly Security $security,
        private readonly NotificationMailer $mailer,
        private readonly PushSender $push,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Notifies each recipient once per target URL and request. The actor and people without
     * access (voter attribute on the subject) are skipped.
     *
     * @param iterable<User|null>             $recipients
     * @param array{0: string, 1: mixed}|null $access     voter attribute and subject
     * @param bool                            $email      false when the caller already sends its own email
     *
     * @return list<User> notified recipients
     */
    public function notify(iterable $recipients, NotificationType $type, string $subject, string $url, ?User $actor = null, ?array $access = null, ?string $refKey = null, bool $email = true): array
    {
        $done = [];
        foreach ($recipients as $recipient) {
            if (null === $recipient || $recipient === $actor || \in_array($recipient, $done, true)) {
                continue;
            }
            $key = $recipient->getId().'|'.$url;
            if (isset($this->notified[$key])) {
                continue;
            }
            if (null !== $access && !$this->security->isGrantedForUser($recipient, $access[0], $access[1])) {
                continue;
            }
            if (null !== $refKey && $this->notifications->hasRef($recipient, $refKey)) {
                continue;
            }
            $notification = new Notification($recipient, $type, $subject, $url, $actor, $refKey);
            if (!$email) {
                $notification->markEmailed();
            }
            $this->em->persist($notification);
            $this->pending[] = $notification;
            $this->notified[$key] = true;
            $done[] = $recipient;
        }

        return $done;
    }

    /**
     * People mentioned as "@Name" in the text, among the candidates (longest names first,
     * so "@Kim Kollegin" wins over a person called "Kim").
     *
     * @param iterable<User> $candidates
     *
     * @return list<User>
     */
    public static function mentions(string $text, iterable $candidates): array
    {
        if (!str_contains($text, '@')) {
            return [];
        }
        $users = [];
        foreach ($candidates as $candidate) {
            $users[spl_object_id($candidate)] = $candidate;
        }
        uasort($users, static fn (User $a, User $b): int => mb_strlen($b->getName()) <=> mb_strlen($a->getName()));

        $found = [];
        foreach ($users as $user) {
            $name = trim($user->getName());
            if ('' === $name) {
                continue;
            }
            $pattern = '/(?<![\p{L}\p{N}.])@'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu';
            if (1 === preg_match($pattern, $text)) {
                $found[] = $user;
                $text = (string) preg_replace($pattern, '', $text);
            }
        }

        return $found;
    }

    /**
     * Sends instant emails and push messages for notifications stored in this request.
     */
    public function deliverPending(): void
    {
        $pending = array_values(array_filter($this->pending, static fn (Notification $n): bool => null !== $n->getId()));
        $this->pending = [];
        if ([] === $pending) {
            return;
        }

        foreach ($pending as $notification) {
            try {
                if (NotificationEmail::Instant === $notification->getRecipient()->getNotificationEmail() && !$notification->isRead() && null === $notification->getEmailedAt()) {
                    $this->mailer->sendInstant($notification);
                    $notification->markEmailed();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Notification mail failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        $this->push->send($pending);
        $this->em->flush();
    }

    /**
     * Notifications created so far in this request (for tests and commands).
     *
     * @return list<Notification>
     */
    public function getPending(): array
    {
        return $this->pending;
    }
}
