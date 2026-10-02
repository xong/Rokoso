<?php

declare(strict_types=1);

namespace App\Enum;

enum CalendarItemType: string
{
    case Event = 'event';
    case Task = 'task';

    public function label(): string
    {
        return 'calendar.type.'.$this->value;
    }
}
