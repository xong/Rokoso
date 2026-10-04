<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Inbox = received mail, Sent = sent from Koopio. Internal messages are always "inbox";
 * for their author they show up under "sent".
 */
enum MessageFolder: string
{
    case Inbox = 'inbox';
    case Sent = 'sent';
}
