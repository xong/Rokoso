<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\Meeting;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Enum\PushCategory;
use App\Mail\MailboxReader;
use App\Mail\MailSynchronizer;
use App\Meeting\MeetingService;
use App\Notification\ActivityNotifier;
use App\Notification\NotificationCenter;
use App\Notification\PushGateway;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;
use App\Tests\Fake\FakePushGateway;

/**
 * Push messages: categories per person, new notification sources, new mail (opt-in) and the test push.
 */
final class PushTest extends AppTestCase
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
        $this->em()->persist(new PushSubscription($this->member, 'https://push.example.org/anna', 'p256dh', 'auth'));
        $this->em()->flush();
    }

    private function gateway(): FakePushGateway
    {
        $gateway = static::getContainer()->get(PushGateway::class);
        self::assertInstanceOf(FakePushGateway::class, $gateway);

        return $gateway;
    }

    private function deliver(): void
    {
        static::getContainer()->get(NotificationCenter::class)->deliverPending();
    }

    private function org(): Organization
    {
        $org = $this->em()->find(Organization::class, $this->org->getId());
        self::assertNotNull($org);

        return $org;
    }

    private function user(User $user): User
    {
        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    /**
     * @return list<NotificationType>
     */
    private function typesOf(User $user): array
    {
        return array_map(static fn (Notification $n): NotificationType => $n->getType(),
            $this->em()->getRepository(Notification::class)->findBy(['recipient' => $user->getId()], ['id' => 'ASC']));
    }

    public function testCategoriesAreSavedAndRespected(): void
    {
        self::assertTrue($this->member->wantsPush(PushCategory::Mentioned));
        self::assertFalse($this->member->wantsPush(PushCategory::Mail), 'new mail is opt-in');

        $this->login($this->member);
        $crawler = $this->client->request('GET', '/profile/notifications');
        self::assertResponseIsSuccessful();
        self::assertCount(\count(PushCategory::cases()), $crawler->filter('input[name="push[]"]'));
        $token = $crawler->filter('input[name="section"]')->closest('form')?->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/profile/notifications', ['_token' => $token, 'section' => 'push', 'push' => ['message', 'mail', 'bogus']]);
        self::assertResponseRedirects('/profile/notifications');

        $this->em()->clear();
        $member = $this->user($this->member);
        self::assertTrue($member->wantsPush(PushCategory::Message));
        self::assertTrue($member->wantsPush(PushCategory::Mail));
        self::assertFalse($member->wantsPush(PushCategory::Mentioned));

        // A mention still lands behind the bell, but without push
        $project = (new Project($this->user($this->admin)))->setName('Fest')->setOrganization($this->org());
        $this->em()->persist($project);
        $this->em()->flush();
        $this->login($this->admin);
        $this->client->request('GET', '/projects/'.$project->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Hallo @anna schulz']);
        self::assertSame([NotificationType::Mentioned], $this->typesOf($this->member));
        self::assertSame([], $this->gateway()->titlesFor((int) $this->member->getId()));
    }

    public function testDirectMessageNotifiesAndPushes(): void
    {
        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/mail/message/new');
        $form = $crawler->selectButton('Senden')->form();
        $values = $form->getPhpValues();
        $values['internal_message_form']['recipients'] = [(string) $this->member->getId()];
        $values['internal_message_form']['subject'] = 'Protokoll';
        $values['internal_message_form']['body'] = 'Kannst du das Protokoll schreiben?';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects();

        self::assertSame([NotificationType::Message], $this->typesOf($this->member));
        $sent = $this->gateway()->sent;
        self::assertCount(1, $sent);
        self::assertSame('Eva Owner hat dir eine Nachricht geschickt: „Protokoll“', $sent[0]['payload']['title']);
        self::assertSame(1, $sent[0]['payload']['badge']);
        self::assertStringContainsString('/notifications/', (string) $sent[0]['payload']['url']);
    }

    public function testContactRequestNotifiesMembersWithoutAssignee(): void
    {
        $account = $this->createMailAccount($this->org());
        $message = (new Message())->setMailAccount($account)->setFolder(MessageFolder::Inbox)
            ->setFrom('mutter@example.org', 'Mutter')->setSubject('Frage zur Einschulung')->setBody('Hallo');
        $message->setThreadKey($message->deriveThreadKey());
        $this->em()->persist($message);
        $this->em()->flush();

        static::getContainer()->get(ActivityNotifier::class)->contactRequest($message);
        $this->em()->flush();
        $this->deliver();

        self::assertSame([NotificationType::Contact], $this->typesOf($this->member));
        self::assertSame([NotificationType::Contact], $this->typesOf($this->admin));
        self::assertSame(['Neue Anfrage über das Kontaktformular: „Frage zur Einschulung“'], $this->gateway()->titlesFor((int) $this->member->getId()));
    }

    public function testMeetingInvitationNotifiesWithoutExtraMail(): void
    {
        $admin = $this->user($this->admin);
        $meeting = (new Meeting($this->org(), $admin))->setTitle('Vollversammlung');
        $this->em()->persist($meeting);
        $this->em()->flush();

        static::getContainer()->get(MeetingService::class)->invite($meeting, $admin);
        $this->em()->flush();
        self::assertEmailCount(2, null, 'only the invitations themselves');
        $this->deliver();

        self::assertSame([NotificationType::Meeting], $this->typesOf($this->member));
        self::assertSame([], $this->typesOf($this->admin), 'not the sender');
        self::assertStringContainsString('Vollversammlung', $this->gateway()->titlesFor((int) $this->member->getId())[0] ?? '');
    }

    public function testNewMailIsPushedOnlyWhenWanted(): void
    {
        $account = $this->createMailAccount($this->org());
        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $sync = function (string ...$subjects) use ($reader, $account): void {
            foreach ($subjects as $subject) {
                $reader->add("From: Paula Post <paula@example.org>\r\nTo: sev@example.org\r\nSubject: $subject\r\n"
                    .'Message-ID: <'.md5($subject)."@example.org>\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nText\r\n");
            }
            $fresh = $this->em()->find(MailAccount::class, $account->getId());
            self::assertNotNull($fresh);
            static::getContainer()->get(MailSynchronizer::class)->sync($fresh);
            $this->deliver();
        };

        $member = $this->user($this->member);
        $member->setPushCategories([PushCategory::Mail]);
        $this->em()->flush();

        // The first fetch brings the existing mail: no push
        $sync('Alt');
        self::assertSame([], $this->gateway()->sent);

        $sync('Elternabend', 'Schulfest');
        $sent = $this->gateway()->sent;
        self::assertCount(1, $sent, 'one summary per fetch');
        self::assertSame('2 neue E-Mails in '.$account->getName(), $sent[0]['payload']['title']);
        self::assertSame("Paula Post: Schulfest\nPaula Post: Elternabend", $sent[0]['payload']['body']);
        self::assertSame('/mail', $sent[0]['payload']['url']);
        self::assertSame([], $this->typesOf($this->member), 'nothing behind the bell');
    }

    public function testTestPush(): void
    {
        $this->login($this->member);
        $crawler = $this->client->request('GET', '/profile/notifications');
        $this->client->submit($crawler->selectButton('Testnachricht senden')->form());
        self::assertResponseRedirects('/profile/notifications');
        self::assertSame(['Push-Benachrichtigungen funktionieren'], $this->gateway()->titlesFor((int) $this->member->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Die Testnachricht ist unterwegs.');

        // A refusal of the push service is shown with its reason (same container for the next requests)
        $this->client->disableReboot();
        $this->gateway()->failure = 'HTTP 403 key mismatch';
        $crawler = $this->client->request('GET', '/profile/notifications');
        $this->client->submit($crawler->selectButton('Testnachricht senden')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'abgelehnt (HTTP 403 key mismatch)');

        // Without a device there is nothing to test
        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/profile/notifications');
        self::assertCount(1, $crawler->filter('form[action="/profile/push/test"][hidden]'));
    }
}
