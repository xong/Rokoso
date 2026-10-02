<?php

declare(strict_types=1);

namespace App\Twig;

use App\Util\Initials;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    #[AsTwigFunction('initials')]
    public function initials(?string $name): string
    {
        return Initials::of((string) $name);
    }

    /**
     * Human readable file size, e.g. "1,2 MB".
     */
    #[AsTwigFilter('filesize')]
    public function filesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < \count($units) - 1) {
            $size /= 1024;
            ++$i;
        }

        return number_format($size, 0 === $i ? 0 : 1, ',', '.').' '.$units[$i];
    }
}
