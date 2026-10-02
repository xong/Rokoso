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
     * Group key for the mail list: today, yesterday, week (7 days), month (30 days), older.
     */
    #[AsTwigFunction('date_group')]
    public function dateGroup(\DateTimeInterface $date): string
    {
        $tz = new \DateTimeZone('Europe/Berlin');
        $day = \DateTimeImmutable::createFromInterface($date)->setTimezone($tz)->setTime(0, 0);
        $today = new \DateTimeImmutable('today', $tz);
        $days = (int) $today->diff($day)->format('%r%a');

        return match (true) {
            $days >= 0 => 'today',
            -1 === $days => 'yesterday',
            $days >= -7 => 'week',
            $days >= -30 => 'month',
            default => 'older',
        };
    }

    /**
     * Compact list date: "14:05" today, "Mo 14:05" this week, "03.10." this year, else "03.10.2025".
     */
    #[AsTwigFilter('short_date')]
    public function shortDate(\DateTimeInterface $date): string
    {
        $local = \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('Europe/Berlin'));

        return match ($this->dateGroup($date)) {
            'today' => $local->format('H:i'),
            'yesterday', 'week' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) $local->format('w')].' '.$local->format('H:i'),
            default => $local->format('Y') === date('Y') ? $local->format('d.m.') : $local->format('d.m.Y'),
        };
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
