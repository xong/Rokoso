<?php

declare(strict_types=1);

namespace App\Agent;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Marks entries an AI agent creates (comments, posts, drafts, history entries …) with the agent's name.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class AgentMarkerListener
{
    public function __construct(private AgentContext $agent)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof AgentAware && null === $entity->getAgentName()) {
            $entity->setAgentName($this->agent->name());
        }
    }
}
