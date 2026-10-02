<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores user-provided images (avatars, logos, project images) under public/uploads
 * with random, non-guessable file names.
 */
final readonly class ImageUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string public path relative to the web root, e.g. "uploads/avatars/ab12….jpg"
     */
    public function store(UploadedFile $file, string $folder, ?string $replaces = null): string
    {
        $extension = $file->guessExtension() ?? 'bin';
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->uploadDir.'/'.$folder, $name);
        $this->remove($replaces);

        return 'uploads/'.$folder.'/'.$name;
    }

    public function remove(?string $path): void
    {
        if (null === $path || !str_starts_with($path, 'uploads/')) {
            return;
        }
        $this->filesystem->remove(\dirname($this->uploadDir).'/'.$path);
    }
}
