<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CalendarItem;
use App\Entity\Comment;
use App\Entity\Draft;
use App\Entity\ForumPost;
use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\SecurityEvent;
use App\Entity\User;
use App\Enum\MessageType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Collects the personal data of a user as plain arrays (DSGVO Art. 15/20): profile, memberships
 * and the content they wrote themselves. Content of other people is not included.
 */
final readonly class AccountExport
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exported_at' => self::date(new \DateTimeImmutable()),
            'profile' => [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'created_at' => self::date($user->getCreatedAt()),
                'two_factor' => $user->isTotpAuthenticationEnabled(),
                'notification_email' => $user->getNotificationEmail()->value,
                'theme' => $user->getTheme()->value,
            ],
            'memberships' => array_map(static fn (Membership $m): array => [
                'organization' => $m->getOrganization()->getName(),
                'role' => $m->getRole()->value,
                'position' => $m->getPosition(),
                'term_ends_on' => $m->getTermEndsOn()?->format('Y-m-d'),
                'since' => self::date($m->getCreatedAt()),
            ], $this->find(Membership::class, 'user', $user)),
            'internal_messages' => array_map(static fn (Message $m): array => [
                'date' => self::date($m->getDate()),
                'subject' => $m->getSubject(),
                'text' => $m->getTextBody(),
            ], array_values(array_filter($this->find(Message::class, 'author', $user), static fn (Message $m): bool => MessageType::Internal === $m->getType()))),
            'forum_posts' => array_map(static fn (ForumPost $p): array => [
                'date' => self::date($p->getCreatedAt()),
                'topic' => $p->getTopic()->getTitle(),
                'text' => $p->getBody(),
            ], $this->find(ForumPost::class, 'author', $user)),
            'comments' => array_map(static fn (Comment $c): array => [
                'date' => self::date($c->getCreatedAt()),
                'text' => $c->getBody(),
            ], $this->find(Comment::class, 'author', $user)),
            'calendar_items' => array_map(static fn (CalendarItem $i): array => [
                'type' => $i->getType()->value,
                'title' => $i->getTitle(),
                'date' => self::date($i->getDate()),
                'description' => $i->getDescription(),
            ], $this->find(CalendarItem::class, 'createdBy', $user)),
            'drafts' => array_map(static fn (Draft $d): array => [
                'updated_at' => self::date($d->getUpdatedAt()),
                'to' => $d->getTo(),
                'subject' => $d->getSubject(),
                'body' => $d->getBody(),
            ], $this->find(Draft::class, 'owner', $user)),
            'notifications' => array_map(static fn (Notification $n): array => [
                'date' => self::date($n->getCreatedAt()),
                'subject' => $n->getSubject(),
                'read' => $n->isRead(),
            ], $this->find(Notification::class, 'recipient', $user)),
            'security_log' => array_map(static fn (SecurityEvent $e): array => [
                'date' => self::date($e->getCreatedAt()),
                'type' => $e->getType(),
                'detail' => $e->getDetail(),
                'ip' => $e->getIp(),
            ], $this->find(SecurityEvent::class, 'user', $user)),
        ];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function find(string $class, string $field, User $user): array
    {
        return $this->em->getRepository($class)->findBy([$field => $user], ['id' => 'ASC']);
    }

    private static function date(\DateTimeInterface $date): string
    {
        return $date->format(\DATE_ATOM);
    }
}
