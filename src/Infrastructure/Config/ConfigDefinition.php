<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('app');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('app')
                    ->children()
                        ->enumNode('env')
                            ->values(['development', 'testing', 'production'])
                            ->defaultValue('production')
                        ->end()
                        ->booleanNode('debug')
                            ->defaultValue(false)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('logging')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('server')
                    ->children()
                        ->scalarNode('host')
                            ->defaultValue('0.0.0.0')
                            ->validate()
                                ->ifTrue(function ($v) { return !is_string($v) || empty(trim($v)); })
                                ->thenInvalid('Server host must be a non-empty string')
                            ->end()
                        ->end()
                        ->integerNode('port')
                            ->min(1)
                            ->max(65535)
                            ->defaultValue(9501)
                        ->end()
                        ->arrayNode('lifecycle_handlers')
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultValue(true)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('settings')
                            ->children()
                                ->booleanNode('reload_async')
                                    ->defaultValue(true)
                                ->end()
                                ->integerNode('max_wait_time')
                                    ->min(1)
                                    ->defaultValue(60)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('backend')
                    ->children()
                        ->arrayNode('servers')
                            ->prototype('scalar')
                                ->validate()
                                    ->ifTrue(function ($v) { return !is_string($v) || empty(trim($v)); })
                                    ->thenInvalid('Backend server must be a non-empty string')
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Parse comma-separated server list, filtering out empty values
     */
    public static function parseServerList(string $servers): array
    {
        if (empty(trim($servers))) {
            return [];
        }

        $serverList = array_map('trim', explode(',', $servers));
        return array_filter($serverList, fn(string $server) => !empty($server));
    }

    /**
     * Parse boolean values from environment variables
     */
    public static function parseBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}