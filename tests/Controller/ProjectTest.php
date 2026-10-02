<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;

final class ProjectTest extends AppTestCase
{
    public function testAdminCreatesProjectInOrganization(): void
    {
        $admin = $this->login();
        $org = $this->createOrganization($admin);
        $this->client->request('GET', '/projects/new');
        $this->client->submitForm('Speichern', [
            'project_form[name]' => 'Schulwegsicherheit',
            'project_form[organization]' => (string) $org->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Schulwegsicherheit');
        self::assertSelectorTextContains('main', 'SEV Musterstadt');
    }

    public function testMembersSeeButCannotManageOrganizationProjects(): void
    {
        $admin = $this->createUser('owner@example.org', 'Owner');
        $member = $this->createUser();
        $org = $this->createOrganization($admin);
        $org->addMember($member, OrganizationRole::Member);
        $project = (new Project($admin))->setName('Elternabend')->setOrganization($org);
        $this->em()->persist($project);
        $this->em()->flush();

        $this->login($member);
        $this->client->request('GET', '/projects');
        self::assertSelectorTextContains('main', 'Elternabend');
        $this->client->request('GET', '/projects/'.$project->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/projects/'.$project->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPersonalProjectsAreOnlyVisibleToCreator(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $project = (new Project($owner))->setName('Privat');
        $this->em()->persist($project);
        $this->em()->flush();

        $this->login();
        $this->client->request('GET', '/projects/'.$project->getId());
        self::assertResponseStatusCodeSame(403);
    }
}
