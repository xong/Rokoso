<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Folder;
use App\Entity\StoredFile;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Folders and files: members of the organization (guests: of their projects) view, upload and edit;
 * deleting is reserved for the creator and the organization's administrators.
 *
 * @extends Voter<string, Folder|StoredFile>
 */
final class FolderVoter extends Voter
{
    public const string VIEW = 'FOLDER_VIEW';
    public const string EDIT = 'FOLDER_EDIT';
    public const string DELETE = 'FOLDER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && ($subject instanceof Folder || $subject instanceof StoredFile);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if (!$subject->isVisibleTo($user)) {
            return false;
        }
        if (self::DELETE !== $attribute) {
            return true;
        }
        $owner = $subject instanceof StoredFile ? $subject->getUploadedBy() : $subject->getCreatedBy();

        return $owner === $user || ($subject->getOrganization()->getMembership($user)?->isAdmin() ?? false);
    }
}
