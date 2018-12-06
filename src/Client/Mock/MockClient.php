<?php

namespace PetsDeli\QueueBundle\Client\Mock;

use Aws\Result;
use PetsDeli\QueueBundle\Client\ClientInterface;

class MockClient implements ClientInterface
{
    /**
     * @var MockNoopAdapter
     */
    private $client;

    /**
     * @var string
     */
    private $resource;

    /**
     * @param MockNoopAdapter $client
     * @param string|null     $resource
     */
    public function __construct(MockNoopAdapter $client, string $resource = null)
    {
        $this->client = $client;
        $this->resource = $resource;
    }

    /**
     * @param mixed $message
     *
     * @return Result
     */
    public function send($message)
    {
        return $this->client->send($message);
    }

    /**
     * @param array $args
     *
     * @return Result
     */
    public function receive(array $args = [])
    {
        return $this->client->receive($args);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($name, array $arguments = [])
    {
        return call_user_func_array([$this->client, $name], $arguments);
    }
}
