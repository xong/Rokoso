<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\Theme;
use App\Mail\MailboxReader;
use App\Mail\MailSynchronizer;
use App\Repository\MessageRepository;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;

final class MailTest extends AppTestCase
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

    private function syncFixture(): Message
    {
        $reader = static::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $reader->add((string) file_get_contents(__DIR__.'/../fixtures/simple.eml'));
        self::assertSame(1, static::getContainer()->get(MailSynchronizer::class)->sync($this->account));

        $message = $this->em()->getRepository(Message::class)->findOneBy(['messageIdHeader' => 'abc123@example.org']);
        self::assertNotNull($message);

        return $message;
    }

    public function testSyncImportsOnceAndKeepsPosition(): void
    {
        $message = $this->syncFixture();
        self::assertSame($this->org, $message->getOrganization());
        self::assertTrue($message->hasAttachments());
        self::assertSame(1, $this->account->getLastUid());

        // Erneuter Abruf importiert nichts doppelt
        self::assertSame(0, static::getContainer()->get(MailSynchronizer::class)->sync($this->account));
    }

    public function testInboxListShowAndSanitizedHtml(): void
    {
        $message = $this->syncFixture();
        $this->login($this->user);

        $this->client->request('GET', '/mail');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Frage zur Schulwegsicherheit');
        self::assertSelectorExists('li[role=presentation]');

        $this->client->request('GET', '/mail/inbox/'.$message->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#detail-heading', 'Frage zur Schulwegsicherheit');
        self::assertSelectorExists('iframe[sandbox]');
        // attachments sit in the header, above the content
        self::assertSelectorTextContains('section[aria-labelledby="attachments-heading"]', 'plan.pdf');
        self::assertSelectorTextContains('#attachments-heading', '1 Anhang');
        $page = (string) $this->client->getResponse()->getContent();
        self::assertLessThan(strpos($page, '<iframe'), strpos($page, 'id="attachments-heading"'));

        $this->client->request('GET', '/mail/'.$message->getId().'/html');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('Zebrastreifen', $html);
        self::assertStringContainsString('img-src data:;', (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('@media (prefers-color-scheme: dark){html{', $html);

        $this->em()->getRepository(User::class)->find($this->user->getId())?->setTheme(Theme::Light);
        $this->em()->flush();
        $this->client->request('GET', '/mail/'.$message->getId().'/html');
        self::assertStringNotContainsString('invert', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/mail/inbox/'.$message->getId().'?view=text');
        self::assertSelectorTextContains('main', 'Viele Grüße');
    }

    public function testListSearch(): void
    {
        $this->syncFixture();
        $this->login($this->user);

        $crawler = $this->client->request('GET', '/mail');
        $this->client->submit($crawler->filter('form[role=search]')->form(['q' => 'Schulweg']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Frage zur Schulwegsicherheit');

        $this->client->request('GET', '/mail?q=Nirgendwo');
        self::assertSelectorTextNotContains('main', 'Frage zur Schulwegsicherheit');

        // every word on its own, anywhere: sender, text, recipients (also with umlauts), attachment names
        foreach (['Eva Zebrastreifen', 'müller grundschule', 'Bert', 'Stadtelternvertretung', 'plan.pdf', '"Stand beim"'] as $query) {
            $this->client->request('GET', '/mail?q='.urlencode($query));
            self::assertSelectorTextContains('main', 'Frage zur Schulwegsicherheit', $query);
        }
        foreach (['Eva Laternenumzug', '"beim Stand"'] as $query) {
            $this->client->request('GET', '/mail?q='.urlencode($query));
            self::assertSelectorTextNotContains('main', 'Frage zur Schulwegsicherheit', $query);
        }

        // text beyond the list preview, and done messages are found from the inbox as well
        $account = $this->em()->find(MailAccount::class, $this->account->getId());
        self::assertNotNull($account);
        $long = (new Message())->setMailAccount($account)->setFrom('tom@example.org', 'Tom')->setSubject('Protokoll Elternrat')
            ->setBody(str_repeat('Lorem ipsum dolor sit amet. ', 20).'Am Ende geht es um den Laternenumzug.')
            ->setToRecipients([['name' => 'Jörg Schäfer', 'address' => 'joerg@example.org']])
            ->markDone(null);
        $this->em()->persist($long);
        $this->em()->flush();
        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Protokoll Elternrat');
        $this->client->request('GET', '/mail?q=Laternenumzug');
        self::assertSelectorTextContains('main', 'Protokoll Elternrat');
        self::assertSelectorTextContains('main', 'auch in erledigten');
        $this->client->request('GET', '/mail?q=Schäfer');
        self::assertSelectorTextContains('main', 'Protokoll Elternrat');
        $this->client->request('GET', '/mail/done?q=Schulweg');
        self::assertSelectorTextNotContains('main', 'Frage zur Schulwegsicherheit');
    }

    public function testOpeningAMessageKeepsTheListPage(): void
    {
        $account = $this->em()->find(MailAccount::class, $this->account->getId());
        self::assertNotNull($account);
        for ($i = 0; $i <= MessageRepository::PAGE_SIZE; ++$i) {
            // the oldest one lands on page 2
            $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')
                ->setSubject(0 === $i ? 'Ganz alte Nachricht' : 'Nachricht '.$i)->setBody('Hallo')
                ->setDate(new \DateTimeImmutable(\sprintf('-%d minutes', 0 === $i ? 1000 : $i)));
            $this->em()->persist($message);
        }
        $this->em()->flush();
        $this->login($this->user);

        $crawler = $this->client->request('GET', '/mail?page=2');
        self::assertSelectorTextContains('main', 'Ganz alte Nachricht');
        $link = $crawler->filter('section[aria-labelledby=list-heading] a')->reduce(static fn ($a): bool => str_contains($a->text(), 'Ganz alte Nachricht'))->link();
        self::assertStringContainsString('page=2', $link->getUri());

        $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section[aria-labelledby=list-heading]', 'Ganz alte Nachricht');
        self::assertSelectorTextNotContains('section[aria-labelledby=list-heading]', 'Nachricht 50');
        self::assertSelectorExists('a[href="/mail?page=1"]');
    }

    public function testHtmlViewIgnoresDeclaredCharset(): void
    {
        $message = $this->syncFixture();
        $message->setBody(null, '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1252"></head><body><p>Schöne Grüße</p></body></html>');
        $this->em()->flush();
        $this->login($this->user);

        $this->client->request('GET', '/mail/'.$message->getId().'/html');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Schöne Grüße', $html);
        self::assertStringNotContainsString('windows-1252', $html);
    }

    public function testTrashAndRestore(): void
    {
        $message = $this->syncFixture();
        $this->login($this->user);
        $crawler = $this->client->request('GET', '/mail/inbox/'.$message->getId());
        $this->client->submit($crawler->filter('form[action$="/trash"]')->form());
        self::assertResponseRedirects('/mail');

        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Schulwegsicherheit');
        $this->client->request('GET', '/mail/trash');
        self::assertSelectorTextContains('main', 'Schulwegsicherheit');
    }

    public function testOtherOrganizationsCannotSeeMail(): void
    {
        $message = $this->syncFixture();
        $stranger = $this->createUser('fremd@example.org', 'Fremd');
        $this->createOrganization($stranger, 'Andere SEV');
        $this->login($stranger);

        $this->client->request('GET', '/mail');
        self::assertSelectorTextNotContains('main', 'Schulwegsicherheit');
        $this->client->request('GET', '/mail/inbox/'.$message->getId());
        self::assertResponseStatusCodeSame(403);
        $attachment = $message->getAttachments()->first();
        self::assertNotFalse($attachment);
        $this->client->request('GET', '/mail/'.$message->getId().'/attachment/'.$attachment->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testReadStateIsPerUser(): void
    {
        $message = $this->syncFixture();
        $colleague = $this->createUser('kollege@example.org', 'Kollege');
        $this->org->addMember($colleague, OrganizationRole::Member);
        $this->em()->flush();

        $this->login($this->user);
        $this->client->request('GET', '/mail/inbox/'.$message->getId());
        $this->client->request('GET', '/mail?show=unread');
        self::assertSelectorTextNotContains('main', 'Schulwegsicherheit');

        $this->login($colleague);
        $this->client->request('GET', '/mail?show=unread');
        self::assertSelectorTextContains('main', 'Schulwegsicherheit');
    }
}
