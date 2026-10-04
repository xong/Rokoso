<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AgendaItem;
use App\Entity\Meeting;
use App\Entity\Resolution;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Meetings, agenda items and resolutions: members of the organization view (and may propose agenda items);
 * organization admins, the creator and the minute taker manage.
 *
 * @extends Voter<string, Meeting|AgendaItem|Resolution>
 */
final class MeetingVoter extends Voter
{
    public const string VIEW = 'MEETING_VIEW';
    public const string MANAGE = 'MEETING_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true)
            && ($subject instanceof Meeting || $subject instanceof AgendaItem || $subject instanceof Resolution);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $meeting = match (true) {
            $subject instanceof Meeting => $subject,
            $subject instanceof AgendaItem => $subject->getMeeting(),
            default => $subject->getMeeting(),
        };
        $organization = $subject->getOrganization();
        $membership = $organization->getMembership($user);
        if (null === $membership || !$organization->hasFeature($subject instanceof Resolution ? Feature::Resolutions : Feature::Meetings)) {
            return false;
        }
        if (self::VIEW === $attribute || $membership->isAdmin()) {
            return true;
        }
        if (null === $meeting) {
            // Standalone resolution: its author
            return $subject instanceof Resolution && $subject->getCreatedBy() === $user;
        }

        return $meeting->getCreatedBy() === $user || $meeting->getMinuteTaker() === $user;
    }
}
