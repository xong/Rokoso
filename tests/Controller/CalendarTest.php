<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Calendar\CalendarService;
use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\OrganizationRole;
use App\Enum\Recurrence;
use App\Tests\AppTestCase;

final class CalendarTest extends AppTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->login();
    }

    public function testCreateEventAndSeeItInMonthWeekAndDay(): void
    {
        $org = $this->createOrganization($this->user);
        $this->client->request('GET', '/calendar/new?date=2026-10-14');
        $this->client->submitForm('Speichern', [
            'calendar_item_form[title]' => 'Elternabend',
            'calendar_item_form[startsAt]' => '2026-10-14T19:00',
            'calendar_item_form[endsAt]' => '2026-10-14T21:00',
            'calendar_item_form[location]' => 'Aula',
            'calendar_item_form[organization]' => (string) $org->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Aula');

        $this->client->request('GET', '/calendar?month=2026-10');
        self::assertResponseIsSuccessful();
        // Mini month: KW links to the week, days to the day view, entries as count
        self::assertSelectorExists('table a[href="/calendar/week/2026/42"]');
        self::assertSelectorExists('table a[href="/calendar/day/2026-10-14"][aria-label$="1 Eintrag"]');
        self::assertSelectorNotExists('table a[href="/calendar/day/2026-10-15"][aria-label*="Eintrag"]');

        $this->client->request('GET', '/calendar/week/2026/42');
        self::assertSelectorTextContains('[aria-labelledby="detail-heading"]', '19:00–21:00');
        $this->client->request('GET', '/calendar/day/2026-10-14');
        self::assertSelectorTextContains('[aria-labelledby="detail-heading"]', 'Elternabend');
        $this->client->request('GET', '/calendar/day/2026-10-15');
        self::assertSelectorTextNotContains('[aria-labelledby="detail-heading"]', 'Elternabend');
    }

    public function testOverlappingEntriesShareTheColumn(): void
    {
        foreach (['Sitzung' => ['10:00', '12:00'], 'Telefonat' => ['11:00', '11:30'], 'Mittag' => ['13:00', '14:00']] as $title => [$from, $to]) {
            $this->em()->persist((new CalendarItem($this->user))->setTitle($title)
                ->setStartsAt(new \DateTimeImmutable('2026-10-14 '.$from))->setEndsAt(new \DateTimeImmutable('2026-10-14 '.$to)));
        }
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/calendar/day/2026-10-14');
        $styles = [];
        foreach ($crawler->filter('li[style*="width"]') as $li) {
            \assert($li instanceof \DOMElement);
            $styles[trim($li->textContent)] = $li->getAttribute('style');
        }
        self::assertCount(3, $styles);
        self::assertStringContainsString('width: 50%', $this->styleFor($styles, 'Sitzung'));
        self::assertStringContainsString('left: 50%', $this->styleFor($styles, 'Telefonat'));
        self::assertStringContainsString('width: 100%', $this->styleFor($styles, 'Mittag'));
    }

    /**
     * @param array<string, string> $styles
     */
    private function styleFor(array $styles, string $title): string
    {
        foreach ($styles as $text => $style) {
            if (str_contains($text, $title)) {
                return $style;
            }
        }
        self::fail('No entry '.$title);
    }

    public function testStartPageShowsCurrentWeekAndUpcoming(): void
    {
        $this->em()->persist((new CalendarItem($this->user))->setTitle('Bald')->setStartsAt(new \DateTimeImmutable('+2 days 10:00')));
        $this->em()->flush();

        $this->client->request('GET', '/calendar');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#upcoming-heading + ul', 'Bald');
        self::assertSelectorTextContains('main', 'KW '.(int) date('W'));
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->client->request('GET', '/calendar/new');
        $this->client->submitForm('Speichern', [
            'calendar_item_form[title]' => 'Falsch',
            'calendar_item_form[startsAt]' => '2026-10-14T19:00',
            'calendar_item_form[endsAt]' => '2026-10-14T18:00',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testWeeklyRecurrenceExpands(): void
    {
        $item = (new CalendarItem($this->user))->setTitle('Vorstandstreffen')
            ->setStartsAt(new \DateTimeImmutable('2026-10-05 18:00'))
            ->setRecurrence(Recurrence::Weekly)
            ->setRecurrenceUntil(new \DateTimeImmutable('2026-10-26'));
        $this->em()->persist($item);
        $this->em()->flush();

        $occurrences = static::getContainer()->get(CalendarService::class)
            ->occurrences($this->user, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-11-01'));
        self::assertSame(['05.10.', '12.10.', '19.10.', '26.10.'], array_map(static fn ($o): string => $o->start->format('d.m.'), $occurrences));
    }

    public function testMonthlyOnTheSecondAndLastWeekday(): void
    {
        $this->client->request('GET', '/calendar/new');
        $this->client->submitForm('Speichern', [
            'calendar_item_form[title]' => 'Stammtisch',
            'calendar_item_form[startsAt]' => '2026-10-08T19:00',
            'calendar_item_form[recurrence]' => Recurrence::MonthlyWeekday->value,
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Monatlich am 2. Donnerstag');

        $item = $this->em()->getRepository(CalendarItem::class)->findOneBy(['title' => 'Stammtisch']);
        self::assertNotNull($item);
        self::assertSame('FREQ=MONTHLY;INTERVAL=1;BYDAY=2TH', $item->getRrule());
        $occurrences = static::getContainer()->get(CalendarService::class)
            ->occurrences($this->user, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2027-01-01'));
        self::assertSame(['08.10.', '12.11.', '10.12.'], array_map(static fn ($o): string => $o->start->format('d.m.'), $occurrences));

        // "last Thursday" only fits a start in the last week of the month
        $item->setStartsAt(new \DateTimeImmutable('2026-10-29 19:00'))->setRecurrence(Recurrence::MonthlyLast);
        self::assertSame('FREQ=MONTHLY;INTERVAL=1;BYDAY=-1TH', $item->getRrule());
        self::assertTrue($item->occursOn(new \DateTimeImmutable('2026-11-26')));
        self::assertFalse($item->occursOn(new \DateTimeImmutable('2026-11-19')));
        $crawler = $this->client->request('GET', '/calendar/item/'.$item->getId().'/edit');
        $this->client->submit($crawler->selectButton('Speichern')->form([
            'calendar_item_form[startsAt]' => '2026-10-22T19:00',
            'calendar_item_form[recurrence]' => Recurrence::MonthlyLast->value,
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'letzten Woche des Monats');
    }

    public function testTaskCanBeMarkedDone(): void
    {
        $task = (new CalendarItem($this->user))->setTitle('Protokoll schreiben')->setType(CalendarItemType::Task);
        $this->em()->persist($task);
        $this->em()->flush();

        $this->client->request('GET', '/calendar/item/'.$task->getId());
        $this->client->submitForm('Erledigt');
        $this->em()->clear();
        self::assertTrue($this->em()->find(CalendarItem::class, $task->getId())?->isDone());
    }

    public function testVisibility(): void
    {
        $other = $this->createUser('other@example.org', 'Other');
        $org = $this->createOrganization($other);
        $item = (new CalendarItem($other))->setTitle('Geheim')->setOrganization($org);
        $this->em()->persist($item);
        $this->em()->flush();

        $this->client->request('GET', '/calendar/item/'.$item->getId());
        self::assertResponseStatusCodeSame(403);

        $org->addMember($this->em()->find(User::class, $this->user->getId()) ?? $this->user, OrganizationRole::Member);
        $this->em()->flush();
        $this->client->request('GET', '/calendar/item/'.$item->getId());
        self::assertResponseIsSuccessful();
    }
}
