<?php

declare(strict_types=1);

namespace App\Agent;

/**
 * Entry that shows "über KI-Agent" when an AI agent created it; set on persist by {@see AgentMarkerListener}.
 */
interface AgentAware
{
    public function getAgentName(): ?string;

    public function setAgentName(?string $agentName): static;
}
