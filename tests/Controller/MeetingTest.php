<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AgendaItem;
use App\Entity\CalendarItem;
use App\Entity\Meeting;
use App\Entity\Organization;
use App\Entity\Resolution;
use App\Entity\User;
use App\Enum\AttendanceStatus;
use App\Enum\MeetingStatus;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\Mime\Email;

/**
 * Committee work: meetings with agenda, proposals, invitations, attendance/quorum, minutes and resolutions.
 */
final class MeetingTest extends AppTestCase
{
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
    }

    private function meeting(string $title = 'Vollversammlung', string $start = '+14 days 19:00'): Meeting
    {
        $meeting = (new Meeting($this->org, $this->admin))->setTitle($title)->setStartsAt(new \DateTimeImmutable($start));
        $this->em()->persist($meeting);
        $this->em()->flush();

        return $meeting;
    }

    private function item(Meeting $meeting, string $title, int $minutes = 10): AgendaItem
    {
        $meeting->addAgendaItem($item = (new AgendaItem($meeting))->setTitle($title)->setDurationMinutes($minutes));
        $this->em()->flush();

        return $item;
    }

    public function testCreateMeetingSyncsCalendarEvent(): void
    {
        $this->login($this->admin);
        $this->client->request('GET', '/meetings/new');
        $this->client->submitForm('Speichern', [
            'meeting_form[title]' => 'Vollversammlung Oktober',
            'meeting_form[organization]' => (string) $this->org->getId(),
            'meeting_form[startsAt]' => '2026-10-20T19:00',
            'meeting_form[location]' => 'Rathaus',
            'meeting_form[minuteTaker]' => (string) $this->member->getId(),
        ]);
        self::assertResponseRedirects();
        $this->em()->clear();
        $meeting = $this->em()->getRepository(Meeting::class)->findOneBy(['title' => 'Vollversammlung Oktober']);
        self::assertNotNull($meeting);
        self::assertSame(MeetingStatus::Planned, $meeting->getStatus());
        self::assertSame($this->member->getId(), $meeting->getMinuteTaker()?->getId());

        $event = $this->em()->getRepository(CalendarItem::class)->findOneBy(['meeting' => $meeting]);
        self::assertNotNull($event);
        self::assertSame('Vollversammlung Oktober', $event->getTitle());
        self::assertSame('Rathaus', $event->getLocation());

        $this->client->request('GET', '/meetings');
        self::assertSelectorTextContains('main', 'Vollversammlung Oktober');
    }

    public function testAgendaOrderingAndProposals(): void
    {
        $meeting = $this->meeting();
        $first = $this->item($meeting, 'Begrüßung', 5);
        $second = $this->item($meeting, 'Schulwege', 30);

        // A member proposes a topic
        $this->login($this->member);
        $this->client->request('GET', '/meetings/'.$meeting->getId().'/agenda/new');
        self::assertSelectorTextContains('main', 'Sitzungsleitung');
        $this->client->submitForm('Thema vorschlagen', ['agenda_item_form[title]' => 'Schulobst']);
        self::assertResponseRedirects();
        $proposal = $this->em()->getRepository(AgendaItem::class)->findOneBy(['title' => 'Schulobst']);
        self::assertNotNull($proposal);
        self::assertTrue($proposal->isProposed());

        // Members cannot reorder
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorTextContains('main', 'Vorschläge');
        self::assertCount(0, $crawler->filter('button[aria-label="„Schulwege“ nach oben"]'));
        $this->client->request('GET', '/meetings/agenda/'.$second->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        // The admin moves an item up and accepts the proposal
        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId());
        $this->client->submit($crawler->filter('button[aria-label="„Schulwege“ nach oben"]')->form());
        self::assertResponseRedirects();
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId());
        $this->client->submit($crawler->selectButton('Übernehmen')->form());
        self::assertResponseRedirects();

        $this->em()->clear();
        $meeting = $this->em()->find(Meeting::class, $meeting->getId());
        self::assertNotNull($meeting);
        self::assertSame(['Schulwege', 'Begrüßung', 'Schulobst'], array_map(static fn (AgendaItem $i): string => $i->getTitle(), $meeting->getAgenda()));
        self::assertSame(35, $meeting->getPlannedMinutes());
        self::assertNotSame($first->getId(), $second->getId());
    }

    public function testInvitationSendsMailsWithIcs(): void
    {
        $meeting = $this->meeting()->setGuestEmails("gast@example.org\nmember@example.org");
        $this->item($meeting, 'Begrüßung');

        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId());
        $this->client->submit($crawler->selectButton('Einladung verschicken')->form());
        self::assertResponseRedirects();
        self::assertEmailCount(4);

        $recipients = [];
        foreach (self::getMailerMessages() as $message) {
            self::assertInstanceOf(Email::class, $message);
            $recipients[] = $message->getTo()[0]->getAddress();
            self::assertSame('owner@example.org', $message->getReplyTo()[0]->getAddress());
            $attachments = $message->getAttachments();
            self::assertCount(1, $attachments);
            self::assertStringContainsString('BEGIN:VEVENT', $attachments[0]->getBody());
            self::assertStringContainsString('SUMMARY:Vollversammlung', $attachments[0]->getBody());
        }
        $recipients = array_values(array_unique($recipients));
        sort($recipients);
        self::assertSame(['anna@example.org', 'gast@example.org', 'member@example.org', 'owner@example.org'], $recipients);

        $this->em()->clear();
        $meeting = $this->em()->find(Meeting::class, $meeting->getId());
        self::assertSame(MeetingStatus::Invited, $meeting?->getStatus());
        self::assertNotNull($meeting->getInvitedAt());

        $this->client->request('GET', '/meetings/'.$meeting->getId().'/meeting.ics');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/calendar', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testAttendanceQuorumAndVotingRight(): void
    {
        $third = $this->createUser('third@example.org', 'Tom Dritter');
        $guest = $this->createUser('guest@example.org', 'Gabi Gast');
        $this->org->addMember($third, OrganizationRole::Member);
        $this->org->addMember($guest, OrganizationRole::Member)->setVotingRight(false);
        $this->em()->flush();
        $meeting = $this->meeting('Sitzung', '-1 day 19:00');

        $this->login($this->admin);
        $this->client->request('GET', '/meetings/'.$meeting->getId().'/attendance');
        self::assertSelectorTextContains('main', 'ohne Stimmrecht');
        $this->client->submitForm('Speichern', [
            'attendance['.$this->admin->getId().']' => 'present',
            'attendance['.$this->member->getId().']' => 'excused',
            'attendance['.$guest->getId().']' => 'present',
        ]);
        self::assertResponseRedirects();

        $this->em()->clear();
        $meeting = $this->em()->find(Meeting::class, $meeting->getId());
        self::assertNotNull($meeting);
        self::assertSame(MeetingStatus::Held, $meeting->getStatus());
        $quorum = $meeting->getQuorum();
        self::assertSame(['voting' => 3, 'present' => 1, 'required' => 2, 'reached' => false], $quorum);
        self::assertSame(AttendanceStatus::Excused, $meeting->getAttendance($this->member)?->getStatus());

        $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorTextContains('main', 'Nicht beschlussfähig: 1 von 3');

        // Grant the voting right on the membership page
        $membership = $this->org->findMembership($guest);
        $this->client->request('GET', '/organizations/'.$this->org->getId().'/members/'.$membership?->getId().'/edit');
        $this->client->submitForm('Speichern', ['membership_form[votingRight]' => '1']);
        self::assertResponseRedirects();
        $this->em()->clear();
        $meeting = $this->em()->find(Meeting::class, $meeting->getId());
        self::assertSame(['voting' => 4, 'present' => 2, 'required' => 2, 'reached' => true], $meeting?->getQuorum());
    }

    public function testMinutesApprovalLocksEditing(): void
    {
        $meeting = $this->meeting('Sitzung', '-1 day 19:00')->setMinuteTaker($this->member);
        $item = $this->item($meeting, 'Schulwege');

        // The minute taker writes the minutes
        $this->login($this->member);
        $this->client->request('GET', '/meetings/'.$meeting->getId().'/minutes');
        $this->client->submitForm('Speichern', [
            'minutes['.$item->getId().']' => 'Ortsbegehung geplant.',
            'notes' => 'Ende 21 Uhr.',
        ]);
        self::assertResponseRedirects();

        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorTextContains('main', 'Ortsbegehung geplant.');
        $this->client->submit($crawler->selectButton('Protokoll genehmigen')->form());
        self::assertResponseRedirects();

        $this->client->request('GET', '/meetings/'.$meeting->getId().'/minutes');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'nicht mehr geändert');
        self::assertSelectorTextContains('main', 'Genehmigt von Eva Owner');

        $this->client->request('GET', '/meetings/'.$meeting->getId().'/print');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ortsbegehung geplant.');
    }

    public function testResolutionsAreNumberedAndSearchable(): void
    {
        $meeting = $this->meeting('Sitzung', '2026-09-10 19:00');
        $item = $this->item($meeting, 'Zebrastreifen');

        $this->login($this->admin);
        foreach (['Antrag Zebrastreifen' => 'Die SEV beantragt einen Zebrastreifen.', 'Budget Elternabend' => 'Bis zu 150 Euro.'] as $title => $text) {
            $this->client->request('GET', '/resolutions/new?agenda='.$item->getId());
            $this->client->submitForm('Speichern', [
                'resolution_form[title]' => $title,
                'resolution_form[text]' => $text,
                'resolution_form[adopted]' => '1',
                'resolution_form[votesYes]' => '5',
            ]);
            self::assertResponseRedirects();
        }

        $this->em()->clear();
        $resolutions = $this->em()->getRepository(Resolution::class)->findBy([], ['id' => 'ASC']);
        self::assertSame(['2026/1', '2026/2'], array_map(static fn (Resolution $r): string => $r->getNumber(), $resolutions));
        self::assertSame($meeting->getId(), $resolutions[0]->getMeeting()?->getId());
        self::assertSame('2026-09-10', $resolutions[0]->getDecidedOn()->format('Y-m-d'));

        $crawler = $this->client->request('GET', '/resolutions?q=zebra');
        self::assertCount(1, $crawler->filter('main ul[role="list"] li'));
        self::assertSelectorTextContains('main', '2026/1 · Antrag Zebrastreifen');

        $this->client->request('GET', '/resolutions/'.$resolutions[1]->getId());
        self::assertSelectorTextContains('main', '5 Ja · 0 Nein · 0 Enthaltungen');

        // Members can read but not edit
        $this->login($this->member);
        $this->client->request('GET', '/resolutions/'.$resolutions[0]->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/resolutions/'.$resolutions[0]->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOutsiderIsForbidden(): void
    {
        $meeting = $this->meeting();
        $item = $this->item($meeting, 'Begrüßung');
        $resolution = (new Resolution($this->org, $this->admin))->setNumber('2026/1')->setTitle('Geheim')->setText('x');
        $this->em()->persist($resolution);
        $this->em()->flush();

        $this->login($this->createUser('outsider@example.org', 'Otto Außen'));
        foreach (['/meetings/'.$meeting->getId(), '/meetings/'.$meeting->getId().'/meeting.ics', '/meetings/'.$meeting->getId().'/agenda/new',
            '/meetings/'.$meeting->getId().'/print', '/resolutions/'.$resolution->getId(), '/resolutions/new?agenda='.$item->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $crawler = $this->client->request('GET', '/meetings');
        self::assertSelectorTextNotContains('main', 'Vollversammlung');
        $crawler = $this->client->request('GET', '/resolutions');
        self::assertSelectorTextNotContains('main', 'Geheim');
    }
}
