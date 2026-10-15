<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Background saving of edit forms (autosave-form controller, header X-Autosave): the controller handles the POST
 * as usual. A redirect means saved (204), a re-rendered page means invalid input (422), a redirect to the login
 * page an expired session (401). Flash messages of the request are dropped, so they do not show up later.
 * JSON answers (compose drafts) and errors pass unchanged.
 */
final class AutosaveListener
{
    public const string HEADER = 'X-Autosave';

    /** @var array<string, list<mixed>>|null */
    private ?array $flashes = null;

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function isAutosave(Request $request): bool
    {
        return $request->isMethod('POST') && $request->headers->has(self::HEADER);
    }

    #[AsEventListener(KernelEvents::REQUEST)]
    public function onRequest(RequestEvent $event): void
    {
        $this->flashes = null;
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !self::isAutosave($request) || !$request->hasPreviousSession()) {
            return;
        }
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $this->flashes = $session->getFlashBag()->peekAll();
        }
    }

    #[AsEventListener(KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !self::isAutosave($request) || $response instanceof JsonResponse) {
            return;
        }
        if ($response->isRedirection()) {
            $target = parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);
            $status = $target === $this->urls->generate('app_login') ? Response::HTTP_UNAUTHORIZED : Response::HTTP_NO_CONTENT;
        } elseif ($response->isSuccessful() || Response::HTTP_UNPROCESSABLE_ENTITY === $response->getStatusCode()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        } else {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface && $session->isStarted()) {
            $session->getFlashBag()->setAll($this->flashes ?? []);
        }
        $event->setResponse(new Response(null, $status, ['Cache-Control' => 'no-store']));
    }
}
