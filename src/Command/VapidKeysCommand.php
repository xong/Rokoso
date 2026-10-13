<?php

declare(strict_types=1);

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates a VAPID key pair for web push; the output belongs in .env.local.
 */
#[AsCommand(name: 'app:vapid-keys', description: 'Schlüsselpaar für Web-Push erzeugen')]
final readonly class VapidKeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            // Typical on Windows: OpenSSL cannot find its configuration file; XAMPP ships one next to php.exe
            $conf = \dirname(\PHP_BINARY).\DIRECTORY_SEPARATOR.'extras'.\DIRECTORY_SEPARATOR.'ssl'.\DIRECTORY_SEPARATOR.'openssl.cnf';
            $io->error($e->getMessage().' – unter Windows ggf. OPENSSL_CONF auf die openssl.cnf der PHP-Installation setzen'
                .(is_file($conf) ? ', z. B. PowerShell: $env:OPENSSL_CONF = \''.$conf.'\'' : '').'.');

            return 1;
        }
        $io->writeln('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return 0;
    }
}
