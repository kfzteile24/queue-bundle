<?php

namespace PetsDeli\QueueBundle\Tests\DependencyInjection;

use PetsDeli\QueueBundle\Client\Aws\SnsClient;
use PetsDeli\QueueBundle\Client\Aws\SqsClient;
use PetsDeli\QueueBundle\Client\Mock\MockClient;
use PetsDeli\QueueBundle\DependencyInjection\PetsDeliQueueExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PetsDeliQueueExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;
    /**
     * @var PetsDeliQueueExtension
     */
    private $extension;

    public function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->extension = new PetsDeliQueueExtension();
    }

    /**
     * @param $config
     *
     * @dataProvider validConfigurationProvider
     */
    public function testTaggedService($config)
    {
        $this->extension->load([$config], $this->container);
        $this->assertEquals(count($config['clients']), count($this->container->findTaggedServiceIds('petsdeli.queue.client')));
    }

    public function testSnsClientSetup()
    {
        $this->extension->load([['clients' => ['sns' => $this->getValidSnsConfiguration()]]], $this->container);
        $this->assertInstanceOf(SnsClient::class, $this->container->get('petsdeli.queue.client.sns'));
    }

    public function testSqsClientSetup()
    {
        $this->extension->load([['clients' => ['sqs' => $this->getValidSqsConfiguration()]]], $this->container);
        $this->assertInstanceOf(SqsClient::class, $this->container->get('petsdeli.queue.client.sqs'));
    }

    public function testMockClientSetup()
    {
        $this->extension->load([['clients' => ['mock' => $this->getValidMockConfiguration()]]], $this->container);
        $this->assertInstanceOf(MockClient::class, $this->container->get('petsdeli.queue.client.mock'));
    }

    /**
     * @return array
     */
    public function validConfigurationProvider()
    {
        $snsClient = $this->getValidSnsConfiguration();
        $sqsClient = $this->getValidSqsConfiguration();
        $mockClient = $this->getValidMockConfiguration();

        return [
            [
                [
                    'clients' => [
                        'sns' => $snsClient
                    ]
                ]
            ],
            [
                [
                    'clients' => [
                        'sqs' => $sqsClient
                    ]
                ]
            ],
            [
                [
                    'clients' => [
                        'mock' => $mockClient
                    ]
                ]
            ],
            [
                [
                    'clients' => [
                        'sns' => $snsClient,
                        'sqs' => $sqsClient,
                        'mock' => $mockClient
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    private function getValidSnsConfiguration()
    {
        return [
            'type' => 'sns',
            'region' => 'eu-central-1',
            'endpoint' => null,
            'resource' => 'arn:aws:sns:eu-central-1:123456789012:test-topic',
            'access_key' => 'AKIAABCDEFGHIJKLMNOP',
            'secret_access_key' => 's3CR3t4Cc3S5K3y'
        ];
    }

    /**
     * @return array
     */
    protected function getValidSqsConfiguration()
    {
        return [
            'type' => 'sqs',
            'region' => 'eu-central-1',
            'endpoint' => null,
            'resource' => 'https://sqs.eu-central-1.amazonaws.com/123456789012/test-queue',
            'access_key' => 'AKIAABCDEFGHIJKLMNOP',
            'secret_access_key' => 's3CR3t4Cc3S5K3y'
        ];
    }

    /**
     * @return array
     */
    protected function getValidMockConfiguration()
    {
        return [
            'type' => 'mock',
            'region' => null,
            'endpoint' => null,
            'resource' => null,
            'access_key' => null,
            'secret_access_key' => null
        ];
    }
}
