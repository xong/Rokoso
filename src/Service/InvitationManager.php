<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InvitationManager
{
    public function __construct(
        private InvitationRepository $invitations,
        private EntityManagerInterface $em,
    ) {
    }

    public function findValid(?string $token): ?Invitation
    {
        if (null === $token || '' === $token) {
            return null;
        }
        $invitation = $this->invitations->findOneBy(['token' => $token]);

        return null === $invitation || $invitation->isExpired() ? null : $invitation;
    }

    /**
     * Adds the user to the invited organization and consumes the invitation.
     */
    public function join(Invitation $invitation, User $user): Organization
    {
        $organization = $invitation->getOrganization();
        if (null === $organization->getMembership($user)) {
            $organization->addMember($user, $invitation->getRole());
        }
        $this->em->remove($invitation);
        $this->em->flush();

        return $organization;
    }
}
