<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Feature;
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

    /** Days after which messages in the trash are deleted for good */
    #[ORM\Column(options: ['default' => 30])]
    #[Assert\Range(min: 1, max: 365)]
    private int $trashDays = 30;

    /** Years after which messages (inbox, sent, internal) are deleted; null = keep */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 30)]
    private ?int $messageRetentionYears = null;

    /** Years after which survey answers and event signups from the public page are deleted; null = keep */
    #[ORM\Column(nullable: true, options: ['default' => 2])]
    #[Assert\Range(min: 1, max: 30)]
    private ?int $submissionRetentionYears = 2;

    /** Months after the last message after which anonymous confidential conversations are deleted */
    #[ORM\Column(options: ['default' => 6])]
    #[Assert\Range(min: 1, max: 60)]
    private int $confidentialRetentionMonths = 6;

    /**
     * Switched-off areas (values of {@see Feature}); stored this way round so new areas start switched on.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $disabledFeatures = [];

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

    public function getTrashDays(): int
    {
        return $this->trashDays;
    }

    public function setTrashDays(?int $trashDays): static
    {
        $this->trashDays = $trashDays ?? 30;

        return $this;
    }

    public function getMessageRetentionYears(): ?int
    {
        return $this->messageRetentionYears;
    }

    public function setMessageRetentionYears(?int $years): static
    {
        $this->messageRetentionYears = $years;

        return $this;
    }

    public function getSubmissionRetentionYears(): ?int
    {
        return $this->submissionRetentionYears;
    }

    public function setSubmissionRetentionYears(?int $years): static
    {
        $this->submissionRetentionYears = $years;

        return $this;
    }

    public function getConfidentialRetentionMonths(): int
    {
        return $this->confidentialRetentionMonths;
    }

    public function setConfidentialRetentionMonths(int $months): static
    {
        $this->confidentialRetentionMonths = $months;

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

    public function hasFeature(Feature|string $feature): bool
    {
        return !\in_array($feature instanceof Feature ? $feature->value : $feature, $this->disabledFeatures, true);
    }

    /**
     * @return list<Feature>
     */
    public function getEnabledFeatures(): array
    {
        return array_values(array_filter(Feature::cases(), $this->hasFeature(...)));
    }

    /**
     * @param iterable<Feature> $features
     */
    public function setEnabledFeatures(iterable $features): static
    {
        $enabled = [];
        foreach ($features as $feature) {
            $enabled[] = $feature->value;
        }
        $this->disabledFeatures = array_values(array_diff(array_map(static fn (Feature $f): string => $f->value, Feature::cases()), $enabled));

        return $this;
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

    public function isConfidant(User $user): bool
    {
        return $this->getMembership($user)?->isConfidant() ?? false;
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
     * Confidants reading the anonymous confidential contact (active members and administrators only).
     *
     * @return list<User>
     */
    public function getConfidants(): array
    {
        return array_values($this->memberships->filter(static fn (Membership $m): bool => $m->isConfidant() && $m->isFull())
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
