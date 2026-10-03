<?php

declare(strict_types=1);

namespace App\Enum;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Excused = 'excused';
    case Absent = 'absent';

    public function label(): string
    {
        return 'meeting.attendance.'.$this->value;
    }
}
