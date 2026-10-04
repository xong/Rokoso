<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Enum\OrganizationRole;
use App\Mail\MailboxReader;
use App\Mail\MailSynchronizer;
use App\Mail\SyncOnOpen;
use App\Repository\MailAccountRepository;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;

final class MailSyncStatusTest extends AppTestCase
{
    public function testSyncProblemsAreShownToAdmins(): void
    {
        $admin = $this->createUser();
        $member = $this->createUser('ben@example.org', 'Ben Berg');
        $organization = $this->createOrganization($admin);
        $organization->addMember($member, OrganizationRole::Member);
        $account = $this->createMailAccount($organization);
        $account->markSynced('connection refused');
        $this->em()->flush();

        $this->login($admin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#sync-heading', 'Postfach-Abruf gestört');
        self::assertSelectorTextContains('body', 'connection refused');

        $this->login($member);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('#sync-heading');

        // a fetch without error clears the hint
        $account = $this->em()->find(MailAccount::class, $account->getId());
        self::assertNotNull($account);
        $account->markSynced();
        $this->em()->flush();
        $this->login($admin);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('#sync-heading');
    }

    public function testStaleAccountIsAProblem(): void
    {
        $account = new MailAccount($this->createOrganization($this->createUser()));
        self::assertNull($account->getSyncProblem());
        self::assertSame('stale', $account->getSyncProblem(new \DateTimeImmutable('+2 hours')));
        $account->setEnabled(false);
        self::assertNull($account->getSyncProblem(new \DateTimeImmutable('+2 hours')));
    }

    public function testSyncOnOpenIsThrottled(): void
    {
        $user = $this->createUser();
        $account = $this->createMailAccount($this->createOrganization($user));
        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $reader->add("From: Eva <eva@example.org>\r\nSubject: Hallo\r\nMessage-ID: <a@example.org>\r\nContent-Type: text/plain\r\n\r\nText\r\n");

        $container = static::getContainer();
        $syncOnOpen = new SyncOnOpen($container->get(MailAccountRepository::class), $container->get(MailSynchronizer::class), 5);
        self::assertSame(1, $syncOnOpen->run($user));

        // within the interval nothing is fetched
        $reader->add("From: Eva <eva@example.org>\r\nSubject: Noch was\r\nMessage-ID: <b@example.org>\r\nContent-Type: text/plain\r\n\r\nText\r\n");
        self::assertSame(0, $syncOnOpen->run($user));

        $this->em()->getConnection()->executeStatement('UPDATE mail_account SET last_sync_at = ?', [(new \DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s')]);
        $this->em()->refresh($account);
        self::assertSame(1, $syncOnOpen->run($user));
        self::assertCount(2, $this->em()->getRepository(Message::class)->findAll());

        // disabled (interval 0 as in the test environment)
        self::assertSame(0, $container->get(SyncOnOpen::class)->run($user));
    }
}
