<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Contact;
use App\Service\ContactDirectory;
use Twig\Attribute\AsTwigFunction;

final readonly class ContactExtension
{
    public function __construct(private ContactDirectory $directory)
    {
    }

    #[AsTwigFunction('contact_for')]
    public function contactFor(string $email): ?Contact
    {
        return $this->directory->find($email);
    }
}
