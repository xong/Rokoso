<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShelfItem>
 */
class ShelfItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShelfItem::class);
    }

    /**
     * @return list<ShelfItem>
     */
    public function forOwner(User $owner): array
    {
        /* @var list<ShelfItem> */
        return $this->createQueryBuilder('s')
            ->addSelect('f', 'a')
            ->leftJoin('s.file', 'f')
            ->leftJoin('s.attachment', 'a')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFor(User $owner, StoredFile|Attachment $target): ?ShelfItem
    {
        return $this->findOneBy(['owner' => $owner, $target instanceof StoredFile ? 'file' : 'attachment' => $target]);
    }
}
