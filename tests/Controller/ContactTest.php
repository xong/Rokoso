<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\Message;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Email;

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

    public function testGroupsFilterAndCircular(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->createMailAccount($org);
        $eva = (new Contact($user))->setFirstName('Eva')->setLastName('Lang')->setEmail('eva@example.org')->setOrganization($org);
        $jonas = (new Contact($user))->setFirstName('Jonas')->setLastName('Becker')->setEmail('jonas@example.org')->setOrganization($org);
        $other = (new Contact($user))->setLastName('Außenstehend')->setOrganization($org);
        $this->em()->persist($eva);
        $this->em()->persist($jonas);
        $this->em()->persist($other);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/contacts/groups/new');
        $values = $crawler->selectButton('Speichern')->form()->getPhpValues();
        $values['contact_group_form']['name'] = 'Schulleitungen';
        $values['contact_group_form']['contacts'] = [(string) $eva->getId(), (string) $jonas->getId()];
        $this->client->request('POST', '/contacts/groups/new?organization='.$org->getId(), $values);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Schulleitungen');
        $group = $this->em()->getRepository(ContactGroup::class)->findOneBy(['name' => 'Schulleitungen']);
        self::assertNotNull($group);
        self::assertCount(2, $group->getContacts());

        $this->client->request('GET', '/contacts?group='.$group->getId());
        self::assertSelectorTextContains('main', 'Eva Lang');
        self::assertSelectorTextNotContains('main', 'Außenstehend');
        // Verteiler im Menü unter Kontakte und direkt aus dem Filter erreichbar
        self::assertSelectorExists('#main-nav a[href="/contacts/groups"]');
        self::assertSelectorExists('a[href="/contacts/groups/'.$group->getId().'"][aria-label*="Schulleitungen"]');

        // Verteiler als Vorschlag im Empfängerfeld
        $crawler = $this->client->request('GET', '/mail/new');
        self::assertStringContainsString('Schulleitungen', (string) $crawler->filter('[data-controller="recipients"]')->attr('data-recipients-options-value'));

        // Rundschreiben: je Empfänger eine eigene E-Mail, Cc ist nicht erlaubt
        $crawler = $this->client->request('GET', '/mail/new?group='.$group->getId());
        self::assertStringContainsString('eva@example.org', (string) $crawler->filter('#compose_form_to')->attr('value'));
        self::assertNotNull($crawler->filter('#compose_form_circular')->attr('checked'));
        $this->client->submitForm('Senden', ['compose_form[subject]' => 'Einladung', 'compose_form[cc]' => 'cc@example.org']);
        self::assertResponseStatusCodeSame(422);
        $this->client->submitForm('Senden', ['compose_form[subject]' => 'Einladung', 'compose_form[cc]' => '']);
        self::assertResponseRedirects();
        self::assertEmailCount(2);
        $sent = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $sent);
        self::assertCount(1, $sent->getTo());

        $message = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Einladung']);
        self::assertNotNull($message);
        self::assertTrue($message->isCircular());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Rundschreiben – einzeln versandt an 2');

        // Verlauf des Kontakts zeigt das Rundschreiben
        $this->client->request('GET', '/contacts/'.$eva->getId());
        self::assertSelectorTextContains('main', 'Einladung');
        self::assertSelectorTextContains('main', 'Schulleitungen');
    }

    public function testGroupOfForeignOrganizationIsForbidden(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $group = (new ContactGroup($this->createOrganization($owner, 'Fremd')))->setName('Intern');
        $this->em()->persist($group);
        $this->em()->flush();

        $this->createMailAccount($this->createOrganization($this->login()));
        $this->client->request('GET', '/contacts/groups/'.$group->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/mail/new?group='.$group->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testImportSkipsKnownAddressesAndExports(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->em()->persist((new Contact($user))->setLastName('Bekannt')->setEmail('known@example.org')->setOrganization($org));
        $this->em()->flush();

        $path = tempnam(sys_get_temp_dir(), 'vcf');
        self::assertIsString($path);
        file_put_contents($path, "BEGIN:VCARD\nVERSION:3.0\nN:Neu;Nina\nEMAIL:nina@example.org\nEND:VCARD\n"
            ."BEGIN:VCARD\nVERSION:3.0\nFN:Doppelt\nEMAIL:KNOWN@example.org\nEND:VCARD\n");

        $crawler = $this->client->request('GET', '/contacts/import');
        $values = $crawler->selectButton('Kontakte importieren')->form()->getPhpValues();
        $values['organization'] = (string) $org->getId();
        $this->client->request('POST', '/contacts/import', $values, ['file' => new UploadedFile($path, 'kontakte.vcf', 'text/vcard', null, true)]);
        self::assertResponseRedirects('/contacts');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', '1 Kontakte importiert, 1 übersprungen');
        self::assertSelectorTextContains('main', 'Nina Neu');

        $this->client->request('GET', '/contacts/export');
        self::assertResponseHeaderSame('content-type', 'text/vcard; charset=utf-8');
        self::assertStringContainsString('EMAIL;TYPE=INTERNET:nina@example.org', (string) $this->client->getInternalResponse()->getContent());
    }

    public function testContactNotes(): void
    {
        $user = $this->login();
        $contact = (new Contact($user))->setLastName('Lang')->setOrganization($this->createOrganization($user));
        $this->em()->persist($contact);
        $this->em()->flush();

        $this->client->request('GET', '/contacts/'.$contact->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Lieber vormittags anrufen.']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Lieber vormittags anrufen.');
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
