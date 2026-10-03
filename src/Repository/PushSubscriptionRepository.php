<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['endpointHash' => PushSubscription::hash($endpoint)]);
    }
}
