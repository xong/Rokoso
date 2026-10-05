<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Icons are only read from assets/icons (no download outside dev), so every referenced icon must be committed there.
 */
final class IconsTest extends TestCase
{
    public function testEveryReferencedIconIsStoredLocally(): void
    {
        $root = \dirname(__DIR__, 2);
        $finder = (new Finder())->files()->in([$root.'/templates', $root.'/src', $root.'/assets/controllers'])->name(['*.twig', '*.php', '*.js']);
        $missing = [];
        foreach ($finder as $file) {
            preg_match_all('/[\'"]lucide:([a-z0-9-]+)[\'"]/', $file->getContents(), $matches);
            foreach ($matches[1] as $name) {
                if (!is_file($root.'/assets/icons/lucide/'.$name.'.svg')) {
                    $missing[$name] = $file->getRelativePathname();
                }
            }
        }

        self::assertSame([], $missing, 'Icons missing in assets/icons/lucide (render them in dev or run ux:icons:lock, then commit)');
    }
}
