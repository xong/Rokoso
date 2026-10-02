<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Color scheme chosen in the profile; "system" follows the operating system (prefers-color-scheme).
 */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return 'user.theme.'.$this->value;
    }
}
