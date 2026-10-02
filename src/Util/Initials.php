<?php

declare(strict_types=1);

namespace App\Util;

final class Initials
{
    /**
     * Up to two uppercase initials of a name ("Anna Maria Schulz" → "AS").
     */
    public static function of(string $name): string
    {
        if (str_contains($name, '@')) {
            $name = strstr($name, '@', true) ?: $name;
        }
        $parts = preg_split('/[\s@._-]+/u', trim($name), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $parts) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = \count($parts) > 1 ? mb_substr($parts[\count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
