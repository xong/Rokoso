<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;
use App\Enum\MailEncryption;
use App\Service\SecretBox;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds the SMTP transport of a mail account.
 */
final readonly class SmtpTransportFactory
{
    public function __construct(
        private SecretBox $secretBox,
        /** e.g. "null://null" in tests: replaces every account transport */
        #[Autowire(env: 'default::MAIL_ACCOUNT_TRANSPORT_OVERRIDE')]
        private ?string $override = null,
    ) {
    }

    public function create(MailAccount $account): TransportInterface
    {
        if (null !== $this->override && '' !== $this->override) {
            return Transport::fromDsn($this->override);
        }

        $username = $account->getSmtpUsername() ?? $account->getImapUsername();
        $encrypted = null !== $account->getSmtpUsername() ? $account->getSmtpPassword() : $account->getImapPassword();
        $password = null === $encrypted ? '' : $this->secretBox->decrypt($encrypted);

        $dsn = \sprintf(
            '%s://%s%s@%s:%d%s',
            MailEncryption::Ssl === $account->getSmtpEncryption() ? 'smtps' : 'smtp',
            rawurlencode($username),
            '' === $password ? '' : ':'.rawurlencode($password),
            $account->getSmtpHost(),
            $account->getSmtpPort(),
            MailEncryption::None === $account->getSmtpEncryption() ? '?auto_tls=false' : '',
        );

        return Transport::fromDsn($dsn);
    }

    /**
     * @throws \Throwable when the connection or login fails
     */
    public function test(MailAccount $account): void
    {
        $transport = $this->create($account);
        if ($transport instanceof SmtpTransport) {
            $transport->start();
            $transport->stop();
        }
    }
}
