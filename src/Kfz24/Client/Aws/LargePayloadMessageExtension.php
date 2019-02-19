<?php

declare(strict_types = 1);

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\S3\S3Client;
use Ramsey\Uuid\Uuid;

class LargePayloadMessageExtension
{
    private const DEFAULT_LARGE_MESSAGE_SIZE_THRESHOLD = 250;

    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var int
     */
    private $largeMessageSizeThreshold;

    /**
     * @param S3Client $s3Client
     */
    public function __construct(S3Client $s3Client)
    {
        $this->s3Client = $s3Client;
        $this->largeMessageSizeThreshold = self::DEFAULT_LARGE_MESSAGE_SIZE_THRESHOLD;
    }

    /**
     * @return int
     */
    public function getLargeMessageSizeThreshold(): int
    {
        return $this->largeMessageSizeThreshold;
    }

    /**
     * @param int $kilobytes
     *
     * @return LargePayloadMessageExtension
     */
    public function setLargeMessageSizeThreshold(int $kilobytes): LargePayloadMessageExtension
    {
        $this->largeMessageSizeThreshold = $kilobytes;

        return $this;
    }

    /**
     * @param array $message
     *
     * @return bool
     */
    public function isMessageLarge(array $message): bool
    {
        $encodedMessage = json_encode($message);
        $messageSizeInBytes = strlen($encodedMessage);

        if ($messageSizeInBytes < ($this->largeMessageSizeThreshold * 1024)) {
            return false;
        }

        return true;
    }

    /**
     * @param array $messageBody
     *
     * @return bool
     */
    public function messageBodyContainsMessageS3Pointer(array $messageBody): bool
    {
        if (isset($messageBody['s3_bucket_name']) && isset($messageBody['s3_key'])) {
            return true;
        }

        return false;
    }

    /**
     * @param array $message
     *
     * @return MessageS3Pointer
     *
     * @throws \Exception
     */
    public function storeMessageInS3(array $message): MessageS3Pointer
    {
        $key = sprintf('%s.json', Uuid::uuid4());

        $result = $this->s3Client->putObject([
            'Key' => $key,
            'Body' => json_encode($message)
        ]);

        $bucketName = (string) $result->get('Bucket') ?? '';

        $messageS3Pointer = new MessageS3Pointer();
        $messageS3Pointer->setS3BucketName($bucketName);
        $messageS3Pointer->setS3Key($key);

        return $messageS3Pointer;
    }

    /**
     * @param MessageS3Pointer $messageS3Pointer
     *
     * @return string
     */
    public function fetchMessageFromS3(MessageS3Pointer $messageS3Pointer): string
    {
        $result = $this->s3Client->getObject([
            'Bucket' => $messageS3Pointer->getS3BucketName(),
            'Key' => $messageS3Pointer->getS3Key()
        ]);

        return (string) $result->get('Body');
    }

    /**
     * @param array $messageBody
     *
     * @return MessageS3Pointer|null
     */
    public function messageS3PointerFromMessageBody(array $messageBody): ?MessageS3Pointer
    {
        if (!$this->messageBodyContainsMessageS3Pointer($messageBody)) {
            return null;
        }

        $messageS3Pointer = new MessageS3Pointer();
        $messageS3Pointer->setS3BucketName($messageBody['s3_bucket_name']);
        $messageS3Pointer->setS3Key($messageBody['s3_key']);

        return $messageS3Pointer;
    }
}
