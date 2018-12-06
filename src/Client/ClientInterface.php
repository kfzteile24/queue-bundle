<?php

namespace PetsDeli\QueueBundle\Client;

use Aws\Result;

interface ClientInterface
{
    /**
     * @param mixed $message
     *
     * @return Result
     */
    public function send($message);

    /**
     * @param array $args
     *
     * @return Result
     */
    public function receive(array $args = []);
}
