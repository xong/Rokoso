<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;

/**
 * Reads raw messages from a mailbox. Implemented via IMAP; replaced by a fake in tests.
 */
interface MailboxReader
{
    /**
     * Fetches messages newer than the account's sync position (read-only, flags stay untouched).
     */
    public function fetchNew(MailAccount $account, int $limit = 200): FetchResult;

    /**
     * @throws \Throwable when the connection or login fails
     */
    public function test(MailAccount $account): void;
}
