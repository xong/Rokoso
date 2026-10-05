<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttendanceStatus;
use App\Enum\MeetingStatus;
use App\Repository\MeetingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Committee meeting of an organization: agenda, invitation, attendance, minutes and resolutions.
 */
#[ORM\Entity(repositoryClass: MeetingRepository::class)]
#[ORM\Index(columns: ['starts_at'])]
class Meeting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column]
    #[Assert\NotNull]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startsAt', message: 'calendar.end_before_start')]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $videoUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;

    /** External guests, one address per line */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $guestEmails = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(length: 20, enumType: MeetingStatus::class)]
    private MeetingStatus $status = MeetingStatus::Planned;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $minuteTaker = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 20000)]
    private ?string $minutesNotes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $minutesApprovedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $minutesApprovedBy = null;

    /** @var Collection<int, AgendaItem> */
    #[ORM\OneToMany(targetEntity: AgendaItem::class, mappedBy: 'meeting', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $agendaItems;

    /** @var Collection<int, Attendance> */
    #[ORM\OneToMany(targetEntity: Attendance::class, mappedBy: 'meeting', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attendances;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $createdBy,
    ) {
        $this->startsAt = new \DateTimeImmutable('+14 days 19:00');
        $this->createdAt = new \DateTimeImmutable();
        $this->agendaItems = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        if (null !== $startsAt) {
            $this->startsAt = $startsAt;
        }

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    /**
     * End for calendar and .ics: explicit end, otherwise start plus the planned agenda time (at least two hours).
     */
    public function getEffectiveEnd(): \DateTimeImmutable
    {
        return $this->endsAt ?? $this->startsAt->modify('+'.max(120, $this->getPlannedMinutes()).' minutes');
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;

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

    public function getGuestEmails(): ?string
    {
        return $this->guestEmails;
    }

    public function setGuestEmails(?string $guestEmails): static
    {
        $this->guestEmails = $guestEmails;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getGuestEmailList(): array
    {
        $parts = preg_split('/[\s,;]+/', (string) $this->guestEmails, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('mb_strtolower', $parts)));
    }

    #[Assert\Callback]
    public function validateGuests(ExecutionContextInterface $context): void
    {
        foreach ($this->getGuestEmailList() as $address) {
            if (false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $context->buildViolation('compose.invalid_address')->setParameter('%address%', $address)
                    ->atPath('guestEmails')->addViolation();
            }
        }
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

    public function getStatus(): MeetingStatus
    {
        return $this->status;
    }

    public function setStatus(MeetingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMinuteTaker(): ?User
    {
        return $this->minuteTaker;
    }

    public function setMinuteTaker(?User $minuteTaker): static
    {
        $this->minuteTaker = $minuteTaker;

        return $this;
    }

    public function getMinutesNotes(): ?string
    {
        return $this->minutesNotes;
    }

    public function setMinutesNotes(?string $minutesNotes): static
    {
        $this->minutesNotes = $minutesNotes;

        return $this;
    }

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function markInvited(): static
    {
        $this->invitedAt = new \DateTimeImmutable();
        if (MeetingStatus::Planned === $this->status) {
            $this->status = MeetingStatus::Invited;
        }

        return $this;
    }

    /** After attendance or minutes have been recorded. */
    public function markHeld(): static
    {
        if (\in_array($this->status, [MeetingStatus::Planned, MeetingStatus::Invited], true)) {
            $this->status = MeetingStatus::Held;
        }

        return $this;
    }

    public function isMinutesApproved(): bool
    {
        return null !== $this->minutesApprovedAt;
    }

    public function getMinutesApprovedAt(): ?\DateTimeImmutable
    {
        return $this->minutesApprovedAt;
    }

    public function getMinutesApprovedBy(): ?User
    {
        return $this->minutesApprovedBy;
    }

    public function approveMinutes(User $by): static
    {
        $this->minutesApprovedAt = new \DateTimeImmutable();
        $this->minutesApprovedBy = $by;
        $this->status = MeetingStatus::Approved;

        return $this;
    }

    public function reopenMinutes(): static
    {
        $this->minutesApprovedAt = null;
        $this->minutesApprovedBy = null;
        $this->status = MeetingStatus::Held;

        return $this;
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function getAgendaItems(): Collection
    {
        return $this->agendaItems;
    }

    /**
     * Accepted agenda items in order (without open proposals).
     *
     * @return list<AgendaItem>
     */
    public function getAgenda(): array
    {
        return array_values($this->agendaItems->filter(static fn (AgendaItem $i): bool => !$i->isProposed())->toArray());
    }

    /**
     * @return list<AgendaItem>
     */
    public function getProposals(): array
    {
        return array_values($this->agendaItems->filter(static fn (AgendaItem $i): bool => $i->isProposed())->toArray());
    }

    public function addAgendaItem(AgendaItem $item): static
    {
        if (!$this->agendaItems->contains($item)) {
            $item->setPosition($this->nextPosition());
            $this->agendaItems->add($item);
        }

        return $this;
    }

    public function removeAgendaItem(AgendaItem $item): static
    {
        $this->agendaItems->removeElement($item);

        return $this;
    }

    public function nextPosition(): int
    {
        $max = 0;
        foreach ($this->agendaItems as $item) {
            $max = max($max, $item->getPosition());
        }

        return $max + 1;
    }

    /** Agenda number (TOP 1, 2 …) of an accepted item, null for proposals. */
    public function numberOf(AgendaItem $item): ?int
    {
        $index = array_search($item, $this->getAgenda(), true);

        return false === $index ? null : $index + 1;
    }

    public function getPlannedMinutes(): int
    {
        return array_sum(array_map(static fn (AgendaItem $i): int => $i->getDurationMinutes() ?? 0, $this->getAgenda()));
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function getAttendance(User $user): ?Attendance
    {
        foreach ($this->attendances as $attendance) {
            $other = $attendance->getUser();
            if ($other === $user || (null !== $user->getId() && $other->getId() === $user->getId())) {
                return $attendance;
            }
        }

        return null;
    }

    public function addAttendance(Attendance $attendance): static
    {
        $this->attendances->add($attendance);

        return $this;
    }

    public function removeAttendance(Attendance $attendance): static
    {
        $this->attendances->removeElement($attendance);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Quorum: present members with voting right against the organization's required share.
     *
     * @return array{voting: int, present: int, required: int, reached: bool}
     */
    public function getQuorum(): array
    {
        $voting = $this->organization->getVotingMembers();
        $present = 0;
        foreach ($this->attendances as $attendance) {
            if (AttendanceStatus::Present === $attendance->getStatus() && \in_array($attendance->getUser(), $voting, true)) {
                ++$present;
            }
        }
        $required = (int) ceil(\count($voting) * $this->organization->getQuorumPercent() / 100);

        return ['voting' => \count($voting), 'present' => $present, 'required' => $required, 'reached' => $present >= $required && $present > 0];
    }

    /** Days between the invitation (now, if not yet sent) and the meeting. */
    public function daysUntil(?\DateTimeImmutable $from = null): int
    {
        $from ??= $this->invitedAt ?? new \DateTimeImmutable();

        return (int) floor(($this->startsAt->getTimestamp() - $from->getTimestamp()) / 86400);
    }

    /** Whether an invitation sent now would undershoot the organization's notice period. */
    public function isNoticeTooShort(): bool
    {
        return $this->daysUntil(new \DateTimeImmutable()) < $this->organization->getInvitationDays();
    }
}
