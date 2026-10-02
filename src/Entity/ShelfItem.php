<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShelfItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry in a user's personal shelf ("Persönliche Ablage"): a reference to a shared file
 * or an email attachment, no copy. Disappears together with its target.
 */
#[ORM\Entity(repositoryClass: ShelfItemRepository::class)]
#[ORM\UniqueConstraint(name: 'shelf_owner_file', columns: ['owner_id', 'file_id'])]
#[ORM\UniqueConstraint(name: 'shelf_owner_attachment', columns: ['owner_id', 'attachment_id'])]
class ShelfItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?StoredFile $file = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Attachment $attachment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function forFile(User $owner, StoredFile $file): self
    {
        $item = new self($owner);
        $item->file = $file;

        return $item;
    }

    public static function forAttachment(User $owner, Attachment $attachment): self
    {
        $item = new self($owner);
        $item->attachment = $attachment;

        return $item;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getFile(): ?StoredFile
    {
        return $this->file;
    }

    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function getTarget(): StoredFile|Attachment
    {
        return $this->file ?? $this->attachment ?? throw new \LogicException('Shelf item without target');
    }

    public function getFilename(): string
    {
        return $this->getTarget()->getFilename();
    }

    public function getMimeType(): string
    {
        return $this->getTarget()->getMimeType();
    }

    public function getSize(): int
    {
        return $this->getTarget()->getSize();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
