<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findForUser(User $user, bool $unreadOnly = false, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')->setParameter('user', $user)
            ->orderBy('n.createdAt', \SortDirection::Descending)->addOrderBy('n.id', \SortDirection::Descending)
            ->setMaxResults($limit);
        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        /* @var list<Notification> */
        return $qb->getQuery()->getResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')->select('COUNT(n.id)')
            ->andWhere('n.recipient = :user')->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    public function markAllRead(User $user): void
    {
        $this->createQueryBuilder('n')->update()
            ->set('n.readAt', ':now')
            ->andWhere('n.recipient = :user')->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())->setParameter('user', $user)
            ->getQuery()->execute();
    }

    /**
     * Marks notifications pointing to the given page as read (the person has seen it).
     */
    public function markReadByUrl(User $user, string $url): void
    {
        $this->createQueryBuilder('n')->update()
            ->set('n.readAt', ':now')
            ->andWhere('n.recipient = :user')->andWhere('n.readAt IS NULL')->andWhere('n.url = :url')
            ->setParameter('now', new \DateTimeImmutable())->setParameter('user', $user)->setParameter('url', $url)
            ->getQuery()->execute();
    }

    public function hasRef(User $user, string $refKey): bool
    {
        return null !== $this->findOneBy(['recipient' => $user, 'refKey' => $refKey]);
    }

    /**
     * Unread notifications not yet sent by email, oldest first.
     *
     * @return list<Notification>
     */
    public function findUnmailed(User $user, ?\DateTimeImmutable $createdBefore = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')->andWhere('n.readAt IS NULL')->andWhere('n.emailedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', \SortDirection::Ascending);
        if (null !== $createdBefore) {
            $qb->andWhere('n.createdAt < :before')->setParameter('before', $createdBefore);
        }

        /* @var list<Notification> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Recipients with unread, unmailed notifications.
     *
     * @return list<User>
     */
    public function findRecipientsWithUnmailed(): array
    {
        /* @var list<User> */
        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->andWhere('EXISTS (SELECT n.id FROM '.Notification::class.' n WHERE n.recipient = u AND n.readAt IS NULL AND n.emailedAt IS NULL)')
            ->getQuery()->getResult();
    }
}
