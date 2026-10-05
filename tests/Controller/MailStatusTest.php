<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\MailRule;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MailRuleField;
use App\Enum\MessageEventType;
use App\Mail\MailboxReader;
use App\Mail\MailSynchronizer;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;

/**
 * Status model (open/done), snooze, bulk actions, undo, threads and inbox rules.
 */
final class MailStatusTest extends AppTestCase
{
    private User $user;
    private Organization $org;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->org = $this->createOrganization($this->user);
        $this->account = $this->createMailAccount($this->org);
    }

    private function message(string $subject, ?string $messageId = null): Message
    {
        $message = (new Message())->setMailAccount($this->account)->setFrom('eva@example.org', 'Eva')
            ->setSubject($subject)->setBody('Hallo')->setMessageIdHeader($messageId);
        $message->setThreadKey($message->deriveThreadKey());
        $this->em()->persist($message);
        $this->em()->flush();

        return $message;
    }

    private function reload(Message $message): Message
    {
        $this->em()->clear();
        $fresh = $this->em()->find(Message::class, $message->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    /**
     * @param list<int|null>       $ids
     * @param array<string, mixed> $fields
     */
    private function action(string $action, array $ids, array $fields = []): void
    {
        // Token from a list that contains at least one own message
        $crawler = $this->client->request('GET', '/mail/all');
        $token = $crawler->filter('#bulk-form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/mail/action', ['_token' => $token, 'action' => $action, 'ids' => $ids, 'return' => 'list'] + $fields);
    }

    private function raw(string $messageId, string $subject, ?string $inReplyTo = null, string $from = 'eva@example.org'): string
    {
        $headers = "From: Eva <$from>\r\nTo: info@sev.example.org\r\nSubject: $subject\r\nDate: Mon, 01 Jun 2026 10:00:00 +0200\r\nMessage-ID: <$messageId>\r\n";
        if (null !== $inReplyTo) {
            $headers .= "In-Reply-To: <$inReplyTo>\r\nReferences: <$inReplyTo>\r\n";
        }

        return $headers."Content-Type: text/plain; charset=utf-8\r\n\r\nText\r\n";
    }

    public function testDoneLeavesInboxAndCanBeUndone(): void
    {
        $message = $this->message('Elternabend');
        $second = $this->message('Kassenbericht');
        $this->login($this->user);

        $this->action('done', [$message->getId()]);
        self::assertResponseRedirects('/mail');
        self::assertTrue($this->reload($message)->isDone());

        $this->client->followRedirect();
        self::assertSelectorTextNotContains('main', 'Elternabend');
        $this->client->request('GET', '/mail/done');
        self::assertSelectorTextContains('main', 'Elternabend');

        // Undo toast posts the inverse action
        $message = $second;
        $this->action('done', [$message->getId()]);
        $crawler = $this->client->followRedirect();
        $undo = $crawler->filter('input[name="undo"]')->closest('form');
        self::assertNotNull($undo);
        self::assertSame('reopen', $undo->filter('input[name="action"]')->attr('value'));
        $this->client->submit($undo->form());
        self::assertFalse($this->reload($message)->isDone());

        $types = array_map(static fn ($e) => $e->getType(), $this->reload($message)->getEvents()->toArray());
        self::assertContains(MessageEventType::Done, $types);
        self::assertContains(MessageEventType::Reopened, $types);
    }

    public function testSnoozeHidesUntilDue(): void
    {
        $message = $this->message('Haushalt');
        $this->login($this->user);

        $this->action('snooze', [$message->getId()], ['until' => 'tomorrow']);
        self::assertTrue($this->reload($message)->isSnoozed());
        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Haushalt');
        $this->client->request('GET', '/mail/snoozed');
        self::assertSelectorTextContains('main', 'Haushalt');

        // Due again → back in the inbox
        $fresh = $this->reload($message);
        $fresh->snooze(new \DateTimeImmutable('-1 minute'));
        $this->em()->flush();
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Haushalt');
    }

    public function testBulkTrashAndProject(): void
    {
        $a = $this->message('Erste');
        $b = $this->message('Zweite');
        $project = (new Project($this->user))->setName('Schulwege')->setOrganization($this->org);
        $this->em()->persist($project);
        $this->em()->flush();
        $this->login($this->user);

        $this->action('project', [$a->getId(), $b->getId()], ['project' => $project->getId()]);
        self::assertSame('Schulwege', $this->reload($a)->getProject()?->getName());
        self::assertSame('Schulwege', $this->reload($b)->getProject()?->getName());

        $this->action('trash', [$a->getId(), $b->getId()]);
        self::assertTrue($this->reload($a)->isTrashed());
        self::assertTrue($this->reload($b)->isTrashed());
    }

    public function testBulkIgnoresForeignMessages(): void
    {
        $other = $this->createUser('max@example.org', 'Max');
        $otherOrg = $this->createOrganization($other, 'Andere');
        $foreign = (new Message())->setMailAccount($this->createMailAccount($otherOrg))->setFrom('x@example.org')->setSubject('Fremd');
        $this->em()->persist($foreign);
        $this->em()->flush();
        $this->message('Eigene');
        $this->login($this->user);

        $this->action('done', [$foreign->getId()]);
        self::assertFalse($this->reload($foreign)->isDone());
    }

    public function testThreadsAndDoneClosesConversation(): void
    {
        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $reader->add($this->raw('first@example.org', 'Frage'));
        $reader->add($this->raw('second@example.org', 'Re: Frage', 'first@example.org'));
        static::getContainer()->get(MailSynchronizer::class)->sync($this->account);

        $repo = $this->em()->getRepository(Message::class);
        $first = $repo->findOneBy(['messageIdHeader' => 'first@example.org']);
        $second = $repo->findOneBy(['messageIdHeader' => 'second@example.org']);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('first@example.org', $second->getThreadKey());

        $this->login($this->user);
        $this->client->request('GET', '/mail/inbox/'.$second->getId());
        self::assertSelectorTextContains('#thread-heading', '2');

        $this->action('done', [$second->getId()]);
        self::assertTrue($this->reload($first)->isDone());
    }

    public function testRulesApplyOnSync(): void
    {
        $project = (new Project($this->user))->setName('Newsletter')->setOrganization($this->org);
        $rule = (new MailRule($this->org))->setName('Rundbrief')->setField(MailRuleField::From)->setNeedle('NEWS@')
            ->setProject($project)->setMarkDone(true)->addAssignee($this->user);
        $this->em()->persist($project);
        $this->em()->persist($rule);
        $this->em()->flush();

        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $reader->add($this->raw('n1@example.org', 'Neues', null, 'news@verband.example.org'));
        $reader->add($this->raw('n2@example.org', 'Anderes'));
        static::getContainer()->get(MailSynchronizer::class)->sync($this->account);

        $repo = $this->em()->getRepository(Message::class);
        $matched = $repo->findOneBy(['messageIdHeader' => 'n1@example.org']);
        $other = $repo->findOneBy(['messageIdHeader' => 'n2@example.org']);
        self::assertNotNull($matched);
        self::assertNotNull($other);
        self::assertSame('Newsletter', $matched->getProject()?->getName());
        self::assertTrue($matched->isDone());
        self::assertCount(1, $matched->getAssignees());
        self::assertNull($other->getProject());
        self::assertFalse($other->isDone());
    }

    public function testAdminManagesRules(): void
    {
        $this->login($this->user);
        $this->client->request('GET', '/organizations/'.$this->org->getId().'/mail-rules/new');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Speichern', [
            'mail_rule_form[name]' => 'Schulamt',
            'mail_rule_form[field]' => 'from',
            'mail_rule_form[needle]' => 'schulamt',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Schulamt');

        $rule = $this->em()->getRepository(MailRule::class)->findOneBy(['name' => 'Schulamt']);
        self::assertNotNull($rule);
        $this->client->request('GET', '/mail-rules/'.$rule->getId().'/edit');
        self::assertResponseIsSuccessful();
    }
}
