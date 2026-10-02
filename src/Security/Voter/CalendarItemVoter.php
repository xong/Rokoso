<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CalendarItem;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Items of an organization: all members view and edit. Without organization: only the creator.
 *
 * @extends Voter<string, CalendarItem>
 */
final class CalendarItemVoter extends Voter
{
    public const string EDIT = 'CALENDAR_ITEM_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof CalendarItem;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $organization = $subject->getOrganization();

        return null === $organization ? $subject->getCreatedBy() === $user : null !== $organization->getMembership($user);
    }
}
