<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Meeting;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meeting>
 */
class MeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
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
            ->orderBy('m.startsAt', $past ? \SortDirection::Descending : \SortDirection::Ascending)
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

    /**
     * Visible meetings with one of the addresses among the guests, newest first.
     *
     * @param list<string> $addresses
     *
     * @return list<Meeting>
     */
    public function findWithGuest(User $user, array $addresses, int $limit = 20): array
    {
        if ([] === $addresses) {
            return [];
        }
        $qb = $this->visibleQuery($user)->orderBy('m.startsAt', \SortDirection::Descending)->setMaxResults($limit);
        $or = $qb->expr()->orX();
        foreach ($addresses as $i => $address) {
            $or->add('m.guestEmails LIKE :g'.$i);
            $qb->setParameter('g'.$i, '%'.addcslashes($address, '%_\\').'%');
        }

        /* @var list<Meeting> */
        return $qb->andWhere($or)->getQuery()->getResult();
    }

    public function visibleQuery(User $user, string $alias = 'm'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->join(Membership::class, $alias.'_vm', 'ON', $alias.'_vm.organization = '.$alias.'.organization AND '.$alias.'_vm.user = :viewer AND '.Membership::fullDql($alias.'_vm'))
            ->andWhere($this->features->dql('IDENTITY('.$alias.'.organization)', Feature::Meetings))
            ->setParameter('viewer', $user);
    }
}
