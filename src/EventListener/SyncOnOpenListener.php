<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Mail\SyncOnOpen;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * After the start page, the inbox or a live counter poll was answered, fetch the user's accounts if they are due.
 */
#[AsEventListener(KernelEvents::TERMINATE)]
final readonly class SyncOnOpenListener
{
    /** Start page, inbox and the live counter poll (every minute while a tab is visible). */
    private const array ROUTES = ['home', 'mail_inbox', 'mail_all', 'counts'];

    public function __construct(private SyncOnOpen $syncOnOpen, private Security $security)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || !$this->syncOnOpen->isEnabled() || !$request->isMethod('GET')
            || !$event->getResponse()->isSuccessful() || !\in_array($route, self::ROUTES, true)) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->syncOnOpen->run($user);
        }
    }
}
