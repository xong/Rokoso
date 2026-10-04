<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use App\Enum\Feature;
use App\Repository\ForumTopicRepository;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Service\Features;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Hauptnavigation (linke Spalte).
 *
 * Neue Menüpunkte werden in {@see self::GROUPS} ergänzt. Aktiv ist der Punkt mit dem längsten
 * `match`-Präfix, mit dem die aktuelle Route beginnt. Neues anlegen läuft über {@see self::COMPOSE}.
 * Punkte eines Bereichs, den keine Organisation des Benutzers nutzt, entfallen ({@see Feature::forRoute()}).
 */
#[AsTwigComponent]
final class Sidebar
{
    /**
     * Groups of menu items; a group with label null has no heading.
     *
     * @var list<array{label: ?string, items: list<array{label: string, icon: string, route: string, match: list<string>, role?: string, children?: list<array{label: string, icon: string, route: string, match?: list<string>}>}>}>
     */
    private const array GROUPS = [
        ['label' => null, 'items' => [
            ['label' => 'nav.today', 'icon' => 'lucide:sun', 'route' => 'home', 'match' => ['home']],
            ['label' => 'nav.notifications', 'icon' => 'lucide:bell', 'route' => 'notification_index', 'match' => ['notification_']],
            [
                'label' => 'nav.mail',
                'icon' => 'lucide:mail',
                'route' => 'mail_inbox',
                'match' => ['mail_'],
                'children' => [
                    ['label' => 'nav.mail_inbox', 'icon' => 'lucide:inbox', 'route' => 'mail_inbox'],
                    ['label' => 'nav.mail_all', 'icon' => 'lucide:mails', 'route' => 'mail_all'],
                    ['label' => 'nav.mail_snoozed', 'icon' => 'lucide:alarm-clock', 'route' => 'mail_snoozed'],
                    ['label' => 'nav.mail_done', 'icon' => 'lucide:circle-check', 'route' => 'mail_done'],
                    ['label' => 'nav.mail_sent', 'icon' => 'lucide:send', 'route' => 'mail_sent'],
                    ['label' => 'nav.mail_drafts', 'icon' => 'lucide:file-pen-line', 'route' => 'mail_drafts'],
                    ['label' => 'nav.mail_trash', 'icon' => 'lucide:trash-2', 'route' => 'mail_trash'],
                ],
            ],
            ['label' => 'nav.forum', 'icon' => 'lucide:messages-square', 'route' => 'forum_index', 'match' => ['forum_']],
        ]],
        ['label' => 'nav.group_plan', 'items' => [
            ['label' => 'nav.calendar', 'icon' => 'lucide:calendar', 'route' => 'calendar_month', 'match' => ['calendar_']],
            ['label' => 'nav.tasks', 'icon' => 'lucide:list-checks', 'route' => 'task_index', 'match' => ['task_']],
            ['label' => 'nav.meetings', 'icon' => 'lucide:presentation', 'route' => 'meeting_index', 'match' => ['meeting_']],
            ['label' => 'nav.resolutions', 'icon' => 'lucide:gavel', 'route' => 'resolution_index', 'match' => ['resolution_']],
            ['label' => 'nav.polls', 'icon' => 'lucide:vote', 'route' => 'poll_index', 'match' => ['poll_']],
            ['label' => 'nav.surveys', 'icon' => 'lucide:clipboard-list', 'route' => 'survey_index', 'match' => ['survey_']],
            ['label' => 'nav.projects', 'icon' => 'lucide:folder-kanban', 'route' => 'project_index', 'match' => ['project_']],
        ]],
        ['label' => 'nav.group_knowledge', 'items' => [
            ['label' => 'nav.files', 'icon' => 'lucide:folder', 'route' => 'file_index', 'match' => ['file_', 'folder_']],
            ['label' => 'nav.shelf', 'icon' => 'lucide:bookmark', 'route' => 'shelf_index', 'match' => ['shelf_']],
            ['label' => 'nav.knowledge', 'icon' => 'lucide:book-open', 'route' => 'wiki_index', 'match' => ['wiki_']],
            [
                'label' => 'nav.contacts',
                'icon' => 'lucide:contact',
                'route' => 'contact_index',
                'match' => ['contact_'],
                'children' => [
                    ['label' => 'nav.contacts_all', 'icon' => 'lucide:contact', 'route' => 'contact_index', 'match' => ['contact_']],
                    ['label' => 'nav.contact_groups', 'icon' => 'lucide:users', 'route' => 'contact_group_index', 'match' => ['contact_group_']],
                ],
            ],
        ]],
        ['label' => 'nav.group_admin', 'items' => [
            [
                'label' => 'nav.admin',
                'icon' => 'lucide:settings',
                'route' => 'organization_index',
                'match' => ['organization_', 'mail_account_', 'mail_rule_', 'snippet_', 'public_settings', 'public_topic_'],
            ],
            // only for platform admins (see getGroups())
            ['label' => 'nav.platform', 'icon' => 'lucide:server-cog', 'route' => 'platform_users', 'match' => ['platform_'], 'role' => 'ROLE_PLATFORM_ADMIN'],
        ]],
    ];

    /**
     * Entries of the "Compose" menu.
     *
     * @var list<array{label: string, icon: string, route: string, params: array<string, string>, feature?: Feature}>
     */
    private const array COMPOSE = [
        ['label' => 'compose_menu.email', 'icon' => 'lucide:mail', 'route' => 'mail_compose', 'params' => []],
        ['label' => 'compose_menu.direct', 'icon' => 'lucide:message-square', 'route' => 'mail_message_new', 'params' => []],
        ['label' => 'compose_menu.topic', 'icon' => 'lucide:messages-square', 'route' => 'forum_topic_choose', 'params' => []],
        ['label' => 'compose_menu.event', 'icon' => 'lucide:calendar-plus', 'route' => 'calendar_item_new', 'params' => [], 'feature' => Feature::Calendar],
        ['label' => 'compose_menu.task', 'icon' => 'lucide:list-plus', 'route' => 'calendar_item_new', 'params' => ['type' => 'task'], 'feature' => Feature::Tasks],
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly MessageRepository $messages,
        private readonly ForumTopicRepository $topics,
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
        private readonly Features $features,
    ) {
    }

    /**
     * Unread messages in the inbox (badge next to "Eingang").
     */
    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->messages->countUnreadInbox($user) : 0;
    }

    /**
     * Forum topics with unread posts (badge next to "Forum").
     */
    public function getUnreadTopicCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->topics->countUnread($user) : 0;
    }

    public function getUnreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notifications->countUnread($user) : 0;
    }

    /**
     * @return list<array{label: string, icon: string, route: string, params: array<string, string>}>
     */
    public function getCompose(): array
    {
        return array_values(array_filter(self::COMPOSE, fn (array $item): bool => $this->isAvailable($item['feature'] ?? Feature::forRoute($item['route']))));
    }

    private function isAvailable(?Feature $feature): bool
    {
        $user = $this->security->getUser();

        return null === $feature || !$user instanceof User || $this->features->isAvailable($user, $feature);
    }

    /**
     * Active sub item: its route is the current one (or _nav), otherwise the longest `match` prefix of the route.
     *
     * @param list<array{label: string, icon: string, route: string, match?: list<string>}> $children
     *
     * @return list<array<string, mixed>>
     */
    private function markActiveChild(array $children, string $route, string $nav): array
    {
        $best = null;
        $bestLength = 0;
        foreach ($children as $i => $child) {
            if ($child['route'] === $nav) {
                $best = $i;
                break;
            }
            foreach ($child['match'] ?? [] as $prefix) {
                if (str_starts_with($route, $prefix) && \strlen($prefix) > $bestLength) {
                    $best = $i;
                    $bestLength = \strlen($prefix);
                }
            }
        }

        $marked = [];
        foreach ($children as $i => $child) {
            $marked[] = $child + ['active' => $i === $best];
        }

        return $marked;
    }

    /**
     * @return list<array{label: ?string, items: list<array<string, mixed>>}>
     */
    public function getGroups(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = (string) $request?->attributes->get('_route');
        // Detailseiten können den aktiven Unterpunkt über das Request-Attribut _nav festlegen
        $nav = (string) $request?->attributes->get('_nav', $route);

        // the item with the longest matching prefix wins (mail_rule_ belongs to admin, not to mail_)
        $best = null;
        $bestLength = 0;
        foreach (self::GROUPS as $group) {
            foreach ($group['items'] as $item) {
                foreach ($item['match'] as $prefix) {
                    if (str_starts_with($route, $prefix) && \strlen($prefix) > $bestLength) {
                        $best = $item['route'];
                        $bestLength = \strlen($prefix);
                    }
                }
            }
        }

        $groups = [];
        foreach (self::GROUPS as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ((isset($item['role']) && !$this->security->isGranted($item['role'])) || !$this->isAvailable(Feature::forRoute($item['route']))) {
                    continue;
                }
                $item['active'] = $item['route'] === $best;
                $item['children'] = $this->markActiveChild($item['children'] ?? [], $route, $nav);
                $item['badge'] = match ($item['route']) {
                    'forum_index' => $this->getUnreadTopicCount(),
                    'notification_index' => $this->getUnreadNotificationCount(),
                    default => 0,
                };
                $item['badge_label'] = 'notification_index' === $item['route'] ? 'notification.unread' : 'forum.unread';
                // key in the JSON of CountsController, updated by the live_counts Stimulus controller
                $item['live'] = match ($item['route']) {
                    'forum_index' => 'forum',
                    'notification_index' => 'notifications',
                    default => null,
                };
                $items[] = $item;
            }
            if ([] !== $items) {
                $groups[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $groups;
    }
}
