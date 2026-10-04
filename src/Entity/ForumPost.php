<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Forum post in Markdown; attachments are uploads linked to the post.
 */
#[ORM\Entity]
class ForumPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'forum.body_required')]
    #[Assert\Length(max: 100000)]
    private string $body = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    /** @var Collection<int, ForumUpload> */
    #[ORM\OneToMany(targetEntity: ForumUpload::class, mappedBy: 'post')]
    #[ORM\OrderBy(['filename' => 'ASC'])]
    private Collection $attachments;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'posts')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ForumTopic $topic,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $author,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTopic(): ForumTopic
    {
        return $this->topic;
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

    /** Keeps the original time when content is taken over from elsewhere (e.g. converted messages). */
    public function backdate(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEditedAt(): ?\DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function markEdited(): static
    {
        $this->editedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isFirst(): bool
    {
        return $this->topic->getFirstPost() === $this;
    }

    /** @return Collection<int, ForumUpload> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }
}
