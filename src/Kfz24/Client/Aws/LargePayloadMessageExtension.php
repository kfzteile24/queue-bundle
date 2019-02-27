<?php

declare(strict_types = 1);

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\S3\S3Client;
use Ramsey\Uuid\Uuid;

class LargePayloadMessageExtension
{
    private const DEFAULT_LARGE_MESSAGE_SIZE_THRESHOLD = 250;
    private const S3_BUCKET_NAME_MARKER = '-..s3BucketName..-';
    private const S3_KEY_MARKER = '-..s3Key..-';

    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string
     */
    private $bucketName;

    /**
     * @var int
     */
    private $largeMessageSizeThreshold = self::DEFAULT_LARGE_MESSAGE_SIZE_THRESHOLD;

    /**
     * @param S3Client $s3Client
     * @param string $bucketName
     */
    public function __construct(S3Client $s3Client, string $bucketName)
    {
        $this->s3Client = $s3Client;
        $this->bucketName = $bucketName;
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
        $messageSizeInBytes = strlen($message['MessageBody']);

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

        $this->s3Client->putObject([
            'Bucket' => $this->bucketName,
            'Key' => $key,
            'Body' => $message['MessageBody']
        ]);

        $messageS3Pointer = new MessageS3Pointer();
        $messageS3Pointer->setS3BucketName($this->bucketName);
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
     * @param string $receiptHandle
     *
     * @return void
     */
    public function deleteMessageFromS3(string $receiptHandle): void
    {
        $bucketName = $this->getS3BucketNameFromReceiptHandle($receiptHandle);
        $key = $this->getS3KeyFromReceiptHandle($receiptHandle);

        $this->s3Client->deleteObject([
            'Bucket' => $bucketName,
            'Key' => $key
        ]);
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

    /**
     * @param string $receiptHandle
     * @param MessageS3Pointer $messageS3Pointer
     *
     * @return string
     */
    public function embedS3PointerInReceiptHandle(string $receiptHandle, MessageS3Pointer $messageS3Pointer): string
    {
        $embeddedBucketName = self::S3_BUCKET_NAME_MARKER . $messageS3Pointer->getS3BucketName() . self::S3_BUCKET_NAME_MARKER;
        $embeddedKey = self::S3_KEY_MARKER . $messageS3Pointer->getS3Key() . self::S3_KEY_MARKER;

        $modifiedReceiptHandle =  $embeddedBucketName . $embeddedKey . $receiptHandle;

        return $modifiedReceiptHandle;
    }

    /**
     * @param string $receiptHandle
     *
     * @return bool
     */
    public function isS3ReceiptHandle(string $receiptHandle): bool
    {
        $containsBucketName = strpos($receiptHandle, self::S3_BUCKET_NAME_MARKER) !== false;
        $containsKey = strpos($receiptHandle, self::S3_KEY_MARKER) !== false;

        return ($containsBucketName && $containsKey);
    }

    /**
     * @param string $receiptHandle
     *
     * @return string
     */
    public function getOriginalReceiptHandle(string $receiptHandle): string
    {
        $keyMarkerFirstOccurence = strpos($receiptHandle, self::S3_KEY_MARKER);
        $keyMarkerSecondOccurence = strpos($receiptHandle, self::S3_KEY_MARKER, $keyMarkerFirstOccurence + 1);
        $embeddedInformationEnding = $keyMarkerSecondOccurence + strlen(self::S3_KEY_MARKER);

        return substr($receiptHandle, $embeddedInformationEnding);
    }

    /**
     * @param string $receiptHandle
     *
     * @return string
     */
    public function getS3BucketNameFromReceiptHandle(string $receiptHandle): string
    {
        return $this->getFromReceiptHandleByMarker($receiptHandle, self::S3_BUCKET_NAME_MARKER);
    }

    /**
     * @param string $receiptHandle
     *
     * @return string
     */
    public function getS3KeyFromReceiptHandle(string $receiptHandle): string
    {
        return $this->getFromReceiptHandleByMarker($receiptHandle, self::S3_KEY_MARKER);
    }

    /**
     * @param string $receiptHandle
     * @param string $marker
     *
     * @return string
     */
    private function getFromReceiptHandleByMarker(string $receiptHandle, string $marker): string
    {
        $markerFirstOccurrence = strpos($receiptHandle, $marker);
        $markerSecondOccurrence = strpos($receiptHandle, $marker, $markerFirstOccurrence + 1);

        $markerLength = strlen($marker);
        $informationBeginning = $markerFirstOccurrence + $markerLength;
        $informationEnding = $markerSecondOccurrence - $informationBeginning;

        return substr($receiptHandle, $informationBeginning, $informationEnding);
    }
}
