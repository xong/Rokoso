<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumTopicRead;
use App\Entity\Membership;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumTopic>
 */
class ForumTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopic::class);
    }

    /**
     * @return list<ForumTopic>
     */
    public function inBoard(ForumBoard $board): array
    {
        /* @var list<ForumTopic> */
        return $this->createQueryBuilder('t')
            ->addSelect('lb')
            ->leftJoin('t.lastPostBy', 'lb')
            ->andWhere('t.board = :board')
            ->setParameter('board', $board)
            ->orderBy('t.lastPostAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * Visible topics whose title or one of whose posts contains the query.
     *
     * @return list<ForumTopic>
     */
    public function search(User $user, string $query, int $limit = 10): array
    {
        /* @var list<ForumTopic> */
        return $this->visibleQuery($user)
            ->andWhere('t.title LIKE :q OR EXISTS (SELECT 1 FROM '.ForumPost::class.' sp WHERE sp.topic = t AND sp.body LIKE :q)')
            ->setParameter('q', '%'.addcslashes($query, '%_\\').'%')
            ->orderBy('t.lastPostAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Most recently active topics in the user's organizations.
     *
     * @return list<ForumTopic>
     */
    public function recentFor(User $user, int $limit = 20): array
    {
        /* @var list<ForumTopic> */
        return $this->visibleQuery($user)
            ->orderBy('t.lastPostAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * @return list<ForumTopic>
     */
    public function forProject(Project $project, User $user): array
    {
        /* @var list<ForumTopic> */
        return $this->visibleQuery($user)
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.lastPostAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * IDs of the given topics with posts the user has not read yet.
     *
     * @param list<ForumTopic> $topics
     *
     * @return list<int>
     */
    public function unreadIds(User $user, array $topics): array
    {
        if ([] === $topics) {
            return [];
        }

        return array_values(array_map(intval(...), $this->createQueryBuilder('t')
            ->select('t.id')
            ->leftJoin(ForumTopicRead::class, 'r', 'WITH', 'r.topic = t AND r.user = :user')
            ->andWhere('t IN (:topics)')
            ->andWhere('r.id IS NULL OR r.readAt < t.lastPostAt')
            ->setParameter('user', $user)
            ->setParameter('topics', $topics)
            ->getQuery()->getSingleColumnResult()));
    }

    public function countUnread(User $user): int
    {
        return (int) $this->visibleQuery($user)
            ->select('COUNT(t.id)')
            ->leftJoin(ForumTopicRead::class, 'r', 'WITH', 'r.topic = t AND r.user = :user')
            ->andWhere('r.id IS NULL OR r.readAt < t.lastPostAt')
            ->getQuery()->getSingleScalarResult();
    }

    private function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->addSelect('b', 'lb')
            ->join('t.board', 'b')
            ->leftJoin('t.lastPostBy', 'lb')
            ->leftJoin('b.parent', 'pb')
            ->leftJoin(Membership::class, 'm', 'WITH', 'm.organization = b.organization AND m.user = :user AND '.Membership::fullDql('m'))
            ->andWhere('m.id IS NOT NULL OR '.Membership::guestDql('t.project', 'user', 'gt').' OR '.Membership::guestDql('b.project', 'user', 'gb')
                .' OR '.Membership::guestDql('pb.project', 'user', 'gpb'))
            ->setParameter('user', $user);
    }
}
