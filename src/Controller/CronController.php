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
        $this->runner->request();

        return new Response("ok\n", Response::HTTP_ACCEPTED, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
