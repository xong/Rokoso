<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Seat of a person at a facility: elected representative and deputy (e.g. of a Kita board).
 */
enum FacilityRole: string
{
    case Representative = 'representative';
    case Deputy = 'deputy';

    public function label(): string
    {
        return 'facility.role.'.$this->value;
    }
}
