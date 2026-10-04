<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrganizationRole;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $color = '#2563eb';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    /** Share of members with voting right that must be present for a quorum */
    #[ORM\Column(options: ['default' => 50])]
    #[Assert\Range(min: 1, max: 100)]
    private int $quorumPercent = 50;

    /** Minimum notice in days between invitation and meeting */
    #[ORM\Column(options: ['default' => 7])]
    #[Assert\Range(min: 0, max: 90)]
    private int $invitationDays = 7;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'organization', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $memberships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
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
        $this->description = $description;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = (string) $color;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getQuorumPercent(): int
    {
        return $this->quorumPercent;
    }

    public function setQuorumPercent(?int $quorumPercent): static
    {
        $this->quorumPercent = $quorumPercent ?? 50;

        return $this;
    }

    public function getInvitationDays(): int
    {
        return $this->invitationDays;
    }

    public function setInvitationDays(?int $invitationDays): static
    {
        $this->invitationDays = $invitationDays ?? 0;

        return $this;
    }

    /**
     * Members with voting right.
     *
     * @return list<User>
     */
    public function getVotingMembers(): array
    {
        return array_values(array_map(static fn (Membership $m): User => $m->getUser(),
            array_filter($this->memberships->toArray(), static fn (Membership $m): bool => $m->isFull() && $m->hasVotingRight())));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMember(User $user, OrganizationRole $role): Membership
    {
        $membership = new Membership($this, $user, $role);
        $this->memberships->add($membership);

        return $membership;
    }

    /**
     * The membership granting full access (active member or administrator); null for guests and former members.
     */
    public function getMembership(User $user): ?Membership
    {
        $membership = $this->findMembership($user);

        return null !== $membership && $membership->isFull() ? $membership : null;
    }

    /**
     * Any membership of the user, including guests and former members.
     */
    public function findMembership(User $user): ?Membership
    {
        return $this->memberships->findFirst(static fn (int $k, Membership $m): bool => $m->getUser() === $user);
    }

    /**
     * Full members see everything of the organization, active guests only the projects released to them.
     */
    public function canSeeProject(User $user, ?Project $project): bool
    {
        $membership = $this->findMembership($user);

        return null !== $membership && ($membership->isFull() || $membership->grantsGuestAccessTo($project));
    }

    public function isAdmin(User $user): bool
    {
        return $this->getMembership($user)?->isAdmin() ?? false;
    }

    public function countAdmins(): int
    {
        return $this->memberships->filter(static fn (Membership $m): bool => $m->isAdmin())->count();
    }

    /**
     * @return list<User>
     */
    public function getAdmins(): array
    {
        return array_values($this->memberships->filter(static fn (Membership $m): bool => $m->isAdmin())
            ->map(static fn (Membership $m): User => $m->getUser())->toArray());
    }

    /**
     * Active members and administrators (without guests and former members).
     *
     * @return list<User>
     */
    public function getMembers(): array
    {
        return array_values($this->memberships->filter(static fn (Membership $m): bool => $m->isFull())
            ->map(static fn (Membership $m): User => $m->getUser())->toArray());
    }

    /**
     * Full members plus the active guests of the project.
     *
     * @return list<User>
     */
    public function getProjectParticipants(?Project $project): array
    {
        return array_values($this->memberships->filter(static fn (Membership $m): bool => $m->isFull() || $m->grantsGuestAccessTo($project))
            ->map(static fn (Membership $m): User => $m->getUser())->toArray());
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
