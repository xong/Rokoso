<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\FileShare;
use App\Entity\Folder;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Service\FileStorage;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class FileVersionShareTest extends AppTestCase
{
    public function testUploadWithSameNameCreatesVersionThatCanBeRestored(): void
    {
        $user = $this->login();
        $folder = $this->createFolder($this->createOrganization($user), $user);

        $this->upload($folder, 'Erste Fassung');
        $this->upload($folder, 'Zweite Fassung');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash, [role=status], main', 'neue Version');

        $files = $this->em()->getRepository(StoredFile::class)->findBy(['folder' => $folder]);
        self::assertCount(1, $files);
        $file = $files[0];
        self::assertCount(1, $file->getVersions());

        // Text preview shows the current content
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        self::assertSelectorTextContains('pre', 'Zweite Fassung');
        $version = $file->getVersions()->first();
        self::assertNotFalse($version);
        $this->client->request('GET', '/files/'.$file->getId().'/version/'.$version->getId().'/download');
        self::assertResponseIsSuccessful();
        self::assertSame('Erste Fassung', $this->downloadedContent());

        // Restoring keeps the current state as another version
        $this->client->submit($crawler->filter('form[action$="/version/'.$version->getId().'/restore"]')->form());
        self::assertResponseRedirects('/files/'.$file->getId());
        $this->client->request('GET', '/files/'.$file->getId().'/download');
        self::assertSame('Erste Fassung', $this->downloadedContent());
        $this->em()->clear();
        $file = $this->em()->getRepository(StoredFile::class)->find($file->getId());
        self::assertNotNull($file);
        self::assertCount(2, $file->getVersions());

        // Deleting removes the content of all versions
        $paths = $file->getAllStoragePaths();
        self::assertCount(3, $paths);
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->filter('form[action$="/files/'.$file->getId().'/delete"]')->form());
        $storage = $this->storage();
        foreach ($paths as $path) {
            self::assertFileDoesNotExist($storage->absolutePath($path));
        }
    }

    public function testVersionUploadFormAndPermissions(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $org = $this->createOrganization($owner);
        $file = $this->createFile($this->createFolder($org, $owner), $owner, 'plan.pdf', 'application/pdf', '%PDF-1.4');

        $this->login($owner);
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        self::assertSelectorExists('iframe[src*="inline=1"]');
        $form = $crawler->selectButton('Als neue Version hochladen')->form();
        $field = $form->get('file_version[files]');
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($this->tempFile('plan-neu.pdf', '%PDF-1.5'));
        $this->client->submit($form);
        self::assertResponseRedirects('/files/'.$file->getId());
        $this->em()->clear();
        $file = $this->em()->getRepository(StoredFile::class)->find($file->getId());
        self::assertNotNull($file);
        self::assertSame('plan-neu.pdf', $file->getFilename());
        self::assertCount(1, $file->getVersions());

        // Submitting without a file shows an error
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->selectButton('Als neue Version hochladen')->form());
        self::assertResponseStatusCodeSame(422);

        // Outsiders cannot upload versions
        $this->login($this->createUser('eve@example.org', 'Eve'));
        $this->client->request('POST', '/files/'.$file->getId().'/version');
        self::assertResponseStatusCodeSame(403);
    }

    public function testShareLinkWorksWithoutLoginUntilRevoked(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $org = $this->createOrganization($owner);
        $file = $this->createFile($this->createFolder($org, $owner), $owner, 'einladung.txt', 'text/plain', 'Herzliche Einladung');

        // Outsiders cannot create links
        $this->login($this->createUser('eve@example.org', 'Eve'));
        $this->client->request('GET', '/files/'.$file->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/files/'.$file->getId().'/share');
        self::assertCount(0, $this->em()->getRepository(FileShare::class)->findAll());

        $this->login($owner);
        $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submitForm('Link erstellen', ['days' => '30']);
        self::assertResponseRedirects('/files/'.$file->getId().'#shares');
        $share = $this->em()->getRepository(FileShare::class)->findOneBy(['file' => $file]);
        self::assertNotNull($share);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $share->getToken());
        self::assertEqualsWithDelta((new \DateTimeImmutable('+30 days'))->getTimestamp(), $share->getExpiresAt()->getTimestamp(), 60);
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        self::assertStringContainsString('/s/'.$share->getToken(), (string) $crawler->filter('#shares input[readonly]')->attr('value'));

        // Public access
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/s/'.$share->getToken());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSelectorTextContains('main, body', 'einladung.txt');
        $this->client->request('GET', '/s/'.$share->getToken().'/download');
        self::assertResponseIsSuccessful();
        self::assertSame('Herzliche Einladung', $this->downloadedContent());
        $this->em()->clear();
        self::assertSame(1, $this->em()->getRepository(FileShare::class)->find($share->getId())?->getDownloads());

        // Unknown and revoked links
        $this->client->request('GET', '/s/'.str_repeat('0', 32));
        self::assertResponseStatusCodeSame(404);
        $this->login($owner);
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->filter('form[action$="/share/'.$share->getId().'/revoke"]')->form());
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/s/'.$share->getToken());
        self::assertResponseStatusCodeSame(404);
    }

    public function testShareExpires(): void
    {
        $owner = $this->createUser();
        $file = $this->createFile($this->createFolder($this->createOrganization($owner), $owner), $owner, 'a.txt', 'text/plain', 'a');
        $share = new FileShare($file, $owner, 1);
        self::assertFalse($share->isExpired());
        self::assertTrue($share->isExpired(new \DateTimeImmutable('+2 days')));
    }

    public function testFileCanBeSentByMail(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->createMailAccount($org);
        $file = $this->createFile($this->createFolder($org, $user), $user, 'plan.txt', 'text/plain', 'Plan');

        $this->client->request('GET', '/files/'.$file->getId());
        $this->client->click($this->client->getCrawler()->filter('a[aria-label="Per E-Mail senden"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="compose_form[storedFiles][]"][value="'.$file->getId().'"][checked]');
        $this->client->submitForm('Senden', [
            'compose_form[to]' => 'eva@example.org',
            'compose_form[subject]' => 'Plan aus den Dateien',
        ]);
        self::assertResponseRedirects();
        $sent = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Plan aus den Dateien']);
        self::assertNotNull($sent);
        self::assertSame('plan.txt', $sent->getAttachments()->first() ? $sent->getAttachments()->first()->getFilename() : null);
    }

    public function testComposeIsPrefilledFromQuery(): void
    {
        $user = $this->login();
        $this->createMailAccount($this->createOrganization($user));

        $crawler = $this->client->request('GET', '/mail/new?subject=Geteilt&body='.rawurlencode('https://example.org'));
        self::assertSame('Geteilt', $crawler->filter('input[name="compose_form[subject]"]')->attr('value'));
        self::assertStringContainsString('https://example.org', $crawler->filter('textarea[name="compose_form[body]"]')->text());
    }

    private function upload(Folder $folder, string $content): void
    {
        $crawler = $this->client->request('GET', '/files/folder/'.$folder->getId());
        $form = $crawler->selectButton('Hochladen')->form();
        $field = $form->get('upload_form[files]');
        self::assertIsArray($field);
        self::assertInstanceOf(FileFormField::class, $field[0]);
        $field[0]->upload($this->tempFile('protokoll.txt', $content));
        $this->client->submit($form);
        self::assertResponseRedirects('/files/folder/'.$folder->getId());
    }

    private function downloadedContent(): string
    {
        ob_start();
        $this->client->getResponse()->sendContent();

        return (string) ob_get_clean();
    }

    private function createFolder(Organization $org, User $user): Folder
    {
        $folder = (new Folder($org, $user))->setName('Allgemein');
        $this->em()->persist($folder);
        $this->em()->flush();

        return $folder;
    }

    private function createFile(Folder $folder, User $user, string $name, string $mime, string $content): StoredFile
    {
        $file = new StoredFile($folder, $name, $mime, \strlen($content), $this->storage()->store($content), $user);
        $this->em()->persist($file);
        $this->em()->flush();

        return $file;
    }

    private function storage(): FileStorage
    {
        $storage = self::getContainer()->get(FileStorage::class);
        self::assertInstanceOf(FileStorage::class, $storage);

        return $storage;
    }

    private function tempFile(string $name, string $content): string
    {
        $dir = sys_get_temp_dir().'/koopio-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }
}
