<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Answer option of a poll; for date finding with start (and end).
 */
#[ORM\Entity]
class PollOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 200)]
    private string $label = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /** @var Collection<int, PollAnswer> */
    #[ORM\OneToMany(targetEntity: PollAnswer::class, mappedBy: 'option', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'options')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Poll $poll,
    ) {
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): Poll
    {
        return $this->poll;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = mb_substr(trim($label), 0, 200);

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setPeriod(\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): static
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        return $this;
    }

    /** @return Collection<int, PollAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(PollAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
        }

        return $this;
    }

    public function removeAnswer(PollAnswer $answer): static
    {
        $this->answers->removeElement($answer);

        return $this;
    }

    /** Number of "yes" (or selected) answers. */
    public function getScore(): int
    {
        return $this->answers->filter(static fn (PollAnswer $a): bool => PollAnswer::YES === $a->getValue())->count();
    }

    /** Number of "maybe" answers (date finding only). */
    public function getMaybeCount(): int
    {
        return $this->answers->filter(static fn (PollAnswer $a): bool => PollAnswer::MAYBE === $a->getValue())->count();
    }

    /** Answer of a person in an open poll (null = no / not selected). */
    public function getAnswerOf(User $user): ?int
    {
        foreach ($this->answers as $answer) {
            $voter = $answer->getBallot()?->getUser();
            if (null !== $voter && ($voter === $user || (null !== $user->getId() && $voter->getId() === $user->getId()))) {
                return $answer->getValue();
            }
        }

        return null;
    }

    /**
     * Names of people who answered with the given value (open polls only).
     *
     * @return list<string>
     */
    public function getVoterNames(int $value = PollAnswer::YES): array
    {
        $names = [];
        foreach ($this->answers as $answer) {
            $voter = $answer->getBallot()?->getUser();
            if (null !== $voter && $answer->getValue() === $value) {
                $names[] = $voter->getName();
            }
        }
        sort($names);

        return $names;
    }
}
