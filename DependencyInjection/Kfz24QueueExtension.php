<?php

namespace Kfz24\QueueBundle\DependencyInjection;

use Aws\Credentials\AssumeRoleWithWebIdentityCredentialProvider;
use Aws\S3\S3Client;
use Kfz24\QueueBundle\Client\Aws\LargePayloadMessageExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Aws\Sts\StsClient;
use Aws\Credentials\CredentialProvider;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Kfz24QueueExtension extends Extension
{
    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $arnFromEnv = getenv(CredentialProvider::ENV_ARN);
        $tokenFromEnv = getenv(CredentialProvider::ENV_TOKEN_FILE);

        $provider = null;
        foreach ($config['clients'] as $name => $client) {
            $clientType = $client['type'];
            $apiVersion = $container->getParameter(sprintf('kfz24.queue.%s.api_version', $clientType));
            $adapterClass = $container->getParameter(sprintf('kfz24.queue.%s.adapter.class', $clientType));
            $clientClass = $container->getParameter(sprintf('kfz24.queue.%s.client.class', $clientType));


            if (empty($arnFromEnv) && empty($tokenFromEnv)) {
                echo '[SQS-Bundle] Role-based access denied due to no token file. Accessing via keys...' . PHP_EOL;

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
            } else {
                if (!$provider) {
                    echo '[SQS-Bundle] Role-based access approved. Accessing via identity token...' . PHP_EOL;
                    echo '[SQS-Bundle] File is: ' . $client['role_based']['web_identity_token_file'] . PHP_EOL;

                    $provider = new AssumeRoleWithWebIdentityCredentialProvider([
                        'RoleArn' => $arnFromEnv,
                        'WebIdentityTokenFile' => $tokenFromEnv,
                        'SessionName' => 'aws-sdk-' . time(),
                        'client' => new StsClient([
                            'region'      => $client['region'],
                            'version'     => $apiVersion,
                            'credentials' => false
                        ]),
                        'region' => $client['region'],
                        'source' => null
                    ]);
                    // Cache the results in a memoize function to avoid loading and parsing
                    // the ini file on every API operation
                    //$provider = CredentialProvider::memoize($provider);
                }

                $adapterDefinition = new Definition($adapterClass, [
                    [
                        'region' => $client['region'],
                        'endpoint' => $client['endpoint'],
                        'version' => $apiVersion,
                        'credentials' => $provider
                    ]
                ]);
            }

            $adapterDefinition->setPublic(false);
            $adapterDefinitionName = sprintf('kfz24.queue.adapter.%s', $name);
            $container->setDefinition($adapterDefinitionName, $adapterDefinition);

            $queueClientDefinition = new Definition($clientClass);
            $queueClientDefinition
                ->addTag('kfz24.queue.client')
                ->setArguments(
                    [
                        new Reference($adapterDefinitionName),
                        new Reference('jms_serializer.serializer'),
                        $client['resource']
                    ]
                );

            if ($clientType === 'sqs') {
                $queueClientDefinition->addMethodCall('setValidator', [new Reference('kfz24.queue.message_validator')]);

                if ($client['large_payload_client']['enabled']) {
                    $s3DefinitionName = sprintf('kfz24.queue.s3client.%s', $name);
                    $largePayloadMessageExtensionDefinitionName = sprintf(
                        'kfz24.queue.large_payload_message_extension.%s',
                        $name
                    );

                    $this->buildS3ClientDefinition(
                        $s3DefinitionName,
                        $client['large_payload_client'],
                        $container,
                        $provider
                    );

                    $this->buildLargePayloadMessageExtensionDefinition(
                        $largePayloadMessageExtensionDefinitionName,
                        $s3DefinitionName,
                        $client['large_payload_client']['bucket'],
                        $container
                    );

                    $queueClientDefinition->addMethodCall(
                        'setLargePayloadMessageExtension',
                        [new Reference($largePayloadMessageExtensionDefinitionName)]
                    );
                }
            }

            $queueClientDefinitionName = sprintf('kfz24.queue.client.%s', $name);
            $container->setDefinition($queueClientDefinitionName, $queueClientDefinition);
        }
    }

    /**
     * @param string $definitionName
     * @param string $s3ClientDefinitionName
     * @param string $bucketName
     * @param ContainerBuilder $container
     */
    private function buildLargePayloadMessageExtensionDefinition(
        string $definitionName,
        string $s3ClientDefinitionName,
        string $bucketName,
        ContainerBuilder $container
    ): void {
        $largePayloadMessageExtensionDefinition = new Definition(LargePayloadMessageExtension::class, [
            new Reference($s3ClientDefinitionName),
            $bucketName
        ]);

        $container->setDefinition($definitionName, $largePayloadMessageExtensionDefinition);
    }

    /**
     * @param string $definitionName
     * @param array $config
     * @param ContainerBuilder $container
     * @param null|mixed $provider
     */
    private function buildS3ClientDefinition(string $definitionName, array $config, ContainerBuilder $container, $provider = null): void
    {
        $usePathStyleEndpointEnvVar = $container->resolveEnvPlaceholders(
            $config['use_path_style_endpoint'],
            true
        );

        $credentials = [
            'key' => $config['access_key'],
            'secret' => $config['secret_access_key'],
        ];
        if ($provider !== null) {
            $credentials = $provider;
        }

        $s3ClientDefinition = new Definition(S3Client::class, [
            [
                'region' => $config['region'],
                'endpoint' => $config['endpoint'],
                'credentials' => $credentials,
                'use_path_style_endpoint' => ($usePathStyleEndpointEnvVar === 'true'),
                'version' => '2006-03-01',
            ],
        ]);

        $container->setDefinition($definitionName, $s3ClientDefinition);
    }

    /**
     * @param string|null $tokenFilePath
     * @return bool
     */
    private function isTokenFileValid(?string $tokenFilePath): bool
    {
        if (empty($tokenFilePath)) {
            return false;
        }

        if (!file_exists($tokenFilePath)) {
            return false;
        }

        return !((file_get_contents($tokenFilePath) === false));
    }
}
