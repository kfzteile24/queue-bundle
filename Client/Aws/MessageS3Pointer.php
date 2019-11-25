<?php

declare(strict_types = 1);

namespace Kfz24\QueueBundle\Client\Aws;

use JsonSerializable;

class MessageS3Pointer implements JsonSerializable
{
    /**
     * @var string
     */
    private $s3BucketName;

    /**
     * @var string
     */
    private $s3Key;

    /**
     * @return string
     */
    public function getS3BucketName(): string
    {
        return $this->s3BucketName;
    }

    /**
     * @param string $s3BucketName
     *
     * @return MessageS3Pointer
     */
    public function setS3BucketName(string $s3BucketName): MessageS3Pointer
    {
        $this->s3BucketName = $s3BucketName;

        return $this;
    }

    /**
     * @return string
     */
    public function getS3Key(): string
    {
        return $this->s3Key;
    }

    /**
     * @param string $s3Key
     *
     * @return MessageS3Pointer
     */
    public function setS3Key(string $s3Key): MessageS3Pointer
    {
        $this->s3Key = $s3Key;

        return $this;
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        return [
            's3_bucket_name' => $this->getS3BucketName(),
            's3_key' => $this->getS3Key(),
        ];
    }
}
