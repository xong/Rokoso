<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;

/**
 * Outcome of {@see OrganizationMailer::send()}: how many addresses got the email, how many not (no mailbox or sending failed).
 */
final readonly class MailboxDelivery
{
    public function __construct(
        public int $mailed,
        public int $skipped = 0,
        public ?MailAccount $account = null,
        public ?string $error = null,
    ) {
    }

    /**
     * Translation key and parameters for the flash message about addresses that got nothing, or null.
     *
     * @return array{string, array<string, int|string>}|null
     */
    public function problem(): ?array
    {
        if (0 === $this->skipped) {
            return null;
        }

        return null === $this->error
            ? ['org_mail.no_mailbox', ['%count%' => $this->skipped]]
            : ['org_mail.failed', ['%count%' => $this->skipped, '%error%' => $this->error]];
    }
}
