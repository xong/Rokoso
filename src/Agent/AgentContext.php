<?php

declare(strict_types=1);

namespace App\Agent;

use App\Entity\AgentToken;
use App\Entity\Organization;
use App\Enum\Feature;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The AI agent of the current request (set by {@see AgentTokenAuthenticator}). While an agent works, content of
 * organizations that do not allow agents ({@see Feature::Agents}) is treated like a switched-off area.
 */
final readonly class AgentContext
{
    public const string ATTRIBUTE = '_agent_token';

    public function __construct(private RequestStack $requests)
    {
    }

    public function current(): ?AgentToken
    {
        $token = $this->requests->getMainRequest()?->attributes->get(self::ATTRIBUTE);

        return $token instanceof AgentToken ? $token : null;
    }

    public function isActive(): bool
    {
        return null !== $this->current();
    }

    /**
     * Name shown at entries the agent created ("über KI-Agent …").
     */
    public function name(): ?string
    {
        $token = $this->current();

        return null === $token ? null : ($token->getClient()?->getName() ?? $token->getName());
    }

    /**
     * Whether the agent may work with content of the organization (personal content without organization always).
     */
    public function allows(?Organization $organization): bool
    {
        return null === $organization || !$this->isActive() || $organization->hasFeature(Feature::Agents);
    }
}
