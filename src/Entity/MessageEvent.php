<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MessageEventType;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entry of a message's history: who did what when (Verlauf).
 */
#[ORM\Entity]
class MessageEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'events')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Message $message,
        #[ORM\Column(length: 20, enumType: MessageEventType::class)]
        private MessageEventType $type,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
        /** Free text such as the project or assignee name. */
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $detail = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->detail = null === $detail ? null : mb_substr($detail, 0, 255);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getType(): MessageEventType
    {
        return $this->type;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
