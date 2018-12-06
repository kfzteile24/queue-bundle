<?php

namespace PetsDeli\QueueBundle\DependencyInjection;

use Aws\Sns\MessageValidator;
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
class PetsDeliQueueExtension extends Extension
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

        $snsValidatorDefinition = new Definition(MessageValidator::class);
        $snsValidatorDefinitionName = 'petsdeli.queue.message_validator';
        $container->setDefinition($snsValidatorDefinitionName, $snsValidatorDefinition);

        foreach ($config['clients'] as $name => $client) {
            $clientType = $client['type'];

            $apiVersion = $container->getParameter(sprintf('petsdeli.queue.%s.api_version', $clientType));
            $adapterClass = $container->getParameter(sprintf('petsdeli.queue.%s.adapter.class', $clientType));
            $clientClass = $container->getParameter(sprintf('petsdeli.queue.%s.client.class', $clientType));

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

            $adapterDefinitionName = sprintf('petsdeli.queue.adapter.%s', $name);
            $container->setDefinition($adapterDefinitionName, $adapterDefinition);

            $queueClientDefinition = new Definition($clientClass);
            $queueClientDefinition
                ->addTag('petsdeli.queue.client')
                ->addArgument(new Reference($adapterDefinitionName))
                ->addArgument($client['resource']);

            if ($clientType === 'sqs') {
                $queueClientDefinition->addMethodCall('setValidator', [new Reference($snsValidatorDefinitionName)]);
            }

            $queueClientDefinitionName = sprintf('petsdeli.queue.client.%s', $name);
            $container->setDefinition($queueClientDefinitionName, $queueClientDefinition);
        }
    }
}
