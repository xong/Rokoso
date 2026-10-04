<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\Membership;
use App\Entity\Organization;
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
            ->leftJoin(Membership::class, 'cm', 'WITH', 'cm.organization = c.organization AND cm.user = :viewer AND '.Membership::fullDql('cm'))
            ->andWhere('cm.id IS NOT NULL OR (c.organization IS NULL AND c.createdBy = :viewer)')
            ->setParameter('viewer', $user);
    }

    /**
     * @return list<Contact>
     */
    public function search(User $user, string $query = '', ?ContactGroup $group = null): array
    {
        $qb = $this->visibleQuery($user)
            ->addOrderBy('c.lastName')
            ->addOrderBy('c.company')
            ->addOrderBy('c.firstName');
        if ('' !== $query) {
            $qb->andWhere('c.firstName LIKE :q OR c.lastName LIKE :q OR c.company LIKE :q OR c.email LIKE :q OR c.position LIKE :q OR c.tags LIKE :q OR c.city LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }

        if (null !== $group) {
            $qb->andWhere(':group MEMBER OF c.groups')->setParameter('group', $group);
        }

        /* @var list<Contact> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Contact>
     */
    public function findInOrganization(Organization $organization): array
    {
        /* @var list<Contact> */
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('c.lastName')
            ->addOrderBy('c.company')
            ->addOrderBy('c.firstName')
            ->getQuery()
            ->getResult();
    }

    /**
     * Other visible contacts of the same institution.
     *
     * @return list<Contact>
     */
    public function findColleagues(Contact $contact, User $user): array
    {
        if (null === $contact->getCompany()) {
            return [];
        }

        /* @var list<Contact> */
        return $this->visibleQuery($user)
            ->andWhere('c.company = :company AND c <> :contact')
            ->setParameter('company', $contact->getCompany())
            ->setParameter('contact', $contact)
            ->orderBy('c.lastName')
            ->addOrderBy('c.firstName')
            ->getQuery()
            ->getResult();
    }

    /**
     * Distinct values of a text field over the visible contacts, for input suggestions.
     *
     * @param 'company'|'position' $field
     *
     * @return list<string>
     */
    public function distinctValues(User $user, string $field): array
    {
        $rows = $this->visibleQuery($user)
            ->select('DISTINCT c.'.$field.' AS value')
            ->andWhere('c.'.$field.' IS NOT NULL')
            ->orderBy('value')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($rows, is_string(...)));
    }

    /**
     * All email addresses of the visible contacts (lower case, as keys).
     *
     * @return array<string, true>
     */
    public function knownEmails(User $user): array
    {
        $known = [];
        foreach ($this->findWithEmail($user) as $contact) {
            foreach ($contact->getEmails() as $email) {
                $known[$email] = true;
            }
        }

        return $known;
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
