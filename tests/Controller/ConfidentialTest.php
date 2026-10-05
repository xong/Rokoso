<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Confidential\ConfidentialCrypto;
use App\Confidential\ConfidentialInbox;
use App\Entity\ConfidentialCase;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\PublicSettings;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Participation\PublicGuard;
use App\Service\RetentionCleaner;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\Email;

/**
 * Anonymous confidential contact: public conversation with access code, answers by confidants, retention.
 */
final class ConfidentialTest extends AppTestCase
{
    private User $admin;
    private User $confidant;
    private Organization $org;
    private PublicSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->org = $this->createOrganization($this->admin);
        $this->confidant = $this->createUser('vera@example.org', 'Vera Vertrauen');
        $this->org->addMember($this->confidant, OrganizationRole::Member)->setConfidant(true);
        $this->settings = (new PublicSettings($this->org))->setSlug('sev-test')->setConfidentialEnabled(true);
        $this->em()->persist($this->settings);
        $this->em()->flush();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function submit(Crawler $crawler, string $button, array $values): Crawler
    {
        $form = $crawler->selectButton($button)->form();
        $data = array_replace_recursive($form->getPhpValues(), $values);
        $name = array_key_first($values);
        \assert(\is_string($name));
        $data[$name][PublicGuard::STAMP] = self::getContainer()->get(PublicGuard::class)->stamp(time() - 30);

        return $this->client->request('POST', $form->getUri(), $data);
    }

    /**
     * Starts a conversation on the public page and returns the access code.
     */
    private function startConversation(string $email = ''): string
    {
        $crawler = $this->client->request('GET', '/p/sev-test/confidential');
        $crawler = $this->submit($crawler, 'Vertraulich senden', ['confidential_new' => [
            'subject' => 'Vorfall in der 4b', 'message' => 'Das darf niemand erfahren.', 'email' => $email, 'consent' => '1',
        ]]);
        self::assertResponseIsSuccessful();

        return trim($crawler->filter('[data-testid="confidential-code"]')->text());
    }

    private function onlyCase(): ConfidentialCase
    {
        $this->em()->clear();
        $cases = $this->em()->getRepository(ConfidentialCase::class)->findAll();
        self::assertCount(1, $cases);

        return $cases[0];
    }

    public function testAnonymousConversationWithAccessCode(): void
    {
        $this->client->request('GET', '/p/sev-test/confidential');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $code = $this->startConversation();
        self::assertTrue(ConfidentialCrypto::isWellFormed($code));

        // stored encrypted, the confidant (and only the confidant) is notified without content
        $raw = $this->em()->getConnection()->fetchAssociative('SELECT c.subject, m.body FROM confidential_case c JOIN confidential_message m ON m.confidential_case_id = c.id');
        self::assertIsArray($raw);
        self::assertStringNotContainsString('Vorfall', (string) $raw['subject']);
        self::assertStringNotContainsString('niemand', (string) $raw['body']);
        $notifications = $this->em()->getRepository(Notification::class)->findAll();
        self::assertCount(1, $notifications);
        self::assertSame($this->confidant->getId(), $notifications[0]->getRecipient()->getId());
        self::assertSame('SEV Musterstadt', $notifications[0]->getSubject());

        // the confidant reads and answers
        $this->login($this->confidant);
        $this->client->request('GET', '/confidential');
        self::assertSelectorTextContains('main', 'Vorfall in der 4b');
        $case = $this->onlyCase();
        $crawler = $this->client->request('GET', '/confidential/'.$case->getId());
        self::assertSelectorTextContains('main', 'Das darf niemand erfahren.');
        $this->client->submit($crawler->selectButton('Antwort senden')->form(['body' => 'Wir kümmern uns darum.']));
        self::assertResponseRedirects('/confidential/'.$case->getId());
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Wir kümmern uns darum.');
        $this->client->submit($crawler->filter('form[action$="/close"]')->form());
        self::assertTrue($this->onlyCase()->isClosed());

        // the person opens the conversation with the code (typed sloppily) and writes again
        $this->client->getCookieJar()->clear();
        $crawler = $this->client->request('GET', '/p/sev-test/confidential');
        $crawler = $this->submit($crawler, 'Gespräch öffnen', ['confidential_open' => ['code' => strtolower(str_replace('-', ' ', $code))]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Wir kümmern uns darum.');
        self::assertSelectorTextContains('main', 'Es gibt eine neue Antwort');
        self::assertSelectorTextNotContains('main', 'Vera');
        $this->submit($crawler, 'Senden', ['confidential_reply' => ['message' => 'Danke!']]);
        self::assertSelectorTextContains('main', 'Deine Nachricht wurde gesendet');
        self::assertSelectorTextContains('main', 'Danke!');

        $case = $this->onlyCase();
        self::assertFalse($case->isClosed());
        self::assertTrue($case->isStaffUnread());
        self::assertCount(3, $case->getMessages());
    }

    public function testUnknownCodeAndForeignAccessAreRejected(): void
    {
        $this->startConversation();
        $crawler = $this->client->request('GET', '/p/sev-test/confidential');
        $this->submit($crawler, 'Gespräch öffnen', ['confidential_open' => ['code' => 'AAAA-BBBB-CCCC-DDDD']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Zu diesem Zugangscode gibt es kein Gespräch');

        $this->client->request('POST', '/p/sev-test/confidential/reply', ['confidential_reply' => ['code' => 'AAAA-BBBB-CCCC-DDDD', 'message' => 'x']]);
        self::assertResponseStatusCodeSame(404);

        $this->em()->getRepository(PublicSettings::class)->findOneBy(['slug' => 'sev-test'])?->setConfidentialEnabled(false);
        $this->em()->flush();
        $this->client->request('GET', '/p/sev-test/confidential');
        self::assertResponseStatusCodeSame(404);

        // admins only see the conversations when they are confidants themselves
        $case = $this->onlyCase();
        $this->login($this->admin);
        $this->client->request('GET', '/confidential');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/confidential/'.$case->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testOptionalEmailGetsNoticeWithoutContentAndStaysHidden(): void
    {
        $code = $this->startConversation('anonym@example.net');
        self::assertNotSame('', $code);
        $case = $this->onlyCase();
        self::assertTrue($case->hasEmail());
        self::assertStringNotContainsString('anonym@example.net', (string) $case->getEmail());

        $this->login($this->confidant);
        $crawler = $this->client->request('GET', '/confidential/'.$case->getId());
        self::assertSelectorTextNotContains('body', 'anonym@example.net');
        $this->client->submit($crawler->selectButton('Antwort senden')->form(['body' => 'Streng geheime Antwort']));

        $notices = [];
        foreach (self::getMailerEvents() as $event) {
            $mail = $event->getMessage();
            if (!$event->isQueued() && $mail instanceof Email && 'anonym@example.net' === ($mail->getTo()[0] ?? null)?->getAddress()) {
                $notices[] = $mail;
            }
        }
        self::assertCount(1, $notices);
        self::assertStringNotContainsString('Streng geheime Antwort', (string) $notices[0]->getHtmlBody().$notices[0]->getTextBody());
        self::assertStringContainsString('/p/sev-test/confidential', (string) $notices[0]->getTextBody());
    }

    public function testRetentionDeletesInactiveConversations(): void
    {
        $inbox = self::getContainer()->get(ConfidentialInbox::class);
        $inbox->open($this->settings, 'Alt', 'Alte Nachricht', null);
        $inbox->open($this->settings, 'Neu', 'Neue Nachricht', null);
        $this->org->setConfidentialRetentionMonths(3);
        $this->em()->flush();
        $this->em()->getConnection()->executeStatement('UPDATE confidential_case SET last_activity_on = ? WHERE id = (SELECT MIN(id) FROM (SELECT id FROM confidential_case) x)', [(new \DateTimeImmutable('-4 months'))->format('Y-m-d')]);

        $result = self::getContainer()->get(RetentionCleaner::class)->clean();
        self::assertSame(1, $result['confidential']);
        self::assertSame('Neu', $inbox->subject($this->onlyCase()));
    }

    public function testEnablingNeedsAConfidant(): void
    {
        $this->org->getMembership($this->confidant)?->setConfidant(false);
        $this->settings->setConfidentialEnabled(false);
        $this->em()->flush();

        $this->login($this->admin);
        $crawler = $this->client->request('GET', '/organizations/'.$this->org->getId().'/public');
        $this->client->submit($crawler->selectButton('Speichern')->form(['public_settings_form[confidentialEnabled]' => '1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'mindestens eine Vertrauensperson');
    }
}
