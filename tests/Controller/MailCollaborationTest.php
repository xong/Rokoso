<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class MailCollaborationTest extends AppTestCase
{
    private User $user;
    private User $colleague;
    private Message $message;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->colleague = $this->createUser('kim@example.org', 'Kim Kollegin');
        $org = $this->createOrganization($this->user);
        $org->addMember($this->colleague, OrganizationRole::Member);
        $account = $this->createMailAccount($org);
        $this->project = (new Project($this->user))->setName('Schulwege')->setOrganization($org);
        $this->message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')
            ->setSubject('Zebrastreifen')->setBody('Hallo');
        $this->em()->persist($this->project);
        $this->em()->persist($this->message);
        $this->em()->flush();
        $this->login($this->user);
    }

    private function showUrl(): string
    {
        return '/mail/inbox/'.$this->message->getId();
    }

    private function reload(): Message
    {
        $this->em()->clear();
        $message = $this->em()->find(Message::class, $this->message->getId());
        self::assertNotNull($message);

        return $message;
    }

    public function testCommentIsShown(): void
    {
        $this->client->request('GET', $this->showUrl());
        $this->client->submitForm('Kommentieren', ['body' => 'Ich kümmere mich drum.']);
        self::assertResponseRedirects($this->showUrl().'#comments');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#comments', 'Ich kümmere mich drum.');
    }

    public function testResizedColumnsAreRendered(): void
    {
        $this->client->getCookieJar()->set(new Cookie('panel_list', '40'));
        $this->client->getCookieJar()->set(new Cookie('panel_comments', '99'));
        $this->client->request('GET', $this->showUrl());

        self::assertSelectorExists('#list-panel[style="--panel-width: 40%"] [role="separator"][aria-controls="list-panel"]');
        self::assertSelectorExists('#comments[style="--panel-width: 60%"] [role="separator"][aria-controls="comments"]');
    }

    public function testAssignMeAndAssignees(): void
    {
        $this->client->request('GET', $this->showUrl());
        $this->client->submitForm('Mir zuordnen');
        self::assertTrue($this->reload()->isAssignedTo($this->em()->find(User::class, $this->user->getId()) ?? $this->user));

        $this->client->request('GET', '/mail?show=mine');
        self::assertSelectorTextContains('main', 'Zebrastreifen');
        $this->client->request('GET', '/mail?show=unassigned');
        self::assertSelectorTextNotContains('main', 'Zebrastreifen');

        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter('form[action$="/assignees"]')->form();
        $values = $form->getPhpValues();
        $values['assignees'] = [(string) $this->colleague->getId()];
        $this->client->request('POST', $form->getUri(), $values);

        $assignees = $this->reload()->getAssignees();
        self::assertCount(1, $assignees);
        self::assertSame('kim@example.org', $assignees->first() ? $assignees->first()->getEmail() : null);
    }

    public function testProjectAssignmentAndFilter(): void
    {
        $second = (new Project($this->user))->setName('Schulfest')->setOrganization($this->project->getOrganization());
        $this->em()->persist($second);
        $this->em()->flush();
        $names = fn (): array => array_map(static fn (Project $p): string => (string) $p->getName(), $this->reload()->getProjects()->toArray());
        $save = function (Project ...$projects): void {
            $crawler = $this->client->request('GET', $this->showUrl());
            $form = $crawler->filter('form[action$="/projects"]')->form();
            $values = $form->getPhpValues();
            $values['projects'] = array_map(static fn (Project $p): string => (string) $p->getId(), $projects);
            $this->client->request('POST', $form->getUri(), $values);
            self::assertResponseRedirects($this->showUrl());
        };

        $save($this->project, $second);
        self::assertEqualsCanonicalizing(['Schulwege', 'Schulfest'], $names());

        // Both in the list badge and in the history; status model: stays open in the inbox, reachable via each project
        $this->client->followRedirect();
        self::assertSelectorTextContains('#list-panel', '+1');
        self::assertSelectorTextContains('main', 'hat das Projekt „Schulfest“ zugeordnet');
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Zebrastreifen');
        foreach ([$this->project, $second] as $project) {
            $this->client->request('GET', '/mail/all?project='.$project->getId());
            self::assertSelectorTextContains('main', 'Zebrastreifen');
            $this->client->request('GET', '/projects/'.$project->getId());
            self::assertSelectorTextContains('#project-messages-heading + ul', 'Zebrastreifen');
        }

        // Unchecking removes only that project
        $save($second);
        self::assertSame(['Schulfest'], $names());
        $this->client->request('GET', '/mail/all?project='.$this->project->getId());
        self::assertSelectorTextNotContains('main', 'Zebrastreifen');
    }

    public function testToolbarTrashReturnsToFilteredList(): void
    {
        $crawler = $this->client->request('GET', '/mail?show=unassigned');
        $form = $crawler->filter('form[action$="/trash"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/mail?show=unassigned');
        self::assertTrue($this->reload()->isTrashed());
    }
}
