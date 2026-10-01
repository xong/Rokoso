<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavigationTest extends WebTestCase
{
    public function testHomeRedirectsToInbox(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/mail');
    }

    #[DataProvider('navigationUrls')]
    public function testNavigationPagesRender(string $url, string $heading): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#list-heading', $heading);
        self::assertSelectorExists(sprintf('#main-nav a[aria-current][href="%s"]', $url));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function navigationUrls(): iterable
    {
        yield ['/mail', 'Eingang'];
        yield ['/mail/sent', 'Ausgang'];
        yield ['/mail/trash', 'Papierkorb'];
        yield ['/mail/new', 'Neue E-Mail'];
        yield ['/calendar', 'Kalender'];
        yield ['/contacts', 'Kontakte'];
        yield ['/projects', 'Projekte'];
        yield ['/organizations', 'Organisationen'];
    }

    public function testCollapsedNavigationIsRenderedFromCookie(): void
    {
        $client = static::createClient();
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('nav_collapsed', '1'));
        $client->request('GET', '/mail');

        self::assertSelectorExists('[data-controller="shell"][data-collapsed="true"]');
    }
}
