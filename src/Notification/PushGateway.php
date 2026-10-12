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
     * @return array{sent: int, expired: list<string>} delivered messages and endpoints that no longer exist
     */
    public function send(array $messages): array;
}
