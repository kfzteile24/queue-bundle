<?php

namespace Kfz24\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('kfz24_queue');

        $rootNode
            ->info('Queue configuration')
            ->children()
                ->arrayNode('clients')
                    ->isRequired()
                    ->prototype('array')
                        ->children()
                            ->enumNode('type')
                                ->values(['sns', 'sqs', 'mock'])
                                ->isRequired()
                            ->end()
                            ->scalarNode('region')
                                ->isRequired()
                            ->end()
                            ->scalarNode('endpoint')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('resource')
                                ->isRequired()
                            ->end()
                            ->scalarNode('access_key')
                                ->isRequired()
                            ->end()
                            ->scalarNode('secret_access_key')
                                ->isRequired()
                            ->end()
                            ->arrayNode('large_payload_client')
                                ->canBeEnabled()
                                ->children()
                                    ->scalarNode('region')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('endpoint')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('bucket')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('access_key')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('secret_access_key')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('use_path_style_endpoint')
                                        ->defaultFalse()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
