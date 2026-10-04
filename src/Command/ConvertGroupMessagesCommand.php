<?php

declare(strict_types=1);

namespace App\Command;

use App\Forum\GroupMessageConverter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off migration: internal messages to an organization or project become forum topics.
 */
#[AsCommand(name: 'app:messages:convert-group', description: 'Interne Rundnachrichten in Forenthemen umwandeln')]
final readonly class ConvertGroupMessagesCommand
{
    public function __construct(
        private GroupMessageConverter $converter,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $topics = $this->converter->convert();
        $io->success(\sprintf('%d Forenthema/-themen angelegt.', $topics));

        return 0;
    }
}
