<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\SecurityEvent;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\SecurityEventRepository;
use App\Service\RetentionCleaner;
use App\Tests\AppTestCase;
use OTPHP\TOTP;

final class SecurityTest extends AppTestCase
{
    public function testTwoFactorSetupAndLogin(): void
    {
        $user = $this->login();
        $this->client->request('GET', '/profile/security/2fa');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img[alt][src^="data:image/svg+xml"]');
        $secret = (string) preg_replace('/\s+/', '', $this->client->getCrawler()->filter('p.font-mono')->text());

        $this->client->submitForm('Einschalten', ['code' => '000000']);
        self::assertResponseStatusCodeSame(422);

        $this->client->submitForm('Einschalten', ['code' => self::totp($secret)]);
        self::assertResponseRedirects('/profile/security');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Deine Ersatzcodes');
        $codes = $this->client->getCrawler()->filter('ul.font-mono li')->each(static fn ($li): string => $li->text());
        self::assertCount(10, $codes);

        // codes are shown only once
        $this->client->request('GET', '/profile/security');
        self::assertSelectorTextContains('body', 'noch 10 Ersatzcodes');

        // login now asks for the second factor
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'geheim-geheim']);
        $this->client->followRedirect();
        self::assertResponseRedirects('/2fa');
        $this->client->request('GET', '/mail');
        self::assertResponseRedirects('/2fa');
        $this->client->followRedirect();
        $this->client->submitForm('Bestätigen', ['_auth_code' => self::totp($secret)]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/mail');
        self::assertResponseIsSuccessful();

        // a backup code works exactly once
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'geheim-geheim']);
        $this->client->request('GET', '/2fa');
        $this->client->submitForm('Bestätigen', ['_auth_code' => $codes[0]]);
        $this->client->request('GET', '/mail');
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertSame(9, $fresh?->countBackupCodes());

        $types = array_map(static fn (SecurityEvent $e): string => $e->getType(), static::getContainer()->get(SecurityEventRepository::class)->findLatest($fresh));
        self::assertContains('two_factor_enabled', $types);
        self::assertContains('login', $types);
    }

    public function testDisableTwoFactorNeedsPassword(): void
    {
        $user = $this->createUser();
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $this->em()->flush();
        $this->login($user);

        $this->client->request('GET', '/profile/security');
        $this->client->submitForm('Zwei-Faktor-Anmeldung ausschalten', ['confirm_password[currentPassword]' => 'falsch']);
        self::assertResponseStatusCodeSame(422);
        $this->client->submitForm('Zwei-Faktor-Anmeldung ausschalten', ['confirm_password[currentPassword]' => 'geheim-geheim']);
        self::assertResponseRedirects('/profile/security');
        $this->em()->clear();
        self::assertFalse($this->em()->find(User::class, $user->getId())?->isTotpAuthenticationEnabled());
    }

    public function testLogoutEverywhereEndsOtherSessions(): void
    {
        $user = $this->login();
        $this->client->request('GET', '/mail');
        self::assertResponseIsSuccessful();

        // another device renewed the stamp: this session is gone
        $fresh = $this->em()->find(User::class, $user->getId());
        $fresh?->renewSessionStamp();
        $this->em()->flush();
        $this->client->request('GET', '/mail');
        self::assertResponseRedirects('/login');

        $this->login($this->em()->find(User::class, $user->getId()));
        $this->client->request('GET', '/profile/security');
        $this->client->submitForm('Überall abmelden');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/mail');
        self::assertResponseRedirects('/login');
    }

    public function testBlockedUserCannotLogIn(): void
    {
        $user = $this->createUser();
        $user->setBlocked(true);
        $this->em()->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'geheim-geheim']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', 'gesperrt');
    }

    public function testExportAndDeleteAccount(): void
    {
        $user = $this->login();
        $other = $this->createUser('ben@example.org', 'Ben Berg');
        $organization = $this->createOrganization($other);
        $organization->addMember($this->em()->find(User::class, $user->getId()) ?? $user, OrganizationRole::Member);
        $this->em()->flush();

        $this->client->request('GET', '/profile/account/export');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('anna@example.org', $data['profile']['email']);
        self::assertSame('SEV Musterstadt', $data['memberships'][0]['organization']);

        $this->client->request('GET', '/profile/account');
        $this->client->submitForm('Konto endgültig löschen', ['confirm_password[currentPassword]' => 'geheim-geheim']);
        self::assertResponseRedirects('/login');

        $this->em()->clear();
        $deleted = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($deleted?->getDeletedAt());
        self::assertSame('Gelöschtes Konto', $deleted->getName());
        self::assertStringEndsWith('@invalid', $deleted->getEmail());
        self::assertCount(1, $this->em()->find(Organization::class, $organization->getId())?->getMemberships() ?? []);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'geheim-geheim']);
        $this->client->followRedirect();
        self::assertSelectorExists('[role=alert]');
    }

    public function testSoleAdminCannotDeleteAccount(): void
    {
        $user = $this->login();
        $this->createOrganization($user);

        $this->client->request('GET', '/profile/account');
        self::assertSelectorTextContains('body', 'einzige Admin');
        self::assertSelectorNotExists('form[name=confirm_password]');
    }

    public function testPlatformAdmin(): void
    {
        $user = $this->login();
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        $user = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($user);
        $user->setPlatformAdmin(true);
        $this->em()->flush();
        $target = $this->createUser('ben@example.org', 'Ben Berg');
        $this->login($user);

        $this->client->request('GET', '/admin?q=ben');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'ben@example.org');
        $this->client->submitForm('Sperren');
        self::assertResponseRedirects('/admin');
        $this->em()->clear();
        self::assertNotNull($this->em()->find(User::class, $target->getId())?->getBlockedAt());

        $this->client->request('GET', '/admin/organizations');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/log');
        self::assertSelectorTextContains('table', 'Konto gesperrt');
    }

    public function testRetentionCleanerRemovesTrashAndOldEntries(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $organization->setTrashDays(7);
        $account = $this->createMailAccount($organization);

        $old = (new Message())->setMailAccount($account)->setFrom('eva@example.org', '')->setSubject('Alt im Papierkorb')->setBody('x')->setTrashed(true);
        $recent = (new Message())->setMailAccount($account)->setFrom('eva@example.org', '')->setSubject('Frisch im Papierkorb')->setBody('x')->setTrashed(true);
        $this->em()->persist($old);
        $this->em()->persist($recent);
        $event = new SecurityEvent('login', $user);
        $this->em()->persist($event);
        $this->em()->flush();
        $this->em()->getConnection()->executeStatement('UPDATE message SET trashed_at = ? WHERE subject = ?', [(new \DateTimeImmutable('-10 days'))->format('Y-m-d H:i:s'), 'Alt im Papierkorb']);
        $this->em()->getConnection()->executeStatement('UPDATE security_event SET created_at = ?', [(new \DateTimeImmutable('-2 years'))->format('Y-m-d H:i:s')]);

        $result = static::getContainer()->get(RetentionCleaner::class)->clean();
        self::assertSame(1, $result['messages']);
        self::assertSame(1, $result['security']);
        $this->em()->clear();
        $subjects = array_map(static fn (Message $m): string => $m->getSubject(), $this->em()->getRepository(Message::class)->findAll());
        self::assertSame(['Frisch im Papierkorb'], $subjects);
    }

    private static function totp(string $secret): string
    {
        if ('' === $secret) {
            self::fail('No TOTP secret on the page');
        }

        return TOTP::createFromSecret($secret)->now();
    }
}
