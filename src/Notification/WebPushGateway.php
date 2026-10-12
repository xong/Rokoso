<?php

declare(strict_types=1);

namespace App\Notification;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Web push with VAPID (minishlink/web-push). Inactive without keys (see `app:vapid-keys`).
 */
final readonly class WebPushGateway implements PushGateway
{
    public function __construct(
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

    public function send(array $messages): array
    {
        $result = ['sent' => 0, 'expired' => []];
        if (!$this->isEnabled() || [] === $messages) {
            return $result;
        }

        try {
            $subject = preg_match('/<([^>]+)>/', $this->contact, $m) ? 'mailto:'.$m[1] : 'mailto:'.$this->contact;
            $webPush = new WebPush(['VAPID' => ['subject' => $subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]], ['TTL' => 86400]);
            foreach ($messages as [$subscription, $payload]) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->getEndpoint(),
                    'publicKey' => $subscription->getPublicKey(),
                    'authToken' => $subscription->getAuthToken(),
                ]), $payload);
            }
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    ++$result['sent'];
                } elseif ($report->isSubscriptionExpired()) {
                    $result['expired'][] = $report->getEndpoint();
                } else {
                    $this->logger->info('Web push failed: {reason}', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Web push failed: {message}', ['message' => $e->getMessage()]);
        }

        return $result;
    }
}
