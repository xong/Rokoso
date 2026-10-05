<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConfidentialCase;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfidentialCase>
 */
final class ConfidentialCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfidentialCase::class);
    }

    /**
     * Cases of the organizations in which the user is a confidant.
     */
    public function visibleQuery(User $user, string $alias = 'c'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->join(Membership::class, 'cm', 'WITH', \sprintf('cm.organization = %s.organization AND cm.user = :confidant AND cm.confidant = true AND %s', $alias, Membership::fullDql('cm')))
            ->setParameter('confidant', $user);
    }

    /**
     * @return list<ConfidentialCase>
     */
    public function findForConfidant(User $user, bool $closed): array
    {
        /* @var list<ConfidentialCase> */
        return $this->visibleQuery($user)
            ->andWhere($closed ? 'c.closedOn IS NOT NULL' : 'c.closedOn IS NULL')
            ->orderBy('c.staffUnread', 'DESC')->addOrderBy('c.lastActivityOn', 'DESC')->addOrderBy('c.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->visibleQuery($user)->select('COUNT(c.id)')
            ->andWhere('c.staffUnread = true')
            ->getQuery()->getSingleScalarResult();
    }

    public function isConfidantAnywhere(User $user): bool
    {
        return (int) $this->getEntityManager()->createQueryBuilder()->select('COUNT(m.id)')->from(Membership::class, 'm')
            ->where('m.user = :user AND m.confidant = true AND '.Membership::fullDql('m'))
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Deletes the conversations without messages since the date (messages go with the database cascade).
     */
    public function deleteInactiveSince(Organization $organization, \DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('c')->delete()
            ->where('c.organization = :org AND c.lastActivityOn < :before')
            ->setParameter('org', $organization)->setParameter('before', $before, 'date_immutable')
            ->getQuery()->execute();
    }
}
