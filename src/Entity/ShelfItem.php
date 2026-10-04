<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShelfItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry in a user's personal shelf ("Merken"): a reference to a shared file, an email
 * attachment, a message or a forum topic, no copy. Disappears together with its target.
 */
#[ORM\Entity(repositoryClass: ShelfItemRepository::class)]
#[ORM\UniqueConstraint(name: 'shelf_owner_file', columns: ['owner_id', 'file_id'])]
#[ORM\UniqueConstraint(name: 'shelf_owner_attachment', columns: ['owner_id', 'attachment_id'])]
#[ORM\UniqueConstraint(name: 'shelf_owner_message', columns: ['owner_id', 'message_id'])]
#[ORM\UniqueConstraint(name: 'shelf_owner_topic', columns: ['owner_id', 'topic_id'])]
class ShelfItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?StoredFile $file = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Attachment $attachment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Message $message = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ForumTopic $topic = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function for(User $owner, StoredFile|Attachment|Message|ForumTopic $target): self
    {
        $item = new self($owner);
        match (true) {
            $target instanceof StoredFile => $item->file = $target,
            $target instanceof Attachment => $item->attachment = $target,
            $target instanceof Message => $item->message = $target,
            $target instanceof ForumTopic => $item->topic = $target,
        };

        return $item;
    }

    public static function forFile(User $owner, StoredFile $file): self
    {
        return self::for($owner, $file);
    }

    public static function forAttachment(User $owner, Attachment $attachment): self
    {
        return self::for($owner, $attachment);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getFile(): ?StoredFile
    {
        return $this->file;
    }

    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function getTopic(): ?ForumTopic
    {
        return $this->topic;
    }

    public function getTarget(): StoredFile|Attachment|Message|ForumTopic
    {
        return $this->file ?? $this->attachment ?? $this->message ?? $this->topic ?? throw new \LogicException('Shelf item without target');
    }

    /**
     * The file behind the entry, if it is a shared file or an attachment.
     */
    public function getFileTarget(): StoredFile|Attachment|null
    {
        return $this->file ?? $this->attachment;
    }

    public function isFile(): bool
    {
        return null !== $this->getFileTarget();
    }

    public function getFilename(): string
    {
        return $this->getFileTarget()?->getFilename() ?? '';
    }

    public function getMimeType(): string
    {
        return $this->getFileTarget()?->getMimeType() ?? '';
    }

    public function getSize(): int
    {
        return $this->getFileTarget()?->getSize() ?? 0;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
