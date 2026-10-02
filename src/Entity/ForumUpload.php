<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * File uploaded to the forum: an image embedded in a post (uploaded while writing, no post yet)
 * or an attachment of a post. Visibility follows the area.
 */
#[ORM\Entity]
class ForumUpload
{
    /** Images that may be shown inline (no SVG: could contain scripts) */
    public const array INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ForumPost $post = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ForumBoard $board,
        #[ORM\Column(length: 255)]
        private string $filename,
        #[ORM\Column(length: 120)]
        private string $mimeType,
        #[ORM\Column]
        private int $size,
        #[ORM\Column(length: 255)]
        private string $storagePath,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $uploadedBy,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoard(): ForumBoard
    {
        return $this->board;
    }

    public function setBoard(ForumBoard $board): static
    {
        $this->board = $board;

        return $this;
    }

    public function getPost(): ?ForumPost
    {
        return $this->post;
    }

    public function setPost(?ForumPost $post): static
    {
        $this->post = $post;
        $post?->getAttachments()->add($this);

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->board->getOrganization();
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isImage(): bool
    {
        return \in_array($this->mimeType, self::INLINE_TYPES, true);
    }
}
