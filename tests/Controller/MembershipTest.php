<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\CalendarItemType;
use App\Enum\MessageType;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;

/**
 * Memberships with function and term, guests per project, handover, project archive and knowledge base.
 */
final class MembershipTest extends AppTestCase
{
    private User $admin;
    private User $member;
    private Organization $org;
    private Project $released;
    private Project $internal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->member = $this->createUser('kim@example.org', 'Kim Member');
        $this->org = $this->createOrganization($this->admin);
        $this->org->addMember($this->member, OrganizationRole::Member);
        $this->released = (new Project($this->admin))->setName('Schulwege')->setOrganization($this->org);
        $this->internal = (new Project($this->admin))->setName('Vorstand intern')->setOrganization($this->org);
        $this->em()->persist($this->released);
        $this->em()->persist($this->internal);
        $this->em()->flush();
    }

    private function guest(): User
    {
        $guest = $this->createUser('school@example.org', 'Sabine Schule');
        $this->org->addMember($guest, OrganizationRole::Guest)->setVotingRight(false)->addGuestProject($this->released);
        $this->em()->flush();

        return $guest;
    }

    private function membership(User $user): Membership
    {
        $membership = $this->org->findMembership($user);
        self::assertNotNull($membership);

        return $membership;
    }

    public function testGuestSeesOnlyReleasedProject(): void
    {
        $guest = $this->guest();
        $board = (new ForumBoard($this->org, $this->admin))->setName('Schulwege')->setProject($this->released);
        $topic = (new ForumTopic($board, $this->admin))->setTitle('Gefahrenstellen');
        $topic->addPost((new ForumPost($topic, $this->admin))->setBody('Kreuzung Nord'));
        $hidden = (new ForumBoard($this->org, $this->admin))->setName('Vorstand');
        $note = (new Message(MessageType::Internal))->setAuthor($this->admin)->setFrom('owner@example.org', 'Eva')
            ->setOrganization($this->org)->setProject($this->released)->setSubject('Interne Notiz')->setBody('nur Mitglieder');
        $task = (new CalendarItem($this->admin))->setTitle('Begehung planen')->setType(CalendarItemType::Task)
            ->setOrganization($this->org)->setProject($this->released)->setStartsAt(null);
        foreach ([$board, $topic, $hidden, $note, $task] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        $this->login($guest);
        $this->client->request('GET', '/projects');
        self::assertSelectorTextContains('main', 'Schulwege');
        self::assertSelectorTextNotContains('main', 'Vorstand intern');
        $this->client->request('GET', '/projects/'.$this->released->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'Interne Notiz');
        $this->client->request('GET', '/projects/'.$this->internal->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/forum');
        self::assertSelectorTextContains('main', 'Schulwege');
        self::assertSelectorTextNotContains('main', 'Vorstand');
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/forum/board/'.$hidden->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/tasks?scope=all');
        self::assertSelectorTextContains('main', 'Begehung planen');
        $this->client->request('GET', '/mail/all/'.$note->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/organizations/'.$this->org->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/knowledge');
        self::assertSelectorTextContains('main', 'Mitgliedern einer Organisation');
    }

    public function testFormerMemberLosesAccess(): void
    {
        $this->membership($this->member)->setTermEndsOn(new \DateTimeImmutable('yesterday'));
        $this->em()->flush();

        $this->login($this->member);
        $this->client->request('GET', '/organizations/'.$this->org->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/projects/'.$this->released->getId());
        self::assertResponseStatusCodeSame(403);

        $this->login($this->admin);
        $this->client->request('GET', '/organizations/'.$this->org->getId());
        self::assertSelectorTextContains('main', 'ehemalig seit');
    }

    public function testAdminEditsMembershipAndKeepsLastAdmin(): void
    {
        $this->login($this->admin);
        $guest = $this->createUser('school@example.org', 'Sabine Schule');
        $this->org->addMember($guest, OrganizationRole::Member);
        $this->em()->flush();
        $id = $this->membership($guest)->getId();

        $this->client->request('GET', '/organizations/'.$this->org->getId().'/members/'.$id.'/edit');
        $this->client->submitForm('Speichern', [
            'membership_form[role]' => 'guest',
            'membership_form[position]' => 'Schulleitung',
            'membership_form[guestProjects]' => [(string) $this->released->getId()],
        ]);
        self::assertResponseRedirects();
        $this->em()->clear();
        $membership = $this->em()->find(Membership::class, $id);
        self::assertNotNull($membership);
        self::assertTrue($membership->isGuest());
        self::assertSame('Schulleitung', $membership->getPosition());
        self::assertTrue($membership->grantsGuestAccessTo($this->em()->find(Project::class, $this->released->getId())));

        $own = $this->em()->getRepository(Membership::class)->findOneBy(['user' => $this->admin->getId()]);
        self::assertNotNull($own);
        $this->client->request('GET', '/organizations/'.$this->org->getId().'/members/'.$own->getId().'/edit');
        $this->client->submitForm('Speichern', ['membership_form[role]' => 'member']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'mindestens einen Administrator');
        $this->em()->clear();
        self::assertTrue($this->em()->find(Membership::class, $own->getId())?->isAdmin());
    }

    public function testHandoverMovesOpenAssignments(): void
    {
        $successor = $this->createUser('new@example.org', 'Nora Neu');
        $this->org->addMember($successor, OrganizationRole::Member);
        $this->released->setLead($this->member);
        $open = (new CalendarItem($this->admin))->setTitle('Offen')->setType(CalendarItemType::Task)
            ->setOrganization($this->org)->setStartsAt(null)->addAssignee($this->member);
        $note = (new Message(MessageType::Internal))->setAuthor($this->admin)->setFrom('owner@example.org', 'Eva')
            ->setOrganization($this->org)->setSubject('Bitte kümmern')->setBody('…')->addAssignee($this->member);
        $this->em()->persist($open);
        $this->em()->persist($note);
        $this->em()->flush();

        $this->login($this->admin);
        $this->client->request('GET', '/organizations/'.$this->org->getId().'/handover?from='.$this->membership($this->member)->getId());
        $this->client->submitForm('Übergeben', [
            'to' => (string) $this->membership($successor)->getId(),
            'end_term' => '1',
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Übergabe von Kim Member an Nora Neu: 3 Einträge übertragen.');

        $this->em()->clear();
        $task = $this->em()->find(CalendarItem::class, $open->getId());
        self::assertNotNull($task);
        self::assertSame(['Nora Neu'], array_map(static fn (User $u): string => $u->getName(), $task->getAssignees()->toArray()));
        self::assertSame('Nora Neu', $this->em()->find(Project::class, $this->released->getId())?->getLead()?->getName());
        $former = $this->em()->getRepository(Membership::class)->findOneBy(['user' => $this->member->getId()]);
        self::assertTrue($former?->isFormer());
    }

    public function testArchivedProjectIsHiddenFromChoices(): void
    {
        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/projects/'.$this->internal->getId());
        $this->client->submit($crawler->filter('form[action$="/archive"]')->form());
        self::assertResponseRedirects();

        $this->client->request('GET', '/projects');
        self::assertSelectorTextNotContains('main', 'Vorstand intern');
        $this->client->request('GET', '/projects?archived=1');
        self::assertSelectorTextContains('main', 'Vorstand intern');

        $crawler = $this->client->request('GET', '/calendar/new');
        self::assertCount(0, $crawler->filter('select option:contains("Vorstand intern")'));
        $this->client->request('GET', '/projects/'.$this->internal->getId());
        self::assertResponseIsSuccessful();
    }

    public function testKnowledgeBaseKeepsRevisions(): void
    {
        $this->login($this->member);
        $this->client->request('GET', '/knowledge/new?organization='.$this->org->getId());
        $this->client->submitForm('Speichern', ['wiki_page_form[title]' => 'Geschäftsordnung', 'wiki_page_form[body]' => 'Einladung 7 Tage vorher']);
        self::assertResponseRedirects();
        $page = $this->em()->getRepository(WikiPage::class)->findOneBy(['title' => 'Geschäftsordnung']);
        self::assertNotNull($page);

        $this->client->request('GET', '/knowledge/'.$page->getId().'/edit');
        $this->client->submitForm('Speichern', ['wiki_page_form[body]' => 'Einladung **14 Tage** vorher']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '14 Tage');

        $this->client->request('GET', '/knowledge/new?parent='.$page->getId());
        $this->client->submitForm('Speichern', ['wiki_page_form[title]' => 'Sitzungen']);
        $this->client->request('GET', '/knowledge?q=14 Tage');
        self::assertSelectorTextContains('main', 'Geschäftsordnung');

        $this->em()->clear();
        $page = $this->em()->find(WikiPage::class, $page->getId());
        self::assertNotNull($page);
        self::assertCount(2, $page->getRevisions());
        self::assertSame('Sitzungen', $page->getChildren()->first() ? $page->getChildren()->first()->getTitle() : null);
        $first = $page->getRevisions()->last();
        self::assertNotFalse($first);

        $crawler = $this->client->request('GET', '/knowledge/revisions/'.$first->getId());
        $this->client->submit($crawler->filter('form[action$="/restore"]')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '7 Tage vorher');

        // members cannot delete, outsiders cannot read
        $this->client->request('GET', '/knowledge/'.$page->getId());
        self::assertSelectorNotExists('form[action$="/delete"]');
        $this->login($this->createUser('outsider@example.org', 'Olaf Out'));
        $this->client->request('GET', '/knowledge/'.$page->getId());
        self::assertResponseStatusCodeSame(403);
    }
}
