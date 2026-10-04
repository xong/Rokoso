<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Contact;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contacts of an organization: all members view and edit. Without organization: only the creator.
 *
 * @extends Voter<string, Contact>
 */
final class ContactVoter extends Voter
{
    public const string EDIT = 'CONTACT_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof Contact;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $organization = $subject->getOrganization();
        if (null !== $organization && !$organization->hasFeature(Feature::Contacts)) {
            return false;
        }

        return null === $organization ? $subject->getCreatedBy() === $user : null !== $organization->getMembership($user);
    }
}
