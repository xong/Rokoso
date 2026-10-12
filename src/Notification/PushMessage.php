<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Content of one push message; the service worker shows it and opens the URL on click.
 * A message with the same tag replaces the previous one on the device.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $title,
        public string $url,
        public string $tag,
        public string $body = '',
    ) {
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->tag];
    }
}
