<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;

/**
 * Stores a copy of a sent message in the account's "sent" folder on the server
 * (the only writing IMAP access). Replaced by a fake in tests.
 */
interface SentFolderWriter
{
    /**
     * @throws \Throwable when the connection or the APPEND fails
     */
    public function append(MailAccount $account, string $raw): void;
}
