<?php

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\Result;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use GuzzleHttp\Promise\Promise;
use Psr\Log\LoggerInterface;

class SqsClient extends AbstractAwsClient
{
    const RESOURCE_NAME = 'QueueUrl';

    private const MESSAGE_BODY = 'MessageBody';
    private const MESSAGES = 'Messages';
    private const MESSAGE = 'Message';
    private const BODY = 'Body';
    private const RECEIPT_HANDLE = 'ReceiptHandle';
    private const ENTRIES = 'Entries';

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
     * @param mixed $message
     *
     * @return Result
     *
     * @throws \Exception
     */
    public function send($message)
    {
        $message = $this->prepareMessageFromArrayMessage($message);
        $message = $this->prepareMessageFromNonArrayMessage($message);

        if (
            $this->largePayloadMessageExtension !== null
            && $this->largePayloadMessageExtension->isMessageLarge($message)
        ) {
            $messageS3Pointer = $this->largePayloadMessageExtension->storeMessageInS3($message);
            $message[self::MESSAGE_BODY] = json_encode($messageS3Pointer);
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
        $messages = $result[self::MESSAGES];
        $handledMessages = [];

        if (null !== $messages) {
            foreach ($messages as $message) {
                $body = json_decode($message[self::BODY], true);

                if (JSON_ERROR_NONE === json_last_error() && is_array($body)) {
                    $message[self::BODY] = $this->handleSnsMessageBody($body);
                }

                if ($this->largePayloadMessageExtension !== null) {
                    $messageS3Pointer = $this->largePayloadMessageExtension
                        ->messageS3PointerFromMessageBody(json_decode($message[self::BODY], true));

                    if ($messageS3Pointer !== null) {
                        $message[self::BODY] = $this->largePayloadMessageExtension->fetchMessageFromS3($messageS3Pointer);
                        $message[self::RECEIPT_HANDLE] = $this->largePayloadMessageExtension->embedS3PointerInReceiptHandle(
                            $message[self::RECEIPT_HANDLE],
                            $messageS3Pointer
                        );
                    }
                }

                if ($message[self::BODY] !== null) {
                    $handledMessages[] = $message;
                }
            }
        }

        $result[self::MESSAGES] = $handledMessages;

        return $result;
    }

    /**
     * @param array $args
     *
     * @return Promise
     */
    public function deleteMessageBatchAsync(array $args = []): Promise
    {
        if ($this->largePayloadMessageExtension === null) {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::deleteMessageBatchAsync($args);
        }

        $originalReceipts = [];

        foreach ($args[self::ENTRIES] as $receipt) {
            if (!$this->largePayloadMessageExtension->isS3ReceiptHandle($receipt[self::RECEIPT_HANDLE])) {
                $originalReceipts[] = $receipt;
                continue;
            }

            $this->largePayloadMessageExtension->deleteMessageFromS3($receipt[self::RECEIPT_HANDLE]);

            $originalReceiptHandle = $this->largePayloadMessageExtension->getOriginalReceiptHandle(
                $receipt[self::RECEIPT_HANDLE]
            );

            $originalReceipt = $receipt;
            $originalReceipt[self::RECEIPT_HANDLE] = $originalReceiptHandle;
            $originalReceipts[] = $originalReceipt;
        }

        $args[self::ENTRIES] = $originalReceipts;

        /** @noinspection PhpUndefinedMethodInspection */
        return parent::deleteMessageBatchAsync($args);
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
                    return $body[self::MESSAGE];
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

    /**
     * @param string $message
     *
     * @return bool
     */
    private function isJsonString(string $message): bool
    {
        return (null !== json_decode($message));
    }

    /**
     * @param mixed $message
     *
     * @return array
     */
    private function prepareMessageFromArrayMessage($message): array
    {
        if (is_array($message)) {
            if (!array_key_exists(self::MESSAGE_BODY, $message)) {
                $message = [self::MESSAGE_BODY => json_encode($message)];
            } else if (is_array($message[self::MESSAGE_BODY]) || is_object($message[self::MESSAGE_BODY])) {
                $message[self::MESSAGE_BODY] = json_encode($message[self::MESSAGE_BODY]);
            }
        }

        return $message;
    }

    /**
     * @param mixed $message
     *
     * @return array
     */
    private function prepareMessageFromNonArrayMessage($message): array
    {
        if (!is_array($message)) {
            if (is_string($message) && $this->isJsonString($message)) {
                $message = [self::MESSAGE_BODY => $message];
            } else {
                $message = [self::MESSAGE_BODY => json_encode($message)];
            }
        }

        return $message;
    }
}
