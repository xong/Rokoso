<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\HttpFoundation\Request;

/**
 * List state from the query string: folder (route), show filter, project, account, search, page.
 */
final readonly class MessageFilter
{
    public const array SHOW = ['all', 'unassigned', 'mine', 'unread', 'internal'];

    public function __construct(
        public string $folder = 'inbox',
        public string $show = 'all',
        public ?int $project = null,
        public ?int $account = null,
        public string $query = '',
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request, string $folder): self
    {
        $show = $request->query->getString('show', 'all');

        return new self(
            $folder,
            \in_array($show, self::SHOW, true) ? $show : 'all',
            $request->query->getInt('project') ?: null,
            $request->query->getInt('account') ?: null,
            trim($request->query->getString('q')),
            max(1, $request->query->getInt('page', 1)),
        );
    }

    /**
     * Query parameters to keep when linking within the list, including the page – opening a message
     * from page 2 keeps page 2 in the list column.
     *
     * @return array<string, string|int>
     */
    public function params(): array
    {
        return $this->filters() + (1 < $this->page ? ['page' => $this->page] : []);
    }

    public function isActive(): bool
    {
        return [] !== $this->filters();
    }

    /**
     * @return array<string, string|int>
     */
    private function filters(): array
    {
        return array_filter([
            'show' => 'all' === $this->show ? null : $this->show,
            'project' => $this->project,
            'account' => $this->account,
            'q' => '' === $this->query ? null : $this->query,
        ], static fn ($v): bool => null !== $v);
    }

    /**
     * A search from the inbox also finds done and snoozed messages (all folders except trash and spam).
     */
    public function searchesAllFolders(): bool
    {
        return '' !== $this->query && 'inbox' === $this->folder;
    }

    /**
     * Search terms: words, or phrases in double quotes (also typographic ones from phones); every term has to match.
     *
     * @return list<string>
     */
    public function terms(): array
    {
        preg_match_all('/["„“”]([^"„“”]+)["„“”]|([^\s"„“”]+)/u', $this->query, $matches, \PREG_SET_ORDER);
        $terms = [];
        foreach ($matches as $match) {
            $term = trim($match[2] ?? $match[1] ?? '');
            if ('' !== $term) {
                $terms[] = $term;
            }
        }

        return \array_slice(array_values(array_unique($terms)), 0, 8);
    }
}
