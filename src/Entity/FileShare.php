<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FileShareRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Public download link for a single file, valid until it expires or is revoked.
 */
#[ORM\Entity(repositoryClass: FileShareRepository::class)]
class FileShare
{
    public const DAY_CHOICES = [1, 7, 30, 90];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private int $downloads = 0;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'shares')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private StoredFile $file,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy,
        int $days,
    ) {
        $this->token = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(\sprintf('+%d days', max(1, $days)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): StoredFile
    {
        return $this->file;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function getDownloads(): int
    {
        return $this->downloads;
    }

    public function countDownload(): void
    {
        ++$this->downloads;
    }
}
