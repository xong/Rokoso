<?php

declare(strict_types=1);

namespace App\Enum;

enum MeetingStatus: string
{
    case Planned = 'planned';
    case Invited = 'invited';
    case Held = 'held';
    case Approved = 'approved';

    public function label(): string
    {
        return 'meeting.status.'.$this->value;
    }
}
