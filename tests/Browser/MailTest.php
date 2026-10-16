<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Project;

final class MailTest extends BrowserTestCase
{
    public function testMarkDoneAndUndoWithTurbo(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $account = (new MailAccount($organization))->setName('Postfach')->setEmailAddress('sev@example.org')
            ->setImapHost('imap.example.org')->setImapUsername('sev@example.org')->setSmtpHost('smtp.example.org');
        $subject = 'Elternabend '.uniqid();
        $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')->setSubject($subject)->setBody('Hallo');
        $message->setThreadKey($message->deriveThreadKey());
        $this->em()->persist($account);
        $this->em()->persist($message);
        $this->em()->flush();

        $this->login($user);
        $this->client->request('GET', '/mail');
        $this->client->waitForElementToContain('main', $subject);
        $this->client->clickLink($subject);
        $this->client->waitForElementToContain('#detail-heading', $subject);

        $this->client->getCrawler()->filter('section[aria-labelledby=detail-heading] button[aria-label="Für mich erledigt"]')->first()->click();
        $this->client->waitForElementToContain('body', 'Für dich erledigt.');
        self::assertSelectorTextNotContains('section[aria-labelledby=list-heading]', $subject);

        $this->client->getCrawler()->selectButton('Rückgängig')->click();
        $this->client->waitForElementToContain('main', $subject);
        self::assertSelectorTextContains('main', $subject);
    }

    public function testOpeningAMessageOnPageTwoKeepsPageAndScrollPosition(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $account = (new MailAccount($organization))->setName('Postfach')->setEmailAddress(uniqid('page-').'@example.org')
            ->setImapHost('imap.example.org')->setImapUsername('sev')->setSmtpHost('smtp.example.org');
        $this->em()->persist($account);
        for ($i = 1; $i <= 80; ++$i) {
            $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')
                ->setSubject(\sprintf('Nachricht %02d', $i))->setBody('Hallo')->setDate(new \DateTimeImmutable(\sprintf('-%d minutes', $i)));
            $message->setThreadKey($message->deriveThreadKey());
            $this->em()->persist($message);
        }
        $this->em()->flush();

        $this->login($user);
        $this->client->request('GET', '/mail');
        $this->client->waitForElementToContain('main', 'Nachricht 01');
        $this->client->clickLink('Ältere');
        $this->client->waitForElementToContain('section[aria-labelledby=list-heading]', 'Nachricht 80');

        $list = '[data-controller~="keep-scroll"]';
        // scroll and click a message in view in one go, so the driver does not scroll on its own
        [$top, $subject] = $this->client->executeScript(<<<JS
            const l = document.querySelector('$list');
            l.scrollTop = 300;
            const box = l.getBoundingClientRect();
            const link = [...l.querySelectorAll('a[href*="/mail/"]')].find((a) => a.getBoundingClientRect().top > box.top + 150);
            link.click();
            return [l.scrollTop, link.querySelector('.truncate.block, span.block').textContent.trim()];
            JS);
        self::assertGreaterThan(0, $top);
        $this->client->waitForElementToContain('#detail-heading', $subject);

        self::assertSelectorTextContains('section[aria-labelledby=list-heading]', 'Nachricht 80');
        self::assertSelectorTextNotContains('section[aria-labelledby=list-heading]', 'Nachricht 01');
        self::assertSame($top, $this->client->executeScript("return document.querySelector('$list').scrollTop;"));
    }

    public function testSearchByTypingAndEnter(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $account = (new MailAccount($organization))->setName('Postfach')->setEmailAddress('sev@example.org')
            ->setImapHost('imap.example.org')->setImapUsername('sev@example.org')->setSmtpHost('smtp.example.org');
        $found = 'Schulweg '.uniqid();
        $other = 'Elternabend '.uniqid();
        $this->em()->persist($account);
        foreach ([$found, $other] as $subject) {
            $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')->setSubject($subject)->setBody('Hallo');
            $message->setThreadKey($message->deriveThreadKey());
            $this->em()->persist($message);
        }
        $this->em()->flush();

        $this->login($user);
        $this->client->request('GET', '/mail');
        $this->client->waitForElementToContain('main', $other);
        $this->client->getCrawler()->filter('#mail-q')->sendKeys($found."\n");
        $this->client->waitForElementToNotContain('section[aria-labelledby=list-heading]', $other);
        self::assertSelectorTextContains('section[aria-labelledby=list-heading]', $found);
        self::assertStringContainsString('q=', (string) $this->client->getCurrentURL());
    }

    public function testSearchStartsWhileTypingWithoutEnter(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $account = (new MailAccount($organization))->setName('Postfach')->setEmailAddress(uniqid('live-').'@example.org')
            ->setImapHost('imap.example.org')->setImapUsername('sev')->setSmtpHost('smtp.example.org');
        $word = 'Laternenumzug'.random_int(1000, 9999);
        $this->em()->persist($account);
        // with a project the form also sends the empty "all projects" option (once a 400 and a full reload)
        $this->em()->persist((new Project($user))->setName('Schulweg')->setOrganization($organization));
        foreach (['Treffen' => 'Wir planen den '.$word.'.', 'Elternabend' => 'Hallo'] as $subject => $body) {
            $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')->setSubject($subject)->setBody($body);
            $message->setThreadKey($message->deriveThreadKey());
            $this->em()->persist($message);
        }
        $this->em()->flush();

        $this->login($user);
        $this->client->request('GET', '/mail');
        $this->client->waitForElementToContain('main', 'Elternabend');
        // a word from the body, no Enter
        $this->client->getCrawler()->filter('#mail-q')->sendKeys($word);
        $this->client->waitForElementToNotContain('section[aria-labelledby=list-heading]', 'Elternabend');
        self::assertSelectorTextContains('section[aria-labelledby=list-heading]', 'Treffen');
        self::assertStringContainsString('q='.$word, (string) $this->client->getCurrentURL());
        // the field keeps text and focus, so typing can go on
        self::assertSame([$word, true], $this->client->executeScript("const q = document.getElementById('mail-q'); return [q.value, document.activeElement === q];"));

        $this->client->executeScript("const q = document.getElementById('mail-q'); q.value = ''; q.dispatchEvent(new Event('search'));");
        $this->client->waitForElementToContain('section[aria-labelledby=list-heading]', 'Elternabend');
    }
}
