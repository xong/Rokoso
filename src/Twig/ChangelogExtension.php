<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Changelog;
use Twig\Attribute\AsTwigFunction;

final readonly class ChangelogExtension
{
    public function __construct(private Changelog $changelog)
    {
    }

    /** Running version (topmost section of CHANGELOG.md). */
    #[AsTwigFunction('app_version')]
    public function version(): string
    {
        return $this->changelog->version();
    }
}
