<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CronRunner;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cron by URL for hosting without a suitable PHP on the command line (see docs/BETRIEB.md).
 * Only active with CRON_TOKEN set; the token is part of the path because URL cron jobs cannot send headers.
 * Silent on success (204), so the cron daemon only mails on failures (500 with the output of the failed tasks).
 */
final readonly class CronController
{
    public function __construct(
        private CronRunner $runner,
        #[Autowire('%env(CRON_TOKEN)%')]
        private string $token,
    ) {
    }

    #[Route('/_cron/{token}', name: 'cron_hook', methods: ['GET', 'POST'])]
    public function __invoke(string $token): Response
    {
        if (\strlen($this->token) < 32 || !hash_equals($this->token, $token)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $failed = $this->runner->run();
        if ([] === $failed) {
            return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'no-store']);
        }
        $report = '';
        foreach ($failed as $command => $output) {
            $report .= '> '.$command."\n".$output."\n\n";
        }

        return new Response($report, Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
