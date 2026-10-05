<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Release notes from CHANGELOG.md: every "## <version> – <date>" section is a release, the topmost is the
 * running version. Users see the notes of a new release on the start page until they have read or dismissed them.
 */
final class Changelog
{
    private const string HEADING = '/^## \[?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)\]?(?:\s+[–-]\s+(\d{4}-\d{2}-\d{2}))?\s*$/mu';

    /** @var list<Release>|null */
    private ?array $releases = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/CHANGELOG.md')]
        private readonly string $file,
    ) {
    }

    /**
     * @return list<Release> newest first
     */
    public function releases(): array
    {
        return $this->releases ??= self::parse(is_file($this->file) ? (string) file_get_contents($this->file) : '');
    }

    public function current(): ?Release
    {
        return $this->releases()[0] ?? null;
    }

    public function version(): string
    {
        return $this->current()->version ?? 'dev';
    }

    /**
     * Release whose notes the user has not seen yet; nothing for accounts created after the release.
     */
    public function unseenBy(User $user): ?Release
    {
        $current = $this->current();
        if (null === $current || $current->version === $user->getSeenVersion()) {
            return null;
        }
        if (null !== $current->date && $user->getCreatedAt() >= $current->date->modify('+1 day')) {
            return null;
        }

        return $current;
    }

    /**
     * @return list<Release>
     */
    public static function parse(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        if (!preg_match_all(self::HEADING, $markdown, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $releases = [];
        foreach ($matches as $i => $match) {
            $start = $match[0][1] + \strlen($match[0][0]);
            $end = $matches[$i + 1][0][1] ?? \strlen($markdown);
            $date = '' !== ($match[2][0] ?? '') ? \DateTimeImmutable::createFromFormat('!Y-m-d', $match[2][0]) : false;
            $releases[] = new Release($match[1][0], false === $date ? null : $date, trim(substr($markdown, $start, $end - $start)));
        }

        return $releases;
    }
}
