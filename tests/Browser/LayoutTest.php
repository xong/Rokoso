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

    /** Jumping to an anchor scrolls the column only; the shell around it must not move (content cut off at the top) */
    public function testAnchorJumpScrollsOnlyTheColumn(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $this->login($user);
        $this->client->request('GET', '/organizations/'.$organization->getId());
        $this->client->waitFor('#members-heading');

        $offsets = $this->client->executeScript(<<<'JS'
            const scroller = document.querySelector('#main section:last-child .overflow-y-auto');
            scroller.insertAdjacentHTML('afterbegin', '<div style="height: 3000px"></div>');
            scroller.insertAdjacentHTML('beforeend', '<span class="sr-only">hidden</span>');
            document.getElementById('members-heading').scrollIntoView();
            return [document.querySelector('[data-controller~="shell"]').scrollTop, document.scrollingElement.scrollTop, scroller.scrollTop];
            JS);

        self::assertIsArray($offsets);
        self::assertSame([0, 0], [$offsets[0], $offsets[1]]);
        self::assertGreaterThan(0, $offsets[2]);
    }
}
