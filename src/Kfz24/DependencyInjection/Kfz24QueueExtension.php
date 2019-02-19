<?php

namespace Kfz24\QueueBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Kfz24QueueExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        foreach ($config['clients'] as $name => $client) {
            $clientType = $client['type'];

            $apiVersion = $container->getParameter(sprintf('kfz24.queue.%s.api_version', $clientType));
            $adapterClass = $container->getParameter(sprintf('kfz24.queue.%s.adapter.class', $clientType));
            $clientClass = $container->getParameter(sprintf('kfz24.queue.%s.client.class', $clientType));

            $adapterDefinition = new Definition($adapterClass, [
                [
                    'region' => $client['region'],
                    'endpoint' => $client['endpoint'],
                    'credentials' => [
                        'key' => $client['access_key'],
                        'secret' => $client['secret_access_key']
                    ],
                    'version' => $apiVersion
                ]
            ]);
            $adapterDefinition->setPublic(false);

            $adapterDefinitionName = sprintf('kfz24.queue.adapter.%s', $name);
            $container->setDefinition($adapterDefinitionName, $adapterDefinition);

            $queueClientDefinition = new Definition($clientClass);
            $queueClientDefinition
                ->addTag('kfz24.queue.client')
                ->addArgument(new Reference($adapterDefinitionName))
                ->addArgument($client['resource']);

            if ($clientType === 'sqs') {
                $queueClientDefinition->addMethodCall('setValidator', [new Reference('kfz24.queue.message_validator')]);
            }

            $queueClientDefinitionName = sprintf('kfz24.queue.client.%s', $name);
            $container->setDefinition($queueClientDefinitionName, $queueClientDefinition);
        }
    }
}
