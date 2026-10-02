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
     * Projects of the user's organizations plus the user's own projects without organization.
     *
     * @return list<Project>
     */
    public function findVisibleFor(User $user): array
    {
        /* @var list<Project> */
        return $this->visibleQuery($user)->orderBy('p.name')->getQuery()->getResult();
    }

    public function visibleQuery(User $user, string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin(Membership::class, 'pm', 'WITH', \sprintf('pm.organization = %s.organization AND pm.user = :viewer', $alias))
            ->andWhere(\sprintf('pm.id IS NOT NULL OR (%1$s.organization IS NULL AND %1$s.createdBy = :viewer)', $alias))
            ->setParameter('viewer', $user);
    }
}
