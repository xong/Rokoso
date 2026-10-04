<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Folder;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class FileTest extends AppTestCase
{
    public function testMemberCreatesRootFolderForOrganization(): void
    {
        $admin = $this->login();
        $org = $this->createOrganization($admin);

        $this->client->request('GET', '/files/folder/new');
        $this->client->submitForm('Speichern', [
            'folder_form[name]' => 'Protokolle',
            'folder_form[organization]' => (string) $org->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Protokolle');

        $this->client->request('GET', '/files');
        self::assertSelectorTextContains('nav[aria-label="Ordner"]', 'Protokolle');
    }

    public function testUploadDownloadAndDelete(): void
    {
        [$admin, $member, $org] = $this->setUpOrganization();
        $folder = $this->createFolder($org, $admin, 'Protokolle');

        $this->login($member);
        $crawler = $this->client->request('GET', '/files/folder/'.$folder->getId());
        $form = $crawler->selectButton('Hochladen')->form();
        $field = $form->get('upload_form[files]');
        self::assertIsArray($field);
        self::assertInstanceOf(FileFormField::class, $field[0]);
        $field[0]->upload($this->tempFile('Protokoll vom Elternabend'));
        $this->client->submit($form);
        self::assertResponseRedirects('/files/folder/'.$folder->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'protokoll.txt');

        $file = $this->em()->getRepository(StoredFile::class)->findOneBy(['filename' => 'protokoll.txt']);
        self::assertNotNull($file);
        self::assertSame($member->getId(), $file->getUploadedBy()?->getId());

        $this->client->request('GET', '/files/'.$file->getId().'/download');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/octet-stream');
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        // Another member cannot delete the file, an organization admin can
        $other = $this->createUser('other@example.org', 'Other');
        $org->addMember($other, OrganizationRole::Member);
        $this->em()->flush();
        $this->login($other);
        $this->client->request('GET', '/files/'.$file->getId());
        self::assertSelectorNotExists('form[action$="/files/'.$file->getId().'/delete"]');

        $this->login($admin);
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->filter('form[action$="/files/'.$file->getId().'/delete"]')->form());
        self::assertResponseRedirects('/files/folder/'.$folder->getId());
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(StoredFile::class)->find($file->getId()));
    }

    public function testNonMembersHaveNoAccess(): void
    {
        [$admin, , $org] = $this->setUpOrganization();
        $folder = $this->createFolder($org, $admin, 'Intern');

        $this->login($this->createUser('stranger@example.org', 'Stranger'));
        $this->client->request('GET', '/files/folder/'.$folder->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/files');
        self::assertSelectorTextNotContains('main', 'Intern');
    }

    public function testSubfoldersInheritOrganizationAndProject(): void
    {
        [$admin, , $org] = $this->setUpOrganization();
        $project = (new Project($admin))->setName('Schulweg')->setOrganization($org);
        $this->em()->persist($project);
        $folder = $this->createFolder($org, $admin, 'Schulweg', $project);

        $this->login($admin);
        $this->client->request('GET', '/files/folder/new?parent='.$folder->getId());
        $this->client->submitForm('Speichern', ['folder_form[name]' => 'Fotos']);
        self::assertResponseRedirects();

        $sub = $this->em()->getRepository(Folder::class)->findOneBy(['name' => 'Fotos']);
        self::assertNotNull($sub);
        self::assertSame($org->getId(), $sub->getOrganization()->getId());
        self::assertSame($project->getId(), $sub->getProject()?->getId());
    }

    public function testFilesAppearOnProjectPageAndCanBeCommented(): void
    {
        [$admin, $member, $org] = $this->setUpOrganization();
        $project = (new Project($admin))->setName('Schulweg')->setOrganization($org);
        $this->em()->persist($project);
        $folder = $this->createFolder($org, $admin, 'Allgemein');
        $file = new StoredFile($folder, 'plan.pdf', 'application/pdf', 1234, 'x/plan.pdf', $admin);
        $file->setProject($project);
        $this->em()->persist($file);
        $this->em()->flush();

        $this->login($member);
        $this->client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorTextContains('[aria-labelledby="project-files-heading"]', 'plan.pdf');

        $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submitForm('Kommentieren', ['body' => 'Bitte bis Freitag prüfen.']);
        self::assertResponseRedirects('/files/'.$file->getId().'#comments');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#comments', 'Bitte bis Freitag prüfen.');
    }

    /**
     * @return array{User, User, Organization}
     */
    private function setUpOrganization(): array
    {
        $admin = $this->createUser('owner@example.org', 'Owner');
        $member = $this->createUser();
        $org = $this->createOrganization($admin);
        $org->addMember($member, OrganizationRole::Member);
        $this->em()->flush();

        return [$admin, $member, $org];
    }

    private function createFolder(Organization $org, User $user, string $name, ?Project $project = null): Folder
    {
        $folder = (new Folder($org, $user))->setName($name)->setProject($project);
        $this->em()->persist($folder);
        $this->em()->flush();

        return $folder;
    }

    private function tempFile(string $content): string
    {
        $dir = sys_get_temp_dir().'/rokoso-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/protokoll.txt';
        file_put_contents($path, $content);

        return $path;
    }
}
