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

    public function testListWidthIsStoredPerPage(): void
    {
        $user = $this->createUser();
        $this->createOrganization($user);
        $this->login($user);
        $this->client->request('GET', '/mail');
        $this->client->waitFor('#list-panel [role="separator"]');
        $width = "return Math.round(document.getElementById('list-panel').getBoundingClientRect().width);";
        $default = $this->client->executeScript($width);

        // End = widest
        $this->client->executeScript(<<<'JS'
            const separator = document.querySelector('#list-panel [role="separator"]');
            separator.focus();
            separator.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
            JS);
        // reduced motion in the test browser still runs a 0.01ms width transition
        $this->client->wait(2)->until(fn (): bool => $this->client->executeScript($width) > $default);
        $wide = $this->client->executeScript($width);
        self::assertGreaterThan($default, $wide);

        // another page keeps its own (default) width, also after a full load
        $this->client->clickLink('Kontakte');
        $this->client->waitFor('#list-panel[data-panel-resize-cookie-value="panel_list_contact_index"]');
        self::assertSame($default, $this->client->executeScript($width));

        $this->client->request('GET', '/mail/done');
        $this->client->waitFor('#list-panel');
        self::assertSame($wide, $this->client->executeScript($width));
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
