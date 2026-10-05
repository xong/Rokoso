<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\CalendarItem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class CalendarExtension
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** Repetition in words, e.g. "Monatlich am 2. Donnerstag" or "Alle 2 Wochen am Donnerstag" */
    #[AsTwigFunction('recurrence_label')]
    public function recurrenceLabel(CalendarItem $item): string
    {
        $date = $item->getDate();

        return $this->translator->trans('calendar.recurrence.show.'.strtolower($item->getRecurrence()->value), [
            '%count%' => $item->getRecurrenceInterval(),
            '%day%' => $date->format('j'),
            '%nth%' => $item->getWeekOfMonth(),
            '%weekday%' => $this->translator->trans('calendar.weekdays.'.$date->format('w')),
        ]);
    }
}
