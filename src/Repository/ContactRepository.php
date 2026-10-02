<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin(Membership::class, 'cm', 'WITH', 'cm.organization = c.organization AND cm.user = :viewer')
            ->andWhere('cm.id IS NOT NULL OR (c.organization IS NULL AND c.createdBy = :viewer)')
            ->setParameter('viewer', $user);
    }

    /**
     * @return list<Contact>
     */
    public function search(User $user, string $query = ''): array
    {
        $qb = $this->visibleQuery($user)
            ->addOrderBy('c.lastName')
            ->addOrderBy('c.company')
            ->addOrderBy('c.firstName');
        if ('' !== $query) {
            $qb->andWhere('c.firstName LIKE :q OR c.lastName LIKE :q OR c.company LIKE :q OR c.email LIKE :q OR c.position LIKE :q OR c.tags LIKE :q OR c.city LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }

        /* @var list<Contact> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Contacts with email address, for the sender avatars.
     *
     * @return list<Contact>
     */
    public function findWithEmail(User $user): array
    {
        /* @var list<Contact> */
        return $this->visibleQuery($user)
            ->andWhere('c.email IS NOT NULL OR c.email2 IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
