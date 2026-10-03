<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\OrganizationRole;
use App\Enum\TaskStatus;
use App\Tests\AppTestCase;

/**
 * Tasks: undated tasks, list scopes, board moves, tasks from forum topics and the project page.
 */
final class TaskTest extends AppTestCase
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

    private function task(string $title, ?User $assignee = null, ?Project $project = null): CalendarItem
    {
        $task = (new CalendarItem($this->admin))->setType(CalendarItemType::Task)->setTitle($title)
            ->setStartsAt(null)->setOrganization($this->org)->setProject($project);
        if (null !== $assignee) {
            $task->addAssignee($assignee);
        }
        $this->em()->persist($task);
        $this->em()->flush();

        return $task;
    }

    public function testUndatedTaskAndEventNeedsStart(): void
    {
        $this->login($this->member);
        $this->client->request('GET', '/calendar/new?type=task');
        $this->client->submitForm('Speichern', [
            'calendar_item_form[title]' => 'Flyer drucken',
            'calendar_item_form[organization]' => (string) $this->org->getId(),
        ]);
        self::assertResponseRedirects();
        $this->em()->clear();
        $task = $this->em()->getRepository(CalendarItem::class)->findOneBy(['title' => 'Flyer drucken']);
        self::assertNotNull($task);
        self::assertNull($task->getStartsAt());
        self::assertSame(TaskStatus::Open, $task->getStatus());

        $this->client->request('GET', '/calendar/item/'.$task->getId());
        self::assertSelectorTextContains('main', 'Ohne Frist');

        // Events still need a start
        $this->client->request('GET', '/calendar/new');
        $this->client->submitForm('Speichern', [
            'calendar_item_form[title]' => 'Sitzung',
            'calendar_item_form[startsAt]' => '',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Termine brauchen einen Beginn.');
    }

    public function testListScopesAndBoardMove(): void
    {
        $mine = $this->task('Protokoll schreiben', $this->member);
        $this->task('Kasse prüfen', $this->admin);

        $this->login($this->member);
        $this->client->request('GET', '/tasks');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Protokoll schreiben');
        self::assertSelectorTextNotContains('main', 'Kasse prüfen');

        $this->client->request('GET', '/tasks?scope=all');
        self::assertSelectorTextContains('main', 'Kasse prüfen');

        // Board: move to "in progress" with the arrow button
        $crawler = $this->client->request('GET', '/tasks?view=board');
        $form = $crawler->filter('button[aria-label="„Protokoll schreiben“ nach „In Arbeit“ verschieben"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/tasks?view=board');
        $this->em()->clear();
        self::assertSame(TaskStatus::InProgress, $this->em()->find(CalendarItem::class, $mine->getId())?->getStatus());

        // Done tasks are hidden in the list unless requested
        $crawler = $this->client->request('GET', '/tasks');
        $this->client->submit($crawler->filter('button[aria-label="„Protokoll schreiben“ als erledigt markieren"]')->form());
        $this->client->request('GET', '/tasks');
        self::assertSelectorTextNotContains('main', 'Protokoll schreiben');
        $this->client->request('GET', '/tasks?done=1');
        self::assertSelectorTextContains('main', 'Protokoll schreiben');

        // Outsiders see nothing
        $this->login($this->createUser('other@example.org', 'Otto Außen'));
        $this->client->request('GET', '/tasks?scope=all&done=1');
        self::assertSelectorTextNotContains('main', 'Protokoll schreiben');
        $this->client->request('GET', '/calendar/item/'.$mine->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testTaskFromForumTopic(): void
    {
        $board = (new ForumBoard($this->org, $this->admin))->setName('Allgemeines');
        $topic = (new ForumTopic($board, $this->admin))->setTitle('Schulweg sicherer machen');
        $topic->addPost((new ForumPost($topic, $this->admin))->setBody('Start'));
        $this->em()->persist($board);
        $this->em()->persist($topic);
        $this->em()->flush();

        $this->login($this->member);
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $this->client->click($crawler->filter('a[aria-label="Aufgabe daraus erstellen"]')->link());
        self::assertSelectorTextContains('main', 'Aufgabe zu „Schulweg sicherer machen“');
        self::assertInputValueSame('calendar_item_form[title]', 'Schulweg sicherer machen');
        $this->client->submitForm('Speichern');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/forum/topic/'.$topic->getId().'"]');

        $this->em()->clear();
        $task = $this->em()->getRepository(CalendarItem::class)->findOneBy(['title' => 'Schulweg sicherer machen']);
        self::assertNotNull($task);
        self::assertSame($topic->getId(), $task->getSourceTopic()?->getId());
        self::assertTrue($task->isTask());
    }

    public function testProjectPageShowsEventsAndTasks(): void
    {
        $project = (new Project($this->admin))->setName('Elternabend')->setOrganization($this->org);
        $this->em()->persist($project);
        $this->em()->persist((new CalendarItem($this->admin))->setTitle('Elternabend Aula')->setOrganization($this->org)
            ->setProject($project)->setStartsAt(new \DateTimeImmutable('+2 days 19:00')));
        $this->em()->flush();
        $this->task('Einladung schreiben', null, $project);

        $this->login($this->member);
        $this->client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorTextContains('section[aria-labelledby="project-events-heading"]', 'Elternabend Aula');
        self::assertSelectorTextContains('section[aria-labelledby="project-tasks-heading"]', 'Einladung schreiben');
    }
}
