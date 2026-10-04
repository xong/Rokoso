<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CalendarItem;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\Recurrence;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        $qb = $this->visibleQuery()
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

    /**
     * Visible tasks, open ones by due date (undated last). "Mine": assigned to the user,
     * or created by them without anyone assigned.
     *
     * @return list<CalendarItem>
     */
    public function findTasks(User $user, bool $mine = false, ?int $projectId = null, bool $withDone = true, ?int $limit = null): array
    {
        $qb = $this->visibleQuery()
            ->addSelect('CASE WHEN i.startsAt IS NULL THEN 1 ELSE 0 END AS HIDDEN undated')
            ->andWhere('i.type = :task')
            ->setParameter('viewer', $user)
            ->setParameter('task', CalendarItemType::Task->value)
            ->orderBy('undated')
            ->addOrderBy('i.startsAt')
            ->addOrderBy('i.id');
        if ($mine) {
            $qb->andWhere(':viewer MEMBER OF i.assignees OR (i.assignees IS EMPTY AND i.createdBy = :viewer)');
        }
        if (null !== $projectId) {
            $qb->andWhere('i.project = :project')->setParameter('project', $projectId);
        }
        if (!$withDone) {
            $qb->andWhere('i.status != :done')->setParameter('done', TaskStatus::Done->value);
        }
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /* @var list<CalendarItem> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Items of the viewer's organizations (full members), of projects released to them as a guest, and their own private ones.
     */
    /**
     * Public events of the organization that may have an occurrence in [from, to).
     *
     * @return list<CalendarItem>
     */
    public function findPublicCandidates(Organization $organization, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /* @var list<CalendarItem> */
        return $this->createQueryBuilder('i')
            ->where('i.organization = :org AND i.public = true AND i.type != :task')
            ->andWhere('i.startsAt < :to')
            ->andWhere('i.recurrence != :none OR COALESCE(i.endsAt, i.startsAt) >= :fromDay')
            ->setParameter('org', $organization)
            ->setParameter('task', CalendarItemType::Task->value)
            ->setParameter('to', $to)
            ->setParameter('fromDay', $from->modify('-1 day'))
            ->setParameter('none', Recurrence::None->value)
            ->orderBy('i.startsAt')
            ->getQuery()
            ->getResult();
    }

    private function visibleQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('i')
            ->leftJoin(Membership::class, 'im', 'WITH', 'im.organization = i.organization AND im.user = :viewer AND '.Membership::fullDql('im'))
            ->andWhere('im.id IS NOT NULL OR (i.organization IS NULL AND i.createdBy = :viewer) OR '.Membership::guestDql('i.project'));
    }
}
