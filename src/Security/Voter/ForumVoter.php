<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumUpload;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Forum: members of the organization read and write (new areas, topics, posts); guests only in the areas
 * and topics of the projects released to them.
 * FORUM_MANAGE (edit/delete) is reserved for the author/creator and the organization's administrators;
 * editing a post's text is reserved for its author.
 *
 * @extends Voter<string, ForumBoard|ForumTopic|ForumPost|ForumUpload>
 */
final class ForumVoter extends Voter
{
    public const string VIEW = 'FORUM_VIEW';
    public const string MANAGE = 'FORUM_MANAGE';
    public const string EDIT_POST = 'FORUM_EDIT_POST';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE, self::EDIT_POST], true)
            && ($subject instanceof ForumBoard || $subject instanceof ForumTopic || $subject instanceof ForumPost || $subject instanceof ForumUpload);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $visible = match (true) {
            $subject instanceof ForumPost => $subject->getTopic()->isVisibleTo($user),
            $subject instanceof ForumUpload => $subject->getBoard()->isVisibleTo($user),
            default => $subject->isVisibleTo($user),
        };
        $organization = $subject instanceof ForumPost ? $subject->getTopic()->getOrganization() : $subject->getOrganization();
        if (!$visible || !$organization->hasFeature(Feature::Forum)) {
            return false;
        }
        $isAdmin = $organization->getMembership($user)?->isAdmin() ?? false;

        $owner = match (true) {
            $subject instanceof ForumBoard => $subject->getCreatedBy(),
            $subject instanceof ForumTopic => $subject->getCreatedBy(),
            $subject instanceof ForumPost => $subject->getAuthor(),
            $subject instanceof ForumUpload => $subject->getUploadedBy(),
        };

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT_POST => $owner === $user,
            default => $owner === $user || $isAdmin,
        };
    }
}
