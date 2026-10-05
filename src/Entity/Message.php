<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MessageEventType;
use App\Enum\MessageFolder;
use App\Enum\MessageType;
use App\Repository\MessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An email (synced from IMAP or sent via Rokoso) or an internal message.
 * Both share list, filters, comments, assignees and projects.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Index(columns: ['date'])]
#[ORM\Index(columns: ['message_id_header'])]
#[ORM\Index(columns: ['thread_key'])]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: MessageFolder::class)]
    private MessageFolder $folder = MessageFolder::Inbox;

    /** Owning organization: visibility for all its members (null for internal messages to single users). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?MailAccount $mailAccount = null;

    #[ORM\Column(nullable: true)]
    private ?int $imapUid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageIdHeader = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inReplyTo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $referencesHeader = null;

    #[ORM\Column(length: 255)]
    private string $fromName = '';

    #[ORM\Column(length: 255)]
    private string $fromAddress = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $replyToAddress = null;

    /** @var list<array{name: string, address: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $toRecipients = [];

    /** @var list<array{name: string, address: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $ccRecipients = [];

    #[ORM\Column(length: 500)]
    private string $subject = '';

    #[ORM\Column(length: 255)]
    private string $snippet = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $textBody = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $htmlBody = null;

    #[ORM\Column]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $trashedAt = null;

    /** Spam (header of the receiving server, blocked sender or marked by hand): only in the spam folder. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $spamAt = null;

    /** Status model: open (null) or done since … (Entscheidung 39). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $doneBy = null;

    /** Hidden from the inbox until this time ("Wiedervorlage"). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $snoozedUntil = null;

    /** Conversation: Message-ID of the first message of the thread. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $threadKey = null;

    /** Rokoso user who wrote the message (internal messages, mails sent from Rokoso). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** Circular letter: sent as a separate email to each "to" recipient. */
    #[ORM\Column]
    private bool $circular = false;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'message_assignee')]
    private Collection $assignees;

    /** @var Collection<int, User> internal messages: individual recipients */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'message_recipient')]
    private Collection $recipientUsers;

    /** @var Collection<int, Attachment> */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attachments;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'message', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Ascending])]
    private Collection $comments;

    /** @var Collection<int, MessageEvent> */
    #[ORM\OneToMany(targetEntity: MessageEvent::class, mappedBy: 'message', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $events;

    public function __construct(
        #[ORM\Column(length: 20, enumType: MessageType::class)]
        private MessageType $type = MessageType::Email,
    ) {
        $this->date = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->assignees = new ArrayCollection();
        $this->recipientUsers = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): MessageType
    {
        return $this->type;
    }

    public function isInternal(): bool
    {
        return MessageType::Internal === $this->type;
    }

    public function getFolder(): MessageFolder
    {
        return $this->folder;
    }

    public function setFolder(MessageFolder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getMailAccount(): ?MailAccount
    {
        return $this->mailAccount;
    }

    public function setMailAccount(?MailAccount $mailAccount): static
    {
        $this->mailAccount = $mailAccount;
        if (null !== $mailAccount) {
            $this->organization = $mailAccount->getOrganization();
        }

        return $this;
    }

    public function getImapUid(): ?int
    {
        return $this->imapUid;
    }

    public function setImapUid(?int $imapUid): static
    {
        $this->imapUid = $imapUid;

        return $this;
    }

    public function getMessageIdHeader(): ?string
    {
        return $this->messageIdHeader;
    }

    public function setMessageIdHeader(?string $messageIdHeader): static
    {
        $this->messageIdHeader = null === $messageIdHeader ? null : mb_substr($messageIdHeader, 0, 255);

        return $this;
    }

    public function getInReplyTo(): ?string
    {
        return $this->inReplyTo;
    }

    public function setInReplyTo(?string $inReplyTo): static
    {
        $this->inReplyTo = null === $inReplyTo ? null : mb_substr($inReplyTo, 0, 255);

        return $this;
    }

    public function getReferencesHeader(): ?string
    {
        return $this->referencesHeader;
    }

    public function setReferencesHeader(?string $referencesHeader): static
    {
        $this->referencesHeader = $referencesHeader;

        return $this;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    /** Name if known, otherwise address. */
    public function getFromLabel(): string
    {
        return '' !== $this->fromName ? $this->fromName : $this->fromAddress;
    }

    public function setFrom(string $address, string $name = ''): static
    {
        $this->fromAddress = mb_substr(mb_strtolower(trim($address)), 0, 255);
        $this->fromName = mb_substr(trim($name), 0, 255);

        return $this;
    }

    public function getReplyToAddress(): ?string
    {
        return $this->replyToAddress;
    }

    public function setReplyToAddress(?string $replyToAddress): static
    {
        $this->replyToAddress = $replyToAddress;

        return $this;
    }

    /** @return list<array{name: string, address: string}> */
    public function getToRecipients(): array
    {
        return $this->toRecipients;
    }

    /** @param list<array{name: string, address: string}> $recipients */
    public function setToRecipients(array $recipients): static
    {
        $this->toRecipients = $recipients;

        return $this;
    }

    /** @return list<array{name: string, address: string}> */
    public function getCcRecipients(): array
    {
        return $this->ccRecipients;
    }

    /** @param list<array{name: string, address: string}> $recipients */
    public function setCcRecipients(array $recipients): static
    {
        $this->ccRecipients = $recipients;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = mb_substr(trim($subject), 0, 500);

        return $this;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    public function hasHtml(): bool
    {
        return null !== $this->htmlBody && '' !== trim($this->htmlBody);
    }

    /**
     * Sets the bodies and derives the list snippet (plain text, collapsed whitespace).
     */
    public function setBody(?string $text, ?string $html = null): static
    {
        $this->textBody = $text;
        $this->htmlBody = $html;
        $plain = $text;
        if ((null === $plain || '' === trim($plain)) && null !== $html) {
            $plain = html_entity_decode(strip_tags((string) preg_replace('#<(style|script|head)\b.*?</\1>#is', '', $html)), \ENT_QUOTES | \ENT_HTML5);
        }
        $this->snippet = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $plain)), 0, 255);

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTrashedAt(): ?\DateTimeImmutable
    {
        return $this->trashedAt;
    }

    public function isTrashed(): bool
    {
        return null !== $this->trashedAt;
    }

    public function setTrashed(bool $trashed): static
    {
        $this->trashedAt = $trashed ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function isSpam(): bool
    {
        return null !== $this->spamAt;
    }

    public function getSpamAt(): ?\DateTimeImmutable
    {
        return $this->spamAt;
    }

    public function setSpam(bool $spam): static
    {
        $this->spamAt = $spam ? ($this->spamAt ?? new \DateTimeImmutable()) : null;

        return $this;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function getDoneBy(): ?User
    {
        return $this->doneBy;
    }

    public function isDone(): bool
    {
        return null !== $this->doneAt;
    }

    public function markDone(?User $user): static
    {
        $this->doneAt = new \DateTimeImmutable();
        $this->doneBy = $user;
        $this->snoozedUntil = null;

        return $this;
    }

    public function reopen(): static
    {
        $this->doneAt = null;
        $this->doneBy = null;

        return $this;
    }

    public function getSnoozedUntil(): ?\DateTimeImmutable
    {
        return $this->snoozedUntil;
    }

    public function isSnoozed(): bool
    {
        return null !== $this->snoozedUntil && $this->snoozedUntil > new \DateTimeImmutable();
    }

    public function snooze(?\DateTimeImmutable $until): static
    {
        $this->snoozedUntil = $until;
        if (null !== $until) {
            $this->reopen();
        }

        return $this;
    }

    public function getThreadKey(): ?string
    {
        return $this->threadKey;
    }

    public function setThreadKey(?string $threadKey): static
    {
        $this->threadKey = null === $threadKey ? null : mb_substr($threadKey, 0, 255);

        return $this;
    }

    /**
     * Thread from the headers: first Message-ID in References, else In-Reply-To, else the own Message-ID.
     */
    public function deriveThreadKey(): ?string
    {
        if (null !== $this->referencesHeader && 1 === preg_match('/<([^>]+)>/', $this->referencesHeader, $m)) {
            return $m[1];
        }

        return $this->inReplyTo ?? $this->messageIdHeader;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function isCircular(): bool
    {
        return $this->circular;
    }

    public function setCircular(bool $circular): static
    {
        $this->circular = $circular;

        return $this;
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

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees->contains($user);
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

    /** @return Collection<int, User> */
    public function getRecipientUsers(): Collection
    {
        return $this->recipientUsers;
    }

    public function addRecipientUser(User $user): static
    {
        if (!$this->recipientUsers->contains($user)) {
            $this->recipientUsers->add($user);
        }

        return $this;
    }

    /** @return Collection<int, Attachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): static
    {
        $this->attachments->add($attachment);

        return $this;
    }

    /**
     * Attachments shown as files (not inline images referenced from the HTML).
     *
     * @return list<Attachment>
     */
    public function getVisibleAttachments(): array
    {
        return array_values($this->attachments->filter(static fn (Attachment $a): bool => !$a->isInline())->toArray());
    }

    public function hasAttachments(): bool
    {
        return [] !== $this->getVisibleAttachments();
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, MessageEvent> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * Records an entry in the message history (Verlauf).
     */
    public function log(MessageEventType $type, ?User $user, ?string $detail = null): static
    {
        $this->events->add(new MessageEvent($this, $type, $user, $detail));

        return $this;
    }
}
