<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Active (not archived) projects of the user's organizations, the projects released to them as a guest
     * and their own projects without organization; $keep is included even if archived (current selection).
     *
     * @return list<Project>
     */
    public function findVisibleFor(User $user, ?Project $keep = null): array
    {
        $qb = $this->visibleQuery($user)->orderBy('p.name');
        if (null !== $keep) {
            $qb->andWhere('p.archivedAt IS NULL OR p = :keep')->setParameter('keep', $keep);
        } else {
            $qb->andWhere('p.archivedAt IS NULL');
        }

        /* @var list<Project> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Project>
     */
    public function findArchivedFor(User $user): array
    {
        /* @var list<Project> */
        return $this->visibleQuery($user)->andWhere('p.archivedAt IS NOT NULL')->orderBy('p.archivedAt', 'DESC')->getQuery()->getResult();
    }

    public function visibleQuery(User $user, string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin(Membership::class, 'pm', 'WITH', \sprintf('pm.organization = %s.organization AND pm.user = :viewer AND ', $alias).Membership::fullDql('pm'))
            ->andWhere(\sprintf('pm.id IS NOT NULL OR (%1$s.organization IS NULL AND %1$s.createdBy = :viewer) OR ', $alias).Membership::guestDql($alias))
            ->setParameter('viewer', $user);
    }
}
