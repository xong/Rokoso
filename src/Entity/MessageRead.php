<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Read marker per user (see ENTSCHEIDUNGEN.md #16). No row = unread.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['message_id', 'user_id'])]
class MessageRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $readAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Message $message,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
        $this->readAt = new \DateTimeImmutable();
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

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }
}
