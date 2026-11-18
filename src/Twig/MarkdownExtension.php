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

    /** `|markdown(breaks: true)` keeps single line breaks within paragraphs */
    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function markdown(string $text, bool $breaks = false): string
    {
        return $breaks ? $this->renderer->renderWithBreaks($text) : $this->renderer->render($text);
    }
}
