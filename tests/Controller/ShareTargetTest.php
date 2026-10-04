<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Folder;
use App\Entity\StoredFile;
use App\Service\FileStorage;
use App\Tests\AppTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ShareTargetTest extends AppTestCase
{
    public function testManifestDeclaresShareTarget(): void
    {
        $this->client->request('GET', '/manifest.webmanifest');
        $manifest = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($manifest);
        self::assertSame('/share', $manifest['share_target']['action']);
        self::assertSame('POST', $manifest['share_target']['method']);
    }

    public function testSharedFilesAreSavedIntoFolder(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $folder = (new Folder($org, $user))->setName('Eingang');
        $this->em()->persist($folder);
        $this->em()->flush();

        $this->client->request('POST', '/share', ['title' => 'Flyer', 'text' => 'Bitte ansehen'], ['files' => [$this->uploadedFile('flyer.txt', 'Sommerfest')]]);
        self::assertResponseRedirects('/share', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'flyer.txt');
        self::assertSelectorTextContains('main', 'Bitte ansehen');
        self::assertStringContainsString('body=Bitte', (string) $crawler->selectLink('Als E-Mail senden')->attr('href'));

        // A folder is required
        $this->client->submitForm('Speichern', ['folder' => '']);
        self::assertResponseRedirects('/share');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'flyer.txt');

        $this->client->submitForm('Speichern', ['folder' => (string) $folder->getId()]);
        self::assertResponseRedirects('/files/folder/'.$folder->getId());
        $file = $this->em()->getRepository(StoredFile::class)->findOneBy(['folder' => $folder]);
        self::assertNotNull($file);
        self::assertSame('flyer.txt', $file->getFilename());
        self::assertSame('Sommerfest', file_get_contents($this->storage()->absolutePath($file->getStoragePath())));

        // Nothing is pending afterwards
        $this->client->request('GET', '/share');
        self::assertSelectorTextNotContains('main', 'flyer.txt');
    }

    public function testDiscardRemovesIncomingFiles(): void
    {
        $this->login();
        $before = glob($this->storage()->absolutePath('incoming').'/*') ?: [];
        $this->client->request('POST', '/share', [], ['files' => [$this->uploadedFile('notiz.txt', 'x')]]);
        $this->client->followRedirect();
        $incoming = array_diff(glob($this->storage()->absolutePath('incoming').'/*') ?: [], $before);
        self::assertNotEmpty($incoming);

        $this->client->submitForm('Verwerfen');
        self::assertResponseRedirects('/mail');
        foreach ($incoming as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testNotifyPurgesOldIncomingFiles(): void
    {
        $storage = $this->storage();
        $old = $storage->storeIncoming($this->uploadedFile('alt.txt', 'alt'));
        touch($storage->absolutePath($old), time() - 2 * 86400);
        $new = $storage->storeIncoming($this->uploadedFile('neu.txt', 'neu'));

        $tester = new CommandTester((new Application($this->client->getKernel()))->find('app:notify'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertFileDoesNotExist($storage->absolutePath($old));
        self::assertFileExists($storage->absolutePath($new));
        $storage->remove($new);
    }

    private function uploadedFile(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir().'/coop-test-'.bin2hex(random_bytes(4)).'-'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function storage(): FileStorage
    {
        $storage = self::getContainer()->get(FileStorage::class);
        self::assertInstanceOf(FileStorage::class, $storage);

        return $storage;
    }
}
