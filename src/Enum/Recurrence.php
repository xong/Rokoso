<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Repetition of a calendar item; the monthly variants take the day from the start date
 * (same day of the month, same weekday of the month like "2nd Thursday", last weekday of the month).
 */
enum Recurrence: string
{
    case None = 'none';
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case MonthlyWeekday = 'MONTHLY_WEEKDAY';
    case MonthlyLast = 'MONTHLY_LAST';
    case Yearly = 'YEARLY';

    public function label(): string
    {
        return 'calendar.recurrence.'.strtolower($this->value);
    }

    /** RFC 5545 frequency */
    public function freq(): string
    {
        return match ($this) {
            self::MonthlyWeekday, self::MonthlyLast => self::Monthly->value,
            default => $this->value,
        };
    }
}
