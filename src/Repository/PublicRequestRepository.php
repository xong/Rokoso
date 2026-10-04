<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PublicHit;
use App\Entity\PublicRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicRequest>
 */
class PublicRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicRequest::class);
    }

    /** Removes throttling hits older than a day (expired requests are removed with their files by PublicSubmissionHandler) */
    public function purgeHits(): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->delete(PublicHit::class, 'h')
            ->where('h.createdAt < :limit')
            ->setParameter('limit', new \DateTimeImmutable('-1 day'))
            ->getQuery()->execute();
    }
}
