<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BlockedSenderRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mails from this address (or, with a leading "@", this domain) land in the organization's spam folder.
 */
#[ORM\Entity(repositoryClass: BlockedSenderRepository::class)]
#[ORM\UniqueConstraint(columns: ['organization_id', 'address'])]
class BlockedSender
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        string $address,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy = null,
    ) {
        $this->address = self::normalize($address);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function normalize(string $address): string
    {
        return mb_strtolower(trim($address));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
