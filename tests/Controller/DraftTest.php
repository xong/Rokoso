<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Draft;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Signature;
use App\Entity\TextSnippet;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Mail\ComposeData;
use App\Mail\MailSender;
use App\Mail\Outbox;
use App\Mail\SentFolderWriter;
use App\Repository\DraftRepository;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeSentFolderWriter;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

final class DraftTest extends AppTestCase
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

    public function testAutosaveCreatesAndUpdatesOneDraft(): void
    {
        $crawler = $this->client->request('GET', '/mail/new');
        $token = (string) $crawler->filter('#compose_form__token')->attr('value');
        $fields = ['account' => $this->account->getId(), 'to' => '', 'subject' => 'Erster Stand', 'body' => 'Hallo', '_token' => $token];

        $this->client->request('POST', '/mail/new', ['compose_form' => $fields], [], ['HTTP_X_AUTOSAVE' => '1', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseIsSuccessful();
        $id = json_decode((string) $this->client->getResponse()->getContent(), true)['id'] ?? null;
        self::assertIsInt($id);

        $this->client->request('POST', '/mail/new', ['compose_form' => ['subject' => 'Zweiter Stand', 'draft' => $id] + $fields], [], ['HTTP_X_AUTOSAVE' => '1', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertSame($id, json_decode((string) $this->client->getResponse()->getContent(), true)['id'] ?? null);

        $drafts = $this->em()->getRepository(Draft::class)->findAll();
        self::assertCount(1, $drafts);
        self::assertSame('Zweiter Stand', $drafts[0]->getSubject());

        $this->client->request('POST', '/mail/new', ['compose_form' => ['_token' => 'falsch'] + $fields], [], ['HTTP_X_AUTOSAVE' => '1', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDraftWithoutRecipientCanBeSavedOpenedAndSent(): void
    {
        $this->client->request('GET', '/mail/new');
        $this->client->submitForm('Als Entwurf speichern', ['compose_form[subject]' => 'Noch nicht fertig', 'compose_form[body]' => 'Text']);
        self::assertResponseRedirects('/mail/drafts');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Noch nicht fertig');
        self::assertSelectorTextContains('main', 'Noch kein Empfänger');

        $draft = $this->em()->getRepository(Draft::class)->findOneBy(['subject' => 'Noch nicht fertig']);
        self::assertNotNull($draft);
        $crawler = $this->client->request('GET', '/mail/new?draft='.$draft->getId());
        self::assertSame('Noch nicht fertig', $crawler->filter('#compose_form_subject')->attr('value'));

        $this->client->submitForm('Senden', ['compose_form[to]' => 'eva@example.org']);
        self::assertResponseRedirects();
        self::assertSame(0, $this->em()->getRepository(Draft::class)->count([]));
        self::assertNotNull($this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Noch nicht fertig']));
    }

    public function testForeignDraftIsForbidden(): void
    {
        $other = $this->createUser('kim@example.org', 'Kim');
        $draft = new Draft($other, $this->account);
        $this->em()->persist($draft);
        $this->em()->flush();

        $this->client->request('GET', '/mail/new?draft='.$draft->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/mail/drafts/'.$draft->getId().'/delete', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSignatureIsInsertedAndMailGoesOutAsHtmlAndToSentFolder(): void
    {
        $this->account->setSentFolder('Sent');
        $this->em()->persist((new Signature($this->user, $this->account))->setBody("Anna Schulz\nVorsitz"));
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/mail/new');
        self::assertStringContainsString("-- \nAnna Schulz\nVorsitz", $crawler->filter('#compose_form_body')->text(normalizeWhitespace: false));

        $this->client->submitForm('Senden', [
            'compose_form[to]' => 'eva@example.org',
            'compose_form[subject]' => 'Formatiert',
            'compose_form[body]' => "Das ist **wichtig**.\nZweite Zeile",
        ]);
        $sent = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Formatiert']);
        self::assertNotNull($sent);
        self::assertStringContainsString('<strong>wichtig</strong>.<br>', (string) $sent->getHtmlBody());
        self::assertStringContainsString('Das ist **wichtig**.', (string) $sent->getTextBody());

        $writer = self::getContainer()->get(SentFolderWriter::class);
        self::assertInstanceOf(FakeSentFolderWriter::class, $writer);
        self::assertCount(1, $writer->appended);
        self::assertSame('Sent', $writer->appended[0]['folder']);
        self::assertStringContainsString('Subject: Formatiert', $writer->appended[0]['raw']);
    }

    public function testSignaturesCanBeSaved(): void
    {
        $this->client->request('GET', '/mail/signatures');
        $this->client->submitForm('Speichern', ['signature['.$this->account->getId().']' => "Viele Grüße\nAnna  \r\n"]);
        self::assertResponseRedirects('/mail/signatures');

        $signature = $this->em()->getRepository(Signature::class)->findOneBy(['user' => $this->user]);
        self::assertSame("Viele Grüße\nAnna", $signature?->getBody());

        $this->client->request('GET', '/mail/signatures');
        $this->client->submitForm('Speichern', ['signature['.$this->account->getId().']' => '']);
        self::assertSame(0, $this->em()->getRepository(Signature::class)->count([]));
    }

    public function testQueuedMailCanBeCancelledOrGoesOutWhenDue(): void
    {
        // the outbox below and the requests share one entity manager
        $this->client->disableReboot();
        $clock = new MockClock('2026-10-04 10:00:00');
        $outbox = new Outbox(
            self::getContainer()->get(MailSender::class),
            self::getContainer()->get(DraftRepository::class),
            $this->em(),
            new NullLogger(),
            $clock,
            10,
        );
        $data = new ComposeData();
        $data->account = $this->account;
        $data->to = 'eva@example.org';
        $data->subject = 'Mit Verzögerung';
        $draft = (new Draft($this->user, $this->account))->apply($data);
        $this->em()->persist($draft);

        self::assertNull($outbox->queue($draft));
        self::assertTrue($draft->isQueued());
        self::assertSame(0, $outbox->sendDue());

        // undo
        $crawler = $this->client->request('GET', '/mail/drafts');
        self::assertSelectorTextContains('#queued-heading', 'Wird gleich gesendet');
        $undo = $crawler->filter('form[action$="/cancel"]');
        self::assertCount(1, $undo);
        $token = (string) $undo->filter('input[name="_token"]')->attr('value');
        $this->client->submit($undo->form());
        self::assertResponseRedirects('/mail/new?draft='.$draft->getId());
        $this->em()->clear();
        $draft = $this->em()->find(Draft::class, $draft->getId());
        self::assertNotNull($draft);
        self::assertFalse($draft->isQueued());

        // queued again, sent when due
        $id = $draft->getId();
        $outbox->queue($draft);
        $clock->modify('+11 seconds');
        self::assertSame(1, $outbox->sendDue());
        self::assertSame(0, $this->em()->getRepository(Draft::class)->count([]));
        self::assertNotNull($this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Mit Verzögerung']));

        // undo after sending: too late
        $this->client->request('POST', '/mail/drafts/'.$id.'/cancel', ['_token' => $token]);
        self::assertResponseRedirects('/mail/sent');
    }

    public function testOtherWritersAreShownOnTheMessage(): void
    {
        $kim = $this->createUser('kim@example.org', 'Kim Kollegin');
        $this->account->getOrganization()->addMember($kim, OrganizationRole::Member);
        $original = (new Message())->setMailAccount($this->account)->setFrom('eva@example.org', 'Eva')->setSubject('Frage')->setBody('?');
        $this->em()->persist($original);
        $reply = new ComposeData();
        $reply->original = $original;
        $this->em()->persist((new Draft($kim, $this->account))->apply($reply));
        $this->em()->flush();

        $this->client->request('GET', '/mail/inbox/'.$original->getId());
        self::assertSelectorTextContains('main', 'Kim Kollegin schreibt gerade eine Antwort');
        $this->client->request('GET', '/mail/new?reply='.$original->getId());
        self::assertSelectorTextContains('main', 'Kim Kollegin schreibt gerade');
    }

    public function testSnippetsAreManagedPerOrganizationAndOfferedWhileWriting(): void
    {
        $organization = $this->account->getOrganization();
        $this->client->request('GET', '/organizations/'.$organization->getId().'/snippets/new');
        $this->client->submitForm('Speichern', ['text_snippet_form[title]' => 'Gruß', 'text_snippet_form[body]' => 'Viele Grüße aus dem Vorstand']);
        self::assertResponseRedirects('/organizations/'.$organization->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Viele Grüße aus dem Vorstand');

        $crawler = $this->client->request('GET', '/mail/new');
        self::assertSame('Viele Grüße aus dem Vorstand', $crawler->filter('#compose-snippet option')->eq(1)->attr('value'));

        $snippet = $this->em()->getRepository(TextSnippet::class)->findOneBy(['title' => 'Gruß']);
        self::assertNotNull($snippet);
        $this->client->request('GET', '/organizations/snippets/'.$snippet->getId().'/edit');
        $this->client->submitForm('Löschen');
        self::assertSame(0, $this->em()->getRepository(TextSnippet::class)->count([]));

        // outsiders cannot add snippets
        $this->login($this->createUser('fremd@example.org', 'Fremd'));
        $this->client->request('GET', '/organizations/'.$organization->getId().'/snippets/new');
        self::assertResponseStatusCodeSame(403);
    }
}
