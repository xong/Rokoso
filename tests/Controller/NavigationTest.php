<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class NavigationTest extends AppTestCase
{
    public function testAnonymousUsersAreSentToLogin(): void
    {
        $this->client->request('GET', '/mail');

        self::assertResponseRedirects('/login');
    }

    public function testHomeRedirectsToInbox(): void
    {
        $this->login();
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/mail');
    }

    public function testCollapsedNavigationIsRenderedFromCookie(): void
    {
        $this->login();
        $this->client->getCookieJar()->set(new Cookie('nav_collapsed', '1'));
        $this->client->request('GET', '/profile');

        self::assertSelectorExists('[data-controller="shell"][data-collapsed="true"]');
        self::assertSelectorTextContains('#main-nav', 'Anna Schulz');
    }
}
