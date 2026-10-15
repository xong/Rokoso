<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\Meeting;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Saving edit forms in the background (autosave-form controller → AutosaveListener).
 */
final class AutosaveTest extends AppTestCase
{
    private const array HEADERS = ['HTTP_X_AUTOSAVE' => '1'];

    private User $admin;
    private User $member;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->member = $this->createUser();
        $this->org = $this->createOrganization($this->admin);
        $this->org->addMember($this->member, OrganizationRole::Member);
        $this->em()->flush();
        $this->login($this->admin);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function autosave(Crawler $crawler, array $values): void
    {
        $this->client->submit($crawler->selectButton('Speichern')->form($values), [], self::HEADERS);
    }

    public function testMeetingIsSavedWithoutRedirectOrFlash(): void
    {
        $meeting = (new Meeting($this->org, $this->admin))->setTitle('Vollversammlung')->setStartsAt(new \DateTimeImmutable('+14 days 19:00'));
        $this->em()->persist($meeting);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId().'/edit');
        self::assertSelectorExists('form[data-controller~="autosave-form"]');
        $this->autosave($crawler, ['meeting_form[location]' => 'Rathaus']);
        self::assertResponseStatusCodeSame(204);
        $this->em()->clear();
        self::assertSame('Rathaus', $this->em()->find(Meeting::class, $meeting->getId())?->getLocation());

        // the "Gespeichert" flash is kept for a real save, not shown on the next page
        $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorTextNotContains('body', 'Gespeichert.');

        $this->autosave($crawler, ['meeting_form[title]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->em()->clear();
        self::assertSame('Vollversammlung', $this->em()->find(Meeting::class, $meeting->getId())?->getTitle());
    }

    public function testNewItemsAreNotSavedInTheBackground(): void
    {
        $this->client->request('GET', '/meetings/new');
        self::assertSelectorNotExists('form[data-controller~="autosave-form"]');
    }

    public function testLoggedOutAutosaveIsUnauthorized(): void
    {
        $meeting = (new Meeting($this->org, $this->admin))->setTitle('Vollversammlung')->setStartsAt(new \DateTimeImmutable('+14 days 19:00'));
        $this->em()->persist($meeting);
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId().'/edit');
        $this->client->getCookieJar()->clear();

        $this->autosave($crawler, ['meeting_form[location]' => 'Rathaus']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testKnowledgeBaseKeepsOneRevisionPerEditingSession(): void
    {
        $page = (new WikiPage($this->org))->setTitle('Geschäftsordnung')->setBody('Einladung 7 Tage vorher');
        $this->em()->persist($page);
        $this->em()->persist($page->record($this->admin));
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/knowledge/'.$page->getId().'/edit');
        $this->autosave($crawler, ['wiki_page_form[body]' => 'Einladung 10 Tage']);
        self::assertResponseStatusCodeSame(204);
        $this->autosave($crawler, ['wiki_page_form[body]' => 'Einladung 14 Tage vorher']);
        $this->client->submit($crawler->selectButton('Speichern')->form(['wiki_page_form[body]' => 'Einladung 14 Tage vorher.']));
        self::assertResponseRedirects();
        self::assertSame(['Einladung 14 Tage vorher.', 'Einladung 7 Tage vorher'], $this->revisionBodies($page));

        // opening the form again starts a new revision
        $this->client->request('GET', '/knowledge/'.$page->getId().'/edit');
        $this->client->submitForm('Speichern', ['wiki_page_form[body]' => 'Einladung 21 Tage vorher']);
        self::assertCount(3, $this->revisionBodies($page));
    }

    /**
     * @return list<string>
     */
    private function revisionBodies(WikiPage $page): array
    {
        $this->em()->clear();
        $page = $this->em()->find(WikiPage::class, $page->getId());
        self::assertNotNull($page);

        return array_values(array_map(static fn ($r): string => $r->getBody(), $page->getRevisions()->toArray()));
    }

    public function testCalendarAssigneesAreNotifiedOnTheFinalSave(): void
    {
        $item = (new CalendarItem($this->admin))->setTitle('Elternabend')->setOrganization($this->org)
            ->setStartsAt(new \DateTimeImmutable('+7 days 19:00'));
        $this->em()->persist($item);
        $this->em()->flush();
        $notifications = fn (): array => $this->em()->getRepository(Notification::class)->findBy(['recipient' => $this->member->getId()]);

        $crawler = $this->client->request('GET', '/calendar/item/'.$item->getId().'/edit');
        $values = ['calendar_item_form[assignees]' => [(string) $this->member->getId()]];
        $this->autosave($crawler, $values);
        self::assertResponseStatusCodeSame(204);
        $this->em()->clear();
        self::assertCount(1, $this->em()->find(CalendarItem::class, $item->getId())?->getAssignees() ?? []);
        self::assertCount(0, $notifications());

        $this->client->submit($crawler->selectButton('Speichern')->form($values));
        self::assertResponseRedirects();
        self::assertCount(1, $notifications());
    }
}
