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

    public function testColourThemeIsChosenInProfile(): void
    {
        $this->login();
        $this->client->request('GET', '/profile');
        self::assertSelectorExists('html[data-theme="system"]');
        self::assertSelectorTextContains('fieldset legend', 'Farbschema');

        $this->client->submitForm('Speichern', ['profile_form[theme]' => 'dark']);
        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertSelectorExists('html[data-theme="dark"]');
        self::assertSelectorExists('meta[name="color-scheme"][content="dark"]');
        self::assertSelectorExists('input[name="profile_form[theme]"][value="dark"][checked]');
    }
}
