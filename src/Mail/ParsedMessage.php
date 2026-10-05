<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class ParsedMessage
{
    /**
     * @param list<array{name: string, address: string}>                                           $to
     * @param list<array{name: string, address: string}>                                           $cc
     * @param list<array{filename: string, mimeType: string, content: string, contentId: ?string}> $attachments
     */
    public function __construct(
        public ?string $messageId,
        public ?string $inReplyTo,
        public ?string $references,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyTo,
        public array $to,
        public array $cc,
        public string $subject,
        public \DateTimeImmutable $date,
        public ?string $text,
        public ?string $html,
        public array $attachments,
        /** The receiving server's spam filter flagged it (X-Spam-Flag / X-Spam-Status). */
        public bool $spamFlagged = false,
    ) {
    }
}
