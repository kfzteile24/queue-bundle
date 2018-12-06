<?php

namespace PetsDeli\QueueBundle\Client\Aws;

use Aws\AwsClientInterface;
use PetsDeli\QueueBundle\Client\ClientInterface;

abstract class AbstractAwsClient implements ClientInterface
{
    const RESOURCE_NAME = '';

    /**
     * @var AwsClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    private $resource;

    /**
     * @param AwsClientInterface $client
     * @param string             $resource
     */
    public function __construct(AwsClientInterface $client, string $resource)
    {
        $this->client = $client;
        $this->resource = $resource;
    }

    /**
     * @param string $name
     * @param array  $args
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
