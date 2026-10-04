<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationEmail;
use App\Enum\Theme;
use App\Repository\UserRepository;
use App\Util\Initials;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'user.email_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 10, enumType: Theme::class, options: ['default' => 'system'])]
    private Theme $theme = Theme::System;

    #[ORM\Column(length: 10, enumType: NotificationEmail::class, options: ['default' => 'instant'])]
    private NotificationEmail $notificationEmail = NotificationEmail::Instant;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastDigestAt = null;

    /** Secret for the personal iCal subscription link; null = no subscription */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $calendarToken = null;

    #[ORM\Column]
    private bool $verified = false;

    /** New address waiting for confirmation (profile email change). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pendingEmail = null;

    /** Hides the setup checklist on the start page. */
    #[ORM\Column(options: ['default' => false])]
    private bool $setupDismissed = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getInitials(): string
    {
        return Initials::of($this->name ?: $this->email);
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getNotificationEmail(): NotificationEmail
    {
        return $this->notificationEmail;
    }

    public function setNotificationEmail(NotificationEmail $notificationEmail): static
    {
        $this->notificationEmail = $notificationEmail;

        return $this;
    }

    public function getLastDigestAt(): ?\DateTimeImmutable
    {
        return $this->lastDigestAt;
    }

    public function setLastDigestAt(?\DateTimeImmutable $lastDigestAt): static
    {
        $this->lastDigestAt = $lastDigestAt;

        return $this;
    }

    public function getCalendarToken(): ?string
    {
        return $this->calendarToken;
    }

    /** Creates a new subscription secret (old links stop working) or removes it */
    public function resetCalendarToken(bool $enabled = true): static
    {
        $this->calendarToken = $enabled ? bin2hex(random_bytes(32)) : null;

        return $this;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function isSetupDismissed(): bool
    {
        return $this->setupDismissed;
    }

    public function setSetupDismissed(bool $setupDismissed): static
    {
        $this->setupDismissed = $setupDismissed;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): static
    {
        $this->pendingEmail = null === $pendingEmail ? null : mb_strtolower(trim($pendingEmail));

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
