<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Enum\Feature;
use App\Tests\AppTestCase;

final class HelpTest extends AppTestCase
{
    public function testIndexListsAreasAndGuides(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', '/help');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Bereiche');
        self::assertSelectorTextContains('main', 'Anleitungen');
        self::assertCount(1, $crawler->filter('main a[href="/help/jemanden-einladen"]')->reduce(static fn ($a): bool => str_contains($a->attr('class') ?? '', 'card')));
        self::assertSelectorExists('#main-nav a[href="/help"][aria-current="page"]');
    }

    public function testArticleIsRenderedFromMarkdown(): void
    {
        $this->login();
        $this->client->request('GET', '/help/kontakte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#detail-heading', 'Kontakte und Verteiler');
        self::assertSelectorExists('.help-article h3');
        self::assertSelectorExists('nav a[href="/help/kontakte"][aria-current="page"]');

        $this->client->request('GET', '/help/gibt-es-nicht');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAreaPagesLinkToTheirArticle(): void
    {
        $this->login();
        $this->client->request('GET', '/contacts');

        self::assertSelectorExists('a[href="/help/kontakte"][aria-label="Hilfe: Kontakte und Verteiler"]');
    }

    public function testArticlesOfSwitchedOffAreasAreHidden(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $org->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::Forum !== $f));
        $this->em()->flush();

        $this->client->request('GET', '/help/forum');
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/help');
        self::assertCount(0, $crawler->filter('a[href="/help/forum"]'));

        $this->em()->find(Organization::class, $org->getId())?->setEnabledFeatures(Feature::cases());
        $this->em()->flush();
        $this->client->request('GET', '/help/forum');
        self::assertResponseIsSuccessful();
    }

    public function testSearchAndPaletteFindArticles(): void
    {
        $this->login();
        $this->client->request('GET', '/search', ['q' => 'Rundschreiben']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main a[href="/help/rundschreiben"]');
        self::assertSelectorExists('li[role="option"][data-on-query][data-url="/help/rundschreiben"]');
    }
}
