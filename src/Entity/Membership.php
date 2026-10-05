<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrganizationRole;
use App\Repository\MembershipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Membership of a person in an organization. Full access only for members and administrators whose
 * term has not ended; guests only see the projects released to them; ended terms are former members.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\UniqueConstraint(columns: ['organization_id', 'user_id'])]
class Membership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $votingRight = true;

    /** Confidant: reads and answers the anonymous confidential contact of the organization. */
    #[ORM\Column(options: ['default' => false])]
    private bool $confidant = false;

    /** Function in the organization (chair, treasurer, secretary …). */
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $position = null;

    /** Last day of the term; afterwards the person is a former member without access. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $termEndsOn = null;

    /** @var Collection<int, Project> projects released to a guest */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'membership_guest_project')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $guestProjects;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'memberships')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 20, enumType: OrganizationRole::class)]
        private OrganizationRole $role = OrganizationRole::Member,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->guestProjects = new ArrayCollection();
    }

    /**
     * DQL condition: the membership (alias) grants full access.
     */
    public static function fullDql(string $alias): string
    {
        return \sprintf("(%1\$s.role <> 'guest' AND (%1\$s.termEndsOn IS NULL OR %1\$s.termEndsOn >= CURRENT_DATE()))", $alias);
    }

    /**
     * DQL condition: the viewer (parameter name) is an active guest with access to the project (DQL expression).
     */
    public static function guestDql(string $projectExpr, string $viewerParam = 'viewer', string $alias = 'gx'): string
    {
        return \sprintf("EXISTS (SELECT 1 FROM %1\$s %2\$s JOIN %2\$s.guestProjects %2\$sp WHERE %2\$s.user = :%3\$s AND %2\$sp = %4\$s AND %2\$s.role = 'guest' AND (%2\$s.termEndsOn IS NULL OR %2\$s.termEndsOn >= CURRENT_DATE()))",
            self::class, $alias, $viewerParam, $projectExpr);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function setRole(OrganizationRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isAdmin(): bool
    {
        return OrganizationRole::Admin === $this->role && $this->isActive();
    }

    public function isGuest(): bool
    {
        return OrganizationRole::Guest === $this->role;
    }

    /** The term has not ended. */
    public function isActive(): bool
    {
        return null === $this->termEndsOn || $this->termEndsOn >= new \DateTimeImmutable('today');
    }

    /** Active member or administrator (neither guest nor former). */
    public function isFull(): bool
    {
        return !$this->isGuest() && $this->isActive();
    }

    public function isFormer(): bool
    {
        return !$this->isActive();
    }

    public function hasVotingRight(): bool
    {
        return $this->votingRight;
    }

    public function setVotingRight(bool $votingRight): static
    {
        $this->votingRight = $votingRight;

        return $this;
    }

    /** Only counts together with full access ({@see Organization::getConfidants()}). */
    public function isConfidant(): bool
    {
        return $this->confidant;
    }

    public function setConfidant(bool $confidant): static
    {
        $this->confidant = $confidant;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $position = trim((string) $position);
        $this->position = '' === $position ? null : $position;

        return $this;
    }

    public function getTermEndsOn(): ?\DateTimeImmutable
    {
        return $this->termEndsOn;
    }

    public function setTermEndsOn(?\DateTimeImmutable $termEndsOn): static
    {
        $this->termEndsOn = $termEndsOn;

        return $this;
    }

    /** @return Collection<int, Project> */
    public function getGuestProjects(): Collection
    {
        return $this->guestProjects;
    }

    public function addGuestProject(Project $project): static
    {
        if (!$this->guestProjects->contains($project)) {
            $this->guestProjects->add($project);
        }

        return $this;
    }

    public function removeGuestProject(Project $project): static
    {
        $this->guestProjects->removeElement($project);

        return $this;
    }

    /** Active guest with access to the project. */
    public function grantsGuestAccessTo(?Project $project): bool
    {
        return null !== $project && $this->isGuest() && $this->isActive() && $this->guestProjects->contains($project);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
