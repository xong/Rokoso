<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
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
     * Organizations the user is a full member of; with $feature only those using the area (choices when creating).
     *
     * @return list<Organization>
     */
    public function findForUser(User $user, ?Feature $feature = null): array
    {
        /** @var list<Organization> $organizations */
        $organizations = $this->createQueryBuilder('o')
            ->join('o.memberships', 'm')
            ->andWhere('m.user = :user AND '.Membership::fullDql('m'))
            ->setParameter('user', $user)
            ->orderBy('o.name')
            ->getQuery()
            ->getResult();

        return null === $feature ? $organizations : array_values(array_filter($organizations, static fn (Organization $o): bool => $o->hasFeature($feature)));
    }
}
