<?php

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\Result;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use GuzzleHttp\Promise\Promise;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class SqsClient extends AbstractAwsClient
{
    private const MAX_BATCH_SIZE = 10;

    protected const RESOURCE_NAME = 'QueueUrl';

    private const MESSAGE_BODY = 'MessageBody';
    private const ID = 'Id';
    private const MESSAGES = 'Messages';
    private const MESSAGE = 'Message';
    private const BODY = 'Body';
    private const RECEIPT_HANDLE = 'ReceiptHandle';
    private const ENTRIES = 'Entries';
    private const ATTRIBUTES = 'Attributes';
    private const ATTRIBUTE_NAMES = 'AttributeNames';
    private const APPROXIMATE_NUMBER_OF_MESSAGES = 'ApproximateNumberOfMessages';

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
        if (is_array($message)) {
            $message = $this->prepareMessageFromArrayMessage($message);
        } else {
            $message = $this->prepareMessageFromNonArrayMessage($message);
        }


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
     * @param array $messages
     */
    public function sendBatch(array $messages)
    {
        if (count($messages) > self::MAX_BATCH_SIZE) {
            throw new \RuntimeException('SQS batch size is hard limited to ' . self::MAX_BATCH_SIZE);
        }
        $messages = array_map(
            function ($message) {
                if (is_array($message)) {
                    $message = $this->prepareMessageFromArrayMessage($message);
                } else {
                    $message = $this->prepareMessageFromNonArrayMessage($message);
                }

                if (
                    $this->largePayloadMessageExtension !== null
                    && $this->largePayloadMessageExtension->isMessageLarge($message)
                ) {
                    $messageS3Pointer = $this->largePayloadMessageExtension->storeMessageInS3($message);
                    $message[self::MESSAGE_BODY] = json_encode($messageS3Pointer);
                }

                $message[self::ID] = Uuid::uuid4()->toString();

                return $message;
            },
            $messages
        );

        /** @noinspection PhpUndefinedMethodInspection */
        return $this->sendMessageBatch([self::ENTRIES => $messages]);
    }

    /**
     * @param array $messages
     */
    public function sendBufferedBatch(array $messages)
    {
        while (count($messages) > 0) {
            $this->sendBatch(array_splice($messages, 0, 10));
        }
    }

    /**
     * @param array $options
     * @return mixed|null
     */
    public function getQueueAttributes(array $options = [])
    {
        $options[self::RESOURCE_NAME] = $this->resource;

        /** @var Result $result */
        $result = parent::getQueueAttributes($options);

        return $result->get(self::ATTRIBUTES);
    }

    /**
     * @return int
     */
    public function getApproximateNumberOfMessages(): int
    {
        $attributes = $this->getQueueAttributes([self::ATTRIBUTE_NAMES => [self::APPROXIMATE_NUMBER_OF_MESSAGES]]);

        return $attributes[self::APPROXIMATE_NUMBER_OF_MESSAGES];
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
     * @param array $args
     *
     * @return Result
     */
    public function deleteMessageBatch(array $args = []): Result
    {
        if ($this->largePayloadMessageExtension === null) {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::deleteMessageBatch($args);
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
        return parent::deleteMessageBatch($args);
    }

    /**
     * @param array $args
     * @param int $timeout
     * @return void
     */
    public function changeMessageVisibility(array $args, int $timeout): void
    {
        foreach ($args[self::ENTRIES] as $receipt) {
            /** @noinspection PhpUndefinedMethodInspection */
            parent::changeMessageVisibility([
                'ReceiptHandle' => $receipt[self::RECEIPT_HANDLE],
                'VisibilityTimeout' => $timeout,
            ]);
        }
    }

    /**
     * @param array $args
     * @param int $timeout
     * @return void
     */
    public function changeMessageVisibilityBatch(array $args): void
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::changeMessageVisibilityBatch([
            self::ENTRIES => $args
        ]);
    }

    /**
     * @param array $args
     *
     * @return Result
     */
    public function purgeQueue(array $args = []): Result
    {
        return parent::purgeQueue($args);
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
     * @param array $message
     *
     * @return array
     */
    private function prepareMessageFromArrayMessage(array $message): array
    {
        if (!array_key_exists(self::MESSAGE_BODY, $message)) {
            $message = [self::MESSAGE_BODY => json_encode($message)];
        } elseif (is_array($message[self::MESSAGE_BODY]) || is_object($message[self::MESSAGE_BODY])) {
            $message[self::MESSAGE_BODY] = json_encode($message[self::MESSAGE_BODY]);
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
        if (is_string($message) && $this->isJsonString($message)) {
            $message = [self::MESSAGE_BODY => $message];
        } else {
            $message = [self::MESSAGE_BODY => json_encode($message)];
        }

        return $message;
    }
}
