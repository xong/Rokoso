<?php

declare(strict_types=1);

namespace App\Enum;

enum MailEncryption: string
{
    case Ssl = 'ssl';
    case StartTls = 'starttls';
    case None = 'none';

    public function label(): string
    {
        return 'mail_account.encryption.'.$this->value;
    }
}
