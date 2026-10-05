<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Reason for a notification; the text is "notification.type.<value>" with %actor% and %subject%.
 */
enum NotificationType: string
{
    case Assigned = 'assigned';
    case Mentioned = 'mentioned';
    case Comment = 'comment';
    case Topic = 'topic';
    case Post = 'post';
    case Due = 'due';
    case Poll = 'poll';
    case Contact = 'contact';
    case Confidential = 'confidential';
    case Invitation = 'invitation';

    public function label(): string
    {
        return 'notification.type.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assigned => 'lucide:user-check',
            self::Mentioned => 'lucide:at-sign',
            self::Comment => 'lucide:message-circle',
            self::Topic, self::Post => 'lucide:messages-square',
            self::Due => 'lucide:alarm-clock',
            self::Poll => 'lucide:vote',
            self::Contact => 'lucide:inbox',
            self::Confidential => 'lucide:lock',
            self::Invitation => 'lucide:user-plus',
        };
    }
}
