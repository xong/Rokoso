<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Enum\MessageFolder;
use App\Enum\MessageType;
use App\Mail\MessageFilter;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public const int PAGE_SIZE = 50;

    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Messages the user may see:
     * - everything belonging to one of the user's organizations (account mails, messages to the org or its projects)
     * - internal messages the user wrote or received personally
     * - internal messages to the user's personal projects
     * – without e-mails of organizations that switched mail off.
     */
    public function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.project', 'p')
            ->andWhere('(m.organization IS NOT NULL AND EXISTS (SELECT 1 FROM '.Membership::class.' vm WHERE vm.organization = m.organization AND vm.user = :viewer AND '.Membership::fullDql('vm').'))'
                .' OR m.author = :viewer'
                .' OR :viewer MEMBER OF m.recipientUsers'
                .' OR (m.organization IS NULL AND p.createdBy = :viewer)')
            ->andWhere(\sprintf("m.type <> '%s' OR %s", MessageType::Email->value, $this->features->dql('IDENTITY(m.organization)', Feature::Mail)))
            ->setParameter('viewer', $user);
    }

    /**
     * Visible messages from or to one of the addresses (contact history), newest first.
     *
     * @param list<string> $addresses
     *
     * @return list<Message>
     */
    public function findForAddresses(User $user, array $addresses, int $limit = 30): array
    {
        if ([] === $addresses) {
            return [];
        }
        $qb = $this->visibleQuery($user)
            ->andWhere('m.trashedAt IS NULL')
            ->andWhere('m.spamAt IS NULL')
            ->orderBy('m.date', 'DESC')
            ->setMaxResults($limit);
        $or = $qb->expr()->orX('m.fromAddress IN (:addresses)');
        foreach ($addresses as $i => $address) {
            $or->add('m.toRecipients LIKE :a'.$i.' OR m.ccRecipients LIKE :a'.$i);
            $qb->setParameter('a'.$i, '%"'.addcslashes($address, '%_\\').'"%');
        }

        /* @var list<Message> */
        return $qb->andWhere($or)->setParameter('addresses', $addresses)->getQuery()->getResult();
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

        if ('spam' !== $filter->folder && 'trash' !== $filter->folder) {
            $qb->andWhere('m.spamAt IS NULL');
        }
        match ($filter->folder) {
            'trash' => $qb->andWhere('m.trashedAt IS NOT NULL'),
            'spam' => $qb->andWhere('m.trashedAt IS NULL')->andWhere('m.spamAt IS NOT NULL'),
            'all' => $qb->andWhere('m.trashedAt IS NULL'),
            'sent' => $qb->andWhere('m.trashedAt IS NULL')
                ->andWhere('m.folder = :sent OR (m.type = :internal AND m.author = :viewer)')
                ->setParameter('sent', MessageFolder::Sent->value)
                ->setParameter('internal', MessageType::Internal->value),
            'done' => $qb->andWhere('m.trashedAt IS NULL')->andWhere('m.doneAt IS NOT NULL'),
            'snoozed' => $qb->andWhere('m.trashedAt IS NULL')
                ->andWhere('m.doneAt IS NULL')
                ->andWhere('m.snoozedUntil > :now')
                ->setParameter('now', new \DateTimeImmutable()),
            default => $this->applyInbox($qb),
        };
        if ('snoozed' === $filter->folder) {
            $qb->orderBy('m.snoozedUntil', 'ASC');
        } elseif ('done' === $filter->folder) {
            $qb->orderBy('m.doneAt', 'DESC');
        }

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
        $qb = $this->visibleQuery($user)
            ->select('COUNT(m.id)')
            ->andWhere('NOT EXISTS (SELECT 1 FROM '.MessageRead::class.' ur WHERE ur.message = m AND ur.user = :viewer)')
        ;
        $qb = $this->applyInbox($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countSpam(User $user): int
    {
        return (int) $this->visibleQuery($user)
            ->select('COUNT(m.id)')
            ->andWhere('m.trashedAt IS NULL')
            ->andWhere('m.spamAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Visible, not yet flagged messages of an organization from this address or domain ("@example.org").
     *
     * @return list<Message>
     */
    public function findFromSender(User $user, Organization $organization, string $address): array
    {
        $qb = $this->visibleQuery($user)
            ->andWhere('m.organization = :org')
            ->andWhere('m.type = :email')
            ->andWhere('m.spamAt IS NULL')
            ->setParameter('org', $organization)
            ->setParameter('email', MessageType::Email->value);
        if (str_starts_with($address, '@')) {
            $qb->andWhere('m.fromAddress LIKE :address')->setParameter('address', '%'.addcslashes($address, '%_\\'));
        } else {
            $qb->andWhere('m.fromAddress = :address')->setParameter('address', $address);
        }

        /* @var list<Message> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Inbox = everything open: not trashed, not spam, not done, not snoozed, not written by the viewer.
     * The project does not matter (Entscheidung 39).
     */
    private function applyInbox(QueryBuilder $qb): QueryBuilder
    {
        return $qb->andWhere('m.trashedAt IS NULL')
            ->andWhere('m.spamAt IS NULL')
            ->andWhere('m.folder = :inbox')
            ->andWhere('m.doneAt IS NULL')
            ->andWhere('m.snoozedUntil IS NULL OR m.snoozedUntil <= :now')
            ->andWhere('m.type = :email OR m.author IS NULL OR m.author != :viewer')
            ->setParameter('inbox', MessageFolder::Inbox->value)
            ->setParameter('email', MessageType::Email->value)
            ->setParameter('now', new \DateTimeImmutable());
    }

    /**
     * Visible messages of the same conversation, oldest first.
     *
     * @return list<Message>
     */
    public function findThread(Message $message, User $user): array
    {
        if (null === $message->getThreadKey()) {
            return [$message];
        }

        /* @var list<Message> */
        return $this->visibleQuery($user)
            ->andWhere('m.threadKey = :key')
            ->andWhere('(m.trashedAt IS NULL AND m.spamAt IS NULL) OR m = :self')
            ->setParameter('key', $message->getThreadKey())
            ->setParameter('self', $message)
            ->orderBy('m.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Thread key of an already stored message with this Message-ID (same organization).
     */
    public function findThreadKey(?Organization $organization, string $messageIdHeader): ?string
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.threadKey')
            ->andWhere('m.messageIdHeader = :mid')
            ->andWhere('m.threadKey IS NOT NULL')
            ->setParameter('mid', $messageIdHeader)
            ->setMaxResults(1);
        if (null !== $organization) {
            $qb->andWhere('m.organization = :org')->setParameter('org', $organization);
        }
        /** @var list<array{threadKey: string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $rows[0]['threadKey'] ?? null;
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
