<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Agenda item (TOP) of a meeting. Members may propose items; managers accept them.
 */
#[ORM\Entity]
class AgendaItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $responsible = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 600)]
    private ?int $durationMinutes = null;

    #[ORM\Column]
    private bool $proposed = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 50000)]
    private ?string $minutes = null;

    /** @var Collection<int, StoredFile> */
    #[ORM\ManyToMany(targetEntity: StoredFile::class)]
    #[ORM\JoinTable(name: 'agenda_item_file')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $attachments;

    /** @var Collection<int, Resolution> */
    #[ORM\OneToMany(targetEntity: Resolution::class, mappedBy: 'agendaItem')]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $resolutions;

    /** @var Collection<int, CalendarItem> */
    #[ORM\OneToMany(targetEntity: CalendarItem::class, mappedBy: 'agendaItem')]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $tasks;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'agendaItems')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Meeting $meeting,
    ) {
        $this->attachments = new ArrayCollection();
        $this->resolutions = new ArrayCollection();
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeeting(): Meeting
    {
        return $this->meeting;
    }

    public function getOrganization(): Organization
    {
        return $this->meeting->getOrganization();
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->meeting->numberOf($this);
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

    public function getResponsible(): ?User
    {
        return $this->responsible;
    }

    public function setResponsible(?User $responsible): static
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function isProposed(): bool
    {
        return $this->proposed;
    }

    public function propose(User $by): static
    {
        $this->proposed = true;
        $this->proposedBy = $by;

        return $this;
    }

    public function accept(): static
    {
        $this->proposed = false;

        return $this;
    }

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function getMinutes(): ?string
    {
        return $this->minutes;
    }

    public function setMinutes(?string $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    /**
     * @return Collection<int, StoredFile>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(StoredFile $file): static
    {
        if (!$this->attachments->contains($file)) {
            $this->attachments->add($file);
        }

        return $this;
    }

    public function removeAttachment(StoredFile $file): static
    {
        $this->attachments->removeElement($file);

        return $this;
    }

    /**
     * @return Collection<int, Resolution>
     */
    public function getResolutions(): Collection
    {
        return $this->resolutions;
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }
}
