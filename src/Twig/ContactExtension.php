<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Contact;
use App\Entity\PublicSettings;
use App\Repository\ContactGroupRepository;
use App\Service\ContactDirectory;
use Twig\Attribute\AsTwigFunction;

final readonly class ContactExtension
{
    public function __construct(private ContactDirectory $directory, private ContactGroupRepository $groups)
    {
    }

    #[AsTwigFunction('contact_for')]
    public function contactFor(string $email): ?Contact
    {
        return $this->directory->find($email);
    }

    /** The public page offers the subscription and has at least one group to subscribe to */
    #[AsTwigFunction('public_subscribable')]
    public function publicSubscribable(PublicSettings $settings): bool
    {
        return $settings->offers('subscribe') && [] !== $this->groups->findPublicSubscribe($settings->getOrganization());
    }
}
