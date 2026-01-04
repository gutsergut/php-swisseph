<?php

declare(strict_types=1);

namespace Swisseph\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Swisseph\OO\Swisseph;

/**
 * Swisseph Extension for Symfony Dependency Injection
 */
class SwissephExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');

        // Register Swisseph service with configuration
        $container
            ->register('swisseph', Swisseph::class)
            ->setPublic(true)
            ->setArguments([
                '$ephePath' => $config['ephe_path'],
            ])
            ->addMethodCall('setDefaultFlags', [$config['default_flags']])
            ->addMethodCall('setSiderealMode', [
                $config['sidereal']['mode'],
                $config['sidereal']['t0'],
                $config['sidereal']['ayan_t0'],
            ]);

        // Apply sidereal mode if enabled
        if ($config['sidereal']['enabled']) {
            $container
                ->getDefinition('swisseph')
                ->addMethodCall('enableSidereal');
        }

        // Apply topocentric if enabled
        if ($config['topocentric']['enabled']) {
            $container
                ->getDefinition('swisseph')
                ->addMethodCall('setTopocentric', [
                    $config['topocentric']['longitude'],
                    $config['topocentric']['latitude'],
                    $config['topocentric']['altitude'],
                ]);
        }

        // Alias for auto-wiring
        $container->setAlias(Swisseph::class, 'swisseph');
    }

    public function getAlias(): string
    {
        return 'swisseph';
    }
}
