<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ForumBoard;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * Areas switched off in the organization settings disappear (menu, routes, lists), their data stays.
 */
final class FeatureTest extends AppTestCase
{
    public function testAdminSwitchesAreaOff(): void
    {
        $owner = $this->login();
        $org = $this->createOrganization($owner);

        $crawler = $this->client->request('GET', '/organizations/'.$org->getId().'/edit');
        $form = $crawler->selectButton('Speichern')->form();
        foreach ($form->all() as $field) {
            if ($field instanceof ChoiceFormField && str_starts_with($field->getName(), 'organization_form[enabledFeatures]')
                && \in_array(Feature::Wiki->value, $field->availableOptionValues(), true)) {
                $field->untick();
            }
        }
        $this->client->submit($form);
        self::assertResponseRedirects();

        $org = $this->em()->find(Organization::class, $org->getId());
        self::assertNotNull($org);
        self::assertFalse($org->hasFeature(Feature::Wiki));
        self::assertTrue($org->hasFeature(Feature::Forum));

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#main-nav a[href="/knowledge"]');
        self::assertSelectorExists('#main-nav a[href="/forum"]');
        $this->client->request('GET', '/knowledge');
        self::assertResponseStatusCodeSame(404);
    }

    public function testContentIsFilteredPerOrganization(): void
    {
        $user = $this->login();
        $off = $this->createOrganization($user, 'SEV Ohne Forum');
        $on = $this->createOrganization($user, 'SEV Mit Forum');
        $off->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::Forum !== $f));
        $hidden = $this->createBoard($off, $user, 'Verborgener Bereich');
        $this->createBoard($on, $user, 'Sichtbarer Bereich');

        $this->client->request('GET', '/forum');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sichtbarer Bereich');
        self::assertSelectorTextNotContains('body', 'Verborgener Bereich');

        $this->client->request('GET', '/forum/board/'.$hidden->getId());
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/forum/board/new');
        self::assertResponseIsSuccessful();
        $options = $crawler->filter('select option')->each(static fn ($o): string => trim($o->text()));
        self::assertContains('SEV Mit Forum', $options);
        self::assertNotContains('SEV Ohne Forum', $options);
    }

    public function testDataStaysWhenSwitchedBackOn(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $board = $this->createBoard($org, $user, 'Schulwege');
        $org->setEnabledFeatures([]);
        $this->em()->flush();

        $this->client->request('GET', '/forum/board/'.$board->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->em()->find(Organization::class, $org->getId())?->setEnabledFeatures(Feature::cases());
        $this->em()->flush();
        $this->client->request('GET', '/forum/board/'.$board->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Schulwege');
    }

    public function testMailAdministrationIsGoneWithoutMail(): void
    {
        $user = $this->login();
        $org = $this->createOrganization($user);
        $account = $this->createMailAccount($org);
        $org->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::Mail !== $f));
        $this->em()->flush();

        $this->client->request('GET', '/mail-accounts/'.$account->getId().'/edit');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/organizations/'.$org->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/mail-accounts/'.$account->getId().'/edit"]');
    }

    private function createBoard(Organization $org, User $user, string $name): ForumBoard
    {
        $board = (new ForumBoard($org, $user))->setName($name);
        $this->em()->persist($board);
        $this->em()->flush();

        return $board;
    }
}
