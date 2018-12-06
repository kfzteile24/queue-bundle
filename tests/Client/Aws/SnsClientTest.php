<?php

namespace PetsDeli\QueueBundle\Tests\Client\Aws;

use Aws\AwsClient;
use Aws\AwsClientInterface;
use PetsDeli\QueueBundle\Client\Aws\SnsClient;

class SnsClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testReceiveException()
    {
        /** @var AwsClient|\PHPUnit_Framework_MockObject_MockObject $awsClient */
        $awsClient = $this
            ->getMockBuilder(AwsClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $client = new SnsClient($awsClient, 'some:topic:arn');
        $client->receive(['some' => 'argument']);
    }

    public function testSend()
    {
        $message = 'test';
        $resource = 'some:topic:arn';

        /** @var AwsClient|\PHPUnit_Framework_MockObject_MockObject $awsClient */
        $awsClient = $this
            ->getMockBuilder(AwsClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['publish', 'arbitrary', 'arbitraryWithArgs'])
            ->getMock();

        $awsClient->expects($this->once())
            ->method('publish')
            ->with([
                'Message' => json_encode($message),
                'TopicArn' => $resource
            ]);

        $awsClient->expects($this->once())
            ->method('arbitrary');

        $awsClient->expects($this->once())
            ->method('arbitraryWithArgs')
            ->with([
                'TopicArn' => $resource,
                'some' => 'argument'
            ]);

        $client = new SnsClient($awsClient, $resource);

        $client->send($message);
        $client->arbitrary();
        $client->arbitraryWithArgs(['some' => 'argument']);
    }
}
