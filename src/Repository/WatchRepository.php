<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumBoard;
use App\Entity\ForumTopic;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Watch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Watch>
 */
class WatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Watch::class);
    }

    public function findWatch(User $user, ForumBoard|ForumTopic|Project $target): ?Watch
    {
        return $this->findOneBy(['user' => $user, self::field($target) => $target]);
    }

    public function isWatching(User $user, ForumBoard|ForumTopic|Project $target): bool
    {
        return null !== $this->findWatch($user, $target);
    }

    /**
     * Adds a watch unless it exists (persisted, not flushed).
     */
    public function watch(User $user, ForumBoard|ForumTopic|Project $target): void
    {
        if (null !== $target->getId() && $this->isWatching($user, $target)) {
            return;
        }
        foreach ($this->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
            if ($pending instanceof Watch && $pending->getUser() === $user && $pending->getTarget() === $target) {
                return;
            }
        }
        $this->getEntityManager()->persist(new Watch($user, $target));
    }

    /**
     * @return list<User>
     */
    public function watchersOf(ForumBoard|ForumTopic|Project $target): array
    {
        if (null === $target->getId()) {
            return [];
        }

        /* @var list<User> */
        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->join(Watch::class, 'w', 'ON', 'w.user = u')
            ->andWhere('w.'.self::field($target).' = :target')->setParameter('target', $target)
            ->getQuery()->getResult();
    }

    private static function field(ForumBoard|ForumTopic|Project $target): string
    {
        return match (true) {
            $target instanceof ForumBoard => 'board',
            $target instanceof ForumTopic => 'topic',
            $target instanceof Project => 'project',
        };
    }
}
