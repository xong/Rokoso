<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Legal texts of the installation (not of an organization), maintained by platform admins.
 */
enum LegalDocument: string
{
    case Privacy = 'privacy';
    case Imprint = 'imprint';

    public function label(): string
    {
        return 'legal.'.$this->value.'.title';
    }
}
