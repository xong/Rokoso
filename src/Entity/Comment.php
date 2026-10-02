<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Internal note on a message or project, visible to everyone who can see the target.
 * Exactly one target relation is set.
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10000)]
    private string $body = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Message $message = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Project $project = null;

    private function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $author,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function onMessage(Message $message, ?User $author): self
    {
        $comment = new self($author);
        $comment->message = $message;

        return $comment;
    }

    public static function onProject(Project $project, ?User $author): self
    {
        $comment = new self($author);
        $comment->project = $project;

        return $comment;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    /** The commented object, for permission checks. */
    public function getTarget(): Message|Project
    {
        return $this->message ?? $this->project ?? throw new \LogicException('Comment without target');
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = trim((string) $body);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
