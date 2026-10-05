<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;

/**
 * Outcome of {@see InvitationManager::invite()}: whether the address has an account and whether the email went out.
 */
final readonly class InvitationResult
{
    public function __construct(
        public Invitation $invitation,
        public bool $hasAccount,
        public bool $mailed,
    ) {
    }
}
