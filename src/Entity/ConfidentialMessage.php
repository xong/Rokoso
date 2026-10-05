<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message in a confidential conversation: from the anonymous person (no author) or from a confidant.
 * The text is encrypted with the key of the case.
 */
#[ORM\Entity]
class ConfidentialMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $createdOn;

    /** Confidant answered (also when the account was deleted later) */
    #[ORM\Column]
    private bool $fromStaff;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'messages')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ConfidentialCase $confidentialCase,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $author = null,
    ) {
        $this->createdOn = new \DateTimeImmutable('today');
        $this->fromStaff = null !== $author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfidentialCase(): ConfidentialCase
    {
        return $this->confidentialCase;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function isFromStaff(): bool
    {
        return $this->fromStaff;
    }

    public function getCreatedOn(): \DateTimeImmutable
    {
        return $this->createdOn;
    }
}
