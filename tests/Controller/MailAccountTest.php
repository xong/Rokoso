<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MailAccount;
use App\Enum\OrganizationRole;
use App\Service\SecretBox;
use App\Tests\AppTestCase;

final class MailAccountTest extends AppTestCase
{
    public function testAdminAddsAccountWithEncryptedPassword(): void
    {
        $admin = $this->login();
        $org = $this->createOrganization($admin);

        $this->client->request('GET', '/organizations/'.$org->getId().'/mail-accounts/new');
        $this->client->submitForm('Speichern', [
            'mail_account_form[name]' => 'Postfach SEV',
            'mail_account_form[emailAddress]' => 'sev@example.org',
            'mail_account_form[imapHost]' => 'imap.example.org',
            'mail_account_form[imapPort]' => '993',
            'mail_account_form[imapUsername]' => 'sev@example.org',
            'mail_account_form[imapPasswordPlain]' => 'streng-geheim',
            'mail_account_form[smtpHost]' => 'smtp.example.org',
            'mail_account_form[smtpPort]' => '465',
        ]);
        self::assertResponseRedirects();

        $account = $this->em()->getRepository(MailAccount::class)->findOneBy(['emailAddress' => 'sev@example.org']);
        self::assertNotNull($account);
        self::assertNotSame('streng-geheim', $account->getImapPassword());
        self::assertSame('streng-geheim', static::getContainer()->get(SecretBox::class)->decrypt((string) $account->getImapPassword()));

        // Leeres Passwort beim Bearbeiten behält das gespeicherte
        $this->client->request('GET', '/mail-accounts/'.$account->getId().'/edit');
        $this->client->submitForm('Speichern', ['mail_account_form[name]' => 'Postfach Vorstand']);
        $account = $this->em()->getRepository(MailAccount::class)->find($account->getId());
        self::assertNotNull($account);
        self::assertSame('Postfach Vorstand', $account->getName());
        self::assertSame('streng-geheim', static::getContainer()->get(SecretBox::class)->decrypt((string) $account->getImapPassword()));

        $this->client->followRedirect();
        $this->client->submitForm('Verbindung testen');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'IMAP: Verbindung erfolgreich');
    }

    public function testMembersCannotManageAccounts(): void
    {
        $admin = $this->createUser('owner@example.org', 'Owner');
        $member = $this->createUser();
        $org = $this->createOrganization($admin);
        $org->addMember($member, OrganizationRole::Member);
        $account = $this->createMailAccount($org);

        $this->login($member);
        $this->client->request('GET', '/mail-accounts/'.$account->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }
}
