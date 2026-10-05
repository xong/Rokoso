<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Resizable columns (panel_resize_controller.js): the width is stored per device in the cookie "panel_<name>"
 * as percent of the parent, so the server renders it without flicker.
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
     * @return array{name: string, min: int, max: int, value: float|null} value = stored width in percent, null = default
     */
    #[AsTwigFunction('panel_width')]
    public function panelWidth(string $name): array
    {
        [$min, $max] = self::PANELS[$name] ?? throw new \InvalidArgumentException(\sprintf('Unknown panel "%s".', $name));
        $stored = $this->requestStack->getMainRequest()?->cookies->get('panel_'.$name);
        $value = is_numeric($stored) ? round(max($min, min($max, (float) $stored)), 1) : null;

        return ['name' => $name, 'min' => $min, 'max' => $max, 'value' => $value];
    }
}
