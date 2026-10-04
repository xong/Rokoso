<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Poll;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Polls: members of the organization view; eligible members vote while the poll runs (secret polls only once);
 * the creator and organization admins manage.
 *
 * @extends Voter<string, Poll>
 */
final class PollVoter extends Voter
{
    public const string VIEW = 'POLL_VIEW';
    public const string VOTE = 'POLL_VOTE';
    public const string MANAGE = 'POLL_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::VOTE, self::MANAGE], true) && $subject instanceof Poll;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $subject->getOrganization()->getMembership($user);
        if (null === $membership || !$subject->getOrganization()->hasFeature(Feature::Polls)) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::VOTE => !$subject->isEnded() && $subject->isEligible($user) && !($subject->isSecret() && $subject->hasVoted($user)),
            default => $membership->isAdmin() || $subject->getCreatedBy() === $user,
        };
    }
}
