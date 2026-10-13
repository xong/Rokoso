<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\PushSubscription;

/**
 * Delivers encrypted payloads to the push services of the browsers ({@see WebPushGateway}; in tests a fake).
 */
interface PushGateway
{
    public function isEnabled(): bool;

    public function getPublicKey(): string;

    /**
     * @param list<array{0: PushSubscription, 1: string}> $messages subscription and JSON payload
     *
     * @return array{sent: int, expired: list<string>, errors: list<string>} delivered messages, endpoints that no longer exist and reasons for other failures
     */
    public function send(array $messages): array;
}
