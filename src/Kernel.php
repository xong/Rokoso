<?php

declare(strict_types=1);

namespace App;

use App\Util\HttpClientFactory;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function process(ContainerBuilder $container): void
    {
        // curl may be loaded but unusable on web hostings (see HttpClientFactory)
        if ($container->hasDefinition('http_client.transport')) {
            $container->getDefinition('http_client.transport')->setFactory([HttpClientFactory::class, 'create']);
        }
    }
}
