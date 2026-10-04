<?php

declare(strict_types=1);

namespace App\Entity;

use App\Mail\ComposeData;
use App\Repository\DraftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unsent email of one person: saved automatically while writing, and queued for a few seconds
 * after "send" so that sending can be undone.
 */
#[ORM\Entity(repositoryClass: DraftRepository::class)]
#[ORM\Index(columns: ['send_at'])]
class Draft
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MailAccount $account;

    #[ORM\Column(type: Types::TEXT)]
    private string $toAddresses = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $ccAddresses = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $bccAddresses = '';

    #[ORM\Column(length: 500)]
    private string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** Message this draft replies to or forwards. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Message $original = null;

    #[ORM\Column]
    private bool $forward = false;

    #[ORM\Column]
    private bool $keepAttachments = true;

    /** @var list<array{name: string, mime: string, size: int, path: string}> files stored for a queued send */
    #[ORM\Column(type: Types::JSON)]
    private array $files = [];

    /** Set while queued: the message goes out at this time unless cancelled. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sendAt = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $sendError = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        MailAccount $account,
    ) {
        $this->account = $account;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getAccount(): MailAccount
    {
        return $this->account;
    }

    public function getTo(): string
    {
        return $this->toAddresses;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function getOriginal(): ?Message
    {
        return $this->original;
    }

    public function isForward(): bool
    {
        return $this->forward;
    }

    /**
     * @return list<array{name: string, mime: string, size: int, path: string}>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function addFile(string $name, string $mime, int $size, string $path): static
    {
        $this->files[] = ['name' => $name, 'mime' => $mime, 'size' => $size, 'path' => $path];

        return $this;
    }

    /**
     * Drops the stored files whose index is not listed.
     *
     * @param list<int> $keep
     *
     * @return list<string> storage paths of the dropped files
     */
    public function keepFiles(array $keep): array
    {
        $dropped = [];
        $kept = [];
        foreach ($this->files as $index => $file) {
            if (\in_array($index, $keep, true)) {
                $kept[] = $file;
            } else {
                $dropped[] = $file['path'];
            }
        }
        $this->files = $kept;

        return $dropped;
    }

    public function getSendAt(): ?\DateTimeImmutable
    {
        return $this->sendAt;
    }

    public function isQueued(): bool
    {
        return null !== $this->sendAt;
    }

    public function queue(\DateTimeImmutable $sendAt): static
    {
        $this->sendAt = $sendAt;
        $this->sendError = null;

        return $this;
    }

    /**
     * Back to a normal draft (cancelled, or sending failed).
     */
    public function unqueue(?string $error = null): static
    {
        $this->sendAt = null;
        $this->sendError = null === $error ? null : mb_substr($error, 0, 1000);

        return $this;
    }

    public function getSendError(): ?string
    {
        return $this->sendError;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Takes over the form state.
     */
    public function apply(ComposeData $data): static
    {
        if (null !== $data->account) {
            $this->account = $data->account;
        }
        $this->toAddresses = $data->to;
        $this->ccAddresses = $data->cc;
        $this->bccAddresses = $data->bcc;
        $this->subject = mb_substr($data->subject, 0, 500);
        $this->body = $data->body;
        $this->project = $data->project;
        $this->original = $data->original;
        $this->forward = $data->forward;
        $this->keepAttachments = $data->keepAttachments;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toComposeData(): ComposeData
    {
        $data = new ComposeData();
        $data->account = $this->account;
        $data->to = $this->toAddresses;
        $data->cc = $this->ccAddresses;
        $data->bcc = $this->bccAddresses;
        $data->subject = $this->subject;
        $data->body = $this->body;
        $data->project = $this->project;
        $data->original = $this->original;
        $data->forward = $this->forward;
        $data->keepAttachments = $this->keepAttachments;

        return $data;
    }
}
