<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SecurityEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityEvent>
 */
class SecurityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEvent::class);
    }

    /**
     * Newest first; all accounts when $user is null (platform admin).
     *
     * @return list<SecurityEvent>
     */
    public function findLatest(?User $user, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')->addSelect('u')
            ->leftJoin('e.actor', 'a')->addSelect('a')
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $user) {
            $qb->andWhere('e.user = :user')->setParameter('user', $user);
        }

        /* @var list<SecurityEvent> */
        return $qb->getQuery()->getResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
