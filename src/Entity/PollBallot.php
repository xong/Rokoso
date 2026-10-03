<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records that a person has voted. In open polls the answers belong to the ballot; in secret polls they don't.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['poll_id', 'user_id'])]
class PollBallot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PollAnswer> */
    #[ORM\OneToMany(targetEntity: PollAnswer::class, mappedBy: 'ballot')]
    private Collection $answers;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'ballots')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Poll $poll,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
        $this->answers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): Poll
    {
        return $this->poll;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function touch(): static
    {
        $this->createdAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, PollAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }
}
