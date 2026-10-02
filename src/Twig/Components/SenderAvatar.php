<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\ContactDirectory;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Avatar for an email address: contact photo if known, otherwise initials (of the contact name if known).
 */
#[AsTwigComponent]
final class SenderAvatar
{
    public string $address = '';
    public string $name = '';
    public string $size = 'size-9 text-xs';

    public function __construct(private readonly ContactDirectory $directory)
    {
    }

    public function getImage(): ?string
    {
        return $this->directory->find($this->address)?->getPhoto();
    }

    public function getLabel(): string
    {
        $contact = $this->directory->find($this->address);

        return null !== $contact ? $contact->getDisplayName() : ('' !== $this->name ? $this->name : $this->address);
    }
}
