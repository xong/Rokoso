<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * @return list<Organization>
     */
    public function findForUser(User $user): array
    {
        /* @var list<Organization> */
        return $this->createQueryBuilder('o')
            ->join('o.memberships', 'm')
            ->andWhere('m.user = :user AND '.Membership::fullDql('m'))
            ->setParameter('user', $user)
            ->orderBy('o.name')
            ->getQuery()
            ->getResult();
    }
}
