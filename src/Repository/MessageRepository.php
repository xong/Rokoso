<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use App\Enum\MessageFolder;
use App\Enum\MessageType;
use App\Mail\MessageFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public const int PAGE_SIZE = 50;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Messages the user may see:
     * - everything belonging to one of the user's organizations (account mails, messages to the org or its projects)
     * - internal messages the user wrote or received personally
     * - internal messages to the user's personal projects.
     */
    public function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.project', 'p')
            ->andWhere('(m.organization IS NOT NULL AND EXISTS (SELECT 1 FROM '.Membership::class.' vm WHERE vm.organization = m.organization AND vm.user = :viewer))'
                .' OR m.author = :viewer'
                .' OR :viewer MEMBER OF m.recipientUsers'
                .' OR (m.organization IS NULL AND p.createdBy = :viewer)')
            ->setParameter('viewer', $user);
    }

    public function isVisibleTo(Message $message, User $user): bool
    {
        return null !== $this->visibleQuery($user)
            ->select('m.id')
            ->andWhere('m = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Message>
     */
    public function findForList(User $user, MessageFilter $filter): array
    {
        $qb = $this->visibleQuery($user)
            ->addSelect('a', 'p')
            ->leftJoin('m.mailAccount', 'a')
            ->orderBy('m.date', 'DESC')
            ->setFirstResult(($filter->page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE + 1);

        match ($filter->folder) {
            'trash' => $qb->andWhere('m.trashedAt IS NOT NULL'),
            'all' => $qb->andWhere('m.trashedAt IS NULL'),
            'sent' => $qb->andWhere('m.trashedAt IS NULL')
                ->andWhere('m.folder = :sent OR (m.type = :internal AND m.author = :viewer)')
                ->setParameter('sent', MessageFolder::Sent->value)
                ->setParameter('internal', MessageType::Internal->value),
            default => $qb->andWhere('m.trashedAt IS NULL')
                ->andWhere('m.folder = :inbox')
                ->andWhere('m.project IS NULL')
                ->andWhere('m.type = :email OR m.author IS NULL OR m.author != :viewer')
                ->setParameter('inbox', MessageFolder::Inbox->value)
                ->setParameter('email', MessageType::Email->value),
        };

        match ($filter->show) {
            'unassigned' => $qb->andWhere('m.assignees IS EMPTY'),
            'mine' => $qb->andWhere(':viewer MEMBER OF m.assignees'),
            'unread' => $qb->andWhere('NOT EXISTS (SELECT 1 FROM '.MessageRead::class.' ur WHERE ur.message = m AND ur.user = :viewer)'),
            'internal' => $qb->andWhere('m.type = :internalOnly')->setParameter('internalOnly', MessageType::Internal->value),
            default => null,
        };

        if (null !== $filter->project) {
            $qb->andWhere('m.project = :project')->setParameter('project', $filter->project);
        }
        if (null !== $filter->account) {
            $qb->andWhere('m.mailAccount = :account')->setParameter('account', $filter->account);
        }
        if ('' !== $filter->query) {
            $qb->andWhere('m.subject LIKE :q OR m.fromName LIKE :q OR m.fromAddress LIKE :q OR m.snippet LIKE :q')
                ->setParameter('q', '%'.addcslashes($filter->query, '%_\\').'%');
        }

        /* @var list<Message> */
        return $qb->getQuery()->getResult();
    }

    /**
     * IDs of the given messages the user has read.
     *
     * @param list<Message> $messages
     *
     * @return array<int, true>
     */
    public function readIds(User $user, array $messages): array
    {
        if ([] === $messages) {
            return [];
        }
        /** @var list<array{id: int}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.message) AS id')
            ->from(MessageRead::class, 'r')
            ->andWhere('r.user = :user')
            ->andWhere('r.message IN (:messages)')
            ->setParameter('user', $user)
            ->setParameter('messages', $messages)
            ->getQuery()
            ->getArrayResult();

        return array_fill_keys(array_map(static fn (array $r): int => (int) $r['id'], $rows), true);
    }

    public function countUnreadInbox(User $user): int
    {
        return (int) $this->visibleQuery($user)
            ->select('COUNT(m.id)')
            ->andWhere('m.trashedAt IS NULL')
            ->andWhere('m.folder = :inbox')
            ->andWhere('m.project IS NULL')
            ->andWhere('m.type = :email OR m.author IS NULL OR m.author != :viewer')
            ->andWhere('NOT EXISTS (SELECT 1 FROM '.MessageRead::class.' ur WHERE ur.message = m AND ur.user = :viewer)')
            ->setParameter('inbox', MessageFolder::Inbox->value)
            ->setParameter('email', MessageType::Email->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForAccount(int $accountId, ?string $messageIdHeader, int $uid): bool
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.id')
            ->andWhere('IDENTITY(m.mailAccount) = :account')
            ->setParameter('account', $accountId)
            ->setMaxResults(1);
        if (null !== $messageIdHeader) {
            $qb->andWhere('m.messageIdHeader = :mid')->setParameter('mid', $messageIdHeader);
        } else {
            $qb->andWhere('m.imapUid = :uid')->setParameter('uid', $uid);
        }

        return [] !== $qb->getQuery()->getResult();
    }
}
