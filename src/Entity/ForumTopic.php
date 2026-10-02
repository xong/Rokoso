<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForumTopicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Forum topic: title plus posts; the first post opens the topic.
 */
#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
#[ORM\Index(name: 'forum_topic_last_post', columns: ['last_post_at'])]
class ForumTopic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** @var Collection<int, ForumPost> */
    #[ORM\OneToMany(targetEntity: ForumPost::class, mappedBy: 'topic', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $posts;

    #[ORM\Column]
    private int $postCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastPostAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $lastPostBy = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ForumBoard $board,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy,
    ) {
        $this->posts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastPostAt = $this->createdAt;
        $this->project = $board->getProject();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoard(): ForumBoard
    {
        return $this->board;
    }

    public function setBoard(ForumBoard $board): static
    {
        $this->board = $board;

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->board->getOrganization();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /** @return Collection<int, ForumPost> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getFirstPost(): ?ForumPost
    {
        return $this->posts->first() ?: null;
    }

    public function addPost(ForumPost $post): static
    {
        $this->posts->add($post);
        ++$this->postCount;
        $this->lastPostAt = $post->getCreatedAt();
        $this->lastPostBy = $post->getAuthor();

        return $this;
    }

    public function removePost(ForumPost $post): static
    {
        $this->posts->removeElement($post);
        $this->postCount = max(0, $this->postCount - 1);

        return $this;
    }

    public function getPostCount(): int
    {
        return $this->postCount;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastPostAt(): \DateTimeImmutable
    {
        return $this->lastPostAt;
    }

    public function getLastPostBy(): ?User
    {
        return $this->lastPostBy;
    }

    #[Assert\Callback]
    public function validateProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->project && $this->project->getOrganization() !== $this->getOrganization()) {
            $context->buildViolation('folder.project_mismatch')->atPath('project')->addViolation();
        }
    }
}
