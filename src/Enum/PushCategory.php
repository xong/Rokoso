<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a person can receive as push message (profile → notifications); several notification types share one.
 */
enum PushCategory: string
{
    case Mentioned = 'mentioned';
    case Message = 'message';
    case Assigned = 'assigned';
    case Due = 'due';
    case Comment = 'comment';
    case Forum = 'forum';
    case Poll = 'poll';
    case Meeting = 'meeting';
    case Contact = 'contact';
    case Confidential = 'confidential';
    case Invitation = 'invitation';
    case Mail = 'mail';

    /** New mail in a shared inbox is frequent, so it has to be switched on. */
    public function isDefault(): bool
    {
        return self::Mail !== $this;
    }

    public function label(): string
    {
        return 'notification.push.category.'.$this->value;
    }

    public function help(): string
    {
        return 'notification.push.category.'.$this->value.'_help';
    }
}
