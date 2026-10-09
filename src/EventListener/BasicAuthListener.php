<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Password protection for a whole instance (e.g. staging) with BASIC_AUTH="user:password"; empty = off.
 * Lives in the app instead of the web server so it survives deployments; the deploy hook and URL cron
 * stay reachable because they have their own tokens (see docs/BETRIEB.md).
 */
final readonly class BasicAuthListener
{
    private const array OPEN_PREFIXES = ['/_deploy', '/_cron/'];

    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire('%env(BASIC_AUTH)%')]
        private string $credentials,
    ) {
    }

    /** Before routing and the firewall */
    #[AsEventListener(KernelEvents::REQUEST, priority: 300)]
    public function onRequest(RequestEvent $event): void
    {
        if ('' === $this->credentials || !$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        foreach (self::OPEN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }
        $given = ($request->getUser() ?? '').':'.($request->getPassword() ?? '');
        if (null !== $request->getUser() && hash_equals($this->credentials, $given)) {
            return;
        }

        $event->setResponse(new Response($this->translator->trans('app.basic_auth'), Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Rokoso", charset="UTF-8"',
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]));
    }
}
