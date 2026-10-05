<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WikiPageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Page of an organization's knowledge base (handbook): Markdown, nested, every save kept as revision.
 */
#[ORM\Entity(repositoryClass: WikiPageRepository::class)]
class WikiPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(max: 100000)]
    private string $body = '';

    #[ORM\ManyToOne(inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?WikiPage $parent = null;

    /** @var Collection<int, WikiPage> */
    #[ORM\OneToMany(targetEntity: WikiPage::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['title' => \SortDirection::Ascending])]
    private Collection $children;

    /** @var Collection<int, WikiRevision> */
    #[ORM\OneToMany(targetEntity: WikiRevision::class, mappedBy: 'page')]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $revisions;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
        $this->children = new ArrayCollection();
        $this->revisions = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = (string) $body;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, WikiPage>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, WikiRevision>
     */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    /**
     * Ancestors from the root down to this page.
     *
     * @return list<WikiPage>
     */
    public function getPath(): array
    {
        $path = [];
        for ($page = $this; null !== $page; $page = $page->getParent()) {
            array_unshift($path, $page);
        }

        return $path;
    }

    /**
     * True for this page and every page below it (not allowed as new parent).
     */
    public function contains(self $page): bool
    {
        return \in_array($this, $page->getPath(), true);
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Records a saved state: updates the metadata and returns the revision to persist.
     */
    public function record(User $author): WikiRevision
    {
        $this->updatedBy = $author;
        $this->updatedAt = new \DateTimeImmutable();
        $revision = new WikiRevision($this, $author);
        $this->revisions->add($revision);

        return $revision;
    }
}
