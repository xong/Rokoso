<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\SecurityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Grants (or with --revoke removes) the platform admin role. There is no UI for this on purpose.
 */
#[AsCommand(name: 'app:user:promote', description: 'Konto zum Plattform-Admin machen (oder mit --revoke zurücknehmen)')]
final readonly class UserPromoteCommand
{
    public function __construct(private UserRepository $users, private EntityManagerInterface $em, private SecurityLog $log)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('E-Mail-Adresse des Kontos')] string $email, #[Option('Recht entziehen')] bool $revoke = false): int
    {
        $user = $this->users->findOneByEmail($email);
        if (null === $user || null !== $user->getDeletedAt()) {
            $io->error('Kein Konto mit dieser Adresse.');

            return 1;
        }
        $user->setPlatformAdmin(!$revoke);
        $this->em->flush();
        if (!$revoke) {
            $this->log->record('platform_admin', $user);
        }
        $io->success(\sprintf('%s ist %s Plattform-Admin.', $user->getEmail(), $revoke ? 'kein' : 'jetzt'));

        return 0;
    }
}
