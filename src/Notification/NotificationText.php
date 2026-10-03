<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Human readable text and absolute link of a notification (bell, email, push).
 */
final readonly class NotificationText
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function text(Notification $notification): string
    {
        return $this->translator->trans($notification->getType()->label(), [
            '%actor%' => $notification->getActor()?->getName() ?? $this->translator->trans('notification.system'),
            '%subject%' => $notification->getSubject(),
        ]);
    }

    /** Link that marks the notification as read and opens its target. */
    public function link(Notification $notification): string
    {
        return $this->urls->generate('notification_open', ['id' => $notification->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
