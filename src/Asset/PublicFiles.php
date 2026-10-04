<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * URLs for files in public/ that need a fixed path (favicon, PWA icons) with a content hash as query string,
 * so browsers and the service worker pick up a new version immediately. JS/CSS get their hash from AssetMapper.
 */
final class PublicFiles
{
    /** @var array<string, string> */
    private array $urls = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    #[AsTwigFunction('public_file')]
    public function url(string $path): string
    {
        if (!isset($this->urls[$path])) {
            $hash = is_file($this->publicDir.$path) ? hash_file('xxh128', $this->publicDir.$path) : false;
            $this->urls[$path] = false === $hash ? $path : $path.'?v='.substr($hash, 0, 10);
        }

        return $this->urls[$path];
    }
}
