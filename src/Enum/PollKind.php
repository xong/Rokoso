<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Decision: yes/no/abstain; choice: free options (single or multiple); schedule: date finding (yes/maybe/no per slot).
 */
enum PollKind: string
{
    case Decision = 'decision';
    case Choice = 'choice';
    case Schedule = 'schedule';

    public function label(): string
    {
        return 'poll.kind.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Decision => 'lucide:vote',
            self::Choice => 'lucide:list-checks',
            self::Schedule => 'lucide:calendar-search',
        };
    }
}
