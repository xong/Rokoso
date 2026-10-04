<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverDimension;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Real browser (Chrome) against the PHP web server in the test environment. Unlike the other tests the data is
 * committed (no DAMA rollback), so every test uses its own e-mail address.
 * Run with `composer test:browser`; needs Chrome and `vendor/bin/bdi detect drivers`.
 */
abstract class BrowserTestCase extends PantherTestCase
{
    protected const string PASSWORD = 'geheim-geheim';

    protected Client $client;

    protected function setUp(): void
    {
        // Panther finds "./drivers/chromedriver" only as a relative path, which Windows cannot start
        $drivers = \dirname(__DIR__, 2).\DIRECTORY_SEPARATOR.'drivers';
        if (!str_contains((string) getenv('PATH'), $drivers)) {
            putenv('PATH='.$drivers.\PATH_SEPARATOR.getenv('PATH'));
        }
        $this->client = static::createPantherClient(['browser' => static::CHROME]);
        $this->client->manage()->window()->setSize(new WebDriverDimension(1400, 900));
        // the browser is shared between tests: start logged out
        $this->client->request('GET', '/login');
        $this->client->manage()->deleteAllCookies();
    }

    protected function em(): EntityManagerInterface
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $name = 'Anna Schulz'): User
    {
        $user = (new User())->setEmail(uniqid('browser-', true).'@example.org')->setName($name)->setVerified(true);
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function createOrganization(User $admin, string $name = 'SEV Musterstadt'): Organization
    {
        $organization = (new Organization())->setName($name);
        $organization->addMember($admin, OrganizationRole::Admin);
        $this->em()->persist($organization);
        $this->em()->flush();

        return $organization;
    }

    protected function login(User $user): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => $user->getEmail(), '_password' => self::PASSWORD]);
        $this->client->waitFor('main');
    }
}
