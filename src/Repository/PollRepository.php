<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumTopic;
use App\Entity\Meeting;
use App\Entity\Membership;
use App\Entity\Poll;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poll>
 */
class PollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, Poll::class);
    }

    /**
     * Polls of the user's organizations: running ones by deadline, ended ones newest first.
     *
     * @return list<Poll>
     */
    public function findForUser(User $user, bool $ended = false, ?int $limit = null): array
    {
        $qb = $this->visibleQuery($user)->setParameter('now', new \DateTimeImmutable())->setMaxResults($limit);
        if ($ended) {
            $qb->andWhere('p.closedAt IS NOT NULL OR p.deadline <= :now')->orderBy('p.createdAt', 'DESC');
        } else {
            $qb->andWhere('p.closedAt IS NULL AND (p.deadline IS NULL OR p.deadline > :now)')
                ->addOrderBy('CASE WHEN p.deadline IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('p.deadline', 'ASC')->addOrderBy('p.createdAt', 'DESC');
        }

        /* @var list<Poll> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Running polls the user may vote in and has not voted in yet.
     *
     * @return list<Poll>
     */
    public function findAwaitingVote(User $user): array
    {
        return array_values(array_filter($this->findForUser($user),
            static fn (Poll $p): bool => $p->isEligible($user) && !$p->hasVoted($user)));
    }

    /** @return list<Poll> */
    public function forTopic(ForumTopic $topic): array
    {
        return $this->enabled($this->findBy(['topic' => $topic], ['createdAt' => 'ASC']));
    }

    /** @return list<Poll> */
    public function forMeeting(Meeting $meeting): array
    {
        return $this->enabled($this->findBy(['meeting' => $meeting], ['createdAt' => 'ASC']));
    }

    /**
     * @param array<Poll> $polls
     *
     * @return list<Poll>
     */
    private function enabled(array $polls): array
    {
        return array_values(array_filter($polls, static fn (Poll $p): bool => $p->getOrganization()->hasFeature(Feature::Polls)));
    }

    public function visibleQuery(User $user, string $alias = 'p'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->join(Membership::class, $alias.'_vm', 'WITH', $alias.'_vm.organization = '.$alias.'.organization AND '.$alias.'_vm.user = :viewer AND '.Membership::fullDql($alias.'_vm'))
            ->andWhere($this->features->dql('IDENTITY('.$alias.'.organization)', Feature::Polls))
            ->setParameter('viewer', $user);
    }
}
