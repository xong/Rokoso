<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CalendarItemType;
use App\Enum\Recurrence;
use App\Enum\TaskStatus;
use App\Repository\CalendarItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use RRule\RRule;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Event or task. Visibility like projects: organization members, otherwise only the creator.
 * Recurring items repeat by a simple rule (frequency, interval, optional end date).
 * Tasks may have no date (start = due date) and carry a status.
 */
#[ORM\Entity(repositoryClass: CalendarItemRepository::class)]
#[ORM\Index(columns: ['starts_at'])]
class CalendarItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: CalendarItemType::class)]
    private CalendarItemType $type = CalendarItemType::Event;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $url = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startsAt', message: 'calendar.end_before_start')]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private bool $allDay = false;

    #[ORM\Column(length: 20, enumType: TaskStatus::class, options: ['default' => 'open'])]
    private TaskStatus $status = TaskStatus::Open;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Message $sourceMessage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ForumTopic $sourceTopic = null;

    /** Task from the minutes of an agenda item */
    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AgendaItem $agendaItem = null;

    /** Calendar entry of a meeting (kept in sync by the meeting) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Meeting $meeting = null;

    #[ORM\Column(length: 20, enumType: Recurrence::class)]
    private Recurrence $recurrence = Recurrence::None;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 99)]
    private int $recurrenceInterval = 1;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $recurrenceUntil = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'calendar_item_assignee')]
    private Collection $assignees;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'calendar_item_participant')]
    private Collection $participants;

    /** Shown on the public page of the organization */
    #[ORM\Column]
    private bool $public = false;

    /** Outsiders may sign up on the public page */
    #[ORM\Column]
    private bool $signup = false;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $signupLimit = null;

    /** @var Collection<int, EventSignup> */
    #[ORM\OneToMany(targetEntity: EventSignup::class, mappedBy: 'item', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $signups;

    /** @var Collection<int, CalendarException> */
    #[ORM\OneToMany(targetEntity: CalendarException::class, mappedBy: 'item', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'ASC'])]
    private Collection $exceptions;

    /** Minutes before the start for a reminder notification; null = none */
    #[ORM\Column(nullable: true, options: ['default' => 1440])]
    private ?int $reminderMinutes = self::DEFAULT_REMINDER;

    /** External guests (email addresses), invited with an .ics file */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $guestEmails = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public const int DEFAULT_REMINDER = 1440;
    public const array REMINDER_CHOICES = [15, 60, 1440, 2880, 10080];

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $createdBy,
    ) {
        $this->startsAt = new \DateTimeImmutable('today 09:00');
        $this->createdAt = new \DateTimeImmutable();
        $this->assignees = new ArrayCollection();
        $this->participants = new ArrayCollection();
        $this->signups = new ArrayCollection();
        $this->exceptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): CalendarItemType
    {
        return $this->type;
    }

    public function setType(CalendarItemType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isTask(): bool
    {
        return CalendarItemType::Task === $this->type;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    /**
     * Date for calendar purposes; undated tasks fall back to their creation (they never show in the calendar).
     */
    public function getDate(): \DateTimeImmutable
    {
        return $this->startsAt ?? $this->createdAt;
    }

    #[Assert\Callback]
    public function validateStart(ExecutionContextInterface $context): void
    {
        if (null === $this->startsAt && !$this->isTask()) {
            $context->buildViolation('calendar.start_required')->atPath('startsAt')->addViolation();
        }
        if (null === $this->startsAt && $this->isRecurring()) {
            $context->buildViolation('task.recurrence_needs_date')->atPath('startsAt')->addViolation();
        }
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
     * Effective end: explicit end, end of day for all-day items, otherwise one hour.
     */
    public function getEffectiveEnd(): \DateTimeImmutable
    {
        $start = $this->getDate();
        if ($this->allDay) {
            return ($this->endsAt ?? $start)->setTime(23, 59, 59);
        }

        return $this->endsAt ?? ($this->isTask() ? $start : $start->modify('+1 hour'));
    }

    public function getDuration(): \DateInterval
    {
        return $this->getDate()->diff($this->getEffectiveEnd());
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): static
    {
        $this->allDay = $allDay;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isDone(): bool
    {
        return TaskStatus::Done === $this->status;
    }

    public function setDone(bool $done): static
    {
        $this->status = $done ? TaskStatus::Done : TaskStatus::Open;

        return $this;
    }

    /**
     * Open task whose due date has passed.
     */
    public function isOverdue(): bool
    {
        return $this->isTask() && !$this->isDone() && null !== $this->startsAt
            && $this->getEffectiveEnd() < new \DateTimeImmutable();
    }

    public function getSourceMessage(): ?Message
    {
        return $this->sourceMessage;
    }

    public function setSourceMessage(?Message $sourceMessage): static
    {
        $this->sourceMessage = $sourceMessage;

        return $this;
    }

    public function getAgendaItem(): ?AgendaItem
    {
        return $this->agendaItem;
    }

    public function setAgendaItem(?AgendaItem $agendaItem): static
    {
        $this->agendaItem = $agendaItem;

        return $this;
    }

    public function getMeeting(): ?Meeting
    {
        return $this->meeting;
    }

    public function setMeeting(?Meeting $meeting): static
    {
        $this->meeting = $meeting;

        return $this;
    }

    public function getSourceTopic(): ?ForumTopic
    {
        return $this->sourceTopic;
    }

    public function setSourceTopic(?ForumTopic $sourceTopic): static
    {
        $this->sourceTopic = $sourceTopic;

        return $this;
    }

    public function getRecurrence(): Recurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(Recurrence $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function isRecurring(): bool
    {
        return Recurrence::None !== $this->recurrence;
    }

    public function getRecurrenceInterval(): int
    {
        return $this->recurrenceInterval;
    }

    public function setRecurrenceInterval(?int $interval): static
    {
        $this->recurrenceInterval = max(1, (int) $interval);

        return $this;
    }

    public function getRecurrenceUntil(): ?\DateTimeImmutable
    {
        return $this->recurrenceUntil;
    }

    public function setRecurrenceUntil(?\DateTimeImmutable $until): static
    {
        $this->recurrenceUntil = $until;

        return $this;
    }

    /**
     * RFC 5545 rule, e.g. "FREQ=WEEKLY;INTERVAL=2;UNTIL=20261231T235959Z".
     */
    public function getRrule(): ?string
    {
        if (!$this->isRecurring()) {
            return null;
        }
        $rule = 'FREQ='.$this->recurrence->value.';INTERVAL='.$this->recurrenceInterval;
        if (null !== $this->recurrenceUntil) {
            $rule .= ';UNTIL='.$this->recurrenceUntil->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        }

        return $rule;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
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

    /** @return Collection<int, User> */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    public function addAssignee(User $user): static
    {
        if (!$this->assignees->contains($user)) {
            $this->assignees->add($user);
        }

        return $this;
    }

    public function removeAssignee(User $user): static
    {
        $this->assignees->removeElement($user);

        return $this;
    }

    /** @return Collection<int, User> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $user): static
    {
        if (!$this->participants->contains($user)) {
            $this->participants->add($user);
        }

        return $this;
    }

    public function removeParticipant(User $user): static
    {
        $this->participants->removeElement($user);

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;

        return $this;
    }

    public function isSignup(): bool
    {
        return $this->signup;
    }

    public function setSignup(bool $signup): static
    {
        $this->signup = $signup;

        return $this;
    }

    public function getSignupLimit(): ?int
    {
        return $this->signupLimit;
    }

    public function setSignupLimit(?int $signupLimit): static
    {
        $this->signupLimit = $signupLimit;

        return $this;
    }

    /**
     * @return Collection<int, EventSignup>
     */
    public function getSignups(): Collection
    {
        return $this->signups;
    }

    /** Persons signed up so far */
    public function getSignupCount(): int
    {
        $count = 0;
        foreach ($this->signups as $signup) {
            $count += $signup->getPersons();
        }

        return $count;
    }

    /** Free places, null when unlimited */
    public function getSignupFree(): ?int
    {
        return null === $this->signupLimit ? null : max(0, $this->signupLimit - $this->getSignupCount());
    }

    /** Public signup is possible: switched on, single upcoming event, places left */
    public function isSignupOpen(): bool
    {
        return $this->public && $this->signup && !$this->isTask() && !$this->isRecurring()
            && $this->getDate() >= new \DateTimeImmutable('today')
            && 0 !== $this->getSignupFree();
    }

    /**
     * @return Collection<int, CalendarException>
     */
    public function getExceptions(): Collection
    {
        return $this->exceptions;
    }

    public function getException(\DateTimeInterface $day): ?CalendarException
    {
        $key = $day->format('Y-m-d');
        foreach ($this->exceptions as $exception) {
            if ($exception->getDate()->format('Y-m-d') === $key) {
                return $exception;
            }
        }

        return null;
    }

    /** Existing or new (persisted with the item) change for the occurrence on that day */
    public function exceptionFor(\DateTimeImmutable $day): CalendarException
    {
        $exception = $this->getException($day);
        if (null === $exception) {
            $exception = new CalendarException($this, $day);
            $this->exceptions->add($exception);
        }

        return $exception;
    }

    public function removeException(CalendarException $exception): static
    {
        $this->exceptions->removeElement($exception);

        return $this;
    }

    /** Whether a recurring item has an occurrence starting on that day (ignoring changes) */
    public function occursOn(\DateTimeImmutable $day): bool
    {
        $rrule = $this->getRrule();
        if (null === $rrule) {
            return $this->getDate()->format('Y-m-d') === $day->format('Y-m-d');
        }
        $start = $this->getDate();

        return new RRule($rrule, $start)->occursAt($day->setTime((int) $start->format('G'), (int) $start->format('i')));
    }

    public function getReminderMinutes(): ?int
    {
        return $this->reminderMinutes;
    }

    public function setReminderMinutes(?int $reminderMinutes): static
    {
        $this->reminderMinutes = $reminderMinutes;

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

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function markInvited(): static
    {
        $this->invitedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Display color: project, organization or default. */
    public function getColor(): string
    {
        return $this->project?->getColor() ?? $this->organization?->getColor() ?? '#64748b';
    }
}
