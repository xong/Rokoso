<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Web push subscription of one browser/device (PWA).
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\UniqueConstraint(name: 'push_endpoint', columns: ['endpoint_hash'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $endpointHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 1000)]
        private string $endpoint,
        #[ORM\Column(length: 255)]
        private string $publicKey,
        #[ORM\Column(length: 255)]
        private string $authToken,
    ) {
        $this->endpointHash = self::hash($endpoint);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
