<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactGroup;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactGroup>
 */
class ContactGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, ContactGroup::class);
    }

    /**
     * Groups of all organizations the user is a full member of.
     *
     * @return list<ContactGroup>
     */
    public function findForUser(User $user): array
    {
        /* @var list<ContactGroup> */
        return $this->createQueryBuilder('g')
            ->addSelect('o')
            ->join('g.organization', 'o')
            ->join(Membership::class, 'cm', 'WITH', 'cm.organization = o AND cm.user = :viewer AND '.Membership::fullDql('cm'))
            ->andWhere($this->features->dql('o.id', Feature::Contacts))
            ->setParameter('viewer', $user)
            ->orderBy('o.name')
            ->addOrderBy('g.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * Groups externals may subscribe to on the public page.
     *
     * @return list<ContactGroup>
     */
    public function findPublicSubscribe(Organization $organization): array
    {
        /* @var list<ContactGroup> */
        return $this->findBy(['organization' => $organization, 'publicSubscribe' => true], ['name' => 'ASC']);
    }
}
