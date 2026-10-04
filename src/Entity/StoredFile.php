<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StoredFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /** @var Collection<int, FileVersion> */
    #[ORM\OneToMany(targetEntity: FileVersion::class, mappedBy: 'file', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $versions;

    /** @var Collection<int, FileShare> */
    #[ORM\OneToMany(targetEntity: FileShare::class, mappedBy: 'file')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $shares;

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
        $this->versions = new ArrayCollection();
        $this->shares = new ArrayCollection();
    }

    /**
     * Keeps the current content as a version and takes over the new one.
     */
    public function replaceWith(string $filename, string $mimeType, int $size, string $storagePath, ?User $uploadedBy): FileVersion
    {
        $version = new FileVersion($this, $this->filename, $this->mimeType, $this->size, $this->storagePath, $this->uploadedBy, $this->createdAt);
        $this->versions->add($version);
        $this->filename = mb_substr($filename, 0, 255);
        $this->mimeType = mb_substr($mimeType, 0, 120);
        $this->size = $size;
        $this->storagePath = $storagePath;
        $this->uploadedBy = $uploadedBy;
        $this->createdAt = new \DateTimeImmutable();

        return $version;
    }

    /**
     * @return Collection<int, FileVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    /**
     * @return list<string> storage paths of the current content and all versions
     */
    public function getAllStoragePaths(): array
    {
        return [$this->storagePath, ...array_values($this->versions->map(static fn (FileVersion $v): string => $v->getStoragePath())->toArray())];
    }

    /**
     * @return Collection<int, FileShare>
     */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function isPdf(): bool
    {
        return 'application/pdf' === $this->mimeType;
    }

    public function isText(): bool
    {
        return \in_array($this->mimeType, ['text/plain', 'text/csv', 'text/markdown', 'text/x-markdown'], true);
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
