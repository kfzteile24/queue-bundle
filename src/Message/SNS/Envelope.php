<?php

namespace Kfz24\QueueBundle\Message\SNS;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class Envelope
 * @package Kfz24\QueueBundle\Message\SNS
 */
class Envelope
{
    /**
     * @var MessageInterface
     * @Serializer\MaxDepth(1)
     */
    protected $message;

    /**
     * @var \DateTimeImmutable
     * @Serializer\Type("DateTimeImmutable")
     */
    protected $createdAt;

    /**
     * AbstractEnvelop constructor.
     * @param MessageInterface $message
     */
    public function __construct(MessageInterface $message)
    {
        $this->message = $message;
    }

    /**
     * @return int
     * @Serializer\VirtualProperty("version")
     */
    public function getVersion(): int
    {
        return $this->message->getVersion();
    }


    /**
     * @return string
     * @Serializer\VirtualProperty("type")
     */
    public function getType(): string
    {
        return $this->message->getType();
    }

    /**
     * @return MessageInterface
     */
    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeImmutable $dateTime
     * @return $this
     */
    public function setCreatedAt(\DateTimeImmutable $dateTime)
    {
        $this->createdAt = $dateTime;

        return $this;
    }
}
