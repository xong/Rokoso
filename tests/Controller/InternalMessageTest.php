<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\OrganizationRole;
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

    public function testMessageToOrganizationReachesAllMembers(): void
    {
        $author = $this->createUser();
        $kim = $this->createUser('kim@example.org', 'Kim Kollegin');
        $org = $this->createOrganization($author);
        $org->addMember($kim, OrganizationRole::Member);
        $this->em()->flush();

        $this->login($author);
        $this->client->request('GET', '/mail/message/new');
        $this->client->submitForm('Senden', [
            'internal_message_form[organization]' => (string) $org->getId(),
            'internal_message_form[subject]' => 'Rundbrief',
            'internal_message_form[body]' => 'Hallo alle',
        ]);
        $message = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Rundbrief']);
        self::assertNotNull($message);
        self::assertTrue($message->isInternal());

        $this->login($kim);
        $this->client->request('GET', '/mail?show=internal');
        self::assertSelectorTextContains('main', 'Rundbrief');
        $this->client->request('GET', '/mail/inbox/'.$message->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Interne Nachricht');
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
