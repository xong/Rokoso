<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\User;
use App\Mail\MessageFilter;
use App\Repository\CalendarItemRepository;
use App\Repository\ContactRepository;
use App\Repository\FolderRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\MessageRepository;
use App\Repository\ResolutionRepository;
use App\Repository\StoredFileRepository;
use App\Repository\WikiPageRepository;

/**
 * Searches all areas the user can see; every repository applies its own visibility rules.
 */
final readonly class GlobalSearch
{
    public const int MIN_LENGTH = 2;
    private const int LIMIT = 8;

    public function __construct(
        private MessageRepository $messages,
        private StoredFileRepository $files,
        private FolderRepository $folders,
        private ForumTopicRepository $topics,
        private ContactRepository $contacts,
        private CalendarItemRepository $items,
        private ResolutionRepository $resolutions,
        private WikiPageRepository $wiki,
    ) {
    }

    /**
     * Results grouped by area; empty groups are left out.
     *
     * @return array<string, list<object>>
     */
    public function search(User $user, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            'messages' => \array_slice($this->messages->findForList($user, new MessageFilter('all', query: $query)), 0, self::LIMIT),
            'files' => $this->files->searchInFolders($this->folders->findVisibleFor($user), $query, self::LIMIT),
            'topics' => $this->topics->search($user, $query, self::LIMIT),
            'contacts' => \array_slice($this->contacts->search($user, $query), 0, self::LIMIT),
            'events' => $this->items->search($user, $query, self::LIMIT),
            'resolutions' => $this->resolutions->search($user, $query, limit: self::LIMIT),
            'wiki' => \array_slice($this->wiki->findVisibleFor($user, $query), 0, self::LIMIT),
        ];

        return array_filter($groups, static fn (array $group): bool => [] !== $group);
    }
}
