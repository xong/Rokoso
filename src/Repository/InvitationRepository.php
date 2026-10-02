<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * @return list<Invitation>
     */
    public function findPending(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['createdAt' => 'DESC']);
    }
}
