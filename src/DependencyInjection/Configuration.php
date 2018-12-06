<?php

namespace PetsDeli\QueueBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('pets_deli_queue');

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
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
