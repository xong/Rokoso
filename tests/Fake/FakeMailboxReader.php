<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Entity\MailAccount;
use App\Mail\FetchResult;
use App\Mail\MailboxReader;

/**
 * In-memory mailbox for tests. Messages are keyed by UID.
 */
final class FakeMailboxReader implements MailboxReader
{
    /** @var array<int, string> */
    public array $messages = [];

    public int $uidValidity = 1;

    public function add(string $raw): int
    {
        $uid = [] === $this->messages ? 1 : max(array_keys($this->messages)) + 1;
        $this->messages[$uid] = $raw;

        return $uid;
    }

    public function fetchNew(MailAccount $account, int $limit = 200): FetchResult
    {
        $this->test($account);
        $last = $account->getUidValidity() === $this->uidValidity ? ($account->getLastUid() ?? 0) : 0;
        $new = array_filter($this->messages, static fn (int $uid): bool => $uid > $last, \ARRAY_FILTER_USE_KEY);
        ksort($new);

        return new FetchResult($this->uidValidity, \array_slice($new, 0, $limit, true), \count($new) <= $limit);
    }

    public function test(MailAccount $account): void
    {
        if ('fail.example.org' === $account->getImapHost()) {
            throw new \RuntimeException('connection refused');
        }
    }
}
