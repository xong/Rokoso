<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Entity\Comment;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumUpload;
use App\Entity\Message;
use App\Enum\MessageType;
use App\Enum\OrganizationRole;
use App\Forum\GroupMessageConverter;
use App\Service\AttachmentStorage;
use App\Tests\AppTestCase;

final class InternalMessageTest extends AppTestCase
{
    public function testMessageToColleagueAppearsInTheirInboxOnly(): void
    {
        $author = $this->createUser();
        $kim = $this->createUser('kim@example.org', 'Kim Kollegin');
        $other = $this->createUser('olaf@example.org', 'Olaf Other');
        $org = $this->createOrganization($author);
        $org->addMember($kim, OrganizationRole::Member);
        $org->addMember($other, OrganizationRole::Member);
        $this->em()->flush();

        $this->login($author);
        $crawler = $this->client->request('GET', '/mail/message/new');
        $form = $crawler->selectButton('Senden')->form();
        $values = $form->getPhpValues();
        $values['internal_message_form']['recipients'] = [(string) $kim->getId()];
        $values['internal_message_form']['subject'] = 'Protokoll';
        $values['internal_message_form']['body'] = 'Kannst du das Protokoll schreiben?';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects();

        // Autor sieht sie im Ausgang, nicht im Eingang
        $this->client->request('GET', '/mail/sent');
        self::assertSelectorTextContains('main', 'Protokoll');
        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Protokoll');

        $this->login($kim);
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main', 'Protokoll');

        $this->login($other);
        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Protokoll');
    }

    public function testFormOffersOnlyPeople(): void
    {
        $this->login();
        $this->client->request('GET', '/mail/message/new');
        self::assertSelectorNotExists('[name="internal_message_form[organization]"]');
        self::assertSelectorNotExists('[name="internal_message_form[project]"]');
        self::assertSelectorExists('a[href="/forum"]');
    }

    public function testGroupMessagesAreConvertedToForumTopics(): void
    {
        $author = $this->createUser();
        $kim = $this->createUser('kim@example.org', 'Kim Kollegin');
        $org = $this->createOrganization($author);
        $org->addMember($kim, OrganizationRole::Member);
        $storage = self::getContainer()->get(AttachmentStorage::class);
        $group = (new Message(MessageType::Internal))->setAuthor($author)->setFrom('owner@example.org', 'Eva')
            ->setOrganization($org)->setSubject('Rundbrief')->setBody('Hallo alle')
            ->setDate(new \DateTimeImmutable('2026-01-10 10:00'));
        $group->addAttachment(new Attachment($group, 'plan.txt', 'text/plain', 4, $storage->store('Plan')));
        $this->em()->persist($group);
        $this->em()->persist(Comment::onMessage($group, $kim)->setBody('Danke!'));
        $this->em()->flush();
        $reply = (new Message(MessageType::Internal))->setAuthor($kim)->setFrom('kim@example.org', 'Kim')
            ->setOrganization($org)->setSubject('Re: Rundbrief')->setBody('Bin dabei')
            ->setInReplyTo((string) $group->getId())->setDate(new \DateTimeImmutable('2026-01-11 09:00'));
        $direct = (new Message(MessageType::Internal))->setAuthor($author)->setFrom('owner@example.org', 'Eva')
            ->addRecipientUser($kim)->setSubject('Nur für Kim')->setBody('Hallo Kim');
        $this->em()->persist($reply);
        $this->em()->persist($direct);
        $this->em()->flush();
        $this->em()->clear();

        self::assertSame(1, self::getContainer()->get(GroupMessageConverter::class)->convert());
        $this->em()->clear();

        $topic = $this->em()->getRepository(ForumTopic::class)->findOneBy(['title' => 'Rundbrief']);
        self::assertNotNull($topic);
        self::assertSame('Frühere Rundnachrichten', $topic->getBoard()->getName());
        $posts = $this->em()->getRepository(ForumPost::class)->findBy(['topic' => $topic], ['createdAt' => 'ASC']);
        self::assertSame(['Hallo alle', 'Bin dabei', 'Danke!'], array_map(static fn (ForumPost $p): string => $p->getBody(), $posts));
        self::assertSame('2026-01-10', $posts[0]->getCreatedAt()->format('Y-m-d'));
        self::assertCount(1, $this->em()->getRepository(ForumUpload::class)->findBy(['post' => $posts[0]]));
        self::assertNull($this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Rundbrief']));
        self::assertNotNull($this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Nur für Kim']));
    }

    public function testRecipientIsRequired(): void
    {
        $this->login();
        $this->client->request('GET', '/mail/message/new');
        $this->client->submitForm('Senden', [
            'internal_message_form[subject]' => 'Ohne Empfänger',
            'internal_message_form[body]' => 'x',
        ]);
        self::assertResponseStatusCodeSame(422);
    }
}
