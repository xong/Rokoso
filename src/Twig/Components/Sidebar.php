<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Hauptnavigation (linke Spalte).
 *
 * Neue Menüpunkte werden in {@see self::ITEMS} ergänzt. Ein Punkt gilt als aktiv,
 * wenn die aktuelle Route mit einem seiner `match`-Präfixe beginnt.
 */
#[AsTwigComponent]
final class Sidebar
{
    /**
     * @var list<array{label: string, icon: string, route: string, match: list<string>, children?: list<array{label: string, icon: string, route: string}>}>
     */
    private const array ITEMS = [
        [
            'label' => 'nav.mail',
            'icon' => 'lucide:mail',
            'route' => 'mail_inbox',
            'match' => ['mail_'],
            'children' => [
                ['label' => 'nav.mail_inbox', 'icon' => 'lucide:inbox', 'route' => 'mail_inbox'],
                ['label' => 'nav.mail_sent', 'icon' => 'lucide:send', 'route' => 'mail_sent'],
                ['label' => 'nav.mail_trash', 'icon' => 'lucide:trash-2', 'route' => 'mail_trash'],
                ['label' => 'nav.mail_compose', 'icon' => 'lucide:square-pen', 'route' => 'mail_compose'],
            ],
        ],
        ['label' => 'nav.calendar', 'icon' => 'lucide:calendar', 'route' => 'calendar', 'match' => ['calendar']],
        ['label' => 'nav.contacts', 'icon' => 'lucide:contact', 'route' => 'contacts', 'match' => ['contacts']],
        ['label' => 'nav.projects', 'icon' => 'lucide:folder-kanban', 'route' => 'projects', 'match' => ['projects']],
        ['label' => 'nav.organizations', 'icon' => 'lucide:building-2', 'route' => 'organizations', 'match' => ['organizations']],
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $route = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        $items = [];
        foreach (self::ITEMS as $item) {
            $item['active'] = array_any($item['match'], static fn (string $prefix): bool => str_starts_with($route, $prefix));
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                $children[] = $child + ['active' => $child['route'] === $route];
            }
            $item['children'] = $children;
            $items[] = $item;
        }

        return $items;
    }
}
