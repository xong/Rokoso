<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use App\Entity\MailAccount;
use App\Entity\Message;

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

        $this->client->getCrawler()->filter('section[aria-labelledby=detail-heading] button[aria-label="Erledigt"]')->first()->click();
        $this->client->waitForElementToContain('body', 'Als erledigt markiert');
        self::assertSelectorTextNotContains('section[aria-labelledby=list-heading]', $subject);

        $this->client->getCrawler()->selectButton('Rückgängig')->click();
        $this->client->waitForElementToContain('main', $subject);
        self::assertSelectorTextContains('main', $subject);
    }
}
