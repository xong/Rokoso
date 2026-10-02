<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumBoard;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumBoard>
 */
class ForumBoardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumBoard::class);
    }

    /**
     * All areas of the user's organizations, ordered by organization and name.
     *
     * @return list<ForumBoard>
     */
    public function findVisibleFor(User $user): array
    {
        /* @var list<ForumBoard> */
        return $this->createQueryBuilder('b')
            ->addSelect('o')
            ->join('b.organization', 'o')
            ->join(Membership::class, 'm', 'WITH', 'm.organization = o AND m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.name')
            ->addOrderBy('b.name')
            ->getQuery()->getResult();
    }

    /**
     * The area and all its descendants.
     *
     * @return list<ForumBoard>
     */
    public static function subtree(ForumBoard $board): array
    {
        $result = [$board];
        foreach ($board->getChildren() as $child) {
            array_push($result, ...self::subtree($child));
        }

        return $result;
    }
}
