<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;

/**
 * Outcome of {@see GuestMailer::send()}: how many guests got the email, how many not (no mailbox or sending failed).
 */
final readonly class GuestDelivery
{
    public function __construct(
        public int $mailed,
        public int $skipped = 0,
        public ?MailAccount $account = null,
        public ?string $error = null,
    ) {
    }

    /**
     * Translation key and parameters for the flash message about guests that got nothing, or null.
     *
     * @return array{string, array<string, int|string>}|null
     */
    public function problem(): ?array
    {
        if (0 === $this->skipped) {
            return null;
        }

        return null === $this->error
            ? ['guest_mail.no_mailbox', ['%count%' => $this->skipped]]
            : ['guest_mail.failed', ['%count%' => $this->skipped, '%error%' => $this->error]];
    }
}
