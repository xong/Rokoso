<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry behind the bell: something happened that concerns the recipient.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'notification_recipient_read', columns: ['recipient_id', 'read_at'])]
#[ORM\UniqueConstraint(name: 'notification_ref', columns: ['recipient_id', 'ref_key'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /** Sent by email (instantly or in the daily digest). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailedAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $recipient,
        #[ORM\Column(length: 20, enumType: NotificationType::class)]
        private NotificationType $type,
        #[ORM\Column(length: 255)]
        private string $subject,
        #[ORM\Column(length: 500)]
        private string $url,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $actor = null,
        /** Prevents duplicates for generated notifications (e.g. due reminders). */
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $refKey = null,
    ) {
        $this->subject = mb_substr($subject, 0, 255);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getRefKey(): ?string
    {
        return $this->refKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(): static
    {
        $this->readAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function getEmailedAt(): ?\DateTimeImmutable
    {
        return $this->emailedAt;
    }

    public function markEmailed(): static
    {
        $this->emailedAt = new \DateTimeImmutable();

        return $this;
    }
}
