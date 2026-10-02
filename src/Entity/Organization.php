<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrganizationRole;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $color = '#2563eb';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'organization', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $memberships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = (string) $color;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMember(User $user, OrganizationRole $role): Membership
    {
        $membership = new Membership($this, $user, $role);
        $this->memberships->add($membership);

        return $membership;
    }

    public function getMembership(User $user): ?Membership
    {
        return $this->memberships->findFirst(static fn (int $k, Membership $m): bool => $m->getUser() === $user);
    }

    public function isAdmin(User $user): bool
    {
        return $this->getMembership($user)?->isAdmin() ?? false;
    }

    public function countAdmins(): int
    {
        return $this->memberships->filter(static fn (Membership $m): bool => $m->isAdmin())->count();
    }

    /** @return list<User> */
    public function getMembers(): array
    {
        return array_values($this->memberships->map(static fn (Membership $m): User => $m->getUser())->toArray());
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
