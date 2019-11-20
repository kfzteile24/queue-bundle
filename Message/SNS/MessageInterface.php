<?php

namespace Kfz24\QueueBundle\Message\SNS;

use JMS\Serializer\Annotation as Serializer;

/**
 * Interface MessageInterface
 * @package Kfz24\QueueBundle\Message\SNS
 * @Serializer\ExclusionPolicy("all")
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
