<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Change to a single occurrence of a recurring item: cancelled, or moved/renamed. Identified by the original day.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['item_id', 'date'])]
class CalendarException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $cancelled = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'exceptions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CalendarItem $item,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
    ) {
        $this->date = $date->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): CalendarItem
    {
        return $this->item;
    }

    /** Original day of the occurrence */
    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Original start of the occurrence (day plus the series' time of day) */
    public function getOriginalStart(): \DateTimeImmutable
    {
        $start = $this->item->getDate();

        return $this->date->setTime((int) $start->format('G'), (int) $start->format('i'));
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): static
    {
        $this->cancelled = $cancelled;

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

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = '' === trim((string) $title) ? null : trim((string) $title);

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = '' === trim((string) $location) ? null : trim((string) $location);

        return $this;
    }

    /** Effective start of the changed occurrence */
    public function getStart(): \DateTimeImmutable
    {
        return $this->startsAt ?? $this->getOriginalStart();
    }

    /** Effective end of the changed occurrence (keeps the series duration if no end was given) */
    public function getEnd(): \DateTimeImmutable
    {
        return $this->endsAt ?? $this->getStart()->add($this->item->getDuration());
    }

    public function isMoved(): bool
    {
        return !$this->cancelled && null !== $this->startsAt && $this->startsAt != $this->getOriginalStart();
    }

    #[Assert\Callback]
    public function validateEnd(ExecutionContextInterface $context): void
    {
        if (null !== $this->startsAt && null !== $this->endsAt && $this->endsAt < $this->startsAt) {
            $context->buildViolation('calendar.end_before_start')->atPath('endsAt')->addViolation();
        }
    }
}
