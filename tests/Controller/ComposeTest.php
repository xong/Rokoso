<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Tests\AppTestCase;

final class ComposeTest extends AppTestCase
{
    private User $user;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->account = $this->createMailAccount($this->createOrganization($this->user));
        $this->login($this->user);
    }

    public function testSendNewMailFilesItUnderSent(): void
    {
        $this->client->request('GET', '/mail/new');
        $this->client->submitForm('Senden', [
            'compose_form[to]' => 'Eva <eva@example.org>, bert@example.org',
            'compose_form[subject]' => 'Treffen am Montag',
            'compose_form[body]' => 'Hallo, wir treffen uns um 19 Uhr.',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Treffen am Montag');

        $sent = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Treffen am Montag']);
        self::assertNotNull($sent);
        self::assertSame(MessageFolder::Sent, $sent->getFolder());
        self::assertSame('sev@example.org', $sent->getFromAddress());
        self::assertSame(['eva@example.org', 'bert@example.org'], array_column($sent->getToRecipients(), 'address'));

        $this->client->request('GET', '/mail/sent');
        self::assertSelectorTextContains('main', 'Treffen am Montag');
    }

    public function testInvalidAddressIsRejected(): void
    {
        $this->client->request('GET', '/mail/new');
        $this->client->submitForm('Senden', ['compose_form[to]' => 'keine-adresse', 'compose_form[subject]' => 'x']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.error', 'keine gültige');
    }

    public function testReplyIsPrefilled(): void
    {
        $original = (new Message())->setMailAccount($this->account)->setFrom('eva@example.org', 'Eva')
            ->setSubject('Frage')->setBody("Zeile 1\nZeile 2")->setMessageIdHeader('orig@example.org');
        $this->em()->persist($original);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/mail/new?reply='.$original->getId());
        self::assertSame('eva@example.org', $crawler->filter('#compose_form_to')->attr('value'));
        self::assertSame('Re: Frage', $crawler->filter('#compose_form_subject')->attr('value'));
        self::assertStringContainsString('> Zeile 2', $crawler->filter('#compose_form_body')->text());

        $this->client->submitForm('Senden');
        $reply = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Re: Frage']);
        self::assertSame('orig@example.org', $reply?->getInReplyTo());
    }
}
