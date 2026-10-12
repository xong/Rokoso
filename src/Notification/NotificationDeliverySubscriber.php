<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Delivers notifications after the response has been sent (or the command has finished).
 * Runs after the other terminate listeners, so mail fetched on open is included.
 */
final readonly class NotificationDeliverySubscriber
{
    public function __construct(private NotificationCenter $center)
    {
    }

    #[AsEventListener(KernelEvents::TERMINATE, priority: -100)]
    #[AsEventListener(ConsoleEvents::TERMINATE, priority: -100)]
    public function deliver(): void
    {
        $this->center->deliverPending();
    }
}
