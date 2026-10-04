<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Earlier content of a shared file, kept when a new version is uploaded.
 */
#[ORM\Entity]
class FileVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'versions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private StoredFile $file,
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
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): StoredFile
    {
        return $this->file;
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
}
