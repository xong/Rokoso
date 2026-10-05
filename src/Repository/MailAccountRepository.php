<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MailAccount;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Enum\OrganizationRole;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailAccount>
 */
class MailAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
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
            ->join(Membership::class, 'm', 'ON', 'm.organization = a.organization AND m.user = :user AND '.Membership::fullDql('m'))
            ->andWhere($this->features->dql('IDENTITY(a.organization)', Feature::Mail))
            ->setParameter('user', $user)
            ->orderBy('a.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enabled accounts of organizations the user administers whose fetching fails or stalls.
     *
     * @return list<MailAccount>
     */
    public function findWithSyncProblems(User $user): array
    {
        /** @var list<MailAccount> $accounts */
        $accounts = $this->createQueryBuilder('a')
            ->join(Membership::class, 'm', 'ON', 'm.organization = a.organization AND m.user = :user AND m.role = :admin AND '.Membership::fullDql('m'))
            ->andWhere('a.enabled = true')
            ->andWhere($this->features->dql('IDENTITY(a.organization)', Feature::Mail))
            ->setParameter('user', $user)
            ->setParameter('admin', OrganizationRole::Admin)
            ->orderBy('a.name')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($accounts, static fn (MailAccount $a): bool => null !== $a->getSyncProblem()));
    }

    /**
     * Marks the account as being fetched if its last fetch is older than $due; false if another request was faster.
     */
    public function claimSync(MailAccount $account, \DateTimeImmutable $due): bool
    {
        return 1 === $this->createQueryBuilder('a')
            ->update()
            ->set('a.lastSyncAt', ':now')
            ->where('a.id = :id AND (a.lastSyncAt IS NULL OR a.lastSyncAt < :due)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $account->getId())
            ->setParameter('due', $due)
            ->getQuery()
            ->execute();
    }
}
