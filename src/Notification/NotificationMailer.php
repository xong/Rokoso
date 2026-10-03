<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\SystemMailer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notification emails: one per notification (instant) or a digest.
 */
final readonly class NotificationMailer
{
    public function __construct(
        private SystemMailer $mailer,
        private NotificationText $text,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function sendInstant(Notification $notification): void
    {
        $recipient = $notification->getRecipient();
        $text = $this->text->text($notification);
        $this->mailer->send($recipient->getEmail(), 'email.notification.subject', 'notification', [
            'subject_params' => ['%text%' => $text],
            'text' => $text,
            'url' => $this->text->link($notification),
            'settings_url' => $this->settingsUrl(),
        ], $recipient->getName());
    }

    /**
     * @param non-empty-list<Notification> $notifications
     */
    public function sendDigest(User $recipient, array $notifications): void
    {
        $items = array_map(fn (Notification $n): array => [
            'text' => $this->text->text($n),
            'url' => $this->text->link($n),
            'date' => $n->getCreatedAt(),
        ], $notifications);
        $this->mailer->send($recipient->getEmail(), 'email.digest.subject', 'digest', [
            'subject_params' => ['%count%' => \count($items)],
            'items' => $items,
            'url' => $this->urls->generate('notification_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'settings_url' => $this->settingsUrl(),
        ], $recipient->getName());
    }

    private function settingsUrl(): string
    {
        return $this->urls->generate('profile_notifications', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
