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
        self::assertSelectorTextContains('table', 'Elternabend');
        self::assertSelectorExists('a[href="/calendar/week/2026/42"]');
        self::assertSelectorExists('a[href="/calendar/day/2026-10-14"]');

        $this->client->request('GET', '/calendar/week/2026/42');
        self::assertSelectorTextContains('main', '19:00–21:00');
        $this->client->request('GET', '/calendar/day/2026-10-14');
        self::assertSelectorTextContains('main', 'Elternabend');
        $this->client->request('GET', '/calendar/day/2026-10-15');
        self::assertSelectorTextNotContains('main', 'Elternabend');
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
