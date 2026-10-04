<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Entries of the message history (Verlauf).
 */
enum MessageEventType: string
{
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case Project = 'project';
    case Done = 'done';
    case Reopened = 'reopened';
    case Snoozed = 'snoozed';
    case Replied = 'replied';
    case Forwarded = 'forwarded';
    case Trashed = 'trashed';
    case Restored = 'restored';
    case Rule = 'rule';
    case SendFailed = 'send_failed';

    public function label(): string
    {
        return 'mail.event.'.$this->value;
    }
}
