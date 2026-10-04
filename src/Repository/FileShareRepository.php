<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FileShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FileShare>
 */
class FileShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileShare::class);
    }

    public function findValid(string $token): ?FileShare
    {
        $share = $this->findOneBy(['token' => $token]);

        return null !== $share && !$share->isExpired() ? $share : null;
    }
}
