<?php

declare(strict_types=1);

namespace App\Command;

use App\Mail\Outbox;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends queued mails whose undo delay has passed. Run via cron, e.g. every minute.
 */
#[AsCommand(name: 'app:mail:outbox', description: 'Wartende E-Mails (Senden rückgängig) verschicken')]
final readonly class MailOutboxCommand
{
    public function __construct(private Outbox $outbox)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(\sprintf('%d E-Mail(s) gesendet', $this->outbox->sendDue()));

        return 0;
    }
}
