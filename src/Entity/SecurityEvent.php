<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SecurityEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry of the security log: logins, two-factor changes, role changes, deletions.
 */
#[ORM\Entity(repositoryClass: SecurityEventRepository::class)]
#[ORM\Index(columns: ['created_at'])]
class SecurityEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        /** Translation key below security_log.type */
        #[ORM\Column(length: 40)]
        private string $type,
        /** Affected account */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?User $user = null,
        /** Who did it, if not the affected person */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $actor = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $detail = null,
        /** Shortened IP address (last block removed) */
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $ip = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        if (null !== $this->detail) {
            $this->detail = mb_substr($this->detail, 0, 255);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
