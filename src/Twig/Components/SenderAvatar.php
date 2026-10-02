<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Avatar for an email address: contact photo if known, otherwise initials.
 */
#[AsTwigComponent]
final class SenderAvatar
{
    public string $address = '';
    public string $name = '';
    public string $size = 'size-9 text-xs';
    public ?string $image = null;
}
