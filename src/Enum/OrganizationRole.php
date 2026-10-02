<?php

declare(strict_types=1);

namespace App\Enum;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return 'organization.role.'.$this->value;
    }
}
