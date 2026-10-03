<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Meeting;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meeting>
 */
class MeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meeting::class);
    }

    /**
     * Meetings of the user's organizations: upcoming ascending, past descending.
     *
     * @return list<Meeting>
     */
    public function findForUser(User $user, bool $past = false, ?int $organizationId = null, ?int $projectId = null, ?int $limit = null): array
    {
        $qb = $this->visibleQuery($user)
            ->andWhere($past ? 'm.startsAt < :today' : 'm.startsAt >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('m.startsAt', $past ? 'DESC' : 'ASC')
            ->setMaxResults($limit);
        if (null !== $organizationId) {
            $qb->andWhere('m.organization = :org')->setParameter('org', $organizationId);
        }
        if (null !== $projectId) {
            $qb->andWhere('m.project = :project')->setParameter('project', $projectId);
        }

        /* @var list<Meeting> */
        return $qb->getQuery()->getResult();
    }

    public function visibleQuery(User $user, string $alias = 'm'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->join(Membership::class, $alias.'_vm', 'WITH', $alias.'_vm.organization = '.$alias.'.organization AND '.$alias.'_vm.user = :viewer')
            ->setParameter('viewer', $user);
    }
}
