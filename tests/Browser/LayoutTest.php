<?php

declare(strict_types=1);

namespace App\Tests\Browser;

/**
 * Only the columns scroll, never the page – also when long lists contain visually hidden (absolutely positioned) elements.
 */
final class LayoutTest extends BrowserTestCase
{
    public function testPageDoesNotScrollBeyondTheShell(): void
    {
        $this->login($this->createUser());
        $this->client->request('GET', '/');
        $this->client->waitFor('#main');

        $overflow = $this->client->executeScript(<<<'JS'
            const scroller = document.querySelector('#main .overflow-y-auto');
            scroller.insertAdjacentHTML('beforeend', '<div style="height: 3000px"></div><span class="sr-only">hidden</span>');
            return document.scrollingElement.scrollHeight - window.innerHeight;
            JS);

        self::assertLessThanOrEqual(0, $overflow);
    }
}
