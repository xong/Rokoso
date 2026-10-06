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
        self::assertResponseStatusCodeSame(202);
        // the mail sync ran after the response
        self::assertNotNull($this->em()->getRepository(Message::class)->findOneBy(['messageIdHeader' => 'abc123@example.org']));

        // right afterwards nothing is due any more
        self::assertSame([], self::getContainer()->get(CronRunner::class)->run());
    }
}
