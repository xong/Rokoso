<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BlockedSender;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\User;
use App\Mail\MailboxReader;
use App\Mail\MailSynchronizer;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;

/**
 * Spam: imported but only shown in the spam folder (server header, blocked senders, marked by hand).
 */
final class MailSpamTest extends AppTestCase
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

    private function sync(string ...$raws): void
    {
        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        foreach ($raws as $raw) {
            $reader->add($raw);
        }
        $account = $this->em()->find(MailAccount::class, $this->account->getId());
        self::assertNotNull($account);
        // The kernel (and the fake mailbox) may have been rebooted by a request: start a new UID range
        $reader->uidValidity = ($account->getUidValidity() ?? 0) + 1;
        static::getContainer()->get(MailSynchronizer::class)->sync($account);
    }

    private function raw(string $messageId, string $subject, string $from = 'eva@example.org', string $extraHeaders = ''): string
    {
        return "From: <$from>\r\nTo: info@sev.example.org\r\nSubject: $subject\r\nDate: Mon, 01 Jun 2026 10:00:00 +0200\r\n"
            ."Message-ID: <$messageId>\r\n{$extraHeaders}Content-Type: text/plain; charset=utf-8\r\n\r\nText\r\n";
    }

    private function find(string $messageId): Message
    {
        $this->em()->clear();
        $message = $this->em()->getRepository(Message::class)->findOneBy(['messageIdHeader' => $messageId]);
        self::assertNotNull($message);

        return $message;
    }

    /**
     * @param list<int|null> $ids
     */
    private function action(string $action, array $ids): void
    {
        $crawler = $this->client->request('GET', '/mail/all/'.$ids[0]);
        $token = $crawler->filter('form[action="/mail/action"] input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/mail/action', ['_token' => $token, 'action' => $action, 'ids' => $ids, 'return' => 'list', 'folder' => 'spam']);
    }

    public function testFlaggedMailOnlyInSpamFolder(): void
    {
        $this->sync(
            $this->raw('s1@example.org', 'Gewinnspiel', 'win@spam.example', "X-Spam-Flag: YES\r\n"),
            $this->raw('s2@example.org', 'Billige Uhren', 'shop@spam.example', "X-Spam-Status: Yes, score=9.1\r\n"),
            $this->raw('h1@example.org', 'Elternabend'),
        );
        self::assertTrue($this->find('s1@example.org')->isSpam());
        self::assertTrue($this->find('s2@example.org')->isSpam());
        self::assertFalse($this->find('h1@example.org')->isSpam());

        $this->login($this->user);
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Elternabend');
        self::assertSelectorTextNotContains('main', 'Gewinnspiel');
        $this->client->request('GET', '/mail/all');
        self::assertSelectorTextNotContains('main', 'Gewinnspiel');
        $this->client->request('GET', '/mail/spam');
        self::assertSelectorTextContains('main', 'Gewinnspiel');
        self::assertSelectorTextContains('main', 'Billige Uhren');
        self::assertSelectorTextNotContains('main', 'Elternabend');

        $this->client->request('GET', '/mail/spam/'.$this->find('s1@example.org')->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Spam-Ordner');
    }

    public function testNotSpamMovesBackToInbox(): void
    {
        $this->sync($this->raw('s1@example.org', 'Rundbrief Schulamt', 'amt@example.org', "X-Spam-Flag: YES\r\n"));
        $this->login($this->user);

        $this->action('not_spam', [$this->find('s1@example.org')->getId()]);
        self::assertResponseRedirects('/mail/spam');
        self::assertFalse($this->find('s1@example.org')->isSpam());
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Rundbrief Schulamt');
    }

    public function testBlockSenderCatchesOldAndNewMails(): void
    {
        $this->sync(
            $this->raw('a1@example.org', 'Werbung 1', 'werbung@shop.example'),
            $this->raw('a2@example.org', 'Werbung 2', 'werbung@shop.example'),
            $this->raw('h1@example.org', 'Elternabend'),
        );
        $this->login($this->user);

        $this->action('block', [$this->find('a1@example.org')->getId()]);
        self::assertTrue($this->find('a1@example.org')->isSpam());
        self::assertTrue($this->find('a2@example.org')->isSpam());
        self::assertFalse($this->find('h1@example.org')->isSpam());
        self::assertNotNull($this->em()->getRepository(BlockedSender::class)->findOneBy(['address' => 'werbung@shop.example']));

        // New mails of the sender go straight to spam, without rules or notifications
        $this->sync($this->raw('a3@example.org', 'Werbung 3', 'Werbung@Shop.example'));
        self::assertTrue($this->find('a3@example.org')->isSpam());

        // "Kein Spam" trusts the sender again
        $this->action('not_spam', [$this->find('a3@example.org')->getId()]);
        self::assertNull($this->em()->getRepository(BlockedSender::class)->findOneBy(['address' => 'werbung@shop.example']));
    }

    public function testAdminBlocksDomain(): void
    {
        $this->login($this->user);
        $crawler = $this->client->request('GET', '/organizations/'.$this->org->getId());
        $this->client->submit($crawler->selectButton('Sperren')->form(['address' => '@Spam.example']));
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('#blocked-heading + p + ul', '@spam.example');

        $this->sync($this->raw('d1@example.org', 'Angebot', 'any@spam.example'), $this->raw('d2@example.org', 'Sonstiges', 'any@nospam.example'));
        self::assertTrue($this->find('d1@example.org')->isSpam());
        self::assertFalse($this->find('d2@example.org')->isSpam());

        $this->client->submit($crawler->filter('form[action$="/delete"] button[aria-label="Entsperren"]')->form());
        self::assertResponseRedirects();
        self::assertSame(0, $this->em()->getRepository(BlockedSender::class)->count(['organization' => $this->org->getId()]));
    }
}
