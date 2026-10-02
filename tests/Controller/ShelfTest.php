<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Entity\Folder;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Enum\OrganizationRole;
use App\Service\AttachmentStorage;
use App\Service\FileStorage;
use App\Tests\AppTestCase;

final class ShelfTest extends AppTestCase
{
    public function testFileFromShelfIsAttachedToMail(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $this->createMailAccount($org);
        $file = $this->createFile($user, $org);

        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->filter('form[action$="/shelf/file/'.$file->getId().'"]')->form());
        self::assertResponseRedirects();

        $this->client->request('GET', '/shelf');
        self::assertSelectorTextContains('main', 'plan.txt');
        $item = $this->em()->getRepository(ShelfItem::class)->findOneBy(['file' => $file]);
        self::assertNotNull($item);

        $crawler = $this->client->request('GET', '/mail/new?shelf[]='.$item->getId());
        self::assertSame('checked', $crawler->filter('input[name="compose_form[shelfItems][]"]')->attr('checked'));
        $this->client->submitForm('Senden', [
            'compose_form[to]' => 'eva@example.org',
            'compose_form[subject]' => 'Plan anbei',
        ]);
        self::assertResponseRedirects();

        $sent = $this->em()->getRepository(Message::class)->findOneBy(['subject' => 'Plan anbei']);
        self::assertNotNull($sent);
        self::assertSame(['plan.txt'], array_map(static fn (Attachment $a): string => $a->getFilename(), $sent->getAttachments()->toArray()));

        // Toggling again removes the reference, the file stays
        $crawler = $this->client->request('GET', '/files/'.$file->getId());
        $this->client->submit($crawler->filter('form[action$="/shelf/file/'.$file->getId().'"]')->form());
        $this->client->request('GET', '/shelf');
        self::assertSelectorTextNotContains('main', 'plan.txt');
        self::assertNotNull($this->em()->getRepository(StoredFile::class)->find($file->getId()));
    }

    public function testAttachmentCanBePutOnShelf(): void
    {
        $user = $this->login();
        $account = $this->createMailAccount($this->createOrganization($user));
        $message = (new Message())->setMailAccount($account)->setFolder(MessageFolder::Inbox)
            ->setFrom('eva@example.org', 'Eva')->setSubject('Einladung')->setBody('Siehe Anhang');
        $storage = self::getContainer()->get(AttachmentStorage::class);
        self::assertInstanceOf(AttachmentStorage::class, $storage);
        $message->addAttachment(new Attachment($message, 'einladung.pdf', 'application/pdf', 3, $storage->store('pdf')));
        $this->em()->persist($message);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/mail/inbox/'.$message->getId());
        $this->client->submit($crawler->filter('form[action*="/shelf/message/"]')->form());
        $this->client->request('GET', '/shelf');
        self::assertSelectorTextContains('main', 'einladung.pdf');
        self::assertSelectorTextContains('main', 'Einladung');
    }

    public function testShelfIsPersonalAndRespectsAccess(): void
    {
        $owner = $this->createUser('owner@example.org', 'Owner');
        $member = $this->createUser();
        $org = $this->createOrganization($owner);
        $org->addMember($member, OrganizationRole::Member);
        $file = $this->createFile($owner, $org);
        $item = ShelfItem::forFile($member, $file);
        $this->em()->persist($item);
        $this->em()->flush();

        // Others neither see nor remove the entry
        $this->login($owner);
        $this->client->request('GET', '/shelf');
        self::assertSelectorTextNotContains('main', 'plan.txt');
        $this->client->request('GET', '/shelf/'.$item->getId().'/download');
        self::assertResponseStatusCodeSame(403);

        // After leaving the organization the entry is hidden
        $this->login($member);
        $this->client->request('GET', '/shelf');
        self::assertSelectorTextContains('main', 'plan.txt');
        $this->em()->createQuery('DELETE FROM App\Entity\Membership m WHERE m.user = :user')
            ->setParameter('user', $member->getId())
            ->execute();
        $this->client->request('GET', '/shelf');
        self::assertSelectorTextNotContains('main', 'plan.txt');
    }

    private function createFile(User $user, Organization $org): StoredFile
    {
        $folder = (new Folder($org, $user))->setName('Allgemein');
        $storage = self::getContainer()->get(FileStorage::class);
        self::assertInstanceOf(FileStorage::class, $storage);
        $path = 'test/'.bin2hex(random_bytes(6));
        @mkdir(\dirname($storage->absolutePath($path)), 0o777, true);
        file_put_contents($storage->absolutePath($path), 'Plan');
        $file = new StoredFile($folder, 'plan.txt', 'text/plain', 4, $path, $user);
        $this->em()->persist($folder);
        $this->em()->persist($file);
        $this->em()->flush();

        return $file;
    }
}
