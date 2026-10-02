<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\MarkdownRenderer;
use Twig\Attribute\AsTwigFilter;

final readonly class MarkdownExtension
{
    public function __construct(private MarkdownRenderer $renderer)
    {
    }

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function markdown(string $text): string
    {
        return $this->renderer->render($text);
    }
}
