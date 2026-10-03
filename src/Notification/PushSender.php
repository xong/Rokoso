<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Web push to the recipients' devices (PWA). Inactive without VAPID keys (see `app:vapid-keys`).
 */
final readonly class PushSender
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private EntityManagerInterface $em,
        private NotificationText $text,
        private LoggerInterface $logger,
        #[Autowire(env: 'VAPID_PUBLIC_KEY')]
        private string $publicKey,
        #[Autowire(env: 'VAPID_PRIVATE_KEY')]
        private string $privateKey,
        #[Autowire(env: 'MAILER_FROM')]
        private string $contact,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->publicKey && '' !== $this->privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @param list<Notification> $notifications
     */
    public function send(array $notifications): void
    {
        if (!$this->isEnabled() || [] === $notifications) {
            return;
        }

        try {
            $subject = preg_match('/<([^>]+)>/', $this->contact, $m) ? 'mailto:'.$m[1] : 'mailto:'.$this->contact;
            $webPush = new WebPush(['VAPID' => ['subject' => $subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]], ['TTL' => 86400]);
            $queued = 0;
            foreach ($notifications as $notification) {
                $payload = json_encode([
                    'title' => $this->text->text($notification),
                    'url' => $this->text->link($notification),
                    'tag' => 'coop-'.$notification->getId(),
                ], \JSON_THROW_ON_ERROR);
                foreach ($this->subscriptions->findBy(['user' => $notification->getRecipient()]) as $subscription) {
                    $webPush->queueNotification(Subscription::create([
                        'endpoint' => $subscription->getEndpoint(),
                        'publicKey' => $subscription->getPublicKey(),
                        'authToken' => $subscription->getAuthToken(),
                    ]), $payload);
                    ++$queued;
                }
            }
            if (0 === $queued) {
                return;
            }
            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $expired = $this->subscriptions->findByEndpoint($report->getEndpoint());
                    if (null !== $expired) {
                        $this->em->remove($expired);
                    }
                } elseif (!$report->isSuccess()) {
                    $this->logger->info('Web push failed: {reason}', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Web push failed: {message}', ['message' => $e->getMessage()]);
        }
    }
}
