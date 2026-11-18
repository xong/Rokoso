<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\AppTestCase;

final class LegalTest extends AppTestCase
{
    public function testPlatformAdminPublishesPrivacyPolicyLinkedOnLoginAndInMails(): void
    {
        $this->client->request('GET', '/privacy');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/login');
        self::assertSelectorNotExists('a[href="/privacy"]');

        $user = $this->login();
        $this->client->request('GET', '/admin/legal');
        self::assertResponseStatusCodeSame(403);
        $admin = $this->em()->find(User::class, $user->getId()) ?? throw new \LogicException();
        $admin->setPlatformAdmin(true);
        $this->em()->flush();
        $this->login($admin);

        $this->client->request('GET', '/admin/legal');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Speichern', [
            'privacy' => "## Verantwortlich\r\n\r\nStadtelternvertretung <script>x</script>",
            'imprint' => '   ',
        ]);
        self::assertResponseRedirects('/admin/legal');

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Datenschutzerklärung');
        self::assertSelectorTextContains('.markdown h2', 'Verantwortlich');
        self::assertSelectorNotExists('.markdown script');
        $this->client->request('GET', '/imprint');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/login');
        self::assertSelectorExists('a[href="/privacy"]');
        self::assertSelectorNotExists('a[href="/imprint"]');

        // every system mail links the privacy policy, in both parts
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Link anfordern', ['email_only[email]' => 'anna@example.org']);
        $mail = self::getMailerMessage() ?? throw new \LogicException();
        self::assertEmailHtmlBodyContains($mail, 'http://localhost/privacy');
        self::assertEmailTextBodyContains($mail, 'Datenschutzerklärung: http://localhost/privacy');
    }
}
