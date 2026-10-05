<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ConfidentialCase;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Confidential conversations: only the confidants of the organization (not administrators as such).
 *
 * @extends Voter<string, ConfidentialCase>
 */
final class ConfidentialCaseVoter extends Voter
{
    public const string VIEW = 'CONFIDENTIAL_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof ConfidentialCase;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getOrganization()->isConfidant($user);
    }
}
