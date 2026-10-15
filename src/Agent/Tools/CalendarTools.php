<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentScope;
use App\Agent\Mcp\Tool;
use App\Agent\Mcp\ToolArguments;
use App\Agent\Mcp\ToolError;
use App\Agent\Mcp\ToolProvider;
use App\Calendar\CalendarService;
use App\Calendar\Occurrence;
use App\Entity\CalendarItem;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\Feature;
use App\Enum\Recurrence;
use App\Enum\TaskStatus;
use App\Notification\ActivityNotifier;
use App\Repository\CalendarItemRepository;
use App\Repository\MembershipRepository;
use App\Security\Voter\CalendarItemVoter;
use App\Security\Voter\MessageVoter;

/**
 * Calendar events and tasks: read, create and change (incl. assignees, who are notified).
 */
final readonly class CalendarTools implements ToolProvider
{
    private const int MAX_DAYS = 366;

    private const array STATUS = ['open', 'in_progress', 'done'];

    private const array ITEM_PROPERTIES = [
        'title' => ['type' => 'string'],
        'description' => ['type' => 'string'],
        'location' => ['type' => 'string'],
        'url' => ['type' => 'string'],
        'start' => ['type' => 'string', 'description' => 'Events: start (2026-10-31T18:00, or a date for all-day); tasks: due date, empty = no due date'],
        'end' => ['type' => 'string', 'description' => 'Events only'],
        'all_day' => ['type' => 'boolean'],
        'project_id' => ['type' => 'integer', 'description' => 'The project decides the organization'],
        'assignee_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'People in charge (colleagues, see list_colleagues); they are notified'],
        'recurrence' => ['type' => 'string', 'enum' => ['none', 'DAILY', 'WEEKLY', 'MONTHLY', 'MONTHLY_WEEKDAY', 'MONTHLY_LAST', 'YEARLY']],
        'recurrence_interval' => ['type' => 'integer', 'minimum' => 1],
        'recurrence_until' => ['type' => 'string', 'description' => 'Last day of the series'],
    ];

    public function __construct(
        private ToolKit $kit,
        private CalendarService $calendar,
        private CalendarItemRepository $items,
        private MembershipRepository $memberships,
        private ActivityNotifier $notifier,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_events', 'Calendar events and dated tasks between two days (at most 366 days, default the next 30). Recurring events appear once per occurrence.',
        scope: AgentScope::CalendarRead,
        properties: [
            'from' => ['type' => 'string', 'description' => 'First day, default today'],
            'to' => ['type' => 'string', 'description' => 'Last day (inclusive)'],
            'project_id' => ['type' => 'integer'],
        ])]
    public function listEvents(ToolArguments $args, User $user): array
    {
        $from = ($args->date('from') ?? new \DateTimeImmutable('today', new \DateTimeZone(CalendarService::TIMEZONE)))->setTime(0, 0);
        $to = ($args->date('to') ?? $from->modify('+29 days'))->setTime(0, 0)->modify('+1 day');
        if ($to <= $from || $from->diff($to)->days > self::MAX_DAYS) {
            throw new ToolError('"to" must be after "from" and at most 366 days later.');
        }

        return ['occurrences' => array_map(fn (Occurrence $o): array => [
            'id' => $o->item->getId(),
            'kind' => $o->item->getType()->value,
            'title' => $o->getTitle(),
            'start' => ToolKit::time($o->start),
            'end' => $o->item->isTask() ? null : ToolKit::time($o->end),
            'all_day' => $o->isAllDayLike(),
            'location' => $o->getLocation(),
            'cancelled' => $o->isCancelled(),
            'recurring' => $o->item->isRecurring(),
            'organization' => ToolKit::org($o->item->getOrganization()),
            'project' => null === $o->item->getProject() ? null : ['id' => $o->item->getProject()->getId(), 'name' => $o->item->getProject()->getName()],
            'url' => $this->kit->url('calendar_item_show', ['id' => $o->item->getId(), 'date' => $o->getDay()]),
        ], $this->calendar->occurrences($user, $from, $to, $args->int('project_id')))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_tasks', 'Tasks, open ones by due date (undated last).',
        scope: AgentScope::CalendarRead,
        properties: [
            'mine' => ['type' => 'boolean', 'description' => 'Only tasks assigned to the person (or created by them without anyone assigned)'],
            'project_id' => ['type' => 'integer'],
            'include_done' => ['type' => 'boolean'],
        ])]
    public function listTasks(ToolArguments $args, User $user): array
    {
        return ['tasks' => array_map($this->details(...), $this->items->findTasks($user, $args->bool('mine'), $args->int('project_id'), $args->bool('include_done'), 200))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_colleagues', 'People who share an organization with the person (for assignee_ids).', scope: AgentScope::CalendarRead)]
    public function listColleagues(ToolArguments $args, User $user): array
    {
        return ['people' => array_map(ToolKit::person(...), $this->memberships->colleaguesOf($user))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('get_calendar_item', 'An event or task with all details.',
        scope: AgentScope::CalendarRead,
        properties: ['item_id' => ['type' => 'integer']],
        required: ['item_id'])]
    public function getCalendarItem(ToolArguments $args, User $user): array
    {
        return $this->details($this->item($args));
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_task', 'Creates a task. Assigned people are notified. From an e-mail: pass message_id, the task links to it.',
        scope: AgentScope::CalendarWrite,
        properties: self::ITEM_PROPERTIES + [
            'status' => ['type' => 'string', 'enum' => self::STATUS],
            'organization_id' => ['type' => 'integer', 'description' => 'Without project: needed when the person belongs to several organizations'],
            'personal' => ['type' => 'boolean', 'description' => 'Personal task without organization'],
            'message_id' => ['type' => 'integer', 'description' => 'E-mail the task comes from'],
        ],
        required: ['title'],
        readOnly: false)]
    public function createTask(ToolArguments $args, User $user): array
    {
        return $this->create(CalendarItemType::Task, $args, $user);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_event', 'Creates a calendar event. Assigned people are notified.',
        scope: AgentScope::CalendarWrite,
        properties: self::ITEM_PROPERTIES + [
            'organization_id' => ['type' => 'integer', 'description' => 'Without project: needed when the person belongs to several organizations'],
            'personal' => ['type' => 'boolean', 'description' => 'Personal event without organization'],
            'message_id' => ['type' => 'integer', 'description' => 'E-mail the event comes from'],
        ],
        required: ['title', 'start'],
        readOnly: false)]
    public function createEvent(ToolArguments $args, User $user): array
    {
        return $this->create(CalendarItemType::Event, $args, $user);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('update_calendar_item', 'Changes an event or task (a recurring event as a whole series): only the given fields; an empty string clears a field. assignee_ids replaces the people in charge.',
        scope: AgentScope::CalendarWrite,
        properties: self::ITEM_PROPERTIES + ['item_id' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => self::STATUS, 'description' => 'Tasks only']],
        required: ['item_id'],
        readOnly: false,
        idempotent: true)]
    public function updateCalendarItem(ToolArguments $args, User $user): array
    {
        $item = $this->item($args);
        $before = $item->getAssignees()->toArray();
        $this->fill($item, $args, $user);
        $this->kit->check(CalendarItemVoter::EDIT, $item, $item->getOrganization());
        $this->kit->validate($item);
        $this->kit->em->flush();
        $this->notifyAssigned($item, $before, $user);

        return $this->details($item);
    }

    /**
     * @return array<string, mixed>
     */
    private function create(CalendarItemType $type, ToolArguments $args, User $user): array
    {
        $item = (new CalendarItem($user))->setType($type)->setStartsAt(null);
        $feature = CalendarItemType::Task === $type ? Feature::Tasks : Feature::Calendar;
        if (!$args->has('project_id') && !$args->bool('personal')) {
            $item->setOrganization($this->kit->organization($user, $feature, $args->int('organization_id'), personal: true));
        }
        if ($args->has('message_id')) {
            $message = $this->kit->find(Message::class, $args->requiredInt('message_id'));
            $this->kit->check(MessageVoter::VIEW, $message, $message->getOrganization());
            $item->setSourceMessage($message);
        }
        $this->fill($item, $args, $user);
        if (null !== $item->getOrganization() && !$item->getOrganization()->hasFeature($feature)) {
            throw new ToolError('The organization of the project does not use this area.');
        }
        $this->kit->check(CalendarItemVoter::EDIT, $item, $item->getOrganization());
        $this->kit->validate($item);
        $this->kit->em->persist($item);
        $this->kit->em->flush();
        $this->notifyAssigned($item, [], $user);

        return $this->details($item);
    }

    private static function recurrence(?string $value): Recurrence
    {
        $value = strtoupper(trim((string) $value));

        return Recurrence::tryFrom('NONE' === $value || '' === $value ? Recurrence::None->value : $value)
            ?? throw new ToolError('Unknown recurrence, use one of: '.implode(', ', array_column(Recurrence::cases(), 'value')).'.');
    }

    private function fill(CalendarItem $item, ToolArguments $args, User $user): void
    {
        if ($args->has('title')) {
            $item->setTitle($args->string('title'));
        }
        foreach (['description' => 'setDescription', 'location' => 'setLocation', 'url' => 'setUrl'] as $argument => $setter) {
            if ($args->has($argument)) {
                $value = $args->string($argument);
                $item->{$setter}('' === $value ? null : $value);
            }
        }
        if ($args->has('all_day')) {
            $item->setAllDay($args->bool('all_day'));
        } elseif ($args->has('start') && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $args->string('start'))) {
            // a date without time: whole day (events) resp. due on that day (tasks)
            $item->setAllDay(true);
        }
        if ($args->has('start')) {
            $start = $args->date('start');
            $item->setStartsAt($start?->setTimezone(new \DateTimeZone(CalendarService::TIMEZONE)));
        }
        if ($args->has('end')) {
            $item->setEndsAt($args->date('end'));
        }
        if ($args->has('status')) {
            $item->setStatus(TaskStatus::from((string) $args->choice('status', self::STATUS)));
        }
        if ($args->has('recurrence')) {
            $item->setRecurrence(self::recurrence($args->string('recurrence')));
        }
        if ($args->has('recurrence_interval')) {
            $item->setRecurrenceInterval($args->int('recurrence_interval'));
        }
        if ($args->has('recurrence_until')) {
            $item->setRecurrenceUntil($args->date('recurrence_until')?->setTime(0, 0));
        }
        if ($args->has('project_id')) {
            $project = $this->kit->project($user, $args->int('project_id'));
            $item->setProject($project);
            if (null !== $project) {
                $item->setOrganization($project->getOrganization());
            }
        }
        if ($args->has('assignee_ids')) {
            $ids = $args->ints('assignee_ids');
            $colleagues = $this->memberships->colleaguesOf($user);
            foreach (array_diff($ids, array_map(static fn (User $u): ?int => $u->getId(), $colleagues)) as $unknown) {
                throw new ToolError(\sprintf('Unknown person %d. Use list_colleagues.', $unknown));
            }
            foreach ($item->getAssignees()->toArray() as $assignee) {
                if (!\in_array($assignee->getId(), $ids, true)) {
                    $item->removeAssignee($assignee);
                }
            }
            foreach ($colleagues as $colleague) {
                if (\in_array($colleague->getId(), $ids, true)) {
                    $item->addAssignee($colleague);
                }
            }
        }
    }

    /**
     * @param array<User> $before
     */
    private function notifyAssigned(CalendarItem $item, array $before, User $user): void
    {
        $added = array_values(array_filter($item->getAssignees()->toArray(), static fn (User $u): bool => !\in_array($u, $before, true)));
        if ([] !== $added) {
            $this->notifier->calendarAssigned($item, $added, $user);
            $this->kit->em->flush();
        }
    }

    private function item(ToolArguments $args): CalendarItem
    {
        $item = $this->kit->find(CalendarItem::class, $args->requiredInt('item_id'));
        $this->kit->check(CalendarItemVoter::EDIT, $item, $item->getOrganization());

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function details(CalendarItem $item): array
    {
        return [
            'id' => $item->getId(),
            'kind' => $item->getType()->value,
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'location' => $item->getLocation(),
            'url_link' => $item->getUrl(),
            'start' => ToolKit::time($item->getStartsAt()),
            'end' => ToolKit::time($item->getEndsAt()),
            'all_day' => $item->isAllDay(),
            'status' => $item->isTask() ? $item->getStatus()->value : null,
            'overdue' => $item->isTask() && $item->isOverdue(),
            'recurrence' => $item->isRecurring() ? ['rule' => $item->getRecurrence()->value, 'interval' => $item->getRecurrenceInterval(), 'until' => ToolKit::day($item->getRecurrenceUntil())] : null,
            'assignees' => array_values(array_map(ToolKit::person(...), $item->getAssignees()->toArray())),
            'organization' => ToolKit::org($item->getOrganization()),
            'project' => null === $item->getProject() ? null : ['id' => $item->getProject()->getId(), 'name' => $item->getProject()->getName()],
            'source_message_id' => $item->getSourceMessage()?->getId(),
            'created_by' => ToolKit::person($item->getCreatedBy()),
            'via_agent' => $item->getAgentName(),
            'url' => $this->kit->url('calendar_item_show', ['id' => $item->getId()]),
        ];
    }
}
