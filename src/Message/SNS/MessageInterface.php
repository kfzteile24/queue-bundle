<?php

namespace Kfz24\QueueBundle\Message\SNS;

/**
 * Interface MessageInterface
 * @package Kfz24\QueueBundle\Message\SNS
 */
interface MessageInterface
{
    /**
     * @return int
     */
    public function getVersion(): int;

    /**
     * @return string
     */
    public function getType(): string;
}
