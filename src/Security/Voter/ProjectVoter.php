<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Projects of an organization: members (and guests of the project) view, administrators manage.
 * Projects without organization: only their creator.
 *
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string MANAGE = 'PROJECT_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $organization = $subject->getOrganization();
        if (null === $organization) {
            return $subject->getCreatedBy() === $user;
        }
        if (!$organization->hasFeature(Feature::Projects)) {
            return false;
        }

        return self::VIEW === $attribute ? $organization->canSeeProject($user, $subject) : ($organization->getMembership($user)?->isAdmin() ?? false);
    }
}
