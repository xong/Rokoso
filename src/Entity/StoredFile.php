<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StoredFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Uploaded file in a folder. Content lives in var/storage/files (see FileStorage).
 */
#[ORM\Entity(repositoryClass: StoredFileRepository::class)]
class StoredFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'files')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Folder $folder,
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
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
        $this->filename = mb_substr($filename, 0, 255);
        $this->mimeType = mb_substr($mimeType, 0, 120);
        $this->project = $folder->getProject();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function setFolder(Folder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->folder->getOrganization();
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->folder->isVisibleTo($user) || ($this->getOrganization()->findMembership($user)?->grantsGuestAccessTo($this->project) ?? false);
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = mb_substr(trim((string) $filename), 0, 255);

        return $this;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
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
        return \in_array($this->mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    #[Assert\Callback]
    public function validateProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->project && $this->project->getOrganization() !== $this->folder->getOrganization()) {
            $context->buildViolation('folder.project_mismatch')->atPath('project')->addViolation();
        }
    }
}
