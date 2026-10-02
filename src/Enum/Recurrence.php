<?php

declare(strict_types=1);

namespace App\Enum;

enum Recurrence: string
{
    case None = 'none';
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Yearly = 'YEARLY';

    public function label(): string
    {
        return 'calendar.recurrence.'.strtolower($this->value);
    }
}
