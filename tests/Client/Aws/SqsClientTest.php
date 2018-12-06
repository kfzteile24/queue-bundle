<?php

namespace PetsDeli\QueueBundle\Tests\Client\Aws;

use Aws\AwsClient;
use Aws\Result;
use Aws\Sns\MessageValidator;
use PetsDeli\QueueBundle\Client\Aws\SqsClient;

class SqsClientTest extends \PHPUnit_Framework_TestCase
{
    const MESSAGE_CONTENT = [
        'test' => true,
        'foo' => 'bar',
        'val' => 4711
    ];

    const RESOURCE = 'https://sqs.eu-central-1.amazonaws.com/123456789012/test-queue';

    public function testReceiveSnsMessage()
    {
        $snsMessage = $this->getSnsMessage(self::MESSAGE_CONTENT);
        $awsClient = $this->getReceivingAwsClientMock(['Messages' => [$this->getSqsMessage($snsMessage)]]);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->setValidator($this->getValidatorMock(true));

        $this->assertEquals([
            'Messages' => [$this->getSqsMessage(self::MESSAGE_CONTENT)]
        ], $client->receive());
    }

    public function testReceiveSnsMessageWithInvalidSignature()
    {
        $snsMessage = $this->getSqsMessage($this->getSnsMessage(self::MESSAGE_CONTENT));
        $awsClient = $this->getReceivingAwsClientMock(['Messages' => [$snsMessage]]);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->setValidator($this->getValidatorMock(false));

        $this->assertEquals([
            'Messages' => [$snsMessage]
        ], $client->receive());
    }

    public function testReceiveSnsMessageWithoutValidator()
    {
        $snsMessage = $this->getSqsMessage($this->getSnsMessage(self::MESSAGE_CONTENT));
        $awsClient = $this->getReceivingAwsClientMock(['Messages' => [$snsMessage]]);

        $client = new SqsClient($awsClient, self::RESOURCE);

        $this->assertEquals([
            'Messages' => [$snsMessage]
        ], $client->receive());
    }

    public function testReceiveSqsMessage()
    {
        $sqsMessage = $this->getSqsMessage(self::MESSAGE_CONTENT);
        $awsClient = $this->getReceivingAwsClientMock(['Messages' => [$sqsMessage]]);

        $client = new SqsClient($awsClient, self::RESOURCE);

        $this->assertEquals([
            'Messages' => [$sqsMessage]
        ], $client->receive());
    }

    public function testReceiveMixedMessages()
    {
        $snsMessage = $this->getSnsMessage(self::MESSAGE_CONTENT);
        $sqsMessage = $this->getSqsMessage(self::MESSAGE_CONTENT);
        $awsClient = $this->getReceivingAwsClientMock(['Messages' => [$sqsMessage, $this->getSqsMessage($snsMessage)]]);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->setValidator($this->getValidatorMock(true));

        $this->assertEquals([
            'Messages' => [$sqsMessage, $this->getSqsMessage(self::MESSAGE_CONTENT)]
        ], $client->receive());
    }

    public function testSendMessage()
    {
        $message = 'test';
        $awsClient = $this->getAwsClientMock();

        $awsClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with([
                'MessageBody' => json_encode($message),
                'QueueUrl' => self::RESOURCE
            ]);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->send($message);
    }

    public function testSendMessageArbitraryArray()
    {
        $awsClient = $this->getAwsClientMock();

        $awsClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with([
                'MessageBody' => json_encode(self::MESSAGE_CONTENT),
                'QueueUrl' => self::RESOURCE
            ]);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->send(self::MESSAGE_CONTENT);
    }

    public function testSendMessageReadyToSend()
    {
        $message = ['MessageBody' => 'ReadyToSend'];

        $awsClient = $this->getAwsClientMock();

        $awsClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with(array_merge([
                'QueueUrl' => self::RESOURCE
            ], $message));

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->send($message);
    }

    public function testSendMessageOverriddenQueueUrl()
    {
        $queueUrl = 'https://www.example.com';
        $message = [
            'MessageBody' => 'ReadyToSend',
            'QueueUrl' => $queueUrl
        ];

        $awsClient = $this->getAwsClientMock();

        $awsClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with($message);

        $client = new SqsClient($awsClient, self::RESOURCE);
        $client->send($message);
    }

    /**
     * @return array
     */
    private function getSnsMessage($body)
    {
        $json = json_encode($body);

        return [
            'Type' => 'Notification',
            'MessageId' => '4743aa35-e3cd-4562-bd9e-b25778778206',
            'TopicArn' => 'arn:aws:sns:eu-central-1:123456789012:sns-test',
            'Message' => $json,
            'Timestamp' => date(DATE_ATOM),
            'SignatureVersion' => '1',
            'Signature' => 'MDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwCg==',
            'SigningCertURL' => 'https://sns.eu-central-1.amazonaws.com/SimpleNotificationService-12345678901234567890123456789012.pem',
            'UnsubscribeURL' => 'https://sns.eu-central-1.amazonaws.com/?Action=Unsubscribe&SubscriptionArn=arn:aws:sns:eu-central-1:123456789012:test-topic:561c618b-82b1-4e46-9ace-1bc62e8fe9de'
        ];
    }

    /**
     * @return array
     */
    private function getSqsMessage($body)
    {
        $json = json_encode($body);

        return [
            'MessageId' => '4743aa35-e3cd-4562-bd9e-b25778778206',
            'ReceiptHandle' => 'MDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwCg==',
            'MD5OfBody' => '2e99d56fa0456a564e968b6194495aa2',
            'Body' => $json
        ];
    }

    /**
     * @param bool $validatesTo
     *
     * @return MessageValidator|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getValidatorMock($validatesTo = true)
    {
        $messageValidator = $this
            ->getMockBuilder(MessageValidator::class)
            ->disableOriginalConstructor()
            ->setMethods(['isValid'])
            ->getMock();

        $messageValidator
            ->expects($this->once())
            ->method('isValid')
            ->will($this->returnValue($validatesTo));

        return $messageValidator;
    }

    /**
     * @param array $returnValue
     *
     * @return AwsClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getReceivingAwsClientMock($returnValue)
    {
        $awsClient = $this->getAwsClientMock();

        $awsClient
            ->expects($this->once())
            ->method('receiveMessage')
            ->with([
                'QueueUrl' => self::RESOURCE
            ])
            ->will($this->returnValue($returnValue));

        return $awsClient;
    }

    /**
     * @return AwsClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getAwsClientMock()
    {
        return $this
            ->getMockBuilder(AwsClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['receiveMessage', 'sendMessage'])
            ->getMock();
    }
}
