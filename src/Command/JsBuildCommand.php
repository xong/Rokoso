<?php

declare(strict_types=1);

namespace App\Command;

use App\Asset\JsBundler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bundles the JavaScript into one file (see JsBundler); --watch rebuilds on changes (new controllers need a restart).
 */
#[AsCommand(name: 'app:js:build', description: 'JavaScript zu einer Datei bündeln (esbuild)')]
final readonly class JsBuildCommand
{
    public function __construct(private JsBundler $bundler)
    {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Verkleinern (Produktion)')] bool $minify = false,
        #[Option(description: 'Bei Änderungen neu bauen')] bool $watch = false,
    ): int {
        $code = $this->bundler->build($minify, $watch, static function (string $text) use ($output): void {
            $output->write($text);
        });
        if (0 === $code) {
            $output->writeln('<info>var/js/build/app.js gebaut</info>');
        }

        return $code;
    }
}
