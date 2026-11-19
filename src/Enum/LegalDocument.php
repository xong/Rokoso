<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Texts of the installation (not of an organization), maintained by platform admins: the optional welcome text on the
 * login page and the legal pages (privacy policy, imprint).
 */
enum LegalDocument: string
{
    case Welcome = 'welcome';
    case Privacy = 'privacy';
    case Imprint = 'imprint';

    public function label(): string
    {
        return 'legal.'.$this->value.'.title';
    }

    /** Own public page (/privacy, /imprint), linked when written; the welcome text only shows on the login page */
    public function isPage(): bool
    {
        return self::Welcome !== $this;
    }
}
