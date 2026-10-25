<?php

declare(strict_types=1);

namespace App\Twig;

use App\Twig\Components\Sidebar;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Resizable columns (panel_resize_controller.js): the width is stored per device and page (menu item, see
 * {@see Sidebar::section()}) in the cookie "panel_<name>_<page>" as percent of the parent, so the server renders
 * it without flicker.
 */
final class PanelWidthExtension
{
    /** name => [min %, max %] */
    public const array PANELS = [
        'list' => [15, 50],
        'comments' => [12, 60],
    ];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array{name: string, cookie: string, min: int, max: int, value: float|null} value = stored width in percent, null = default
     */
    #[AsTwigFunction('panel_width')]
    public function panelWidth(string $name): array
    {
        [$min, $max] = self::PANELS[$name] ?? throw new \InvalidArgumentException(\sprintf('Unknown panel "%s".', $name));
        $request = $this->requestStack->getMainRequest();
        $cookie = 'panel_'.$name.'_'.Sidebar::section((string) $request?->attributes->get('_route'));
        $stored = $request?->cookies->get($cookie);
        $value = is_numeric($stored) ? round(max($min, min($max, (float) $stored)), 1) : null;

        return ['name' => $name, 'cookie' => $cookie, 'min' => $min, 'max' => $max, 'value' => $value];
    }
}
