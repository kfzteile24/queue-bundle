<?php

namespace Kfz24\QueueBundle\Message\SNS;

/**
 * Class PriorityEnvelope
 * @package Kfz24\QueueBundle\Message\SNS
 */
class PriorityEnvelope extends Envelope
{
    /**
     * @var int|null
     * @Serializer\Type("int")
     */
    protected $sequence;

    /**
     * PriorityMessageEnvelop constructor.
     * @param MessageInterface $message
     * @param int $sequence
     */
    public function __construct(MessageInterface $message, int $sequence)
    {
        $this->sequence = $sequence;
        parent::__construct($message);
    }

    /**
     * @return int
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }
}
