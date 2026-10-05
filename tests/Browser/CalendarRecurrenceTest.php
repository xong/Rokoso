<?php

declare(strict_types=1);

namespace App\Tests\Browser;

/**
 * The repetition choices name the day of the start ("Monatlich am 2. Donnerstag") and hide "last …" when it does not fit.
 */
final class CalendarRecurrenceTest extends BrowserTestCase
{
    public function testLabelsFollowTheStart(): void
    {
        $this->login($this->createUser());
        $this->client->request('GET', '/calendar/new');
        $this->client->waitFor('#calendar_item_form_recurrence');

        $options = fn (): array => $this->client->executeScript(
            'return [...document.querySelectorAll("#calendar_item_form_recurrence option")].filter((o) => !o.hidden).map((o) => o.textContent.trim());'
        );
        $setStart = function (string $value): void {
            $this->client->executeScript(\sprintf(
                'const i = document.getElementById("calendar_item_form_startsAt"); i.value = "%s"; i.dispatchEvent(new Event("input", {bubbles: true}));',
                $value,
            ));
        };

        $setStart('2026-10-08T19:00');
        $labels = $options();
        self::assertContains('Wöchentlich am Donnerstag', $labels);
        self::assertContains('Monatlich am 8.', $labels);
        self::assertContains('Monatlich am 2. Donnerstag', $labels);
        self::assertNotContains('Monatlich am letzten Donnerstag', $labels);

        $setStart('2026-10-29T19:00');
        $labels = $options();
        self::assertContains('Monatlich am 5. Donnerstag', $labels);
        self::assertContains('Monatlich am letzten Donnerstag', $labels);
    }

    public function testIntervalAppearsWithItsUnit(): void
    {
        $this->login($this->createUser());
        $this->client->request('GET', '/calendar/new');
        $this->client->waitFor('#calendar_item_form_recurrence');

        $state = fn (): array => $this->client->executeScript(
            'return [document.querySelector("[data-recurrence-target=options]").hidden, document.querySelector("[data-recurrence-target=unit]").textContent.trim()];'
        );
        self::assertTrue($state()[0]);

        $this->client->executeScript(
            'const s = document.getElementById("calendar_item_form_recurrence"); s.value = "WEEKLY"; s.dispatchEvent(new Event("change", {bubbles: true}));'
        );
        self::assertSame([false, 'Woche'], $state());

        $this->client->executeScript(
            'const i = document.getElementById("calendar_item_form_recurrenceInterval"); i.value = "3"; i.dispatchEvent(new Event("input", {bubbles: true}));'
        );
        self::assertSame([false, 'Wochen'], $state());

        $this->client->executeScript(
            'const s = document.getElementById("calendar_item_form_recurrence"); s.value = "YEARLY"; s.dispatchEvent(new Event("change", {bubbles: true}));'
        );
        self::assertSame([false, 'Jahre'], $state());
    }
}
