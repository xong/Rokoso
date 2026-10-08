<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Personal status of a message (see ENTSCHEIDUNGEN.md #84): done for me and Wiedervorlage.
 * "Für alle erledigt" stays on the message itself ({@see Message::markDone()}). A row without either value is removed.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['message_id', 'user_id'])]
class MessageUserState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $snoozedUntil = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Message $message,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function isDone(): bool
    {
        return null !== $this->doneAt;
    }

    public function setDone(bool $done): static
    {
        $this->doneAt = $done ? new \DateTimeImmutable() : null;
        if ($done) {
            $this->snoozedUntil = null;
        }

        return $this;
    }

    public function getSnoozedUntil(): ?\DateTimeImmutable
    {
        return $this->snoozedUntil;
    }

    public function isSnoozed(): bool
    {
        return null !== $this->snoozedUntil && $this->snoozedUntil > new \DateTimeImmutable();
    }

    public function snooze(?\DateTimeImmutable $until): static
    {
        $this->snoozedUntil = $until;

        return $this;
    }

    public function isEmpty(): bool
    {
        return null === $this->doneAt && null === $this->snoozedUntil;
    }
}
