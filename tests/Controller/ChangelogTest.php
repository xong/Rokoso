<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\Changelog;
use App\Tests\AppTestCase;

/**
 * Release notes: page in the profile, hint on the start page until read or dismissed.
 */
final class ChangelogTest extends AppTestCase
{
    public function testParsesSectionsNewestFirst(): void
    {
        $releases = Changelog::parse("# Neuigkeiten\r\nVorwort\r\n\r\n## 1.1.0 – 2026-11-02\r\n### Neu\r\n- B\r\n\r\n## [1.0.0]\r\n- A\r\n");

        self::assertCount(2, $releases);
        self::assertSame('1.1.0', $releases[0]->version);
        self::assertSame('2026-11-02', $releases[0]->date?->format('Y-m-d'));
        self::assertSame("### Neu\n- B", $releases[0]->notes);
        self::assertSame("#### Neu\n- B", $releases[0]->notesFrom(4));
        self::assertNull($releases[1]->date);
        self::assertSame('- A', $releases[1]->notes);
    }

    public function testHintOnStartPageUntilRead(): void
    {
        $user = $this->login($this->olderUser());
        $version = self::getContainer()->get(Changelog::class)->version();

        $crawler = $this->client->request('GET', '/');
        self::assertSelectorTextContains('#release-heading', $version);

        $this->client->submit($crawler->selectButton('Gelesen')->form());
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorNotExists('#release-heading');
        self::assertSame($version, $this->reload($user)->getSeenVersion());
    }

    public function testReadingThePageMarksTheVersionSeen(): void
    {
        $user = $this->login($this->olderUser());

        $this->client->request('GET', '/profile/changelog');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Neuigkeiten');
        self::assertNotNull($this->reload($user)->getSeenVersion());

        $this->client->request('GET', '/');
        self::assertSelectorNotExists('#release-heading');
    }

    public function testNoHintForAccountsCreatedAfterTheRelease(): void
    {
        $this->login();

        $this->client->request('GET', '/');
        $date = self::getContainer()->get(Changelog::class)->current()?->date;
        if (null !== $date && $date < new \DateTimeImmutable('today')) {
            self::assertSelectorNotExists('#release-heading');
        } else {
            self::assertSelectorExists('#release-heading');
        }
    }

    private function reload(User $user): User
    {
        $this->em()->clear();

        return $this->em()->find(User::class, $user->getId()) ?? throw new \LogicException('User gone.');
    }

    private function olderUser(): User
    {
        $user = $this->createUser();
        new \ReflectionProperty(User::class, 'createdAt')->setValue($user, new \DateTimeImmutable('2020-01-01'));
        $this->em()->flush();

        return $user;
    }
}
