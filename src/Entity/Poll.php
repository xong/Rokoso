<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PollKind;
use App\Repository\PollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Vote or date finding within an organization, optionally attached to a meeting, agenda item, forum topic or project.
 * Secret polls store only who has voted (ballot), the answers have no link to the person.
 */
#[ORM\Entity(repositoryClass: PollRepository::class)]
class Poll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: PollKind::class)]
    private PollKind $kind = PollKind::Decision;

    /** Choice polls: several options may be selected */
    #[ORM\Column]
    private bool $multiple = false;

    #[ORM\Column]
    private bool $secret = false;

    /** Only members with voting right may vote */
    #[ORM\Column]
    private bool $votingOnly = false;

    /** Circular resolution: closing a decision poll records a resolution */
    #[ORM\Column]
    private bool $circular = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Meeting $meeting = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AgendaItem $agendaItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ForumTopic $topic = null;

    /** Result of a circular resolution */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Resolution $resolution = null;

    /** Date chosen when closing a schedule poll */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CalendarItem $calendarItem = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $chosenStartsAt = null;

    /** @var Collection<int, PollOption> */
    #[ORM\OneToMany(targetEntity: PollOption::class, mappedBy: 'poll', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $options;

    /** @var Collection<int, PollBallot> */
    #[ORM\OneToMany(targetEntity: PollBallot::class, mappedBy: 'poll', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $ballots;

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
        $this->options = new ArrayCollection();
        $this->ballots = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = '' === trim((string) $description) ? null : $description;

        return $this;
    }

    public function getKind(): PollKind
    {
        return $this->kind;
    }

    public function setKind(PollKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function isMultiple(): bool
    {
        return PollKind::Choice === $this->kind && $this->multiple;
    }

    public function setMultiple(bool $multiple): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isSecret(): bool
    {
        return PollKind::Schedule !== $this->kind && $this->secret;
    }

    public function setSecret(bool $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function isVotingOnly(): bool
    {
        return $this->votingOnly;
    }

    public function setVotingOnly(bool $votingOnly): static
    {
        $this->votingOnly = $votingOnly;

        return $this;
    }

    public function isCircular(): bool
    {
        return PollKind::Decision === $this->kind && $this->circular;
    }

    public function setCircular(bool $circular): static
    {
        $this->circular = $circular;

        return $this;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function close(): static
    {
        $this->closedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function reopen(): static
    {
        $this->closedAt = null;

        return $this;
    }

    /** Closed manually or deadline passed: no more votes. */
    public function isEnded(): bool
    {
        return null !== $this->closedAt || (null !== $this->deadline && $this->deadline <= new \DateTimeImmutable());
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

    public function getMeeting(): ?Meeting
    {
        return $this->meeting;
    }

    public function setMeeting(?Meeting $meeting): static
    {
        $this->meeting = $meeting;

        return $this;
    }

    public function getAgendaItem(): ?AgendaItem
    {
        return $this->agendaItem;
    }

    public function setAgendaItem(?AgendaItem $agendaItem): static
    {
        $this->agendaItem = $agendaItem;
        if (null !== $agendaItem) {
            $this->meeting = $agendaItem->getMeeting();
        }

        return $this;
    }

    public function getTopic(): ?ForumTopic
    {
        return $this->topic;
    }

    public function setTopic(?ForumTopic $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function getResolution(): ?Resolution
    {
        return $this->resolution;
    }

    public function setResolution(?Resolution $resolution): static
    {
        $this->resolution = $resolution;

        return $this;
    }

    public function getCalendarItem(): ?CalendarItem
    {
        return $this->calendarItem;
    }

    public function getChosenStartsAt(): ?\DateTimeImmutable
    {
        return $this->chosenStartsAt;
    }

    public function choose(PollOption $option, ?CalendarItem $calendarItem): static
    {
        $this->chosenStartsAt = $option->getStartsAt();
        $this->calendarItem = $calendarItem;

        return $this;
    }

    /** @return list<PollOption> */
    public function getOptions(): array
    {
        return array_values($this->options->toArray());
    }

    public function addOption(PollOption $option): static
    {
        if (!$this->options->contains($option)) {
            $option->setPosition($this->options->count());
            $this->options->add($option);
        }

        return $this;
    }

    public function getOption(int $id): ?PollOption
    {
        foreach ($this->options as $option) {
            if ($option->getId() === $id) {
                return $option;
            }
        }

        return null;
    }

    /** @return Collection<int, PollBallot> */
    public function getBallots(): Collection
    {
        return $this->ballots;
    }

    public function addBallot(PollBallot $ballot): static
    {
        if (!$this->ballots->contains($ballot)) {
            $this->ballots->add($ballot);
        }

        return $this;
    }

    public function getBallot(User $user): ?PollBallot
    {
        foreach ($this->ballots as $ballot) {
            $voter = $ballot->getUser();
            if ($voter === $user || (null !== $user->getId() && $voter->getId() === $user->getId())) {
                return $ballot;
            }
        }

        return null;
    }

    public function hasVoted(User $user): bool
    {
        return null !== $this->getBallot($user);
    }

    /**
     * People who may vote: members of the organization, with voting right if required.
     *
     * @return list<User>
     */
    public function getEligible(): array
    {
        return $this->votingOnly ? $this->organization->getVotingMembers() : $this->organization->getMembers();
    }

    public function isEligible(User $user): bool
    {
        $membership = $this->organization->getMembership($user);

        return null !== $membership && (!$this->votingOnly || $membership->hasVotingRight());
    }

    /** Highest yes count of all options (to highlight the leaders). */
    public function getTopScore(): int
    {
        $max = 0;
        foreach ($this->options as $option) {
            $max = max($max, $option->getScore());
        }

        return $max;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
