<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Tests\AppTestCase;

final class AuthTest extends AppTestCase
{
    public function testRegistrationSendsConfirmationAndBlocksLoginUntilVerified(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Konto anlegen', [
            'registration_form[name]' => 'Bert Beispiel',
            'registration_form[email]' => 'Bert@Example.org',
            'registration_form[plainPassword][first]' => 'ein-langes-passwort',
            'registration_form[plainPassword][second]' => 'ein-langes-passwort',
        ]);

        self::assertResponseRedirects();
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'bert@example.org');
        $link = $this->linkFromLastMail('/verify/email\?');

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('bert@example.org');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        // Login vor Bestätigung schlägt fehl
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'bert@example.org', '_password' => 'ein-langes-passwort']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', 'bestätige zuerst');

        // Bestätigungslink aus der Mail öffnen
        $this->client->request('GET', $link);
        self::assertResponseRedirects('/login');
        $this->em()->clear();
        self::assertTrue(static::getContainer()->get(UserRepository::class)->findOneByEmail('bert@example.org')?->isVerified());
    }

    public function testLoginAndLogout(): void
    {
        $this->createUser();
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'geheim-geheim']);

        self::assertResponseRedirects('/');
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/mail');
        self::assertResponseRedirects('/login');
    }

    public function testPasswordReset(): void
    {
        $this->createUser();
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Link anfordern', ['email_only[email]' => 'anna@example.org']);
        self::assertResponseRedirects('/reset-password/check-email');

        $this->client->request('GET', $this->linkFromLastMail('/reset-password/reset/'));
        $this->client->followRedirect();
        $this->client->submitForm('Passwort speichern', [
            'form[plainPassword][first]' => 'neues-passwort-123',
            'form[plainPassword][second]' => 'neues-passwort-123',
        ]);
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->client->submitForm('Anmelden', ['_username' => 'anna@example.org', '_password' => 'neues-passwort-123']);
        self::assertResponseRedirects('/');
    }

    public function testProfileNameAndPasswordChange(): void
    {
        $this->login();
        $this->client->request('GET', '/profile');
        $this->client->submitForm('Speichern', ['profile_form[name]' => 'Anna Neu']);
        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#main-nav', 'Anna Neu');

        $this->client->request('GET', '/profile/password');
        $this->client->submitForm('Passwort ändern', [
            'change_password_form[currentPassword]' => 'falsch',
            'change_password_form[plainPassword][first]' => 'neues-passwort-123',
            'change_password_form[plainPassword][second]' => 'neues-passwort-123',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.error', 'falsch');
    }

    public function testEmailChangeNeedsConfirmation(): void
    {
        $this->login();
        $this->client->request('GET', '/profile/email');
        $this->client->submitForm('Adresse ändern', ['email_only[email]' => 'anna.neu@example.org']);
        self::assertResponseRedirects('/profile/email');

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'anna.neu@example.org');
        $this->client->request('GET', $this->linkFromLastMail('/profile/email/confirm\?'));
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertNotNull(static::getContainer()->get(UserRepository::class)->findOneByEmail('anna.neu@example.org'));
    }
}
