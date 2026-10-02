<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class FetchResult
{
    /**
     * @param array<int, string> $messages raw RFC 822 messages keyed by IMAP UID, ascending
     */
    public function __construct(
        public int $uidValidity,
        public array $messages,
        public bool $complete = true,
    ) {
    }
}
