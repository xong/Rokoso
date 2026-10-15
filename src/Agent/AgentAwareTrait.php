<?php

declare(strict_types=1);

namespace App\Agent;

use Doctrine\ORM\Mapping as ORM;

/**
 * @see AgentAware
 */
trait AgentAwareTrait
{
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $agentName = null;

    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    public function setAgentName(?string $agentName): static
    {
        $this->agentName = null === $agentName ? null : mb_substr($agentName, 0, 80);

        return $this;
    }
}
