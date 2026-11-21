<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\MailAccount;
use App\Entity\Organization;
use App\Entity\User;
use App\Mail\OrganizationMailer;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final readonly class OrganizationMailExtension
{
    public function __construct(private OrganizationMailer $mailer, private Security $security)
    {
    }

    /** Mailbox that sends invitations of the organization on behalf of the current user, or null */
    #[AsTwigFunction('org_mailbox')]
    public function mailbox(?Organization $organization): ?MailAccount
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->mailer->account($organization, $user) : null;
    }
}
