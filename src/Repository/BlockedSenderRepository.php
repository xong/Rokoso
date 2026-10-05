<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlockedSender;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedSender>
 */
class BlockedSenderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedSender::class);
    }

    /**
     * @return list<BlockedSender>
     */
    public function findForOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['address' => 'ASC']);
    }

    public function isBlocked(Organization $organization, string $fromAddress): bool
    {
        $address = BlockedSender::normalize($fromAddress);
        if ('' === $address) {
            return false;
        }
        $domain = strrchr($address, '@');

        return [] !== $this->findBy(['organization' => $organization, 'address' => false === $domain ? [$address] : [$address, $domain]], limit: 1);
    }

    public function findOneFor(Organization $organization, string $fromAddress): ?BlockedSender
    {
        return $this->findOneBy(['organization' => $organization, 'address' => BlockedSender::normalize($fromAddress)]);
    }
}
