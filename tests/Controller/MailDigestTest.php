<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\NotificationEmail;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Notification\NotificationCenter;
use App\Notification\NotificationMailer;
use App\Tests\AppTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Daily summary of new mails: opt-in, only visible inbox mails, alone or together with the notification digest.
 */
final class MailDigestTest extends AppTestCase
{
    use ClockSensitiveTrait;

    private User $admin;
    private User $member;
    private Organization $org;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->member = $this->createUser();
        $this->org = $this->createOrganization($this->admin);
        $this->org->addMember($this->member, OrganizationRole::Member);
        $this->account = $this->createMailAccount($this->org);
        $this->em()->flush();
    }

    private function message(string $subject, string $body = 'Hallo', ?MailAccount $account = null): Message
    {
        $message = (new Message())->setMailAccount($account ?? $this->account)->setFrom('anna@example.org', 'Anna Schmidt')
            ->setSubject($subject)->setBody($body);
        $message->setThreadKey($message->deriveThreadKey());
        $this->em()->persist($message);
        $this->em()->flush();

        return $message;
    }

    private function notify(string $time): void
    {
        self::mockTime($time);
        $tester = new CommandTester((new Application($this->client->getKernel()))->find('app:notify'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }

    /** @return list<TemplatedEmail> */
    private function mailsTo(string $address): array
    {
        $mails = [];
        foreach (self::getMailerEvents() as $event) {
            $email = $event->getMessage();
            if (!$event->isQueued() && $email instanceof TemplatedEmail && $email->getTo()[0]->getAddress() === $address) {
                $mails[] = $email;
            }
        }

        return $mails;
    }

    public function testSummaryOfNewMailsIsOptIn(): void
    {
        $this->member->setMailDigest(true);
        $this->em()->flush();
        $this->message('Elternabend am Donnerstag', 'Liebe Eltern, '.str_repeat('wir laden euch herzlich ein ', 10));
        $this->message('Gewinnspiel')->setSpam(true);
        $this->message('Schon erledigt')->markDone($this->admin);
        $otherOrg = $this->createOrganization($this->createUser('other@example.org'), 'Andere');
        $this->message('Fremde Mail', account: $this->createMailAccount($otherOrg, 'fremd@example.org'));
        $this->em()->flush();

        // not before 7 o'clock
        $today = new \DateTimeImmutable('today');
        $this->notify($today->setTime(6, 30)->format('Y-m-d H:i'));
        self::assertSame([], $this->mailsTo($this->member->getEmail()));

        $this->notify($today->setTime(7, 15)->format('Y-m-d H:i'));
        $mails = $this->mailsTo($this->member->getEmail());
        self::assertCount(1, $mails);
        self::assertSame('Rokoso: 1 neue E-Mail', $mails[0]->getSubject());
        $text = (string) $mails[0]->getTextBody();
        self::assertStringContainsString('Anna Schmidt <anna@example.org>', $text);
        self::assertStringContainsString('Elternabend am Donnerstag', $text);
        self::assertStringContainsString('Liebe Eltern, wir laden', $text);
        self::assertStringContainsString('…', $text);
        self::assertStringNotContainsString('Gewinnspiel', $text);
        self::assertStringNotContainsString('Schon erledigt', $text);
        self::assertStringNotContainsString('Fremde Mail', $text);
        self::assertStringContainsString('Elternabend am Donnerstag', (string) $mails[0]->getHtmlBody());
        self::assertTrue($mails[0]->getHeaders()->has('List-Unsubscribe'));
        // without the checkbox nothing
        self::assertSame([], $this->mailsTo('owner@example.org'));

        // once a day
        $this->notify($today->setTime(9, 0)->format('Y-m-d H:i'));
        self::assertCount(1, $this->mailsTo($this->member->getEmail()));
    }

    public function testNoMailWithoutNewMails(): void
    {
        $this->member->setMailDigest(true);
        $this->em()->flush();

        $this->notify((new \DateTimeImmutable('today'))->setTime(8, 0)->format('Y-m-d H:i'));
        self::assertSame([], $this->mailsTo($this->member->getEmail()));
        $this->em()->clear();
        self::assertNotNull($this->em()->find(User::class, $this->member->getId())?->getLastMailDigestAt());
    }

    public function testDailyModeSendsOneCombinedMail(): void
    {
        $this->member->setMailDigest(true)->setNotificationEmail(NotificationEmail::Daily);
        $this->em()->flush();
        $this->message('Frage zur Anmeldung');
        self::getContainer()->get(NotificationCenter::class)->notify([$this->member], NotificationType::Comment, 'Protokoll', '/projects', $this->admin);
        $this->em()->flush();

        $this->notify((new \DateTimeImmutable('today'))->setTime(8, 0)->format('Y-m-d H:i'));
        $mails = $this->mailsTo($this->member->getEmail());
        self::assertCount(1, $mails);
        self::assertSame('Rokoso: neue E-Mails und Benachrichtigungen', $mails[0]->getSubject());
        $text = (string) $mails[0]->getTextBody();
        self::assertStringContainsString('Protokoll', $text);
        self::assertStringContainsString('Frage zur Anmeldung', $text);
    }

    public function testSettingAndUnsubscribe(): void
    {
        $this->login($this->member);
        $this->client->request('GET', '/profile/notifications');
        $this->client->submitForm('Speichern', ['email' => 'instant', 'mail_digest' => true]);
        self::assertResponseRedirects('/profile/notifications');
        $this->em()->clear();
        self::assertTrue($this->em()->find(User::class, $this->member->getId())?->wantsMailDigest());

        // the unsubscribe link in every mail also ends the summary
        $user = $this->em()->find(User::class, $this->member->getId());
        self::assertNotNull($user);
        $this->client->request('POST', self::getContainer()->get(NotificationMailer::class)->unsubscribeUrl($user));
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertFalse($this->em()->find(User::class, $this->member->getId())?->wantsMailDigest());
    }

    public function testPreview(): void
    {
        self::assertSame('Kurz', NotificationMailer::preview(' Kurz '));
        $preview = NotificationMailer::preview(str_repeat('Wort ', 40));
        self::assertLessThanOrEqual(101, mb_strlen($preview));
        self::assertStringEndsWith('Wort…', $preview);
        self::assertSame(str_repeat('x', 100).'…', NotificationMailer::preview(str_repeat('x', 120)));
    }
}
