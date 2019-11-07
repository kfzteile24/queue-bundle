<?php

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\AwsClientInterface;
use Kfz24\QueueBundle\Client\ClientInterface;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractAwsClient implements ClientInterface
{
    const RESOURCE_NAME = '';

    /**
     * @var AwsClientInterface
     */
    protected $client;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var string
     */
    private $resource;

    /**
     * @param AwsClientInterface $client
     * @param SerializerInterface $serializer
     * @param string $resource
     */
    public function __construct(AwsClientInterface $client, SerializerInterface $serializer, string $resource)
    {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->resource = $resource;
    }

    /**
     * @param string $name
     * @param array $args
     *
     * @return mixed
     */
    public function __call($name, array $args = [])
    {
        if (1 === count($args) && is_array($args[0])) {
            $args[0] = $this->applyResource($args[0]);
        }

        return call_user_func_array([$this->client, $name], $args);
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private function applyResource(array $args = [])
    {
        if (!array_key_exists(static::RESOURCE_NAME, $args) && null !== $this->resource) {
            $args[static::RESOURCE_NAME] = $this->resource;
        }

        return $args;
    }
}
