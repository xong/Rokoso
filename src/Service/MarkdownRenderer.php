<?php

declare(strict_types=1);

namespace App\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

/**
 * Markdown for forum posts: GitHub flavour, raw HTML is escaped, unsafe links (javascript: …) are dropped.
 * Only own images (relative URLs) are embedded; external images become links (no tracking).
 */
final class MarkdownRenderer
{
    private ?MarkdownConverter $converter = null;

    private ?MarkdownConverter $breaksConverter = null;

    public function render(string $markdown): string
    {
        return $this->converter()->convert($markdown)->getContent();
    }

    /**
     * Like render(), but single line breaks within a paragraph stay line breaks (addresses, poems, plain-text habits).
     */
    public function renderWithBreaks(string $markdown): string
    {
        $this->breaksConverter ??= $this->createConverter(true);

        return $this->breaksConverter->convert($markdown)->getContent();
    }

    /**
     * HTML part of an email: line breaks stay as in the text part.
     */
    public function renderEmail(string $markdown): string
    {
        return $this->renderWithBreaks($markdown);
    }

    private function converter(): MarkdownConverter
    {
        return $this->converter ??= $this->createConverter(false);
    }

    private function createConverter(bool $hardBreaks): MarkdownConverter
    {
        $environment = new Environment([
            'renderer' => ['soft_break' => $hardBreaks ? "<br>\n" : "\n"],
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'external_link' => [
                'internal_hosts' => [],
                'open_in_new_window' => true,
                'noopener' => 'external',
                'noreferrer' => 'external',
                'nofollow' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addEventListener(DocumentParsedEvent::class, self::replaceExternalImages(...));

        return new MarkdownConverter($environment);
    }

    private static function replaceExternalImages(DocumentParsedEvent $event): void
    {
        $external = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image && (!str_starts_with($node->getUrl(), '/') || str_starts_with($node->getUrl(), '//'))) {
                $external[] = $node;
            }
        }
        foreach ($external as $image) {
            $link = new Link($image->getUrl(), null, $image->getTitle());
            foreach ($image->children() as $child) {
                $link->appendChild($child);
            }
            if (!$link->hasChildren()) {
                $link->appendChild(new Text($image->getUrl()));
            }
            $image->replaceWith($link);
        }
    }
}
