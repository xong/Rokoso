<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumTopicRead;
use App\Entity\ForumUpload;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ForumTest extends AppTestCase
{
    public function testMemberCreatesBoardTopicAndReply(): void
    {
        [$admin, $member, $org] = $this->setUpOrganization();

        $this->login($member);
        $this->client->request('GET', '/forum/board/new');
        $this->client->submitForm('Speichern', [
            'forum_board_form[name]' => 'Allgemeines',
            'forum_board_form[organization]' => (string) $org->getId(),
        ]);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Allgemeines');

        $this->client->click($crawler->selectLink('Neues Thema')->link());
        $this->client->submitForm('Thema eröffnen', [
            'forum_topic_form[title]' => 'Elternabend',
            'forum_topic_form[post][body]' => "Wer kommt **mit**?\n\n<script>alert(1)</script>",
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Elternabend');
        self::assertSelectorTextContains('.markdown strong', 'mit');
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('&lt;script&gt;', (string) $this->client->getResponse()->getContent());

        // The admin sees the topic as unread, replies, and it becomes read
        $this->login($admin);
        $this->client->request('GET', '/forum');
        self::assertSelectorTextContains('main', 'Elternabend (ungelesen)');

        $topic = $this->em()->getRepository(ForumTopic::class)->findOneBy(['title' => 'Elternabend']);
        self::assertNotNull($topic);
        // read marks have second precision: move the member's mark into the past so the reply is newer
        $this->em()->createQuery('UPDATE '.ForumTopicRead::class.' r SET r.readAt = :past')
            ->setParameter('past', new \DateTimeImmutable('-1 minute'))->execute();
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $this->client->submitForm('Antwort senden', ['forum_post_form[body]' => 'Ich bin dabei.']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorCount(2, 'article');

        $this->client->request('GET', '/forum');
        self::assertSelectorTextNotContains('main', 'ungelesen');

        $this->em()->clear();
        $topic = $this->em()->getRepository(ForumTopic::class)->find($topic->getId());
        self::assertNotNull($topic);
        self::assertSame(2, $topic->getPostCount());
        self::assertSame($admin->getId(), $topic->getLastPostBy()?->getId());

        // For the member the reply makes the topic unread again
        $this->login($member);
        $this->client->request('GET', '/forum');
        self::assertSelectorTextContains('main', 'Elternabend (ungelesen)');
    }

    public function testExternalImagesBecomeLinks(): void
    {
        [$admin, , $org] = $this->setUpOrganization();
        $topic = $this->createTopic($this->createBoard($org, $admin), $admin, '![Logo](https://tracker.example.com/pixel.png) [x](javascript:alert(1))');

        $this->login($admin);
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertSelectorNotExists('.markdown img');
        self::assertSelectorExists('.markdown a[href="https://tracker.example.com/pixel.png"][rel~="noopener"]');
        self::assertSelectorNotExists('.markdown a[href^="javascript"]');
    }

    public function testPermissions(): void
    {
        [$admin, $member, $org] = $this->setUpOrganization();
        $board = $this->createBoard($org, $admin);
        $topic = $this->createTopic($board, $admin, 'Erster Beitrag');
        $reply = new ForumPost($topic, $member);
        $reply->setBody('Antwort');
        $topic->addPost($reply);
        $this->em()->persist($reply);
        $this->em()->flush();

        // Outsiders see nothing
        $this->login($this->createUser('outsider@example.org', 'Outsider'));
        $this->client->request('GET', '/forum/board/'.$board->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/forum');
        self::assertSelectorTextNotContains('main', 'Thema');

        // Members edit only their own posts and cannot delete foreign topics
        $this->login($member);
        $this->client->request('GET', '/forum/post/'.$topic->getFirstPost()?->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/forum/post/'.$reply->getId().'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Speichern', ['forum_post_form[body]' => 'Geänderte Antwort']);
        self::assertResponseRedirects();
        $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertSelectorTextContains('main', 'Geänderte Antwort');
        self::assertSelectorTextContains('main', 'bearbeitet');
        self::assertSelectorNotExists('form[action$="/forum/topic/'.$topic->getId().'/delete"]');

        // The organization admin deletes the reply and then the topic
        $this->login($admin);
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        self::assertSelectorNotExists('form[action$="/forum/post/'.$topic->getFirstPost()?->getId().'/delete"]');
        $this->client->submit($crawler->filter('form[action$="/forum/post/'.$reply->getId().'/delete"]')->form());
        self::assertResponseRedirects('/forum/topic/'.$topic->getId());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('form[action$="/forum/topic/'.$topic->getId().'/delete"]')->form());
        self::assertResponseRedirects('/forum/board/'.$board->getId());
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(ForumTopic::class)->find($topic->getId()));
    }

    public function testImageUploadAndAttachments(): void
    {
        [$admin, $member, $org] = $this->setUpOrganization();
        $board = $this->createBoard($org, $admin);
        $topic = $this->createTopic($board, $admin, 'Bilder');

        $this->login($member);
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $token = (string) $crawler->filter('[data-markdown-editor-token-value]')->attr('data-markdown-editor-token-value');

        // Images only
        $this->client->request('POST', '/forum/board/'.$board->getId().'/image', ['_token' => $token], [
            'image' => new UploadedFile($this->tempFile('notiz.txt', 'kein Bild'), 'notiz.txt', null, null, true),
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/forum/board/'.$board->getId().'/image', ['_token' => $token], [
            'image' => new UploadedFile($this->tempFile('foto.png', $this->png()), 'foto.png', null, null, true),
        ]);
        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsString($data['url']);
        self::assertSame('![foto]('.$data['url'].')', $data['markdown']);

        // Relative images are rendered inline
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $form = $crawler->selectButton('Antwort senden')->form(['forum_post_form[body]' => 'Schaut mal: '.$data['markdown']]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorExists('.markdown img[src="'.$data['url'].'"]');

        $this->client->request('GET', $data['url']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        // Not visible outside the organization
        $this->login($this->createUser('outsider@example.org', 'Outsider'));
        $this->client->request('GET', $data['url']);
        self::assertResponseStatusCodeSame(403);

        // Attachments of a reply are offered for download
        $this->login($member);
        $crawler = $this->client->request('GET', '/forum/topic/'.$topic->getId());
        $form = $crawler->selectButton('Antwort senden')->form(['forum_post_form[body]' => 'Mit Anhang']);
        $field = $form->get('forum_post_form[files]');
        self::assertIsArray($field);
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $field[0]);
        $field[0]->upload($this->tempFile('protokoll.txt', 'Protokoll'));
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorTextContains('ol[aria-label="Beiträge"] > li:last-child', 'protokoll.txt');

        $upload = $this->em()->getRepository(ForumUpload::class)->findOneBy(['filename' => 'protokoll.txt']);
        self::assertNotNull($upload);
        self::assertNotNull($upload->getPost());
        $this->client->request('GET', '/forum/upload/'.$upload->getId().'?download=1');
        self::assertResponseHeaderSame('Content-Type', 'application/octet-stream');
    }

    public function testProjectPageListsTopics(): void
    {
        [$admin, , $org] = $this->setUpOrganization();
        $project = (new Project($admin))->setName('Schulweg')->setOrganization($org);
        $this->em()->persist($project);
        $board = $this->createBoard($org, $admin);
        $board->setProject($project);
        $this->em()->flush();
        $this->createTopic($board, $admin, 'Text');

        $this->login($admin);
        $this->client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorTextContains('section[aria-labelledby="project-forum-heading"]', 'Thema in Schulweg');
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

    private function createBoard(Organization $org, User $user): ForumBoard
    {
        $board = (new ForumBoard($org, $user))->setName('Allgemeines');
        $this->em()->persist($board);
        $this->em()->flush();

        return $board;
    }

    private function createTopic(ForumBoard $board, User $user, string $body): ForumTopic
    {
        $topic = (new ForumTopic($board, $user))->setTitle('Thema in '.($board->getProject()?->getName() ?? $board->getName()));
        $post = (new ForumPost($topic, $user))->setBody($body);
        $topic->addPost($post);
        $this->em()->persist($topic);
        $this->em()->flush();

        return $topic;
    }

    private function tempFile(string $name, string $content): string
    {
        $dir = sys_get_temp_dir().'/koopio-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    }
}
