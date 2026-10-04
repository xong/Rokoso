<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SurveyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Survey for outsiders, answered via a public link. Questions are fixed once it is opened.
 */
#[ORM\Entity(repositoryClass: SurveyRepository::class)]
class Survey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;

    /** No name or email is asked for */
    #[ORM\Column]
    private bool $anonymous = true;

    /** Listed on the public page; otherwise only reachable via the link */
    #[ORM\Column]
    private bool $listed = false;

    #[ORM\Column(length: 32, unique: true)]
    private string $token;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** @var Collection<int, SurveyQuestion> */
    #[ORM\OneToMany(targetEntity: SurveyQuestion::class, mappedBy: 'survey', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $questions;

    /** @var Collection<int, SurveyResponse> */
    #[ORM\OneToMany(targetEntity: SurveyResponse::class, mappedBy: 'survey', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $responses;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $createdBy,
    ) {
        $this->token = bin2hex(random_bytes(16));
        $this->questions = new ArrayCollection();
        $this->responses = new ArrayCollection();
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

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): static
    {
        $this->anonymous = $anonymous;

        return $this;
    }

    public function isListed(): bool
    {
        return $this->listed;
    }

    public function setListed(bool $listed): static
    {
        $this->listed = $listed;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    /** Still being prepared: questions may change */
    public function isDraft(): bool
    {
        return null === $this->openedAt;
    }

    public function isOpen(): bool
    {
        return null !== $this->openedAt && null === $this->closedAt;
    }

    public function isClosed(): bool
    {
        return null !== $this->closedAt;
    }

    public function open(): void
    {
        $this->openedAt ??= new \DateTimeImmutable();
        $this->closedAt = null;
    }

    public function close(): void
    {
        $this->closedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, SurveyQuestion>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(SurveyQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $question->setPosition(\count($this->questions));
            $this->questions->add($question);
        }

        return $this;
    }

    public function removeQuestion(SurveyQuestion $question): static
    {
        $this->questions->removeElement($question);

        return $this;
    }

    /**
     * @return Collection<int, SurveyResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
