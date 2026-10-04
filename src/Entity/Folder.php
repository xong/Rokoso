<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FolderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Folder in the shared file area. Top-level folders belong to an organization (optionally a project);
 * subfolders inherit the organization and thus the visibility.
 */
#[ORM\Entity(repositoryClass: FolderRepository::class)]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Assert\Regex(pattern: '#[/\\\\]#', match: false, message: 'folder.invalid_name')]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** @var Collection<int, Folder> */
    #[ORM\OneToMany(targetEntity: Folder::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, StoredFile> */
    #[ORM\OneToMany(targetEntity: StoredFile::class, mappedBy: 'folder')]
    #[ORM\OrderBy(['filename' => 'ASC'])]
    private Collection $files;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Subfolders always belong to the organization of their parent folder.
     */
    public function __construct(
        Organization $organization,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy,
        #[ORM\ManyToOne(inversedBy: 'children')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Folder $parent = null,
    ) {
        $this->children = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->organization = $organization;
        if (null !== $parent) {
            $this->organization = $parent->getOrganization();
            $this->project = $parent->getProject();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getParent(): ?Folder
    {
        return $this->parent;
    }

    /**
     * Path from the top-level folder down to this one.
     *
     * @return list<Folder>
     */
    public function getPath(): array
    {
        $path = [];
        for ($folder = $this; null !== $folder; $folder = $folder->getParent()) {
            array_unshift($path, $folder);
        }

        return $path;
    }

    public function isAncestorOf(Folder $folder): bool
    {
        return \in_array($this, $folder->getPath(), true) && $folder !== $this;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    /**
     * Full members see all folders; guests the folders assigned (directly or via a parent folder) to their projects.
     */
    public function isVisibleTo(User $user): bool
    {
        $membership = $this->organization->findMembership($user);

        return null !== $membership && ($membership->isFull()
            || array_any($this->getPath(), static fn (Folder $f): bool => $membership->grantsGuestAccessTo($f->getProject())));
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
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

    /** @return Collection<int, Folder> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /** @return Collection<int, StoredFile> */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Assert\Callback]
    public function validateProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->project && $this->project->getOrganization() !== $this->organization) {
            $context->buildViolation('folder.project_mismatch')->atPath('project')->addViolation();
        }
    }
}
