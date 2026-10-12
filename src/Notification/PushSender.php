<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\PushCategory;
use App\Repository\NotificationRepository;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Web push to the recipients' devices (PWA), only for the categories they chose in their profile.
 * The payload carries the number of unread notifications for the badge on the app icon.
 */
final readonly class PushSender
{
    public function __construct(
        private PushGateway $gateway,
        private PushSubscriptionRepository $subscriptions,
        private NotificationRepository $notifications,
        private EntityManagerInterface $em,
        private NotificationText $text,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->gateway->isEnabled();
    }

    public function getPublicKey(): string
    {
        return $this->gateway->getPublicKey();
    }

    public function hasSubscription(User $user): bool
    {
        return null !== $this->subscriptions->findOneBy(['user' => $user]);
    }

    /**
     * @param list<Notification> $notifications
     */
    public function send(array $notifications): void
    {
        $messages = [];
        foreach ($notifications as $notification) {
            if (!$notification->isRead()) {
                $messages[] = [$notification->getRecipient(), $notification->getType()->pushCategory(), $this->message($notification)];
            }
        }
        $this->deliver($messages);
    }

    /**
     * @param list<array{0: User, 1: PushCategory|null, 2: PushMessage}> $messages recipient, category (null = always) and content
     *
     * @return int delivered messages
     */
    public function deliver(array $messages): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        $queue = [];
        $badges = [];
        foreach ($messages as [$user, $category, $message]) {
            if (null !== $category && !$user->wantsPush($category)) {
                continue;
            }
            $subscriptions = $this->subscriptions->findBy(['user' => $user]);
            if ([] === $subscriptions) {
                continue;
            }
            $badge = $badges[(int) $user->getId()] ??= $this->notifications->countUnread($user);
            $payload = json_encode($message->toArray() + ['badge' => $badge], \JSON_THROW_ON_ERROR);
            foreach ($subscriptions as $subscription) {
                $queue[] = [$subscription, $payload];
            }
        }
        if ([] === $queue) {
            return 0;
        }

        $result = $this->gateway->send($queue);
        foreach ($result['expired'] as $endpoint) {
            $expired = $this->subscriptions->findByEndpoint($endpoint);
            if (null !== $expired) {
                $this->em->remove($expired);
            }
        }

        return $result['sent'];
    }

    private function message(Notification $notification): PushMessage
    {
        return new PushMessage($this->text->text($notification), $this->text->link($notification), 'rokoso-'.$notification->getId());
    }
}
