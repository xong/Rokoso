<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MailEncryption;
use App\Repository\MailAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * IMAP/SMTP mailbox of an organization. Passwords are stored encrypted (see SecretBox).
 */
#[ORM\Entity(repositoryClass: MailAccountRepository::class)]
class MailAccount
{
    public const int STALE_AFTER_MINUTES = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $emailAddress = '';

    /** Display name used as sender, e.g. "Stadtelternvertretung Musterstadt". */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $senderName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $imapHost = '';

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 65535)]
    private int $imapPort = 993;

    #[ORM\Column(length: 10, enumType: MailEncryption::class)]
    private MailEncryption $imapEncryption = MailEncryption::Ssl;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $imapUsername = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $imapPassword = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $smtpHost = '';

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 65535)]
    private int $smtpPort = 465;

    #[ORM\Column(length: 10, enumType: MailEncryption::class)]
    private MailEncryption $smtpEncryption = MailEncryption::Ssl;

    /** Empty: same as IMAP. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $smtpUsername = null;

    /** Empty: same as IMAP. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $smtpPassword = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $inboxFolder = 'INBOX';

    /** IMAP folder that receives a copy of every mail sent from Rokoso (null = no copy). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sentFolder = null;

    /** Only messages newer than this many days are imported on the first sync. */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 3650)]
    private int $importDays = 90;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(nullable: true)]
    private ?int $lastUid = null;

    #[ORM\Column(nullable: true)]
    private ?int $uidValidity = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastSyncError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): static
    {
        $this->emailAddress = mb_strtolower(trim((string) $emailAddress));

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getImapHost(): string
    {
        return $this->imapHost;
    }

    public function setImapHost(?string $imapHost): static
    {
        $this->imapHost = trim((string) $imapHost);

        return $this;
    }

    public function getImapPort(): int
    {
        return $this->imapPort;
    }

    public function setImapPort(?int $imapPort): static
    {
        $this->imapPort = (int) $imapPort;

        return $this;
    }

    public function getImapEncryption(): MailEncryption
    {
        return $this->imapEncryption;
    }

    public function setImapEncryption(MailEncryption $imapEncryption): static
    {
        $this->imapEncryption = $imapEncryption;

        return $this;
    }

    public function getImapUsername(): string
    {
        return $this->imapUsername;
    }

    public function setImapUsername(?string $imapUsername): static
    {
        $this->imapUsername = trim((string) $imapUsername);

        return $this;
    }

    /** Encrypted value. */
    public function getImapPassword(): ?string
    {
        return $this->imapPassword;
    }

    /** Expects an already encrypted value. */
    public function setImapPassword(?string $imapPassword): static
    {
        $this->imapPassword = $imapPassword;

        return $this;
    }

    public function getSmtpHost(): string
    {
        return $this->smtpHost;
    }

    public function setSmtpHost(?string $smtpHost): static
    {
        $this->smtpHost = trim((string) $smtpHost);

        return $this;
    }

    public function getSmtpPort(): int
    {
        return $this->smtpPort;
    }

    public function setSmtpPort(?int $smtpPort): static
    {
        $this->smtpPort = (int) $smtpPort;

        return $this;
    }

    public function getSmtpEncryption(): MailEncryption
    {
        return $this->smtpEncryption;
    }

    public function setSmtpEncryption(MailEncryption $smtpEncryption): static
    {
        $this->smtpEncryption = $smtpEncryption;

        return $this;
    }

    public function getSmtpUsername(): ?string
    {
        return $this->smtpUsername;
    }

    public function setSmtpUsername(?string $smtpUsername): static
    {
        $this->smtpUsername = '' === trim((string) $smtpUsername) ? null : trim((string) $smtpUsername);

        return $this;
    }

    /** Encrypted value. */
    public function getSmtpPassword(): ?string
    {
        return $this->smtpPassword;
    }

    /** Expects an already encrypted value. */
    public function setSmtpPassword(?string $smtpPassword): static
    {
        $this->smtpPassword = $smtpPassword;

        return $this;
    }

    public function getInboxFolder(): string
    {
        return $this->inboxFolder;
    }

    public function setInboxFolder(?string $inboxFolder): static
    {
        $this->inboxFolder = trim((string) $inboxFolder);

        return $this;
    }

    public function getSentFolder(): ?string
    {
        return $this->sentFolder;
    }

    public function setSentFolder(?string $sentFolder): static
    {
        $this->sentFolder = '' === trim((string) $sentFolder) ? null : trim((string) $sentFolder);

        return $this;
    }

    public function getImportDays(): int
    {
        return $this->importDays;
    }

    public function setImportDays(?int $importDays): static
    {
        $this->importDays = (int) $importDays;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastUid(): ?int
    {
        return $this->lastUid;
    }

    public function getUidValidity(): ?int
    {
        return $this->uidValidity;
    }

    /**
     * Remembers the sync position. A changed UIDVALIDITY means the server renumbered the folder.
     */
    public function setSyncPosition(?int $uidValidity, ?int $lastUid): static
    {
        $this->uidValidity = $uidValidity;
        $this->lastUid = $lastUid;

        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function getLastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    /**
     * "error" when the last fetch failed, "stale" when an enabled account was not fetched for an hour
     * (cron not running), otherwise null.
     */
    public function getSyncProblem(?\DateTimeImmutable $now = null): ?string
    {
        if (!$this->enabled) {
            return null;
        }
        if (null !== $this->lastSyncError) {
            return 'error';
        }
        $now ??= new \DateTimeImmutable();
        $since = $this->lastSyncAt ?? $this->createdAt;

        return $since < $now->modify('-'.self::STALE_AFTER_MINUTES.' minutes') ? 'stale' : null;
    }

    public function markSynced(?string $error = null): static
    {
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->lastSyncError = $error;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return \sprintf('%s <%s>', $this->name, $this->emailAddress);
    }
}
