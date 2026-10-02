<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarItem;

/**
 * One concrete appearance of a (possibly recurring) calendar item.
 */
final readonly class Occurrence
{
    public function __construct(
        public CalendarItem $item,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }

    public function coversDay(\DateTimeImmutable $day): bool
    {
        $dayStart = $day->setTime(0, 0);

        return $this->start < $dayStart->modify('+1 day') && $this->end >= $dayStart;
    }

    /** Spans several days or is all-day: shown in the all-day row. */
    public function isAllDayLike(): bool
    {
        return $this->item->isAllDay() || $this->start->format('Y-m-d') !== $this->end->format('Y-m-d');
    }

    /** Minutes since midnight of the given day (clamped), for the time grid. */
    public function startMinute(\DateTimeImmutable $day): int
    {
        $dayStart = $day->setTime(0, 0);

        return $this->start < $dayStart ? 0 : (int) (($this->start->getTimestamp() - $dayStart->getTimestamp()) / 60);
    }

    public function endMinute(\DateTimeImmutable $day): int
    {
        $dayEnd = $day->setTime(0, 0)->modify('+1 day');
        $end = $this->end > $dayEnd ? $dayEnd : $this->end;

        return max($this->startMinute($day) + 30, (int) (($end->getTimestamp() - $day->setTime(0, 0)->getTimestamp()) / 60));
    }
}
