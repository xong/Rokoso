<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;

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
        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter('form[action$="/project"]')->form();
        $values = $form->getPhpValues();
        $values['project'] = (string) $this->project->getId();
        $this->client->request('POST', $form->getUri(), $values);
        self::assertSame('Schulwege', $this->reload()->getProject()?->getName());
        self::assertResponseRedirects($this->showUrl());

        // Status model: stays open in the inbox until done, also reachable on the project page
        $this->client->followRedirect();
        self::assertSelectorExists('form[action="/mail/action"] input[name="undo"]');
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Zebrastreifen');
        $this->client->request('GET', '/mail/all?project='.$this->project->getId());
        self::assertSelectorTextContains('main', 'Zebrastreifen');
        $this->client->request('GET', '/projects/'.$this->project->getId());
        self::assertSelectorTextContains('#project-messages-heading + ul', 'Zebrastreifen');
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
