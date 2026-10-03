<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResolutionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Resolution (Beschluss) of an organization, usually taken under an agenda item of a meeting.
 * Numbered per organization and year ("2026/3").
 */
#[ORM\Entity(repositoryClass: ResolutionRepository::class)]
#[ORM\Index(columns: ['decided_on'])]
class Resolution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $number = '';

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20000)]
    private string $text = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $decidedOn;

    #[ORM\Column]
    private bool $adopted = true;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $votesYes = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $votesNo = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $votesAbstain = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Meeting $meeting = null;

    #[ORM\ManyToOne(inversedBy: 'resolutions')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AgendaItem $agendaItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

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
        $this->decidedOn = new \DateTimeImmutable('today');
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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = trim((string) $text);

        return $this;
    }

    public function getDecidedOn(): \DateTimeImmutable
    {
        return $this->decidedOn;
    }

    public function setDecidedOn(?\DateTimeImmutable $decidedOn): static
    {
        if (null !== $decidedOn) {
            $this->decidedOn = $decidedOn;
        }

        return $this;
    }

    public function isAdopted(): bool
    {
        return $this->adopted;
    }

    public function setAdopted(bool $adopted): static
    {
        $this->adopted = $adopted;

        return $this;
    }

    public function getVotesYes(): ?int
    {
        return $this->votesYes;
    }

    public function setVotesYes(?int $votesYes): static
    {
        $this->votesYes = $votesYes;

        return $this;
    }

    public function getVotesNo(): ?int
    {
        return $this->votesNo;
    }

    public function setVotesNo(?int $votesNo): static
    {
        $this->votesNo = $votesNo;

        return $this;
    }

    public function getVotesAbstain(): ?int
    {
        return $this->votesAbstain;
    }

    public function setVotesAbstain(?int $votesAbstain): static
    {
        $this->votesAbstain = $votesAbstain;

        return $this;
    }

    public function hasVotes(): bool
    {
        return null !== $this->votesYes || null !== $this->votesNo || null !== $this->votesAbstain;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
