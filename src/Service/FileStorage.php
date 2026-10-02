<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Private storage for files in the file area and forum (outside the web root).
 */
final readonly class FileStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage/files')]
        private string $baseDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string path relative to the storage directory
     */
    public function storeUpload(UploadedFile $file): string
    {
        $dir = date('Y/m');
        $name = bin2hex(random_bytes(16));
        $file->move($this->baseDir.'/'.$dir, $name);

        return $dir.'/'.$name;
    }

    public function absolutePath(string $path): string
    {
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Invalid storage path.');
        }

        return $this->baseDir.'/'.$path;
    }

    public function remove(string $path): void
    {
        $this->filesystem->remove($this->absolutePath($path));
    }
}
