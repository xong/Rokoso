<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Knowledge base: all (full) members of the organization read and edit; admins delete.
 *
 * @extends Voter<string, WikiPage>
 */
final class WikiVoter extends Voter
{
    public const string EDIT = 'WIKI_EDIT';
    public const string DELETE = 'WIKI_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof WikiPage;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $subject->getOrganization()->getMembership($user);

        return null !== $membership && $subject->getOrganization()->hasFeature(Feature::Wiki) && (self::EDIT === $attribute || $membership->isAdmin());
    }
}
