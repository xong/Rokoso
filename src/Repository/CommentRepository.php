<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return list<Comment>
     */
    public function forProject(Project $project): array
    {
        /* @var list<Comment> */
        return $this->createQueryBuilder('c')
            ->addSelect('a')
            ->leftJoin('c.author', 'a')
            ->andWhere('c.project = :project')
            ->setParameter('project', $project)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()->getResult();
    }
}
