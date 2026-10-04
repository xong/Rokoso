<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\Contact;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\WikiPage;
use App\Enum\CalendarItemType;
use App\Enum\MessageFolder;
use App\Enum\NotificationType;
use App\Tests\AppTestCase;

final class TodaySearchTest extends AppTestCase
{
    public function testTodayShowsWhatNeedsAttention(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $message = (new Message())->setMailAccount($this->createMailAccount($org))->setFolder(MessageFolder::Inbox)
            ->setFrom('eva@example.org', 'Eva')->setSubject('Bitte um Rückruf')->setBody('Hallo')
            ->addAssignee($user);
        $task = (new CalendarItem($user))->setType(CalendarItemType::Task)->setTitle('Protokoll schreiben')
            ->setOrganization($org)->setStartsAt(new \DateTimeImmutable('-2 days'))->addAssignee($user);
        $later = (new CalendarItem($user))->setType(CalendarItemType::Task)->setTitle('Jahresbericht')
            ->setOrganization($org)->setStartsAt(new \DateTimeImmutable('+30 days'))->addAssignee($user);
        $event = (new CalendarItem($user))->setTitle('Vorstandstreffen')->setOrganization($org)
            ->setStartsAt(new \DateTimeImmutable('tomorrow 18:00'));
        $mention = new Notification($user, NotificationType::Mentioned, 'Du wurdest im Forum erwähnt', '/forum');
        foreach ([$message, $task, $later, $event, $mention] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section[aria-labelledby="today-messages"]', 'Bitte um Rückruf');
        self::assertSelectorTextContains('section[aria-labelledby="today-tasks"]', 'Protokoll schreiben');
        self::assertSelectorTextContains('section[aria-labelledby="today-tasks"]', 'Überfällig');
        self::assertSelectorTextNotContains('section[aria-labelledby="today-tasks"]', 'Jahresbericht');
        self::assertSelectorTextContains('section[aria-labelledby="today-events"]', 'Vorstandstreffen');
        self::assertSelectorTextContains('section[aria-labelledby="today-mentions"]', 'erwähnt');
        self::assertSelectorTextContains('section[aria-labelledby="today-polls"]', 'Nichts offen');
        // Command palette and shortcut help are part of every app page
        self::assertSelectorExists('dialog#command-palette input[role="combobox"][aria-controls="palette-list"]');
        self::assertSelectorExists('dialog#shortcuts-dialog');
    }

    public function testSearchFindsOnlyVisibleContent(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $other = $this->createOrganization($this->createUser('eve@example.org', 'Eve'), 'Fremder Verein');

        $page = (new WikiPage($org))->setTitle('Schulwegplan erstellen')->setBody('Anleitung');
        $hidden = (new WikiPage($other))->setTitle('Schulwegplan geheim')->setBody('Intern');
        $contact = (new Contact($user))->setOrganization($org)->setFirstName('Paula')->setLastName('Schulweg');
        $message = (new Message())->setMailAccount($this->createMailAccount($org))->setFolder(MessageFolder::Inbox)
            ->setFrom('eva@example.org', 'Eva')->setSubject('Frage zum Schulweg')->setBody('Hallo');
        $event = (new CalendarItem($user))->setTitle('Begehung Schulweg')->setOrganization($org);
        foreach ([$page, $hidden, $contact, $message, $event] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        $this->client->request('GET', '/search?q=Schulweg');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role=status]', 'Treffer');
        self::assertSelectorTextContains('section[aria-labelledby="search-wiki"]', 'Schulwegplan erstellen');
        self::assertSelectorTextNotContains('main', 'Schulwegplan geheim');
        self::assertSelectorTextContains('section[aria-labelledby="search-contacts"]', 'Paula');
        self::assertSelectorTextContains('section[aria-labelledby="search-messages"]', 'Frage zum Schulweg');
        self::assertSelectorTextContains('section[aria-labelledby="search-events"]', 'Begehung Schulweg');

        $this->client->request('GET', '/search?q=x');
        self::assertSelectorTextContains('[role=status]', 'mindestens zwei Zeichen');

        $this->client->request('GET', '/search?q=Nirgendwo');
        self::assertSelectorTextContains('[role=status]', 'Keine Treffer');
    }

    public function testCountsAreReturnedAsJson(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $message = (new Message())->setMailAccount($this->createMailAccount($org))->setFolder(MessageFolder::Inbox)
            ->setFrom('eva@example.org', 'Eva')->setSubject('Neu')->setBody('Hallo');
        $this->em()->persist($message);
        $this->em()->persist(new Notification($user, NotificationType::Mentioned, 'Erwähnt', '/'));
        $this->em()->flush();

        $this->client->request('GET', '/counts');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $counts = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['inbox' => 1, 'forum' => 0, 'notifications' => 1], $counts);

        $this->client->request('GET', '/');
        self::assertSelectorExists('[data-live-count="inbox"]:not([hidden])');
        self::assertSelectorExists('[data-live-count="forum"][hidden]');
    }
}
