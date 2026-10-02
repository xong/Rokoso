<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * ORG_VIEW: members · ORG_MANAGE: administrators of the organization.
 *
 * @extends Voter<string, Organization>
 */
final class OrganizationVoter extends Voter
{
    public const string VIEW = 'ORG_VIEW';
    public const string MANAGE = 'ORG_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $subject->getMembership($user);

        return match ($attribute) {
            self::VIEW => null !== $membership,
            default => $membership?->isAdmin() ?? false,
        };
    }
}
