<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Test-Datenbank einmal pro Lauf frisch aufbauen; DAMA rollt jeden Test zurück.
$kernel = new Kernel('test', true);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);
foreach ([
    ['command' => 'doctrine:database:drop', '--force' => true, '--if-exists' => true],
    ['command' => 'doctrine:database:create'],
    ['command' => 'doctrine:schema:create'],
] as $command) {
    $application->run(new ArrayInput($command + ['--quiet' => true]), new NullOutput());
}
$kernel->shutdown();
