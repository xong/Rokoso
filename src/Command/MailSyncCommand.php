<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Feature;
use App\Mail\MailSynchronizer;
use App\Repository\MailAccountRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches new mails of all active accounts (organizations using e-mail). Run via cron, e.g. every 5 minutes.
 */
#[AsCommand(name: 'app:mail:sync', description: 'Neue E-Mails aller aktiven Konten abrufen')]
final readonly class MailSyncCommand
{
    public function __construct(
        private MailAccountRepository $accounts,
        private MailSynchronizer $synchronizer,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Nur dieses Konto (ID)')] ?int $account = null): int
    {
        $list = null === $account ? $this->accounts->findBy(['enabled' => true]) : array_filter([$this->accounts->find($account)]);

        foreach ($list as $mailAccount) {
            if (!$mailAccount->getOrganization()->hasFeature(Feature::Mail)) {
                continue;
            }
            $count = $this->synchronizer->sync($mailAccount);
            $error = $mailAccount->getLastSyncError();
            if (null === $error) {
                $io->writeln(\sprintf('%s: %d neue E-Mail(s)', $mailAccount->getEmailAddress(), $count));
            } else {
                $io->warning(\sprintf('%s: %s', $mailAccount->getEmailAddress(), $error));
            }
        }

        return 0;
    }
}
