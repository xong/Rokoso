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
     * Adds the user to the invited organization and consumes the invitation. Former members and guests
     * get the invited role and a new open term.
     */
    public function join(Invitation $invitation, User $user): Organization
    {
        $organization = $invitation->getOrganization();
        $membership = $organization->findMembership($user);
        if (null === $membership) {
            $organization->addMember($user, $invitation->getRole());
        } elseif (!$membership->isFull()) {
            $membership->setRole($invitation->getRole())->setTermEndsOn(null);
        }
        $this->em->remove($invitation);
        $this->em->flush();

        return $organization;
    }
}
