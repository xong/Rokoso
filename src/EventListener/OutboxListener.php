<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Mail\Outbox;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends due mails from the outbox after the response went out, so no cron job is needed for "undo send".
 */
#[AsEventListener(KernelEvents::TERMINATE)]
final readonly class OutboxListener
{
    public function __construct(private Outbox $outbox)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if ($event->isMainRequest() && $this->outbox->getDelay() > 0) {
            $this->outbox->sendDue();
        }
    }
}
