<?php

declare(strict_types=1);

namespace App\Entity;

use App\Agent\AgentScope;
use App\Repository\AgentTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Access of an AI agent with the rights of one person: a personal token (shown once) or a connection
 * made via OAuth (short-lived access token plus refresh token). Only hashes are stored.
 * Logging out everywhere, blocking and deleting the account end all agent access (session stamp).
 */
#[ORM\Entity(repositoryClass: AgentTokenRepository::class)]
class AgentToken
{
    /** Validity of personal tokens in days, 0 = unlimited */
    public const array DAY_CHOICES = [30, 90, 365, 0];

    public const string PERSONAL_PREFIX = 'rks_';
    public const string ACCESS_PREFIX = 'rka_';
    public const string REFRESH_PREFIX = 'rkr_';
    public const int ACCESS_LIFETIME = 3600;
    public const int REFRESH_DAYS = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    /** last characters of the personal token, to tell them apart */
    #[ORM\Column(length: 8)]
    private string $hint = '';

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $refreshHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $refreshExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 16)]
    private string $sessionStamp;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $scopes = [];

    /**
     * @param list<AgentScope> $scopes
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 80)]
        private string $name,
        array $scopes,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?OAuthClient $client = null,
    ) {
        $this->name = mb_substr(trim($name), 0, 80);
        $this->scopes = array_map(static fn (AgentScope $s): string => $s->value, $scopes);
        $this->createdAt = new \DateTimeImmutable();
        $this->sessionStamp = $user->getSessionStamp();
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * New secret of a personal token; returned once, only its hash is kept.
     */
    public function issuePersonal(int $days): string
    {
        $secret = self::PERSONAL_PREFIX.bin2hex(random_bytes(24));
        $this->tokenHash = self::hash($secret);
        $this->hint = substr($secret, -4);
        $this->expiresAt = $days > 0 ? $this->createdAt->modify(\sprintf('+%d days', $days)) : null;

        return $secret;
    }

    /**
     * New access and refresh token of an OAuth connection (the old refresh token stops working).
     *
     * @return array{access: string, refresh: string}
     */
    public function issueOAuth(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $access = self::ACCESS_PREFIX.bin2hex(random_bytes(24));
        $refresh = self::REFRESH_PREFIX.bin2hex(random_bytes(24));
        $this->tokenHash = self::hash($access);
        $this->refreshHash = self::hash($refresh);
        $this->expiresAt = $now->modify(\sprintf('+%d seconds', self::ACCESS_LIFETIME));
        $this->refreshExpiresAt = $now->modify(\sprintf('+%d days', self::REFRESH_DAYS));

        return ['access' => $access, 'refresh' => $refresh];
    }

    /**
     * Usable: not expired, the account is active and nobody logged out everywhere since.
     */
    public function isValid(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return (null === $this->expiresAt || $this->expiresAt > $now) && $this->isCurrent();
    }

    public function canRefresh(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return null !== $this->refreshExpiresAt && $this->refreshExpiresAt > $now && $this->isCurrent();
    }

    private function isCurrent(): bool
    {
        return $this->sessionStamp === $this->user->getSessionStamp() && $this->user->isActive();
    }

    public function allows(AgentScope $scope): bool
    {
        return \in_array($scope->value, $this->scopes, true) || \in_array($scope->writeScope()->value, $this->scopes, true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClient(): ?OAuthClient
    {
        return $this->client;
    }

    public function isPersonal(): bool
    {
        return null === $this->client;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    /**
     * @return list<AgentScope>
     */
    public function getScopes(): array
    {
        return array_values(array_filter(array_map(AgentScope::tryFrom(...), $this->scopes)));
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * End of the whole access: the token itself, for OAuth connections the refresh token.
     */
    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->isPersonal() ? $this->expiresAt : $this->refreshExpiresAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * Records the use at most once a minute (avoids a write per request).
     */
    public function touch(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        if (null !== $this->lastUsedAt && $this->lastUsedAt > $now->modify('-1 minute')) {
            return false;
        }
        $this->lastUsedAt = $now;

        return true;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
