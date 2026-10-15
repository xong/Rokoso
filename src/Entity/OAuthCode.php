<?php

declare(strict_types=1);

namespace App\Entity;

use App\Agent\AgentScope;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Authorization code after the person agreed (valid ten minutes, single use, PKCE S256).
 */
#[ORM\Entity]
class OAuthCode
{
    public const int LIFETIME = 600;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $codeHash = '';

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $scopes;

    /**
     * @param list<AgentScope> $scopes
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private OAuthClient $client,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(type: Types::TEXT)]
        private string $redirectUri,
        #[ORM\Column(length: 128)]
        private string $codeChallenge,
        array $scopes,
    ) {
        $this->scopes = array_map(static fn (AgentScope $s): string => $s->value, $scopes);
        $this->expiresAt = new \DateTimeImmutable(\sprintf('+%d seconds', self::LIFETIME));
    }

    public function issue(): string
    {
        $code = bin2hex(random_bytes(24));
        $this->codeHash = AgentToken::hash($code);

        return $code;
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->expiresAt <= $now;
    }

    public function verifies(string $verifier): bool
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return '' !== $verifier && hash_equals($this->codeChallenge, $challenge);
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * @return list<AgentScope>
     */
    public function getScopes(): array
    {
        return array_values(array_filter(array_map(AgentScope::tryFrom(...), $this->scopes)));
    }
}
