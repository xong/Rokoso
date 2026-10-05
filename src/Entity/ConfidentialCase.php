<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConfidentialCaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Anonymous confidential conversation from the public page. The person writing only knows an access code
 * (stored as keyed hash); subject, email address and messages are encrypted with a key of their own
 * ({@see \App\Confidential\ConfidentialCrypto}). Only dates are kept, no times, no IP addresses.
 */
#[ORM\Entity(repositoryClass: ConfidentialCaseRepository::class)]
#[ORM\Index(columns: ['organization_id', 'last_activity_on'])]
class ConfidentialCase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $createdOn;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $lastActivityOn;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedOn = null;

    /** Encrypted email address for reply notices; never shown */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $email = null;

    /** New message from the person writing, not yet read by a confidant */
    #[ORM\Column]
    private bool $staffUnread = true;

    /** New answer not yet read by the person writing */
    #[ORM\Column]
    private bool $reporterUnread = false;

    /** @var Collection<int, ConfidentialMessage> */
    #[ORM\OneToMany(targetEntity: ConfidentialMessage::class, mappedBy: 'confidentialCase', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $messages;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\Column(length: 64, unique: true)]
        private string $lookupHash,
        /** Case key, encrypted with the master key */
        #[ORM\Column(type: Types::TEXT)]
        private string $wrappedKey,
        /** Encrypted subject */
        #[ORM\Column(type: Types::TEXT)]
        private string $subject,
    ) {
        $this->createdOn = new \DateTimeImmutable('today');
        $this->lastActivityOn = $this->createdOn;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getLookupHash(): string
    {
        return $this->lookupHash;
    }

    public function getWrappedKey(): string
    {
        return $this->wrappedKey;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function hasEmail(): bool
    {
        return null !== $this->email;
    }

    public function getCreatedOn(): \DateTimeImmutable
    {
        return $this->createdOn;
    }

    public function getLastActivityOn(): \DateTimeImmutable
    {
        return $this->lastActivityOn;
    }

    public function getClosedOn(): ?\DateTimeImmutable
    {
        return $this->closedOn;
    }

    public function isClosed(): bool
    {
        return null !== $this->closedOn;
    }

    public function close(): void
    {
        $this->closedOn = new \DateTimeImmutable('today');
    }

    public function reopen(): void
    {
        $this->closedOn = null;
    }

    public function isStaffUnread(): bool
    {
        return $this->staffUnread;
    }

    public function markStaffRead(): void
    {
        $this->staffUnread = false;
    }

    public function isReporterUnread(): bool
    {
        return $this->reporterUnread;
    }

    public function markReporterRead(): void
    {
        $this->reporterUnread = false;
    }

    /** @return Collection<int, ConfidentialMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    /**
     * @param string $body encrypted text
     */
    public function addMessage(string $body, ?User $author): ConfidentialMessage
    {
        $message = new ConfidentialMessage($this, $body, $author);
        $this->messages->add($message);
        $this->lastActivityOn = new \DateTimeImmutable('today');
        if (null === $author) {
            $this->staffUnread = true;
            $this->closedOn = null;
        } else {
            $this->reporterUnread = true;
        }

        return $message;
    }
}
