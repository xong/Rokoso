<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\BasicAuthListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;

final class BasicAuthListenerTest extends TestCase
{
    private function handle(string $credentials, string $path, ?string $user = null, ?string $password = null): ?int
    {
        $server = null === $user ? [] : ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $password ?? ''];
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create($path, server: $server), HttpKernelInterface::MAIN_REQUEST);
        (new BasicAuthListener(new IdentityTranslator(), $credentials))->onRequest($event);

        return $event->getResponse()?->getStatusCode();
    }

    public function testOffWithoutCredentials(): void
    {
        self::assertNull($this->handle('', '/login'));
    }

    public function testAsksForPasswordAndAcceptsTheRightOne(): void
    {
        self::assertSame(401, $this->handle('stage:geheim', '/login'));
        self::assertSame(401, $this->handle('stage:geheim', '/login', 'stage', 'falsch'));
        self::assertSame(401, $this->handle('stage:geheim', '/login', 'andere', 'geheim'));
        self::assertNull($this->handle('stage:geheim', '/login', 'stage', 'geheim'));
    }

    public function testDeployHookAndCronStayReachable(): void
    {
        self::assertNull($this->handle('stage:geheim', '/_deploy'));
        self::assertNull($this->handle('stage:geheim', '/_cron/token'));
    }
}
