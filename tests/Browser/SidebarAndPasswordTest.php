<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;

final class SidebarAndPasswordTest extends BrowserTestCase
{
    public function testCollapsedNavigationScrollsAndShowsFlyouts(): void
    {
        $user = $this->createUser();
        $this->createOrganization($user);
        $this->login($user);
        $this->client->manage()->window()->setSize(new WebDriverDimension(1200, 650));

        $this->client->getCrawler()->filter('button[data-action="shell#toggleCollapsed"]')->click();
        $this->client->waitFor('[data-collapsed="true"]');

        /** @var array{scrollable: bool, scrolled: bool} $state */
        $state = $this->client->executeScript(<<<'JS'
            const list = document.querySelector('[data-controller="nav-flyout"]');
            const scrollable = list.scrollHeight > list.clientHeight;
            list.scrollTop = list.scrollHeight;
            return {scrollable, scrolled: list.scrollTop > 0};
            JS);
        self::assertTrue($state['scrollable']);
        self::assertTrue($state['scrolled']);

        // the flyout of the last entry opens next to the navigation (mouse and keyboard), not clipped by the scroll area
        $items = $this->client->findElements(WebDriverBy::cssSelector('[data-flyout-item] > a'));
        $last = end($items);
        self::assertNotFalse($last);
        $driver = $this->client->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);
        $driver->action()->moveToElement($last)->perform();
        $this->assertLastFlyoutVisible();

        $driver->action()->moveByOffset(600, 0)->perform();
        $this->client->executeScript("[...document.querySelectorAll('[data-flyout-item] > a')].pop().focus()");
        $this->assertLastFlyoutVisible();
    }

    private function assertLastFlyoutVisible(): void
    {
        /** @var array{display: string, top: float, bottom: float, left: float, navRight: float, height: float} $flyout */
        $flyout = $this->client->executeScript(<<<'JS'
            const flyout = [...document.querySelectorAll('[data-flyout-item]')].pop().querySelector(':scope > [data-flyout]');
            const rect = flyout.getBoundingClientRect();
            return {display: getComputedStyle(flyout).display, top: rect.top, bottom: rect.bottom, left: rect.left, height: innerHeight, navRight: document.getElementById('main-nav').getBoundingClientRect().right};
            JS);
        self::assertNotSame('none', $flyout['display']);
        self::assertGreaterThanOrEqual($flyout['navRight'] - 16, $flyout['left']);
        self::assertLessThanOrEqual($flyout['height'], $flyout['bottom']);
    }

    public function testPasswordCanBeRevealed(): void
    {
        $this->client->request('GET', '/login');
        $input = $this->client->findElement(WebDriverBy::id('password'));
        $input->sendKeys('geheim');
        self::assertSame('password', $input->getAttribute('type'));

        $button = $this->client->findElement(WebDriverBy::cssSelector('button[aria-controls="password"]'));
        $button->click();
        self::assertSame('text', $input->getAttribute('type'));
        self::assertSame('true', $button->getAttribute('aria-pressed'));

        $button->click();
        self::assertSame('password', $input->getAttribute('type'));
        self::assertSame('false', $button->getAttribute('aria-pressed'));
    }
}
