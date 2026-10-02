<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\UserRepository;
use App\Tests\AppTestCase;

final class OrganizationTest extends AppTestCase
{
    public function testCreateOrganizationMakesCreatorAdmin(): void
    {
        $user = $this->login();
        $this->client->request('GET', '/organizations/new');
        $this->client->submitForm('Speichern', [
            'organization_form[name]' => 'SEV Musterstadt',
            'organization_form[color]' => '#16a34a',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'SEV Musterstadt');

        $org = $this->em()->getRepository(Organization::class)->findOneBy(['name' => 'SEV Musterstadt']);
        self::assertNotNull($org);
        self::assertTrue($org->isAdmin($this->em()->find(User::class, $user->getId()) ?? $user));
    }

    public function testNonMembersCannotSeeOrganization(): void
    {
        $org = $this->createOrganization($this->createUser('owner@example.org', 'Owner'));
        $this->login();
        $this->client->request('GET', '/organizations/'.$org->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testMembersCannotEdit(): void
    {
        $member = $this->createUser();
        $org = $this->createOrganization($this->createUser('owner@example.org', 'Owner'));
        $org->addMember($member, OrganizationRole::Member);
        $this->em()->flush();
        $this->login($member);

        $this->client->request('GET', '/organizations/'.$org->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/organizations/'.$org->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLastAdminCannotLeave(): void
    {
        $owner = $this->login();
        $org = $this->createOrganization($owner);
        $membership = $org->getMembership($owner);
        self::assertNotNull($membership);

        $crawler = $this->client->request('GET', '/organizations/'.$org->getId());
        $form = $crawler->filter(\sprintf('form[action$="/members/%d/remove"]', $membership->getId()))->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[role=status]', 'mindestens einen Administrator');
    }

    public function testInvitationForNewUserRegistersAndJoins(): void
    {
        $owner = $this->login();
        $org = $this->createOrganization($owner);
        $this->client->request('GET', '/organizations/'.$org->getId());
        $this->client->submitForm('Einladen', [
            'invitation_form[email]' => 'neu@example.org',
            'invitation_form[role]' => 'member',
        ]);
        self::assertEmailCount(1);
        $link = $this->linkFromLastMail('/invitation/');

        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        self::assertSelectorTextContains('main', 'Organisation');
        $this->client->clickLink('Neues Konto anlegen');
        $this->client->submitForm('Konto anlegen', [
            'registration_form[name]' => 'Neue Person',
            'registration_form[email]' => 'neu@example.org',
            'registration_form[plainPassword][first]' => 'ein-langes-passwort',
            'registration_form[plainPassword][second]' => 'ein-langes-passwort',
        ]);
        self::assertResponseRedirects('/login');

        $this->em()->clear();
        $newUser = static::getContainer()->get(UserRepository::class)->findOneByEmail('neu@example.org');
        self::assertNotNull($newUser);
        self::assertTrue($newUser->isVerified());
        $org = $this->em()->find(Organization::class, $org->getId());
        self::assertNotNull($org?->getMembership($newUser));
    }

    public function testExistingUserAcceptsInvitation(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $org = $this->createOrganization($owner);
        $invitation = new \App\Entity\Invitation($org, 'anna@example.org', OrganizationRole::Admin, $owner);
        $this->em()->persist($invitation);
        $this->em()->flush();

        $user = $this->login();
        $this->client->request('GET', '/invitation/'.$invitation->getToken());
        $this->client->submitForm('Einladung annehmen');
        self::assertResponseRedirects('/organizations/'.$org->getId());

        $this->em()->clear();
        $org = $this->em()->find(Organization::class, $org->getId());
        self::assertTrue($org?->isAdmin($this->em()->find(User::class, $user->getId()) ?? $user));
    }
}
