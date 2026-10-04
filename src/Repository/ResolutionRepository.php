<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Resolution;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resolution>
 */
class ResolutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resolution::class);
    }

    /**
     * Resolutions of the user's organizations, newest first.
     *
     * @return list<Resolution>
     */
    public function search(User $user, ?string $query = null, ?int $year = null, ?int $projectId = null, ?int $organizationId = null, ?int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join(Membership::class, 'vm', 'WITH', 'vm.organization = r.organization AND vm.user = :viewer AND '.Membership::fullDql('vm'))
            ->setParameter('viewer', $user)
            ->orderBy('r.decidedOn', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit);
        $query = trim((string) $query);
        if ('' !== $query) {
            $qb->andWhere('r.title LIKE :q OR r.text LIKE :q OR r.number LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $year) {
            $qb->andWhere('r.decidedOn >= :from AND r.decidedOn < :to')
                ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
                ->setParameter('to', new \DateTimeImmutable(($year + 1).'-01-01'));
        }
        if (null !== $projectId) {
            $qb->andWhere('r.project = :project')->setParameter('project', $projectId);
        }
        if (null !== $organizationId) {
            $qb->andWhere('r.organization = :org')->setParameter('org', $organizationId);
        }

        /* @var list<Resolution> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Years with resolutions visible to the user, newest first.
     *
     * @return list<int>
     */
    public function years(User $user): array
    {
        $dates = $this->createQueryBuilder('r')
            ->select('r.decidedOn')
            ->join(Membership::class, 'vm', 'WITH', 'vm.organization = r.organization AND vm.user = :viewer AND '.Membership::fullDql('vm'))
            ->setParameter('viewer', $user)
            ->getQuery()
            ->getSingleColumnResult();
        $years = array_values(array_unique(array_map(static fn (mixed $d): int => (int) ($d instanceof \DateTimeInterface ? $d->format('Y') : substr((string) $d, 0, 4)), $dates)));
        rsort($years);

        return $years;
    }

    /** Next running number "{year}/{n}" within the organization. */
    public function nextNumber(Organization $organization, int $year): string
    {
        $numbers = $this->createQueryBuilder('r')
            ->select('r.number')
            ->andWhere('r.organization = :org AND r.number LIKE :prefix')
            ->setParameter('org', $organization)
            ->setParameter('prefix', $year.'/%')
            ->getQuery()
            ->getSingleColumnResult();
        $max = 0;
        foreach ($numbers as $number) {
            $max = max($max, (int) substr((string) $number, \strlen($year.'/')));
        }

        return $year.'/'.($max + 1);
    }
}
