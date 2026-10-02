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

    public function render(string $markdown): string
    {
        return $this->converter()->convert($markdown)->getContent();
    }

    private function converter(): MarkdownConverter
    {
        if (null !== $this->converter) {
            return $this->converter;
        }
        $environment = new Environment([
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

        return $this->converter = new MarkdownConverter($environment);
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
