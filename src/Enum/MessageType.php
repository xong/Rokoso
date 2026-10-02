<?php

declare(strict_types=1);

namespace App\Enum;

enum MessageType: string
{
    case Email = 'email';
    case Internal = 'internal';
}
