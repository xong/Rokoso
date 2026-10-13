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
        $result = ['sent' => 0, 'expired' => [], 'errors' => []];
        if (!$this->isEnabled() || [] === $messages) {
            return $result;
        }

        // logged as error: prod's fingers_crossed handler drops anything below
        $fail = function (string $reason) use (&$result): void {
            $result['errors'][] = $reason;
            $this->logger->error('Web push failed: {reason}', ['reason' => $reason]);
        };

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
                    // the push service explains itself in the body (e.g. VAPID key not matching the subscription)
                    $response = $report->getResponse();
                    $fail(null !== $response
                        ? trim(\sprintf('HTTP %d %s', $response->getStatusCode(), mb_substr(trim((string) $response->getBody()), 0, 200)))
                        : mb_substr($report->getReason(), 0, 300));
                }
            }
        } catch (\Throwable $e) {
            $fail($e->getMessage());
        }

        return $result;
    }
}
