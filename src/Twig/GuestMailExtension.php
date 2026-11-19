<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\MailAccount;
use App\Entity\Organization;
use App\Entity\User;
use App\Mail\GuestMailer;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final readonly class GuestMailExtension
{
    public function __construct(private GuestMailer $guests, private Security $security)
    {
    }

    /** Mailbox that invites guests of the organization on behalf of the current user, or null */
    #[AsTwigFunction('guest_mailbox')]
    public function mailbox(?Organization $organization): ?MailAccount
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->guests->account($organization, $user) : null;
    }
}
