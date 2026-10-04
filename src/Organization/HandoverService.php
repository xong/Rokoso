<?php

declare(strict_types=1);

namespace App\Organization;

use App\Entity\CalendarItem;
use App\Entity\MailRule;
use App\Entity\Meeting;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Watch;
use App\Enum\CalendarItemType;
use App\Repository\WatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hands over the responsibilities of one person in an organization to another one (change of office).
 * Does not flush.
 */
final readonly class HandoverService
{
    public const array SCOPES = ['messages', 'tasks', 'rules', 'projects', 'meetings'];

    public function __construct(
        private EntityManagerInterface $em,
        private WatchRepository $watches,
    ) {
    }

    /**
     * @param list<string> $scopes subset of SCOPES
     *
     * @return array<string, int> number of transferred items per scope
     */
    public function transfer(Organization $organization, User $from, User $to, array $scopes): array
    {
        $counts = [];
        foreach (array_intersect(self::SCOPES, $scopes) as $scope) {
            $counts[$scope] = match ($scope) {
                'messages' => $this->reassign($this->assigned(Message::class, $organization, $from), $from, $to),
                'tasks' => $this->reassign($this->assigned(CalendarItem::class, $organization, $from, 'AND e.type = :task'), $from, $to),
                'rules' => $this->reassign($this->assigned(MailRule::class, $organization, $from), $from, $to),
                'projects' => $this->projects($organization, $from, $to),
                'meetings' => $this->meetings($organization, $from, $to),
            };
        }

        return $counts;
    }

    /**
     * Open items (messages, tasks) or rules of the organization assigned to the person.
     *
     * @template T of Message|CalendarItem|MailRule
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function assigned(string $class, Organization $organization, User $from, string $extra = ''): array
    {
        $query = $this->em->createQuery(\sprintf('SELECT e FROM %s e WHERE e.organization = :org AND :from MEMBER OF e.assignees %s', $class, $extra))
            ->setParameter('org', $organization)
            ->setParameter('from', $from);
        if ('' !== $extra) {
            $query->setParameter('task', CalendarItemType::Task->value);
        }
        /** @var list<T> $items */
        $items = $query->getResult();

        return array_values(array_filter($items, static fn (Message|CalendarItem|MailRule $i): bool => $i instanceof MailRule || !$i->isDone()));
    }

    /**
     * @param list<Message|CalendarItem|MailRule> $items
     */
    private function reassign(array $items, User $from, User $to): int
    {
        foreach ($items as $item) {
            $item->removeAssignee($from);
            $item->addAssignee($to);
        }

        return \count($items);
    }

    /**
     * Project lead and watching of the organization's projects.
     */
    private function projects(Organization $organization, User $from, User $to): int
    {
        $count = 0;
        foreach ($this->em->getRepository(Project::class)->findBy(['organization' => $organization]) as $project) {
            $moved = false;
            if ($project->getLead() === $from) {
                $project->setLead($to);
                $moved = true;
            }
            $watch = $this->watches->findWatch($from, $project);
            if ($watch instanceof Watch) {
                if (!$this->watches->isWatching($to, $project)) {
                    $this->em->persist(new Watch($to, $project));
                }
                $moved = true;
            }
            $count += (int) $moved;
        }

        return $count;
    }

    /**
     * Minute taking in upcoming meetings.
     */
    private function meetings(Organization $organization, User $from, User $to): int
    {
        /** @var list<Meeting> $meetings */
        $meetings = $this->em->createQuery(\sprintf('SELECT m FROM %s m WHERE m.organization = :org AND m.minuteTaker = :from AND m.startsAt >= :now', Meeting::class))
            ->setParameter('org', $organization)
            ->setParameter('from', $from)
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->getResult();
        foreach ($meetings as $meeting) {
            $meeting->setMinuteTaker($to);
        }

        return \count($meetings);
    }
}
