<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\ForumTopic;
use App\Entity\Message;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Repository\ShelfItemRepository;
use App\Security\Voter\FolderVoter;
use App\Security\Voter\ForumVoter;
use App\Security\Voter\MessageVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Personal shelf ("Merken"): references to files, attachments, messages and forum topics.
 * Only entries whose target the user may (still) see are offered, e.g. after leaving an organization.
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

    /**
     * Entries that are files (to attach them to an email).
     *
     * @return list<ShelfItem>
     */
    public function files(User $owner): array
    {
        return array_values(array_filter($this->items($owner), static fn (ShelfItem $i): bool => $i->isFile()));
    }

    public function isAccessible(ShelfItem $item): bool
    {
        $target = $item->getTarget();

        return match (true) {
            $target instanceof StoredFile => $this->security->isGranted(FolderVoter::VIEW, $target),
            $target instanceof Attachment => $this->security->isGranted(MessageVoter::VIEW, $target->getMessage()),
            $target instanceof Message => $this->security->isGranted(MessageVoter::VIEW, $target),
            $target instanceof ForumTopic => $this->security->isGranted(ForumVoter::VIEW, $target),
        };
    }

    public function find(User $owner, StoredFile|Attachment|Message|ForumTopic $target): ?ShelfItem
    {
        return $this->items->findFor($owner, $target);
    }

    public function read(ShelfItem $item): string
    {
        $target = $item->getFileTarget() ?? throw new \LogicException('Shelf item is no file');
        $path = $target instanceof StoredFile
            ? $this->files->absolutePath($target->getStoragePath())
            : $this->attachments->absolutePath($target->getStoragePath());

        return (string) file_get_contents($path);
    }
}
