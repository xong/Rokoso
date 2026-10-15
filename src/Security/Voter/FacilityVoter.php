<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Facility;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Facilities of an organization: all full members view and edit (like its contacts).
 *
 * @extends Voter<string, Facility>
 */
final class FacilityVoter extends Voter
{
    public const string EDIT = 'FACILITY_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof Facility;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $organization = $subject->getOrganization();

        return $user instanceof User
            && $organization->hasFeature(Feature::Facilities)
            && $organization->hasFeature(Feature::Contacts)
            && null !== $organization->getMembership($user);
    }
}
