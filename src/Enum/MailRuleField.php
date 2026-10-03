<?php

declare(strict_types=1);

namespace App\Enum;

enum MailRuleField: string
{
    case From = 'from';
    case Subject = 'subject';
    case To = 'to';
    case Body = 'body';

    public function label(): string
    {
        return 'mail_rule.field.'.$this->value;
    }
}
