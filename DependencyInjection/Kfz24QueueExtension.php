<?php

namespace Kfz24\QueueBundle\DependencyInjection;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use Aws\Sts\StsClient;
use Kfz24\QueueBundle\Client\Aws\LargePayloadMessageExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Aws\Credentials\CredentialProvider;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Kfz24QueueExtension extends Extension
{
    private const USE_WEB_TOKEN = 'USE_WEB_TOKEN';

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

        $tokenFromEnv = getenv(CredentialProvider::ENV_TOKEN_FILE);
        echo "CONTENTS: " . PHP_EOL;
        var_dump(file_get_contents($tokenFromEnv));
        $arnFromEnv = getenv(CredentialProvider::ENV_ARN);

        $provider = null;
        foreach ($config['clients'] as $name => $client) {
            $clientType = $client['type'];
            $apiVersion = $container->getParameter(sprintf('kfz24.queue.%s.api_version', $clientType));
            $adapterClass = $container->getParameter(sprintf('kfz24.queue.%s.adapter.class', $clientType));
            $clientClass = $container->getParameter(sprintf('kfz24.queue.%s.client.class', $clientType));

            $credentials = [];
            if ($this->containsKeys($client)) {
                $credentials = [
                    'key' => $client['access_key'],
                    'secret' => $client['secret_access_key']
                ];
            } else {
                if (!$provider) {
                    try {
                        $stsClient = new StsClient(['region' => $client['region'], 'version' => 'latest']);
                        $provider = $stsClient->assumeRoleWithWebIdentity([
                            'RoleArn' => 'arn:aws:iam::726569450381:role/k24-integration-2-default-search-service',
                            'RoleSessionName' => sprintf("%s-%s", 'aws-sdk', time()),
                            'WebIdentityToken' => file_get_contents('/var/run/secrets/eks.amazonaws.com/serviceaccount/token'),
                        ]);

                        if (!isset($provider['Credentials'])) {
                            throw new \Exception("Failed to assume role and retrieve credentials.");
                        }
                    } catch (\Throwable $exception) {
                        throw new \Exception("[SQS-Bundle] Message: " . $exception->getMessage(). " Token is:" . $tokenFromEnv);
                    }
                }
            }

            if ($client['type'] === 'sqs') {
                $adapterDefinition = new SqsClient([
                    'region' => $client['region'],
                    'version' => 'latest',
                    'credentials' => new Credentials($provider['Credentials']['AccessKeyId'], $provider['Credentials']['SecretAccessKey'], $provider['Credentials']['SessionToken']),
                    'endpoint' => $client['endpoint'],
                ]);
            } else {
                $adapterDefinition = new Definition($adapterClass, [
                    [
                        'region' => $client['region'],
                        'credentials' => new Credentials($provider['Credentials']['AccessKeyId'], $provider['Credentials']['SecretAccessKey'], $provider['Credentials']['SessionToken']),
                        'version' => $apiVersion,
                        'endpoint' => $client['endpoint'],
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
                        $credentials
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

        $credentials = [];
        if ($provider !== null) {
            $credentials = $provider;
        }

        if ($this->containsKeys($config)) {
            $credentials = [
                'key' => $config['access_key'],
                'secret' => $config['secret_access_key']
            ];
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

        if (strpos($tokenFilePath, 'eks.amazonaws.com') === false) {
            return false;
        }

        return !((file_get_contents($tokenFilePath) === false));
    }

    /**
     * @param array $clientConfigs
     * @return bool
     */
    private function containsKeys(array $clientConfigs): bool
    {
        if (empty($clientConfigs['access_key']) && empty($clientConfigs['secret_access_key'])) {
            return false;
        }

        return true;
    }
}
