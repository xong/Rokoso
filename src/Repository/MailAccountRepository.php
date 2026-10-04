<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MailAccount;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailAccount>
 */
class MailAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailAccount::class);
    }

    /**
     * @return list<MailAccount>
     */
    public function findForOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['name' => 'ASC']);
    }

    /**
     * Accounts the user may read and send from (member of the owning organization).
     *
     * @return list<MailAccount>
     */
    public function findForUser(User $user): array
    {
        /* @var list<MailAccount> */
        return $this->createQueryBuilder('a')
            ->join(Membership::class, 'm', 'WITH', 'm.organization = a.organization AND m.user = :user AND '.Membership::fullDql('m'))
            ->setParameter('user', $user)
            ->orderBy('a.name')
            ->getQuery()
            ->getResult();
    }
}
