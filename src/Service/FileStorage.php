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
    private const string INCOMING = 'incoming';

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

    /**
     * @return string path relative to the storage directory
     */
    public function store(string $content): string
    {
        $path = date('Y/m').'/'.bin2hex(random_bytes(16));
        $this->filesystem->dumpFile($this->absolutePath($path), $content);

        return $path;
    }

    /**
     * Stores an existing file from outside the storage (e.g. one shared to the app).
     *
     * @return string path relative to the storage directory
     */
    public function storeFrom(string $sourcePath): string
    {
        $path = date('Y/m').'/'.bin2hex(random_bytes(16));
        $this->filesystem->copy($sourcePath, $this->absolutePath($path), true);

        return $path;
    }

    /**
     * @return string path of the copy, relative to the storage directory
     */
    public function copy(string $path): string
    {
        return $this->storeFrom($this->absolutePath($path));
    }

    /**
     * Keeps a file shared to the app until the user picks a folder (see purgeIncoming()).
     *
     * @return string path relative to the storage directory
     */
    public function storeIncoming(UploadedFile $file): string
    {
        $name = bin2hex(random_bytes(16));
        $file->move($this->baseDir.'/'.self::INCOMING, $name);

        return self::INCOMING.'/'.$name;
    }

    public function isIncoming(string $path): bool
    {
        return str_starts_with($path, self::INCOMING.'/');
    }

    /**
     * Removes shared files nobody saved.
     */
    public function purgeIncoming(\DateTimeImmutable $before): int
    {
        $count = 0;
        foreach (glob($this->baseDir.'/'.self::INCOMING.'/*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $before->getTimestamp()) {
                $this->filesystem->remove($path);
                ++$count;
            }
        }

        return $count;
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
