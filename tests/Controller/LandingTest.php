<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppTestCase;

final class LandingTest extends AppTestCase
{
    public function testLoginPageExplainsRokosoAndKeepsTheForm(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Gemeinsam arbeiten in der Elternvertretung');
        self::assertSelectorTextContains('#login-title', 'Anmelden');
        self::assertSelectorTextContains('#about', 'Was Rokoso kann');
        self::assertSelectorTextContains('#about', 'Gemeinsames Postfach');
        self::assertSelectorExists('a[href="https://github.com/xong/Rokoso"]');
        // no welcome text of the installation yet
        self::assertSelectorNotExists('main .markdown');
        self::assertCount(1, $crawler->filter('h1'));
        self::assertSelectorExists('#username[autofocus]');

        $this->client->submitForm('Anmelden', ['_username' => 'nobody@example.org', '_password' => 'falsch']);
        $this->client->followRedirect();
        self::assertSelectorExists('#login-title ~ form [role=alert]');
    }
}
