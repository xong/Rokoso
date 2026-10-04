<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Survey;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Surveys: members of the organization view results; the creator and organization admins manage.
 *
 * @extends Voter<string, Survey>
 */
final class SurveyVoter extends Voter
{
    public const string VIEW = 'SURVEY_VIEW';
    public const string MANAGE = 'SURVEY_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Survey;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $subject->getOrganization()->getMembership($user);
        if (null === $membership) {
            return false;
        }

        return self::VIEW === $attribute || $membership->isAdmin() || $subject->getCreatedBy() === $user;
    }
}
