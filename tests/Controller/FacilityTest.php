<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\Facility;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\FacilityRole;
use App\Enum\Feature;
use App\Tests\AppTestCase;

final class FacilityTest extends AppTestCase
{
    public function testCreateAssignReplaceAndCircular(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->createMailAccount($org);
        $known = (new Contact($user))->setFirstName('Ali')->setLastName('Yilmaz')->setEmail('ali@example.org')->setPhone('0345 1')->setOrganization($org);
        $this->em()->persist($known);
        $this->em()->flush();

        $this->client->request('GET', '/contacts/facilities/new');
        $this->client->submitForm('Speichern', [
            'facility_form[name]' => 'Kita Sonnenschein',
            'facility_form[district]' => 'Nord',
            'facility_form[email]' => 'Kita@Example.org',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Kita Sonnenschein');
        $facility = $this->em()->getRepository(Facility::class)->findOneBy(['name' => 'Kita Sonnenschein']);
        self::assertNotNull($facility);
        self::assertSame('kita@example.org', $facility->getEmail());
        self::assertSelectorExists('#main-nav a[href="/contacts/facilities"][aria-current]');

        // neue Person wird Kontakt der Organisation
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/representative');
        $this->client->submitForm('Speichern', [
            'facility_person_form[firstName]' => 'Miriam',
            'facility_person_form[lastName]' => 'Koch',
            'facility_person_form[email]' => 'M.Koch@example.org',
            'facility_person_form[reportedOn]' => '2026-09-01',
        ]);
        self::assertResponseRedirects('/contacts/facilities/'.$facility->getId());
        $this->em()->clear();
        $facility = $this->em()->find(Facility::class, $facility->getId());
        self::assertNotNull($facility);
        $miriam = $facility->getRepresentative();
        self::assertNotNull($miriam);
        self::assertSame('m.koch@example.org', $miriam->getEmail());
        self::assertSame('Kita Sonnenschein', $miriam->getCompany());
        self::assertSame('Vertretung', $miriam->getPosition());
        self::assertSame($org->getId(), $miriam->getOrganization()?->getId());
        self::assertSame('2026-09-01', $facility->getReportedOn()?->format('Y-m-d'));

        // bekannte Adresse: vorhandener Kontakt wird Stellvertretung, seine Angaben bleiben
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/deputy');
        $this->client->submitForm('Speichern', [
            'facility_person_form[firstName]' => 'Ali',
            'facility_person_form[lastName]' => 'Y.',
            'facility_person_form[email]' => 'ALI@example.org',
            'facility_person_form[phone]' => '0345 2',
        ]);
        self::assertResponseRedirects();
        $this->em()->clear();
        $deputy = $this->em()->find(Facility::class, $facility->getId())?->getDeputy();
        self::assertNotNull($deputy);
        self::assertSame($known->getId(), $deputy->getId());
        self::assertSame('Yilmaz', $deputy->getLastName());
        self::assertSame('0345 1', $deputy->getPhone());
        self::assertSame(2, $this->em()->getRepository(Contact::class)->count([]));

        // Kontaktseite zeigt, wen die Person vertritt
        $this->client->request('GET', '/contacts/'.$known->getId());
        self::assertSelectorTextContains('main', 'Vertritt');
        self::assertSelectorTextContains('main', 'Kita Sonnenschein');

        // Rundschreiben an Vertretungen und Stellvertretungen
        $this->client->request('GET', '/contacts/facilities');
        self::assertSelectorTextContains('main', 'An Vertretungen und Stellvertretungen (2)');
        $crawler = $this->client->request('GET', '/mail/new?facilities='.$org->getId().'&recipients=all');
        $to = (string) $crawler->filter('#compose_form_to')->attr('value');
        self::assertStringContainsString('Miriam Koch <m.koch@example.org>', $to);
        self::assertStringContainsString('ali@example.org', $to);
        self::assertNotNull($crawler->filter('#compose_form_circular')->attr('checked'));
        $crawler = $this->client->request('GET', '/mail/new?facilities='.$org->getId());
        self::assertStringNotContainsString('ali@example.org', (string) $crawler->filter('#compose_form_to')->attr('value'));

        // Wechsel: bisherige Vertretung wird auf Wunsch gelöscht
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/representative');
        self::assertSelectorTextContains('main', 'Miriam Koch aus den Kontakten löschen');
        $this->client->submitForm('Speichern', [
            'facility_person_form[firstName]' => 'Sandra',
            'facility_person_form[lastName]' => 'Neumann',
            'facility_person_form[email]' => 'sandra@example.org',
            'facility_person_form[removePrevious]' => '1',
        ]);
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->find(Contact::class, $miriam->getId()));
        self::assertSame('Sandra Neumann', $this->em()->find(Facility::class, $facility->getId())?->getRepresentative()?->getDisplayName());

        // Suche findet die Einrichtung über die Person, auch global
        $this->client->request('GET', '/contacts/facilities?q=Neumann');
        self::assertSelectorTextContains('main ul', 'Kita Sonnenschein');
        $this->client->request('GET', '/search?q=Sonnenschein');
        self::assertSelectorTextContains('main', 'Einrichtungen');
    }

    public function testDeputyMovingUpAndRelease(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        [$facility, $rep, $deputy] = $this->facility($org, $user);

        // Stellvertretung rückt auf: derselbe Kontakt, der Platz der Stellvertretung wird frei
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/representative');
        $this->client->submitForm('Speichern', [
            'facility_person_form[lastName]' => 'Wolf',
            'facility_person_form[email]' => (string) $deputy->getEmail(),
        ]);
        $this->em()->clear();
        $facility = $this->em()->find(Facility::class, $facility->getId());
        self::assertNotNull($facility);
        self::assertSame($deputy->getId(), $facility->getRepresentative()?->getId());
        self::assertNull($facility->getDeputy());
        self::assertNotNull($this->em()->find(Contact::class, $rep->getId()), 'without the checkbox the contact stays');

        // Austragen und Kontakt löschen
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/representative');
        $this->client->submitForm('Austragen', ['delete_contact' => '1']);
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->find(Facility::class, $facility->getId())?->getRepresentative());
        self::assertNull($this->em()->find(Contact::class, $deputy->getId()));
    }

    public function testFiltersAndReview(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->createMailAccount($org);
        [$current] = $this->facility($org, $user, 'Kita Aktuell', '-1 month');
        [$old] = $this->facility($org, $user, 'Kita Alt', '-13 months');
        $empty = (new Facility($org))->setName('Kita Leer')->setEmail('leer@example.org');
        $this->em()->persist($empty);
        $this->em()->flush();

        self::assertFalse($current->needsReview());
        self::assertTrue($old->needsReview());
        self::assertFalse($empty->needsReview());

        $this->client->request('GET', '/contacts/facilities?status=review');
        self::assertSelectorTextContains('main ul', 'Kita Alt');
        self::assertSelectorTextNotContains('main ul', 'Kita Aktuell');
        $this->client->request('GET', '/contacts/facilities?status=missing');
        self::assertSelectorTextContains('main ul', 'Kita Leer');
        self::assertSelectorTextNotContains('main ul', 'Kita Alt');

        // Einrichtungen ohne Vertretung über ihre eigene Adresse
        $crawler = $this->client->request('GET', '/mail/new?facilities='.$org->getId().'&recipients=missing');
        self::assertSame('Kita Leer <leer@example.org>', $crawler->filter('#compose_form_to')->attr('value'));
    }

    public function testForeignFacilityIsForbidden(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $foreign = $this->createOrganization($owner, 'Fremd');
        [$facility] = $this->facility($foreign, $owner);

        $this->createMailAccount($this->createOrganization($this->login()));
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId().'/representative');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/mail/new?facilities='.$foreign->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/contacts/facilities');
        self::assertSelectorTextNotContains('main', 'Kita');
    }

    public function testAreaCanBeSwitchedOff(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        [$facility] = $this->facility($org, $user);
        $org->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::Facilities !== $f));
        $this->em()->flush();

        $this->client->request('GET', '/contacts');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#main-nav a[href="/contacts/facilities"]');
        $this->client->request('GET', '/contacts/facilities');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/contacts/facilities/'.$facility->getId());
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array{Facility, Contact, Contact}
     */
    private function facility(Organization $org, User $user, string $name = 'Kita Sonnenschein', string $reported = '-1 month'): array
    {
        $domain = strtolower(str_replace(' ', '-', $name)).'.example.org';
        $rep = (new Contact($user))->setLastName('Koch')->setEmail('koch@'.$domain)->setOrganization($org);
        $deputy = (new Contact($user))->setLastName('Wolf')->setEmail('wolf@'.$domain)->setOrganization($org);
        $facility = (new Facility($org))->setName($name)
            ->setPerson(FacilityRole::Representative, $rep)
            ->setPerson(FacilityRole::Deputy, $deputy)
            ->setReportedOn(new \DateTimeImmutable($reported));
        $this->em()->persist($rep);
        $this->em()->persist($deputy);
        $this->em()->persist($facility);
        $this->em()->flush();

        return [$facility, $rep, $deputy];
    }
}
