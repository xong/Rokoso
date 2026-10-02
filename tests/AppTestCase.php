<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

abstract class AppTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email = 'anna@example.org', string $name = 'Anna Schulz', bool $verified = true, string $password = 'geheim-geheim'): User
    {
        $user = (new User())->setEmail($email)->setName($name)->setVerified($verified);
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * First link in the last sent mail matching the given path fragment.
     */
    protected function linkFromLastMail(string $pathPattern): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(1, preg_match('#(?:https?://[^/\s"<]+)?'.$pathPattern.'[^\s"<]*#', (string) $email->getHtmlBody(), $m));

        return html_entity_decode($m[0]);
    }

    protected function login(?User $user = null): User
    {
        $user ??= $this->createUser();
        $this->client->loginUser($user);

        return $user;
    }
}
