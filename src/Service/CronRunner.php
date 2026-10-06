<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Scheduled tasks for hosting whose cron cannot run a suitable PHP (see docs/BETRIEB.md): a cron job calls
 * /_cron/<CRON_TOKEN> every minute and the due commands run with the web PHP.
 * The last runs are kept in a file that is also the lock, so overlapping calls never run a task twice.
 */
final readonly class CronRunner
{
    /** command => interval in minutes (same as the cron lines in docs/BETRIEB.md) */
    public const array TASKS = [
        'app:mail:outbox' => 1,
        'app:mail:sync' => 5,
        'app:notify' => 15,
        'reset-password:remove-expired' => 1440,
    ];

    /** A call a few seconds early (cron jitter) still counts. */
    private const int TOLERANCE = 30;

    public function __construct(
        private KernelInterface $kernel,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire('%kernel.cache_dir%/cron.json')]
        private string $stateFile,
    ) {
    }

    /**
     * Runs the due tasks.
     *
     * @return array<string, string> failed command => its output; empty when everything went fine
     */
    public function run(): array
    {
        $handle = fopen($this->stateFile, 'c+');
        if (false === $handle) {
            return ['cron' => 'Cannot open '.$this->stateFile];
        }
        try {
            if (!flock($handle, \LOCK_EX | \LOCK_NB)) {
                return []; // the previous call is still running
            }

            $state = json_decode((string) stream_get_contents($handle), true);
            $state = \is_array($state) ? $state : [];
            $now = $this->clock->now()->getTimestamp();
            $due = array_keys(array_filter(
                self::TASKS,
                static fn (int $minutes, string $command): bool => $now - (int) ($state[$command] ?? 0) >= $minutes * 60 - self::TOLERANCE,
                \ARRAY_FILTER_USE_BOTH,
            ));
            if ([] === $due) {
                return [];
            }
            foreach ($due as $command) {
                $state[$command] = $now;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($state));
            fflush($handle);

            return $this->runCommands($due);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $commands
     *
     * @return array<string, string>
     */
    private function runCommands(array $commands): array
    {
        ignore_user_abort(true);
        if (\function_exists('set_time_limit')) {
            set_time_limit(600);
        }

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $failed = [];
        foreach ($commands as $command) {
            $input = new ArrayInput(['command' => $command, '--no-interaction' => true]);
            $input->setInteractive(false);
            $output = new BufferedOutput();
            if (0 !== $application->run($input, $output)) {
                $failed[$command] = trim($output->fetch());
                $this->logger->error('Scheduled task {command} failed: {output}', ['command' => $command, 'output' => $failed[$command]]);
            }
        }

        return $failed;
    }
}
