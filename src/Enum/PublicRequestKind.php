<?php

declare(strict_types=1);

namespace App\Enum;

enum PublicRequestKind: string
{
    case Contact = 'contact';
    case Signup = 'signup';
    case Subscribe = 'subscribe';
}
