<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Scheduled tasks for hosting whose cron cannot run a suitable PHP (see docs/BETRIEB.md): a cron job calls
 * /_cron/<CRON_TOKEN> every minute and the due commands run with the web PHP after the response was sent.
 * The last runs are kept in a file that is also the lock, so overlapping calls never run a task twice.
 */
final class CronRunner
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

    private bool $requested = false;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.cache_dir%/cron.json')]
        private readonly string $stateFile,
    ) {
    }

    /**
     * Runs the due tasks once the current response has been sent.
     */
    public function request(): void
    {
        $this->requested = true;
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function onTerminate(): void
    {
        if ($this->requested) {
            $this->requested = false;
            $this->run();
        }
    }

    /**
     * @return list<string> the commands that ran
     */
    public function run(): array
    {
        $handle = fopen($this->stateFile, 'c+');
        if (false === $handle) {
            $this->logger->error('Cron state file {file} cannot be opened', ['file' => $this->stateFile]);

            return [];
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

            $this->runCommands($due);

            return $due;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $commands
     */
    private function runCommands(array $commands): void
    {
        ignore_user_abort(true);
        if (\function_exists('set_time_limit')) {
            set_time_limit(600);
        }

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        foreach ($commands as $command) {
            $input = new ArrayInput(['command' => $command, '--no-interaction' => true]);
            $input->setInteractive(false);
            $output = new BufferedOutput();
            if (0 !== $application->run($input, $output)) {
                $this->logger->error('Scheduled task {command} failed: {output}', ['command' => $command, 'output' => $output->fetch()]);
            }
        }
    }
}
