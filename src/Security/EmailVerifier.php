<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\SystemMailer;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Sends signed confirmation links for new accounts and changed email addresses.
 */
final readonly class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $helper,
        private SystemMailer $mailer,
    ) {
    }

    public function sendRegistrationConfirmation(User $user): void
    {
        $signature = $this->helper->generateSignature('app_verify_email', (string) $user->getId(), $user->getEmail(), ['id' => $user->getId()]);
        $this->mailer->send($user->getEmail(), 'email.verify.subject', 'verify', [
            'name' => $user->getName(),
            'url' => $signature->getSignedUrl(),
        ], $user->getName());
    }

    public function sendEmailChangeConfirmation(User $user): void
    {
        $email = (string) $user->getPendingEmail();
        $signature = $this->helper->generateSignature('profile_email_confirm', (string) $user->getId(), $email, ['id' => $user->getId()]);
        $this->mailer->send($email, 'email.email_change.subject', 'email_change', [
            'name' => $user->getName(),
            'url' => $signature->getSignedUrl(),
        ], $user->getName());
    }
}
