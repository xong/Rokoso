<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * GET filter forms send their "all" option as an empty value (?project=). InputBag::getInt() answers that
 * with 400 Bad Request, so empty query parameters are dropped before any controller reads them – an empty
 * value means the same as a missing one everywhere in the app.
 */
#[AsEventListener(KernelEvents::REQUEST, priority: 250)]
final readonly class EmptyQueryListener
{
    public function __invoke(RequestEvent $event): void
    {
        $query = $event->getRequest()->query;
        foreach ($query->all() as $name => $value) {
            if ('' === $value) {
                $query->remove((string) $name);
            }
        }
    }
}
