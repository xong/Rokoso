<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\ForumBoard;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class NavigationTest extends AppTestCase
{
    public function testAnonymousUsersAreSentToLogin(): void
    {
        $this->client->request('GET', '/mail');

        self::assertResponseRedirects('/login');
    }

    public function testHomeShowsTodayOverview(): void
    {
        $this->login();
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Heute');
        self::assertSelectorExists('nav a[href="/"][aria-current="page"]');
    }

    public function testCollapsedNavigationIsRenderedFromCookie(): void
    {
        $this->login();
        $this->client->getCookieJar()->set(new Cookie('nav_collapsed', '1'));
        $this->client->request('GET', '/profile');

        self::assertSelectorExists('[data-controller~="shell"][data-collapsed="true"]');
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

    public function testSidebarIsGroupedWithComposeMenuAndBottomBar(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', '/');

        self::assertSelectorTextContains('#nav-nav-group_plan', 'Planen');
        self::assertSelectorExists('ul[aria-labelledby="nav-nav-group_admin"] a[href="/organizations"]');
        $compose = $crawler->filter('details[data-controller="dropdown"] summary')->first();
        self::assertSame('Verfassen', trim($compose->text()));
        self::assertCount(1, $crawler->filter('details a[href="/forum/topic/new"]'));
        self::assertCount(1, $crawler->filter('details a[href="/calendar/new?type=task"]'));
        self::assertSame('Umfrage', trim($crawler->filter('details a[href="/surveys/new"]')->text()));
        self::assertSelectorExists('nav[aria-label="Schnellnavigation"] button[data-action="shell#open"][aria-expanded="false"]');
    }

    public function testAdminPagesHighlightAdministration(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $crawler = $this->client->request('GET', '/organizations');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav a[href="/organizations"][aria-current="page"]');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/organizations/%d#accounts-heading"]', $org->getId())));
        self::assertCount(0, $crawler->filter('#main-nav a[href="/mail"][aria-current="page"]'));
    }

    public function testNewTopicChoosesBoard(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->client->request('GET', '/forum/topic/new');
        self::assertSelectorExists('a[href="/forum/board/new"]');

        // the request resets the entity manager
        $org = $this->em()->find(Organization::class, $org->getId());
        $user = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($org);
        self::assertNotNull($user);
        $board = (new ForumBoard($org, $user))->setName('Allgemeines');
        $this->em()->persist($board);
        $this->em()->flush();
        $this->client->request('GET', '/forum/topic/new');
        self::assertResponseRedirects();
        self::assertStringContainsString((string) $board->getId(), (string) $this->client->getResponse()->headers->get('Location'));

        $org = $this->em()->find(Organization::class, $org->getId());
        $user = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($org);
        self::assertNotNull($user);
        $second = (new ForumBoard($org, $user))->setName('Schulen');
        $this->em()->persist($second);
        $this->em()->flush();
        $this->client->request('GET', '/forum/topic/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Schulen');
        self::assertSelectorTextContains('main', 'Mit @Name holst du jemanden dazu');
    }

    public function testCreatePagesExplainWhatTheyAreFor(): void
    {
        $this->createMailAccount($this->createOrganization($this->login()));
        $pages = [
            '/mail/new' => 'gemeinsamen Eingang',
            '/meetings/new' => 'Tagesordnung',
            '/polls/new' => 'Umlaufbeschluss',
            '/surveys/new' => 'ohne Konto',
        ];
        foreach ($pages as $url => $text) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSelectorTextContains('main p.max-w-prose', $text, $url);
        }

        // both intros are rendered, CSS shows the one of the chosen type
        $crawler = $this->client->request('GET', '/calendar/new?type=task');
        self::assertCount(2, $crawler->filter('main p.max-w-prose'));
        self::assertSelectorTextContains('main p.max-w-prose.hidden', 'Verantwortliche werden benachrichtigt');
    }

    public function testEmptyStatesOfferNextStep(): void
    {
        $this->login();
        $this->client->request('GET', '/contacts');

        self::assertSelectorExists('a[href="/contacts/new"]');
    }

    public function testSetupChecklistTracksProgressAndCanBeHidden(): void
    {
        $user = $this->login();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('section[aria-labelledby="setup-heading"]', '0 von 5');
        self::assertSelectorExists('section[aria-labelledby="setup-heading"] a[href="/organizations/new"]');

        $org = $this->createOrganization($user);
        $event = (new CalendarItem($user))->setTitle('Treffen')->setOrganization($org)->setStartsAt(new \DateTimeImmutable('tomorrow 18:00'));
        $this->em()->persist($event);
        $this->em()->flush();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('section[aria-labelledby="setup-heading"]', '2 von 5');

        $this->client->submitForm('Ausblenden');
        $this->client->followRedirect();
        self::assertSelectorNotExists('section[aria-labelledby="setup-heading"]');
    }

    public function testMembersGetTheirOwnFirstSteps(): void
    {
        $admin = $this->createUser('admin@example.org', 'Ada Admin');
        $org = $this->createOrganization($admin);
        $member = $this->createUser();
        $org->addMember($member, OrganizationRole::Member);
        $this->em()->flush();
        $this->login($member);

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('section[aria-labelledby="setup-heading"]', '0 von 3');
        self::assertSelectorExists('section[aria-labelledby="setup-heading"] a[href="/profile/security"]');
        self::assertSelectorExists('section[aria-labelledby="setup-heading"] a[href="/help/erste-schritte"]');
        self::assertSelectorNotExists('section[aria-labelledby="setup-heading"] a[href="/organizations/new"]');

        $this->client->request('GET', '/profile/calendar');
        $this->client->submitForm('Abo-Link erzeugen');
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('section[aria-labelledby="setup-heading"]', '1 von 3');
    }
}
