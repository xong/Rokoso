<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\Message;
use App\Tests\AppTestCase;

final class ContactTest extends AppTestCase
{
    public function testCreateSearchAndShowInMailList(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $account = $this->createMailAccount($org);
        $message = (new Message())->setMailAccount($account)->setFrom('eva@example.org', '')->setSubject('Hallo')->setBody('x');
        $this->em()->persist($message);
        $this->em()->flush();

        $this->client->request('GET', '/contacts/new');
        $this->client->submitForm('Speichern', [
            'contact_form[firstName]' => 'Eva',
            'contact_form[lastName]' => 'Müller',
            'contact_form[position]' => 'Schulleitung',
            'contact_form[email]' => 'Eva@Example.org',
            'contact_form[organization]' => (string) $org->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Eva Müller');

        $this->client->request('GET', '/contacts?q=Schulleitung');
        self::assertSelectorTextContains('main', 'Eva Müller');
        $this->client->request('GET', '/contacts?q=niemand');
        self::assertSelectorTextNotContains('main', 'Eva Müller');

        // Bekannter Absender: Initialen des Kontakts statt der Adresse
        $this->client->request('GET', '/mail');
        self::assertSelectorTextContains('main ul', 'EM');
    }

    public function testContactNeedsAName(): void
    {
        $this->login();
        $this->client->request('GET', '/contacts/new');
        $this->client->submitForm('Speichern', ['contact_form[email]' => 'x@example.org']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPrivateContactsAreHiddenFromOthers(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $contact = (new Contact($owner))->setLastName('Geheim');
        $this->em()->persist($contact);
        $this->em()->flush();

        $this->login();
        $this->client->request('GET', '/contacts');
        self::assertSelectorTextNotContains('main', 'Geheim');
        $this->client->request('GET', '/contacts/'.$contact->getId());
        self::assertResponseStatusCodeSame(403);
    }
}
