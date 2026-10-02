<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrganizationRole;
use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pending invitation of an email address into an organization.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
class Invitation
{
    public const string VALIDITY = '+14 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 20, enumType: OrganizationRole::class)]
        private OrganizationRole $role,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $invitedBy = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->token = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->createdAt->modify(self::VALIDITY) < new \DateTimeImmutable();
    }
}
