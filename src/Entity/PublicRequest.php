<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PublicRequestKind;
use App\Repository\PublicRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A public submission waiting for the sender to confirm their email address.
 */
#[ORM\Entity(repositoryClass: PublicRequestRepository::class)]
class PublicRequest
{
    public const string LIFETIME = '7 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\Column(enumType: PublicRequestKind::class)]
        private PublicRequestKind $kind,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(type: Types::JSON)]
        private array $payload,
    ) {
        $this->token = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getKind(): PublicRequestKind
    {
        return $this->kind;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->createdAt < new \DateTimeImmutable('-'.self::LIFETIME);
    }
}
