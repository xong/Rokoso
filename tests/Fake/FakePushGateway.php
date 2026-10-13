<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Notification\PushGateway;

/**
 * Collects push payloads per user id instead of sending them.
 */
final class FakePushGateway implements PushGateway
{
    /** @var list<array{user: int|null, payload: array<string, mixed>}> */
    public array $sent = [];

    /** Answer of the push service when set: nothing is delivered. */
    public ?string $failure = null;

    public function isEnabled(): bool
    {
        return true;
    }

    public function getPublicKey(): string
    {
        return 'test-key';
    }

    public function send(array $messages): array
    {
        if (null !== $this->failure) {
            return ['sent' => 0, 'expired' => [], 'errors' => [$this->failure]];
        }
        foreach ($messages as [$subscription, $payload]) {
            /** @var array<string, mixed> $data */
            $data = json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);
            $this->sent[] = ['user' => $subscription->getUser()->getId(), 'payload' => $data];
        }

        return ['sent' => \count($messages), 'expired' => [], 'errors' => []];
    }

    /**
     * @return list<string> titles of the messages sent to the user
     */
    public function titlesFor(int $userId): array
    {
        return array_values(array_map(static fn (array $s): string => (string) $s['payload']['title'],
            array_filter($this->sent, static fn (array $s): bool => $s['user'] === $userId)));
    }
}
