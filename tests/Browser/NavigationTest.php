<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use Facebook\WebDriver\WebDriverKeys;

final class NavigationTest extends BrowserTestCase
{
    public function testLoginShowsTodayAndPaletteNavigates(): void
    {
        $user = $this->createUser();
        $this->createOrganization($user);
        $this->login($user);
        self::assertSelectorTextContains('h1', 'Heute');

        // Ctrl+K opens the palette, typing filters, Enter opens the first hit
        $this->client->getKeyboard()->pressKey(WebDriverKeys::CONTROL);
        $this->client->getKeyboard()->sendKeys('k');
        $this->client->getKeyboard()->releaseKey(WebDriverKeys::CONTROL);
        $this->client->waitForVisibility('#palette-input');
        $this->client->getKeyboard()->sendKeys('Kontakte');
        $this->client->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        $this->client->wait(5)->until(static fn ($driver): bool => str_contains((string) $driver->getCurrentURL(), '/contacts'));

        // shortcut "g k" opens the calendar
        $this->client->getKeyboard()->sendKeys('g');
        $this->client->getKeyboard()->sendKeys('k');
        $this->client->wait(5)->until(static fn ($driver): bool => str_contains((string) $driver->getCurrentURL(), '/calendar'));
        self::assertSelectorExists('main');
    }
}
