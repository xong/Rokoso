<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationEmail;
use App\Enum\Theme;
use App\Repository\UserRepository;
use App\Util\Initials;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'user.email_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface, TwoFactorInterface, BackupCodeInterface
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

    /** TOTP secret (Base32); set = two-factor login active. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $totpSecret = null;

    /** @var list<string> SHA-256 hashes of unused backup codes */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $backupCodes = [];

    /** Changing it ends all sessions and remember-me cookies ("log out everywhere"). */
    #[ORM\Column(length: 32, options: ['default' => ''])]
    private string $sessionStamp = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $platformAdmin = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $blockedAt = null;

    /** Account deleted by its owner or a platform admin; personal data is removed, content stays anonymous. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->sessionStamp = bin2hex(random_bytes(8));
    }

    /**
     * Sessions stay valid only while password, address, roles and session stamp are unchanged
     * and the account is neither blocked nor deleted.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }
        // the session copy holds only a crc32c hash of the password (see __serialize)
        $password = 8 === \strlen($this->password) ? hash('crc32c', $user->password) : $user->password;

        return $password === $this->password
            && $this->email === $user->email
            && $this->sessionStamp === $user->sessionStamp
            && $this->getRoles() === $user->getRoles()
            && $user->isActive();
    }

    public function isActive(): bool
    {
        return null === $this->blockedAt && null === $this->deletedAt;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return null !== $this->totpSecret ? new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6) : null;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;
        if (null === $totpSecret) {
            $this->backupCodes = [];
        }

        return $this;
    }

    /**
     * Replaces the backup codes; returns the new codes in plain text (shown once).
     *
     * @return list<string>
     */
    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $codes[] = substr(bin2hex(random_bytes(5)), 0, 10);
        }
        $this->backupCodes = array_map(static fn (string $c): string => hash('sha256', $c), $codes);

        return $codes;
    }

    public function countBackupCodes(): int
    {
        return \count($this->backupCodes);
    }

    public function isBackupCode(string $code): bool
    {
        return \in_array(hash('sha256', strtolower(trim($code))), $this->backupCodes, true);
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = hash('sha256', strtolower(trim($code)));
        $this->backupCodes = array_values(array_filter($this->backupCodes, static fn (string $c): bool => $c !== $hash));
    }

    public function getSessionStamp(): string
    {
        return $this->sessionStamp;
    }

    public function renewSessionStamp(): static
    {
        $this->sessionStamp = bin2hex(random_bytes(8));

        return $this;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platformAdmin;
    }

    public function setPlatformAdmin(bool $platformAdmin): static
    {
        $this->platformAdmin = $platformAdmin;

        return $this;
    }

    public function getBlockedAt(): ?\DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blockedAt = $blocked ? ($this->blockedAt ?? new \DateTimeImmutable()) : null;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Removes personal data; the row stays so authored content keeps a (neutral) author.
     */
    public function anonymize(): static
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->email = 'deleted-'.$this->id.'-'.bin2hex(random_bytes(4)).'@invalid';
        $this->name = 'Gelöschtes Konto';
        $this->password = '';
        $this->avatar = null;
        $this->calendarToken = null;
        $this->pendingEmail = null;
        $this->totpSecret = null;
        $this->backupCodes = [];
        $this->platformAdmin = false;
        $this->notificationEmail = NotificationEmail::Off;
        $this->renewSessionStamp();

        return $this;
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
        return $this->platformAdmin ? ['ROLE_USER', 'ROLE_PLATFORM_ADMIN'] : ['ROLE_USER'];
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
