<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Draft;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Draft>
 */
final class DraftRepository extends ServiceEntityRepository
{
    /** A draft counts as "being written" for this long after its last change. */
    public const string ACTIVE_FOR = '-30 minutes';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Draft::class);
    }

    /**
     * Own unsent drafts, newest first.
     *
     * @return list<Draft>
     */
    public function findOpenFor(User $user): array
    {
        /* @var list<Draft> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner = :user')
            ->andWhere('d.sendAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('d.updatedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function countOpenFor(User $user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.owner = :user')
            ->andWhere('d.sendAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Own drafts waiting to be sent (still cancellable).
     *
     * @return list<Draft>
     */
    public function findQueuedFor(User $user): array
    {
        /* @var list<Draft> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner = :user')
            ->andWhere('d.sendAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('d.sendAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Draft>
     */
    public function findDue(\DateTimeImmutable $now): array
    {
        /* @var list<Draft> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.sendAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('d.sendAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Other people currently writing a reply to / forwarding the message.
     *
     * @return list<User>
     */
    public function findOtherWriters(Message $message, User $except): array
    {
        /** @var list<Draft> $drafts */
        $drafts = $this->createQueryBuilder('d')
            ->addSelect('o')
            ->join('d.owner', 'o')
            ->andWhere('d.original = :message')
            ->andWhere('d.owner != :me')
            ->andWhere('d.updatedAt >= :since')
            ->setParameter('message', $message)
            ->setParameter('me', $except)
            ->setParameter('since', new \DateTimeImmutable(self::ACTIVE_FOR))
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($drafts as $draft) {
            $users[spl_object_id($draft->getOwner())] = $draft->getOwner();
        }

        return array_values($users);
    }
}
