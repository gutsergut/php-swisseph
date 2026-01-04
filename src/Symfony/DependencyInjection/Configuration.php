<?php

declare(strict_types=1);

namespace Swisseph\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Swisseph\Constants as C;

/**
 * Configuration for Swisseph Bundle
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('swisseph');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('ephe_path')
                    ->info('Path to Swiss Ephemeris data files directory')
                    ->defaultValue('%kernel.project_dir%/var/swisseph/ephe')
                ->end()
                ->integerNode('default_flags')
                    ->info('Default calculation flags (bitwise combination)')
                    ->defaultValue(C::SEFLG_SWIEPH | C::SEFLG_SPEED)
                ->end()
                ->arrayNode('sidereal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable sidereal zodiac calculations')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('mode')
                            ->info('Sidereal mode/ayanamsha ID')
                            ->defaultValue(C::SE_SIDM_LAHIRI)
                        ->end()
                        ->floatNode('t0')
                            ->info('Reference Julian Day for custom ayanamsha')
                            ->defaultValue(0.0)
                        ->end()
                        ->floatNode('ayan_t0')
                            ->info('Ayanamsha value at t0 for custom mode')
                            ->defaultValue(0.0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('topocentric')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable topocentric (parallax) calculations')
                            ->defaultFalse()
                        ->end()
                        ->floatNode('longitude')
                            ->info('Observer longitude in degrees')
                            ->defaultValue(0.0)
                        ->end()
                        ->floatNode('latitude')
                            ->info('Observer latitude in degrees')
                            ->defaultValue(0.0)
                        ->end()
                        ->floatNode('altitude')
                            ->info('Observer altitude in meters above sea level')
                            ->defaultValue(0.0)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
