<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a person wants to receive notifications by email.
 */
enum NotificationEmail: string
{
    case Instant = 'instant';
    case Daily = 'daily';
    case Off = 'off';

    public function label(): string
    {
        return 'notification.email.'.$this->value;
    }
}
