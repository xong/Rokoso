<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MailRule;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailRule>
 */
class MailRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailRule::class);
    }

    /**
     * @return list<MailRule>
     */
    public function findForOrganization(Organization $organization, bool $enabledOnly = false): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('r.id', \SortDirection::Ascending);
        if ($enabledOnly) {
            $qb->andWhere('r.enabled = true');
        }

        /* @var list<MailRule> */
        return $qb->getQuery()->getResult();
    }
}
