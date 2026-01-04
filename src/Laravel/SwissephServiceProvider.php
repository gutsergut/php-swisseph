<?php

declare(strict_types=1);

namespace Swisseph\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Swisseph\OO\Swisseph;

/**
 * Laravel Service Provider for Swiss Ephemeris
 *
 * @example
 * Add to config/app.php:
 * ```php
 * 'providers' => [
 *     // ...
 *     Swisseph\Laravel\SwissephServiceProvider::class,
 * ],
 *
 * 'aliases' => [
 *     // ...
 *     'Swisseph' => Swisseph\Laravel\SwissephFacade::class,
 * ],
 * ```
 *
 * Publish config:
 * ```bash
 * php artisan vendor:publish --provider="Swisseph\Laravel\SwissephServiceProvider"
 * ```
 */
class SwissephServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/swisseph.php',
            'swisseph'
        );

        $this->app->singleton(Swisseph::class, function (Application $app) {
            $config = $app['config']['swisseph'];

            $swisseph = new Swisseph($config['ephe_path'] ?? null);

            // Apply default flags from config
            if (isset($config['default_flags'])) {
                $swisseph->setDefaultFlags($config['default_flags']);
            }

            // Apply sidereal mode if configured
            if (isset($config['sidereal_mode'])) {
                $swisseph->setSiderealMode(
                    $config['sidereal_mode'],
                    $config['sidereal_t0'] ?? 0.0,
                    $config['sidereal_ayan_t0'] ?? 0.0
                );

                if ($config['enable_sidereal'] ?? false) {
                    $swisseph->enableSidereal();
                }
            }

            // Apply topocentric if configured
            if (isset($config['topocentric'])) {
                $topo = $config['topocentric'];
                $swisseph->setTopocentric(
                    $topo['longitude'],
                    $topo['latitude'],
                    $topo['altitude'] ?? 0.0
                );

                if ($topo['enabled'] ?? false) {
                    $swisseph->enableTopocentric();
                }
            }

            return $swisseph;
        });

        $this->app->alias(Swisseph::class, 'swisseph');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__ . '/../../config/swisseph.php' => config_path('swisseph.php'),
            ], 'swisseph-config');

            // Register commands
            $this->commands([
                SwissephTestCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Swisseph::class,
            'swisseph',
        ];
    }
}
