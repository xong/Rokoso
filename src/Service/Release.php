<?php

declare(strict_types=1);

namespace App\Service;

/**
 * One section of CHANGELOG.md; the notes are Markdown.
 */
final readonly class Release
{
    public function __construct(
        public string $version,
        public ?\DateTimeImmutable $date,
        public string $notes,
    ) {
    }

    /**
     * Notes with their headings moved so that the topmost one has the given level (fits the page outline).
     */
    public function notesFrom(int $level): string
    {
        if (!preg_match_all('/^(#{1,6}) /m', $this->notes, $matches)) {
            return $this->notes;
        }
        $shift = $level - min(array_map(strlen(...), $matches[1]));

        return (string) preg_replace_callback(
            '/^(#{1,6}) /m',
            static fn (array $m): string => str_repeat('#', max(1, min(6, \strlen($m[1]) + $shift))).' ',
            $this->notes,
        );
    }
}
