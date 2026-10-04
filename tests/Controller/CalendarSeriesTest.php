<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Calendar\CalendarService;
use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\Recurrence;
use App\Tests\AppTestCase;
use Symfony\Component\Mime\Email;

final class CalendarSeriesTest extends AppTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->login();
    }

    private function series(): CalendarItem
    {
        $item = (new CalendarItem($this->user))->setTitle('Vorstandstreffen')->setLocation('Rathaus')
            ->setStartsAt(new \DateTimeImmutable('2026-10-05 18:00'))->setEndsAt(new \DateTimeImmutable('2026-10-05 20:00'))
            ->setRecurrence(Recurrence::Weekly)->setRecurrenceUntil(new \DateTimeImmutable('2026-10-26'));
        $this->em()->persist($item);
        $this->em()->flush();

        return $item;
    }

    /** @return list<string> */
    private function starts(): array
    {
        $this->em()->clear();

        return array_map(static fn ($o): string => $o->start->format('d.m. H:i').' '.$o->getLocation(), static::getContainer()->get(CalendarService::class)
            ->occurrences($this->em()->find(User::class, $this->user->getId()) ?? throw new \LogicException(), new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-11-01')));
    }

    public function testMoveAndCancelSingleOccurrence(): void
    {
        $item = $this->series();
        $id = $item->getId();

        $this->client->request('GET', "/calendar/item/$id?date=2026-10-12");
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '12.10.2026 18:00');
        $this->client->clickLink('Nur diesen Termin ändern');
        $this->client->submitForm('Speichern', [
            'calendar_exception_form[startsAt]' => '2026-10-13T19:00',
            'calendar_exception_form[endsAt]' => '2026-10-13T21:00',
            'calendar_exception_form[location]' => 'Schule',
        ]);
        self::assertResponseRedirects("/calendar/item/$id?date=2026-10-12");
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Geändert');
        self::assertSelectorTextContains('main', 'Abweichungen von der Serie');

        $this->client->request('GET', "/calendar/item/$id?date=2026-10-19");
        $this->client->submitForm('Diesen Termin absagen');
        self::assertResponseRedirects();

        self::assertSame(['05.10. 18:00 Rathaus', '13.10. 19:00 Schule', '26.10. 18:00 Rathaus'], $this->starts());

        // The cancelled occurrence is still reachable and can be restored
        $this->client->request('GET', "/calendar/item/$id?date=2026-10-19");
        self::assertSelectorTextContains('main', 'Abgesagt');
        $this->client->submitForm('Wie in der Serie');
        self::assertSame(['05.10. 18:00 Rathaus', '13.10. 19:00 Schule', '19.10. 18:00 Rathaus', '26.10. 18:00 Rathaus'], $this->starts());

        // Days without an occurrence of the series are not found
        $this->client->request('GET', "/calendar/item/$id/occurrence/2026-10-14");
        self::assertResponseStatusCodeSame(404);
    }

    public function testIcalSubscription(): void
    {
        $item = $this->series();
        $item->exceptionFor(new \DateTimeImmutable('2026-10-19'))->setCancelled(true);
        $this->em()->flush();

        $this->client->request('GET', '/profile/calendar');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Abo-Link erzeugen');
        $this->client->followRedirect();
        $url = (string) $this->client->getCrawler()->filter('#calendar-feed-url')->attr('value');
        self::assertMatchesRegularExpression('#/ical/[a-f0-9]{64}\.ics$#', $url);

        // Works without login
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', (string) parse_url($url, \PHP_URL_PATH));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $ics = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('X-WR-CALNAME:', $ics);
        self::assertSame(3, substr_count($ics, 'BEGIN:VEVENT'), 'expanded, cancelled one left out');
        self::assertStringNotContainsString('DTSTART:20261019', $ics);

        $this->client->request('GET', '/ical/'.str_repeat('a', 64).'.ics');
        self::assertResponseStatusCodeSame(404);

        // Switching off invalidates the link
        $this->login($this->user);
        $this->client->request('GET', '/profile/calendar');
        $this->client->submitForm('Abo abschalten');
        $this->client->request('GET', (string) parse_url($url, \PHP_URL_PATH));
        self::assertResponseStatusCodeSame(404);
    }

    public function testInviteGuestsWithIcsAttachment(): void
    {
        $item = $this->series();
        $item->exceptionFor(new \DateTimeImmutable('2026-10-19'))->setCancelled(true);
        $item->setGuestEmails("gast@example.org\nkein-gueltiger-gast");
        self::assertCount(1, static::getContainer()->get('validator')->validate($item));
        $item->setGuestEmails("gast@example.org\npresse@example.org");
        $this->em()->flush();

        $this->client->request('GET', '/calendar/item/'.$item->getId());
        self::assertSelectorTextContains('main', 'presse@example.org');
        $this->client->submitForm('Gäste einladen');
        self::assertEmailCount(2);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Einladung: Vorstandstreffen am 05.10.2026', $email->getSubject());
        $attachment = $email->getAttachments()[0];
        self::assertSame('termin.ics', $attachment->getFilename());
        $ics = $attachment->getBody();
        self::assertStringContainsString('RRULE:FREQ=WEEKLY', $ics);
        self::assertStringContainsString('EXDATE:20261019T160000Z', $ics);

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Zuletzt eingeladen');
    }
}
