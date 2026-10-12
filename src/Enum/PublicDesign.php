<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Layout of the public page: classic (header bar, cards), banner (colored header area) or plain (centered, no boxes).
 */
enum PublicDesign: string
{
    case Classic = 'classic';
    case Banner = 'banner';
    case Plain = 'plain';

    public function label(): string
    {
        return 'public.design.'.$this->value;
    }
}
