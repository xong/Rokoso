<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\NotificationEmail;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Notification\NotificationCenter;
use App\Tests\AppTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Message;

/**
 * Notifications: mentions, comments, watching, opening, settings and reminders.
 */
final class NotificationTest extends AppTestCase
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

    /**
     * @return list<Notification>
     */
    private function notificationsOf(User $user): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(Notification::class)->findBy(['recipient' => $user->getId()], ['id' => 'ASC']);
    }

    public function testMentionParsing(): void
    {
        $eva = new User();
        $eva->setName('Eva');
        $evaMuster = new User();
        $evaMuster->setName('Eva Muster');
        $bob = new User();
        $bob->setName('Bob');

        self::assertSame([$evaMuster], NotificationCenter::mentions('Hallo @eva muster, bitte prüfen', [$eva, $evaMuster, $bob]));
        self::assertSame([$eva, $bob], NotificationCenter::mentions('@Eva und @Bob!', [$eva, $evaMuster, $bob]));
        self::assertSame([], NotificationCenter::mentions('mail an eva@Bob.de oder @Bobby', [$eva, $bob]));
    }

    public function testProjectCommentNotifiesMentionedAndWatchers(): void
    {
        $project = (new Project($this->admin))->setName('Elternabend')->setOrganization($this->org);
        $this->em()->persist($project);
        $this->em()->flush();

        $this->login($this->member);
        $this->client->request('GET', '/projects/'.$project->getId());
        $this->client->submitForm('Kommentieren', ['body' => '@Eva Owner, der Raum ist gebucht.']);
        self::assertResponseRedirects();
        self::assertEmailCount(1);

        $notifications = $this->notificationsOf($this->admin);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::Mentioned, $notifications[0]->getType());
        self::assertSame('Anna Schulz', $notifications[0]->getActor()?->getName());
        self::assertCount(0, $this->notificationsOf($this->member));

        // The admin answers; the member commented before and is notified
        $this->login($this->admin);
        $this->client->request('GET', '/projects/'.$project->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Danke!']);
        $notifications = $this->notificationsOf($this->member);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::Comment, $notifications[0]->getType());
    }

    public function testWatchedTopicNotifiesOnReplyAndOpeningMarksRead(): void
    {
        $board = (new ForumBoard($this->org, $this->admin))->setName('Allgemeines');
        $topic = (new ForumTopic($board, $this->admin))->setTitle('Schulweg');
        $topic->addPost((new ForumPost($topic, $this->admin))->setBody('Start'));
        $this->em()->persist($board);
        $this->em()->persist($topic);
        $this->em()->flush();

        // The admin watches the topic via the bell button
        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $this->client->submit($crawler->filter('form[action*="/watch/topic/"]')->form());
        self::assertResponseRedirects('/forum/topic/'.$topic->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('form[action*="/watch/topic/"] button[aria-pressed="true"]');

        $this->login($this->member);
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $this->client->submitForm('Antwort senden', ['forum_post_form[body]' => 'Ich übernehme das.']);
        self::assertResponseRedirects();

        $notifications = $this->notificationsOf($this->admin);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::Post, $notifications[0]->getType());

        $this->login($this->admin);
        $this->client->request('GET', '/notifications');
        self::assertSelectorTextContains('main', 'Anna Schulz hat in „Schulweg“ geantwortet');
        self::assertSelectorTextContains('nav[aria-label="Hauptnavigation"]', '1 ungelesene Benachrichtigungen');
        $this->client->request('GET', '/notifications/'.$notifications[0]->getId());
        self::assertResponseRedirects('/forum/topic/'.$topic->getId());
        self::assertTrue($this->notificationsOf($this->admin)[0]->isRead());

        // Others cannot open someone else's notification
        $this->login($this->member);
        $this->client->request('GET', '/notifications/'.$notifications[0]->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmailSettings(): void
    {
        $project = (new Project($this->admin))->setName('Fest')->setOrganization($this->org);
        $this->em()->persist($project);
        $this->em()->flush();

        $this->login($this->member);
        $this->client->request('GET', '/profile/notifications');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Speichern', ['email' => 'off']);
        self::assertResponseRedirects('/profile/notifications');
        $this->em()->clear();
        self::assertSame(NotificationEmail::Off, $this->em()->find(User::class, $this->member->getId())?->getNotificationEmail());

        // With mails off, a mention creates a notification but no mail
        $this->login($this->admin);
        $this->client->request('GET', '/projects/'.$project->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Hallo @anna schulz']);
        self::assertEmailCount(0);
        self::assertCount(1, $this->notificationsOf($this->member));
    }

    public function testOneClickUnsubscribeFromMail(): void
    {
        $project = (new Project($this->admin))->setName('Fest')->setOrganization($this->org);
        $this->em()->persist($project);
        $this->em()->flush();

        $this->login($this->admin);
        $this->client->request('GET', '/projects/'.$project->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Hallo @anna schulz']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Message::class, $email);
        self::assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->getHeaderBody('List-Unsubscribe-Post'));
        $header = $email->getHeaders()->getHeaderBody('List-Unsubscribe');
        self::assertIsString($header);
        self::assertStringStartsWith('<', $header);
        $url = trim($header, '<>');

        $this->client->request('GET', '/logout');
        $this->client->request('GET', $url.'x');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'anna@example.org');

        // what mail programs send for one-click unsubscribe
        $this->client->request('POST', $url, ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertSame(NotificationEmail::Off, $this->em()->find(User::class, $this->member->getId())?->getNotificationEmail());
    }

    public function testCalendarAssignmentAndDueReminder(): void
    {
        $item = (new CalendarItem($this->admin))->setType(CalendarItemType::Task)->setTitle('Protokoll schreiben')
            ->setStartsAt(new \DateTimeImmutable('+3 hours'))->setOrganization($this->org);
        $item->addAssignee($this->member);
        $this->em()->persist($item);
        $this->em()->flush();

        $tester = new CommandTester((new Application($this->client->getKernel()))->find('app:notify'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $notifications = $this->notificationsOf($this->member);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::Due, $notifications[0]->getType());

        // Running again does not remind twice
        $tester->execute([]);
        self::assertCount(1, $this->notificationsOf($this->member));
        self::assertSame([], $this->notificationsOf($this->admin));
    }

    public function testReminderFollowsItemSetting(): void
    {
        // One hour before: not yet due at three hours ahead
        $later = (new CalendarItem($this->admin))->setTitle('Sitzung später')->setOrganization($this->org)
            ->setStartsAt(new \DateTimeImmutable('+3 hours'))->setReminderMinutes(60)->addParticipant($this->member);
        // One week before: due although it starts in five days
        $week = (new CalendarItem($this->admin))->setTitle('Elternabend')->setOrganization($this->org)
            ->setStartsAt(new \DateTimeImmutable('+5 days'))->setReminderMinutes(10080)->addParticipant($this->member);
        // No reminder at all
        $none = (new CalendarItem($this->admin))->setTitle('Ohne Erinnerung')->setOrganization($this->org)
            ->setStartsAt(new \DateTimeImmutable('+1 hour'))->setReminderMinutes(null)->addParticipant($this->member);
        // Without participants the creator is reminded
        $own = (new CalendarItem($this->admin))->setTitle('Eigener Termin')->setStartsAt(new \DateTimeImmutable('+2 hours'));
        foreach ([$later, $week, $none, $own] as $item) {
            $this->em()->persist($item);
        }
        $this->em()->flush();

        $tester = new CommandTester((new Application($this->client->getKernel()))->find('app:notify'));
        $tester->execute([]);
        self::assertSame(['Elternabend'], array_map(static fn ($n): string => $n->getSubject(), $this->notificationsOf($this->member)));
        self::assertSame(['Eigener Termin'], array_map(static fn ($n): string => $n->getSubject(), $this->notificationsOf($this->admin)));
    }
}
