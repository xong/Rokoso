<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\MailAccount;
use App\Entity\User;
use App\Enum\Feature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * MAIL_ACCOUNT_USE: organization members (read, send) · MAIL_ACCOUNT_MANAGE: organization administrators.
 *
 * @extends Voter<string, MailAccount>
 */
final class MailAccountVoter extends Voter
{
    public const string USE = 'MAIL_ACCOUNT_USE';
    public const string MANAGE = 'MAIL_ACCOUNT_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::USE, self::MANAGE], true) && $subject instanceof MailAccount;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if (!$subject->getOrganization()->hasFeature(Feature::Mail)) {
            return false;
        }
        $membership = $subject->getOrganization()->getMembership($user);

        return self::USE === $attribute ? null !== $membership : ($membership?->isAdmin() ?? false);
    }
}
