<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LegalDocument;
use App\Repository\LegalTextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Privacy policy or imprint of the installation (Markdown), public under /privacy and /imprint and linked in every system mail.
 */
#[ORM\Entity(repositoryClass: LegalTextRepository::class)]
class LegalText
{
    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 20, enumType: LegalDocument::class)]
        private LegalDocument $document,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $content = trim(str_replace("\r\n", "\n", $content));
        if ($content !== $this->content) {
            $this->content = $content;
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
