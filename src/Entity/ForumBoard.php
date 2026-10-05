<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForumBoardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Forum area. Top-level areas belong to an organization (optionally a project);
 * sub-areas inherit the organization and thus the visibility.
 */
#[ORM\Entity(repositoryClass: ForumBoardRepository::class)]
class ForumBoard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** @var Collection<int, ForumBoard> */
    #[ORM\OneToMany(targetEntity: ForumBoard::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    private Collection $children;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Sub-areas always belong to the organization of their parent area.
     */
    public function __construct(
        Organization $organization,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy,
        #[ORM\ManyToOne(inversedBy: 'children')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?ForumBoard $parent = null,
    ) {
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->organization = $parent?->getOrganization() ?? $organization;
        $this->project = $parent?->getProject();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = '' === trim((string) $description) ? null : trim((string) $description);

        return $this;
    }

    public function getParent(): ?ForumBoard
    {
        return $this->parent;
    }

    /**
     * Path from the top-level area down to this one.
     *
     * @return list<ForumBoard>
     */
    public function getPath(): array
    {
        $path = [];
        for ($board = $this; null !== $board; $board = $board->getParent()) {
            array_unshift($path, $board);
        }

        return $path;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    /**
     * Full members see all areas; guests the areas assigned (directly or via a parent area) to their projects.
     */
    public function isVisibleTo(User $user): bool
    {
        $membership = $this->organization->findMembership($user);

        return null !== $membership && ($membership->isFull()
            || array_any($this->getPath(), static fn (ForumBoard $b): bool => $membership->grantsGuestAccessTo($b->getProject())));
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

    /** @return Collection<int, ForumBoard> */
    public function getChildren(): Collection
    {
        return $this->children;
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
