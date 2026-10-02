<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CalendarItem;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\Recurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarItem>
 */
class CalendarItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarItem::class);
    }

    /**
     * Visible items that may have an occurrence in [from, to): single items overlapping the range
     * plus all recurring items starting before its end (expanded later).
     *
     * @return list<CalendarItem>
     */
    public function findCandidates(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $projectId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin(Membership::class, 'im', 'WITH', 'im.organization = i.organization AND im.user = :viewer')
            ->andWhere('im.id IS NOT NULL OR (i.organization IS NULL AND i.createdBy = :viewer)')
            ->andWhere('i.startsAt < :to')
            ->andWhere('i.recurrence != :none OR COALESCE(i.endsAt, i.startsAt) >= :fromDay')
            ->setParameter('viewer', $user)
            ->setParameter('to', $to)
            ->setParameter('fromDay', $from->modify('-1 day'))
            ->setParameter('none', Recurrence::None->value)
            ->orderBy('i.startsAt');
        if (null !== $projectId) {
            $qb->andWhere('i.project = :project')->setParameter('project', $projectId);
        }

        /* @var list<CalendarItem> */
        return $qb->getQuery()->getResult();
    }
}
