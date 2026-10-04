<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Deploy hook for hosting without a suitable PHP on the command line (see docs/BETRIEB.md):
 * GitHub Actions calls it after switching the release; it runs the migrations with the web PHP.
 * Only active with DEPLOY_TOKEN set; the release name guards against still reaching the old release
 * (realpath cache of the web server after switching the symlink), the caller retries on 409.
 */
final class DeployController
{
    private const array COMMANDS = [
        ['command' => 'doctrine:migrations:migrate', '--allow-no-migration' => true],
        ['command' => 'cache:warmup'],
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
        #[Autowire('%env(DEPLOY_TOKEN)%')]
        private readonly string $token,
    ) {
    }

    #[Route('/_deploy', name: 'deploy_hook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (\strlen($this->token) < 32) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        if (!hash_equals($this->token, (string) $request->headers->get('X-Deploy-Token'))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $release = basename($this->kernel->getProjectDir());
        if ($request->request->getString('release') !== $release) {
            return new Response('running release: '.$release."\n", Response::HTTP_CONFLICT, ['Content-Type' => 'text/plain']);
        }

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $status = Response::HTTP_OK;
        foreach (self::COMMANDS as $command) {
            $input = new ArrayInput($command + ['--no-interaction' => true]);
            $input->setInteractive(false);
            $output->writeln('> '.$command['command']);
            if (0 !== $application->run($input, $output)) {
                $status = Response::HTTP_INTERNAL_SERVER_ERROR;
                break;
            }
        }
        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        return new Response($output->fetch(), $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
