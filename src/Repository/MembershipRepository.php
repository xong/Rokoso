<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /**
     * Users who share at least one organization with the given user (incl. the user).
     *
     * @return list<User>
     */
    public function colleaguesOf(User $user): array
    {
        /* @var list<User> */
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->join(Membership::class, 'm', 'ON', 'm.user = u AND '.Membership::fullDql('m'))
            ->join(Membership::class, 'mine', 'ON', 'mine.organization = m.organization AND mine.user = :user AND '.Membership::fullDql('mine'))
            ->setParameter('user', $user)
            ->orderBy('u.name')
            ->getQuery()
            ->getResult();
    }
}
