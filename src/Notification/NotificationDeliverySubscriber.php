<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Delivers notifications after the response has been sent (or the command has finished).
 */
final readonly class NotificationDeliverySubscriber
{
    public function __construct(private NotificationCenter $center)
    {
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function deliver(): void
    {
        $this->center->deliverPending();
    }
}
