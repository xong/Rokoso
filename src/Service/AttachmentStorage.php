<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Private file storage for message attachments (outside the web root).
 */
final readonly class AttachmentStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage/attachments')]
        private string $baseDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string path relative to the storage directory
     */
    public function store(string $content): string
    {
        $path = date('Y/m').'/'.bin2hex(random_bytes(16));
        $this->filesystem->dumpFile($this->baseDir.'/'.$path, $content);

        return $path;
    }

    public function absolutePath(string $path): string
    {
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Invalid storage path.');
        }

        return $this->baseDir.'/'.$path;
    }

    public function read(string $path): string
    {
        return (string) file_get_contents($this->absolutePath($path));
    }

    public function remove(string $path): void
    {
        $this->filesystem->remove($this->absolutePath($path));
    }
}
