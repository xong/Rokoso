<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * File of a message. Content lives in var/storage (see AttachmentStorage).
 */
#[ORM\Entity]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'attachments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Message $message,
        #[ORM\Column(length: 255)]
        private string $filename,
        #[ORM\Column(length: 120)]
        private string $mimeType,
        #[ORM\Column]
        private int $size,
        #[ORM\Column(length: 255)]
        private string $storagePath,
        /** Content-ID for inline images referenced as cid: in the HTML body */
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $contentId = null,
    ) {
        $this->filename = mb_substr($filename, 0, 255);
        $this->mimeType = mb_substr($mimeType, 0, 120);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getContentId(): ?string
    {
        return $this->contentId;
    }

    public function isInline(): bool
    {
        return null !== $this->contentId && str_starts_with($this->mimeType, 'image/');
    }
}
