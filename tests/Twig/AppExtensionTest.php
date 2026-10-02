<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

final class AppExtensionTest extends TestCase
{
    public function testDateGroups(): void
    {
        $ext = new AppExtension();
        $tz = new \DateTimeZone('Europe/Berlin');

        self::assertSame('today', $ext->dateGroup(new \DateTimeImmutable('now', $tz)));
        self::assertSame('yesterday', $ext->dateGroup(new \DateTimeImmutable('yesterday 12:00', $tz)));
        self::assertSame('week', $ext->dateGroup(new \DateTimeImmutable('-5 days', $tz)));
        self::assertSame('month', $ext->dateGroup(new \DateTimeImmutable('-20 days', $tz)));
        self::assertSame('older', $ext->dateGroup(new \DateTimeImmutable('-60 days', $tz)));
    }

    public function testInitialsAndFilesize(): void
    {
        $ext = new AppExtension();

        self::assertSame('AS', $ext->initials('Anna Maria Schulz'));
        self::assertSame('E', $ext->initials('eva@example.org'));
        self::assertSame('EM', $ext->initials('eva.mueller@example.org'));
        self::assertSame('1,5 KB', $ext->filesize(1536));
    }
}
