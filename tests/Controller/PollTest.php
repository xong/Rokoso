<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\ForumBoard;
use App\Entity\ForumTopic;
use App\Entity\Meeting;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Poll;
use App\Entity\PollAnswer;
use App\Entity\Resolution;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\PollKind;
use App\Poll\PollService;
use App\Tests\AppTestCase;

/**
 * Polls: open and secret votes, voting right, circular resolutions, date finding, embedding.
 */
final class PollTest extends AppTestCase
{
    private User $admin;
    private User $member;
    private Organization $org;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->member = $this->createUser();
        $this->org = $this->createOrganization($this->admin);
        $this->org->addMember($this->member, OrganizationRole::Member);
        $this->em()->flush();
    }

    /**
     * @param list<string>                                                   $labels
     * @param list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable|null}> $slots
     */
    private function poll(Poll $poll, array $labels = [], array $slots = []): Poll
    {
        self::getContainer()->get(PollService::class)->create($poll, $labels, $slots, $this->admin);
        $this->em()->flush();

        return $poll;
    }

    private function reload(Poll $poll): Poll
    {
        $this->em()->clear();
        $reloaded = $this->em()->find(Poll::class, $poll->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * Votes via the form on the poll page (token from the page).
     *
     * @param array<string, mixed> $data
     */
    private function vote(Poll $poll, array $data): void
    {
        $crawler = $this->client->request('GET', '/polls/'.$poll->getId());
        $this->token = (string) $crawler->filter('form[action$="/vote"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/polls/'.$poll->getId().'/vote', ['_token' => $this->token] + $data);
    }

    private function close(Poll $poll, ?int $option = null): void
    {
        $crawler = $this->client->request('GET', '/polls/'.$poll->getId());
        $token = $crawler->filter('form[action$="/close"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/polls/'.$poll->getId().'/close', ['_token' => $token, 'option' => (string) $option]);
        self::assertResponseRedirects();
    }

    public function testCreateChoicePollAndChangeOpenVote(): void
    {
        $this->login($this->admin);
        $this->client->request('GET', '/polls/new');
        $this->client->submitForm('Abstimmung starten', [
            'poll_form[title]' => 'Termin Sommerfest',
            'poll_form[kind]' => 'choice',
            'poll_form[organization]' => (string) $this->org->getId(),
            'poll_form[options]' => "Grillen\n",
        ]);
        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('main', 'mindestens zwei Antwortmöglichkeiten');

        $this->client->submitForm('Abstimmung starten', [
            'poll_form[options]' => "Grillen\nPicknick\n\nGrillen",
        ]);
        self::assertResponseRedirects();
        $poll = $this->em()->getRepository(Poll::class)->findOneBy(['title' => 'Termin Sommerfest']);
        self::assertNotNull($poll);
        self::assertSame(['Grillen', 'Picknick'], array_map(static fn ($o) => $o->getLabel(), $poll->getOptions()));
        self::assertNotNull($this->em()->getRepository(Notification::class)->findOneBy(['recipient' => $this->member]));
        [$grill, $picnic] = $poll->getOptions();

        $this->login($this->member);
        $this->client->request('GET', '/polls');
        self::assertSelectorTextContains('main', 'Deine Stimme fehlt');
        $this->vote($poll, ['choice' => [(string) $grill->getId(), (string) $picnic->getId()]]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'genau eine Möglichkeit');

        $this->vote($poll, ['choice' => [(string) $grill->getId()]]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Anna Schulz');
        $this->vote($poll, ['choice' => [(string) $picnic->getId()]]);

        $poll = $this->reload($poll);
        self::assertCount(1, $poll->getBallots());
        [$grill, $picnic] = $poll->getOptions();
        self::assertSame(0, $grill->getScore());
        self::assertSame(1, $picnic->getScore());
        self::assertSame(PollAnswer::YES, $picnic->getAnswerOf($this->member));
    }

    public function testSecretVoteKeepsNoLinkAndCannotBeChanged(): void
    {
        $poll = $this->poll(new Poll($this->org, $this->admin)->setTitle('Vorsitz')->setSecret(true));
        [$yes] = $poll->getOptions();

        $this->login($this->member);
        $this->vote($poll, ['choice' => [(string) $yes->getId()]]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/polls/'.$poll->getId());
        self::assertSelectorTextContains('main', 'bereits abgestimmt');
        self::assertSelectorTextContains('main', 'nach dem Ende');
        self::assertSelectorNotExists('form[action$="/vote"]');
        $this->client->request('POST', '/polls/'.$poll->getId().'/vote', ['_token' => $this->token, 'choice' => [(string) $yes->getId()]]);
        self::assertResponseStatusCodeSame(403);

        $poll = $this->reload($poll);
        self::assertTrue($poll->hasVoted($this->member));
        $answers = $this->em()->getRepository(PollAnswer::class)->findAll();
        self::assertCount(1, $answers);
        self::assertNull($answers[0]->getBallot());
        self::assertSame(1, $poll->getOptions()[0]->getScore());
        self::assertSame([], $poll->getOptions()[0]->getVoterNames());
    }

    public function testVotingOnlyExcludesMembersWithoutVotingRight(): void
    {
        $guest = $this->createUser('guest@example.org', 'Gabi Gast');
        $this->org->addMember($guest, OrganizationRole::Member)->setVotingRight(false);
        $this->em()->flush();
        $poll = $this->poll(new Poll($this->org, $this->admin)->setTitle('Haushalt')->setVotingOnly(true));
        self::assertNull($this->em()->getRepository(Notification::class)->findOneBy(['recipient' => $guest]));
        $open = $this->poll(new Poll($this->org, $this->admin)->setTitle('Stimmungsbild'));

        $this->login($guest);
        $this->vote($open, ['choice' => [(string) $open->getOptions()[0]->getId()]]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/polls/'.$poll->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'nicht stimmberechtigt');
        $this->client->request('POST', '/polls/'.$poll->getId().'/vote', ['_token' => $this->token, 'choice' => [(string) $poll->getOptions()[0]->getId()]]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCircularResolutionIsRecorded(): void
    {
        $poll = $this->poll(new Poll($this->org, $this->admin)->setTitle('Beitritt Bündnis')->setCircular(true)
            ->setDescription('Wir treten bei.'));
        [$yes, $no] = $poll->getOptions();
        self::assertSame(['Ja', 'Nein', 'Enthaltung'], array_map(static fn ($o) => $o->getLabel(), $poll->getOptions()));

        $this->login($this->member);
        $this->vote($poll, ['choice' => [(string) $no->getId()]]);
        $this->login($this->admin);
        $this->vote($poll, ['choice' => [(string) $yes->getId()]]);
        $this->close($poll);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'als Beschluss '.date('Y').'/1 eingetragen');

        $resolution = $this->em()->getRepository(Resolution::class)->findOneBy(['title' => 'Beitritt Bündnis']);
        self::assertNotNull($resolution);
        self::assertFalse($resolution->isAdopted());
        self::assertSame([1, 1, 0], [$resolution->getVotesYes(), $resolution->getVotesNo(), $resolution->getVotesAbstain()]);
        self::assertSame('Wir treten bei.', $resolution->getText());
        self::assertTrue($this->reload($poll)->isEnded());

        // Ended: no more votes, results visible to everyone
        $this->client->request('POST', '/polls/'.$poll->getId().'/vote', ['_token' => $this->token, 'choice' => [(string) $yes->getId()]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/polls?ended=1');
        self::assertSelectorTextContains('main', 'Beitritt Bündnis');
    }

    public function testScheduleForMeetingMovesTheMeeting(): void
    {
        $meeting = new Meeting($this->org, $this->admin)->setTitle('Vollversammlung')->setStartsAt(new \DateTimeImmutable('+20 days 19:00'))
            ->setEndsAt(new \DateTimeImmutable('+20 days 21:00'));
        $this->em()->persist($meeting);
        $this->em()->flush();

        $this->login($this->admin);
        $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorExists('a[href="/polls/new?meeting='.$meeting->getId().'&kind=schedule"]');
        $this->client->request('GET', '/polls/new?meeting='.$meeting->getId().'&kind=schedule');
        $this->client->submitForm('Abstimmung starten', [
            'poll_form[slot1]' => '2026-11-12T19:00',
            'poll_form[slot2]' => '2026-11-10T18:30',
            'poll_form[slotMinutes]' => '',
        ]);
        self::assertResponseRedirects();
        $poll = $this->em()->getRepository(Poll::class)->findOneBy(['meeting' => $meeting]);
        self::assertNotNull($poll);
        self::assertSame(PollKind::Schedule, $poll->getKind());
        self::assertSame('Terminfindung: Vollversammlung', $poll->getTitle());
        [$first, $second] = $poll->getOptions();
        self::assertSame('2026-11-10 18:30', $first->getStartsAt()?->format('Y-m-d H:i'));

        $this->login($this->member);
        $this->vote($poll, ['slot' => [(string) $first->getId() => '2', (string) $second->getId() => '1']]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'vielleicht: Anna Schulz');

        $this->login($this->admin);
        $this->client->request('GET', '/meetings/'.$meeting->getId());
        self::assertSelectorTextContains('main', 'Terminfindung: Vollversammlung');
        $this->close($poll, $second->getId());

        $this->em()->clear();
        $meeting = $this->em()->find(Meeting::class, $meeting->getId());
        self::assertSame('2026-11-12 19:00', $meeting?->getStartsAt()->format('Y-m-d H:i'));
        self::assertSame('2026-11-12 21:00', $meeting->getEndsAt()?->format('Y-m-d H:i'));
        $event = $this->em()->getRepository(CalendarItem::class)->findOneBy(['meeting' => $meeting]);
        self::assertSame('2026-11-12 19:00', $event?->getStartsAt()?->format('Y-m-d H:i'));
        self::assertSame($event->getId(), $this->em()->find(Poll::class, $poll->getId())?->getCalendarItem()?->getId());
    }

    public function testScheduleWithoutMeetingCreatesEvent(): void
    {
        $start = new \DateTimeImmutable('+5 days 10:00');
        $poll = $this->poll(new Poll($this->org, $this->admin)->setTitle('Ortsbegehung')->setKind(PollKind::Schedule),
            slots: [[$start, $start->modify('+2 hours')], [$start->modify('+1 day'), null]]);
        [$first] = $poll->getOptions();

        $this->login($this->member);
        $this->vote($poll, ['slot' => [(string) $first->getId() => '1']]);
        $this->login($this->admin);
        $this->close($poll, $first->getId());

        $event = $this->em()->getRepository(CalendarItem::class)->findOneBy(['title' => 'Ortsbegehung']);
        self::assertNotNull($event);
        self::assertSame($start->format('Y-m-d H:i'), $event->getStartsAt()?->format('Y-m-d H:i'));
        self::assertSame(['Anna Schulz'], array_map(static fn (User $u) => $u->getName(), $event->getParticipants()->toArray()));
    }

    public function testPollsInForumTopic(): void
    {
        $board = new ForumBoard($this->org, $this->admin)->setName('Allgemeines');
        $topic = new ForumTopic($board, $this->admin)->setTitle('Sommerfest');
        $this->em()->persist($board);
        $this->em()->persist($topic);
        $this->em()->flush();

        $this->login($this->member);
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertSelectorExists('a[href="/polls/new?topic='.$topic->getId().'"]');
        $this->client->request('GET', '/polls/new?topic='.$topic->getId());
        $this->client->submitForm('Abstimmung starten', ['poll_form[kind]' => 'decision']);
        self::assertResponseRedirects();

        $poll = $this->em()->getRepository(Poll::class)->findOneBy(['topic' => $topic]);
        self::assertSame('Sommerfest', $poll?->getTitle());
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertSelectorTextContains('main', 'Läuft');
    }

    public function testOutsiderIsForbidden(): void
    {
        $poll = $this->poll(new Poll($this->org, $this->admin)->setTitle('Intern'));
        $this->login($this->createUser('outsider@example.org', 'Otto Außen'));
        $this->client->request('GET', '/polls/'.$poll->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/polls');
        self::assertSelectorTextNotContains('main', 'Intern');
    }
}
