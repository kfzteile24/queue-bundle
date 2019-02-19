<?php

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\Result;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Psr\Log\LoggerInterface;

class SqsClient extends AbstractAwsClient
{
    const RESOURCE_NAME = 'QueueUrl';

    /**
     * @var MessageValidator
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LargePayloadMessageExtension
     */
    private $largePayloadMessageExtension;

    /**
     * @param MessageValidator $validator
     *
     * @return SqsClient
     */
    public function setValidator(MessageValidator $validator): SqsClient
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return SqsClient
     */
    public function setLogger(LoggerInterface $logger): SqsClient
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param LargePayloadMessageExtension $largePayloadMessageExtension
     *
     * @return SqsClient
     */
    public function setLargePayloadMessageExtension(
        LargePayloadMessageExtension $largePayloadMessageExtension
    ): SqsClient {
        $this->largePayloadMessageExtension = $largePayloadMessageExtension;

        return $this;
    }

    /**
     * @param string|array $message
     *
     * @return Result
     *
     * @throws \Exception
     */
    public function send($message)
    {
        if (!is_array($message) || !array_key_exists('MessageBody', $message)) {
            $message = ['MessageBody' => json_encode($message)];
        }

        if (
            $this->largePayloadMessageExtension !== null
            && $this->largePayloadMessageExtension->isMessageLarge($message)
        ) {
            $messageS3Pointer = $this->largePayloadMessageExtension->storeMessageInS3($message);
            $message['MessageBody'] = json_encode($messageS3Pointer);
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return $this->sendMessage($message);
    }

    /**
     * @param array $args
     *
     * @return Result
     */
    public function receive(array $args = [])
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $result = $this->receiveMessage($args);
        $messages = $result['Messages'];
        $handledMessages = [];

        if (null !== $messages) {
            foreach ($messages as $message) {
                $body = json_decode($message['Body'], true);

                if (JSON_ERROR_NONE === json_last_error() && is_array($body)) {
                    $message['Body'] = $this->handleSnsMessageBody($body);
                }

                if ($this->largePayloadMessageExtension !== null) {
                    $messageS3Pointer = $this->largePayloadMessageExtension
                        ->messageS3PointerFromMessageBody((array) $message['Body']);

                    if ($messageS3Pointer !== null) {
                        $message['Body'] = $this->largePayloadMessageExtension->fetchMessageFromS3($messageS3Pointer);
                    }
                }

                if ($message['Body'] !== null) {
                    $handledMessages[] = $message;
                }
            }
        }

        $result['Messages'] = $handledMessages;

        return $result;
    }

    /**
     * @param array $body
     *
     * @return string|null
     */
    private function handleSnsMessageBody(array $body): ?string
    {
        // determining whether this is originally a SNS message by loading it
        // into a model which is checking for the presence of keys unique to
        // the SNS envelop and throws an exception otherwise
        try {
            $message = new Message($body);

            if (null !== $this->validator) {
                // if message is legit and valid unfold the body to get rid of the envelop
                if ($this->validator->isValid($message)) {
                    return $body['Message'];
                }

                if ($this->logger) {
                    $this->logger->warning(sprintf('Message %s failed SSL validation', $message['MessageId']));
                }

                return null;
            }
        } catch (\InvalidArgumentException $e) {
            // the constructor of the Message class throws the exception if the passed
            // data simply isn't representing a SNS Message…which is perfectly ok
        }

        return json_encode($body);
    }
}
