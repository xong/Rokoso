<?php

declare(strict_types=1);

namespace App\Help;

use App\Enum\Feature;

/**
 * One article of the built-in help, read from help/NN-slug.md.
 */
final readonly class HelpArticle
{
    /**
     * @param list<string> $routes route prefixes the article explains (for the "?" link)
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $body,
        public array $routes = [],
        public ?Feature $feature = null,
        public bool $guide = false,
    ) {
    }
}
