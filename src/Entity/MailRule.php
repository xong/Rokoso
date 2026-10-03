<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MailRuleField;
use App\Repository\MailRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Applied to newly fetched mails: if the field contains the text, set project/assignees/done.
 */
#[ORM\Entity(repositoryClass: MailRuleRepository::class)]
class MailRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    /** Only mails of this account; null = all accounts of the organization. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?MailAccount $mailAccount = null;

    #[ORM\Column(length: 20, enumType: MailRuleField::class)]
    private MailRuleField $field = MailRuleField::From;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $needle = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'mail_rule_assignee')]
    private Collection $assignees;

    #[ORM\Column]
    private bool $markDone = false;

    #[ORM\Column]
    private bool $enabled = true;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
        $this->assignees = new ArrayCollection();
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

    public function getMailAccount(): ?MailAccount
    {
        return $this->mailAccount;
    }

    public function setMailAccount(?MailAccount $mailAccount): static
    {
        $this->mailAccount = $mailAccount;

        return $this;
    }

    public function getField(): MailRuleField
    {
        return $this->field;
    }

    public function setField(MailRuleField $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getNeedle(): string
    {
        return $this->needle;
    }

    public function setNeedle(?string $needle): static
    {
        $this->needle = trim((string) $needle);

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

    /** @return Collection<int, User> */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    public function addAssignee(User $user): static
    {
        if (!$this->assignees->contains($user)) {
            $this->assignees->add($user);
        }

        return $this;
    }

    public function removeAssignee(User $user): static
    {
        $this->assignees->removeElement($user);

        return $this;
    }

    public function isMarkDone(): bool
    {
        return $this->markDone;
    }

    public function setMarkDone(bool $markDone): static
    {
        $this->markDone = $markDone;

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

    /**
     * Case-insensitive "contains" on the chosen field.
     */
    public function matches(Message $message): bool
    {
        if (!$this->enabled || '' === $this->needle) {
            return false;
        }
        if (null !== $this->mailAccount && $this->mailAccount !== $message->getMailAccount()) {
            return false;
        }
        $haystack = match ($this->field) {
            MailRuleField::From => $message->getFromAddress().' '.$message->getFromName(),
            MailRuleField::Subject => $message->getSubject(),
            MailRuleField::To => implode(' ', array_map(static fn (array $r): string => $r['address'].' '.$r['name'], [...$message->getToRecipients(), ...$message->getCcRecipients()])),
            MailRuleField::Body => (string) ($message->getTextBody() ?? $message->getSnippet()),
        };

        return str_contains(mb_strtolower($haystack), mb_strtolower($this->needle));
    }
}
