<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use App\Entity\Meeting;
use Facebook\WebDriver\WebDriverBy;

/**
 * Automatic saving of edit forms, browser backup of new entries and growing text fields.
 */
final class FormSavingTest extends BrowserTestCase
{
    public function testEditFormSavesWhileTyping(): void
    {
        $user = $this->createUser();
        $org = $this->createOrganization($user);
        $meeting = (new Meeting($org, $user))->setTitle('Vollversammlung')->setStartsAt(new \DateTimeImmutable('+14 days 19:00'));
        $this->em()->persist($meeting);
        $this->em()->flush();
        $this->login($user);

        $this->client->request('GET', '/meetings/'.$meeting->getId().'/edit');
        $this->client->waitFor('#meeting_form_location');
        $this->client->findElement(WebDriverBy::id('meeting_form_location'))->sendKeys('Rathaus');
        $this->client->waitForElementToContain('form [role="status"]', 'Automatisch gespeichert');

        $this->em()->clear();
        self::assertSame('Rathaus', $this->em()->find(Meeting::class, $meeting->getId())?->getLocation());
    }

    public function testNewEntryIsRestoredAfterReloadAndTextareaGrows(): void
    {
        $user = $this->createUser();
        $this->createOrganization($user);
        $this->login($user);

        $this->client->request('GET', '/meetings/new');
        $this->client->waitFor('#meeting_form_description');
        $textarea = $this->client->findElement(WebDriverBy::id('meeting_form_description'));
        $before = $textarea->getSize()->getHeight();
        $textarea->sendKeys(str_repeat("Zeile\n", 12));
        self::assertGreaterThan($before, $textarea->getSize()->getHeight());

        // a reload keeps what was typed
        $this->client->reload();
        $this->client->waitFor('#meeting_form_description');
        $this->client->waitForElementToContain('form [role="status"]', 'wiederhergestellt');
        self::assertStringContainsString('Zeile', (string) $this->client->findElement(WebDriverBy::id('meeting_form_description'))->getAttribute('value'));
    }
}
