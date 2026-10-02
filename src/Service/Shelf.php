<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Repository\ShelfItemRepository;
use App\Security\Voter\FolderVoter;
use App\Security\Voter\MessageVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Personal shelf: references to files and attachments. Only entries whose target the
 * user may (still) see are offered, e.g. after leaving an organization.
 */
final readonly class Shelf
{
    public function __construct(
        private ShelfItemRepository $items,
        private Security $security,
        private FileStorage $files,
        private AttachmentStorage $attachments,
    ) {
    }

    /**
     * @return list<ShelfItem>
     */
    public function items(User $owner): array
    {
        return array_values(array_filter($this->items->forOwner($owner), $this->isAccessible(...)));
    }

    public function isAccessible(ShelfItem $item): bool
    {
        $target = $item->getTarget();

        return $target instanceof StoredFile
            ? $this->security->isGranted(FolderVoter::VIEW, $target)
            : $this->security->isGranted(MessageVoter::VIEW, $target->getMessage());
    }

    public function find(User $owner, StoredFile|Attachment $target): ?ShelfItem
    {
        return $this->items->findFor($owner, $target);
    }

    public function read(ShelfItem $item): string
    {
        $target = $item->getTarget();
        $path = $target instanceof StoredFile
            ? $this->files->absolutePath($target->getStoragePath())
            : $this->attachments->absolutePath($target->getStoragePath());

        return (string) file_get_contents($path);
    }
}
