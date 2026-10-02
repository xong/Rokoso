<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Folder;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folder>
 */
class FolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
    }

    /**
     * All folders of the user's organizations, ordered by organization and name (for the folder tree).
     *
     * @return list<Folder>
     */
    public function findVisibleFor(User $user): array
    {
        /* @var list<Folder> */
        return $this->createQueryBuilder('f')
            ->addSelect('o')
            ->join('f.organization', 'o')
            ->join(Membership::class, 'm', 'WITH', 'm.organization = o AND m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.name')
            ->addOrderBy('f.name')
            ->getQuery()->getResult();
    }

    /**
     * The folder and all its descendants.
     *
     * @return list<Folder>
     */
    public static function subtree(Folder $folder): array
    {
        $result = [$folder];
        foreach ($folder->getChildren() as $child) {
            array_push($result, ...self::subtree($child));
        }

        return $result;
    }
}
