<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Mail\MailboxReader;
use App\Service\CronRunner;
use App\Tests\AppTestCase;
use App\Tests\Fake\FakeMailboxReader;

/**
 * Cron by URL: only with the token, runs the due tasks after the response and each task only once per interval.
 */
final class CronTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @unlink(self::getContainer()->getParameter('kernel.cache_dir').'/cron.json');
    }

    public function testWrongTokenIsNotFound(): void
    {
        $this->client->request('GET', '/_cron/falsch');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRunsDueTasksOncePerInterval(): void
    {
        $user = $this->createUser();
        $this->createMailAccount($this->createOrganization($user));
        $reader = self::getContainer()->get(MailboxReader::class);
        self::assertInstanceOf(FakeMailboxReader::class, $reader);
        $reader->add((string) file_get_contents(__DIR__.'/../fixtures/simple.eml'));

        $this->client->request('GET', '/_cron/test-cron-token-0123456789abcdefgh');
        // silent on success, so the cron daemon sends no mail
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        self::assertNotNull($this->em()->getRepository(Message::class)->findOneBy(['messageIdHeader' => 'abc123@example.org']));

        // right afterwards nothing is due any more
        self::assertSame([], self::getContainer()->get(CronRunner::class)->run());
    }

    public function testFailedTaskIsReported(): void
    {
        $user = $this->createUser();
        $this->createMailAccount($this->createOrganization($user))->setImapHost('fail.example.org');
        $this->em()->flush();

        // phpunit.dist.xml silences console output (SHELL_VERBOSITY=-1); the web server does not
        $_SERVER['SHELL_VERBOSITY'] = $_ENV['SHELL_VERBOSITY'] = 0;
        try {
            $this->client->request('GET', '/_cron/test-cron-token-0123456789abcdefgh');
        } finally {
            $_SERVER['SHELL_VERBOSITY'] = $_ENV['SHELL_VERBOSITY'] = -1;
        }
        self::assertResponseStatusCodeSame(500);
        $report = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('> app:mail:sync', $report);
        self::assertStringContainsString('connection refused', $report);
        self::assertStringNotContainsString('app:mail:outbox', $report);
    }
}
