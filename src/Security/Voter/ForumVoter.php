<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumUpload;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Forum: members of the organization read and write (new areas, topics, posts).
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
        $organization = match (true) {
            $subject instanceof ForumPost => $subject->getTopic()->getOrganization(),
            default => $subject->getOrganization(),
        };
        $membership = $organization->getMembership($user);
        if (null === $membership) {
            return false;
        }

        $owner = match (true) {
            $subject instanceof ForumBoard => $subject->getCreatedBy(),
            $subject instanceof ForumTopic => $subject->getCreatedBy(),
            $subject instanceof ForumPost => $subject->getAuthor(),
            $subject instanceof ForumUpload => $subject->getUploadedBy(),
        };

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT_POST => $owner === $user,
            default => $owner === $user || $membership->isAdmin(),
        };
    }
}
