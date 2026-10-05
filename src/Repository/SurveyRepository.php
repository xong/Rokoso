<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Survey;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Survey>
 */
class SurveyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, Survey::class);
    }

    /**
     * Surveys of the user's organizations, newest first.
     *
     * @return list<Survey>
     */
    public function findForUser(User $user): array
    {
        /* @var list<Survey> */
        return $this->createQueryBuilder('s')
            ->addSelect('o')
            ->join('s.organization', 'o')
            ->join(Membership::class, 'vm', 'ON', 'vm.organization = o AND vm.user = :viewer AND '.Membership::fullDql('vm'))
            ->andWhere($this->features->dql('o.id', Feature::Surveys))
            ->setParameter('viewer', $user)
            ->orderBy('s.createdAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Open surveys shown on the public page.
     *
     * @return list<Survey>
     */
    public function findListed(Organization $organization): array
    {
        /* @var list<Survey> */
        return $this->createQueryBuilder('s')
            ->where('s.organization = :org AND s.listed = true AND s.openedAt IS NOT NULL AND s.closedAt IS NULL')
            ->setParameter('org', $organization)
            ->orderBy('s.openedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
