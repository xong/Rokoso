<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\ForumTopic;
use App\Entity\Message;
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
            ->addSelect('f', 'a', 'm', 't')
            ->leftJoin('s.file', 'f')
            ->leftJoin('s.attachment', 'a')
            ->leftJoin('s.message', 'm')
            ->leftJoin('s.topic', 't')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.createdAt', \SortDirection::Descending)
            ->addOrderBy('s.id', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function findFor(User $owner, StoredFile|Attachment|Message|ForumTopic $target): ?ShelfItem
    {
        $field = match (true) {
            $target instanceof StoredFile => 'file',
            $target instanceof Attachment => 'attachment',
            $target instanceof Message => 'message',
            $target instanceof ForumTopic => 'topic',
        };

        return $this->findOneBy(['owner' => $owner, $field => $target]);
    }
}
