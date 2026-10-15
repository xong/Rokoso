<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Client of an AI app (e.g. claude.ai) registered by itself (OAuth dynamic client registration).
 * Public client without secret: the authorization code is protected by PKCE.
 */
#[ORM\Entity]
class OAuthClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $clientId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<string> $redirectUris
     */
    public function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(type: Types::JSON)]
        private array $redirectUris,
    ) {
        $this->name = mb_substr(trim($name), 0, 100);
        $this->clientId = 'rkc_'.bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    public function hasRedirectUri(string $uri): bool
    {
        return \in_array($uri, $this->redirectUris, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
