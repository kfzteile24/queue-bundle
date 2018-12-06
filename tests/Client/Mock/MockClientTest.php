<?php

namespace Kfz24\QueueBundle\Tests\Client\Mock;

use Kfz24\QueueBundle\Client\Mock\MockClient;
use Kfz24\QueueBundle\Client\Mock\MockNoopAdapter;

class MockClientTest extends \PHPUnit_Framework_TestCase
{
    public function testSend()
    {
        $client = $this->getClient();

        $this->assertNull($client->send('someMessage'));
    }

    public function testReceive()
    {
        $expectedResponse = 'expected response';

        $client = $this->getClient($expectedResponse);

        $this->assertEquals($expectedResponse, $client->receive());
    }

    public function testArbitraryMethodCall()
    {
        $expectedResponse = 'expected arbitrary response';

        $client = $this->getClient($expectedResponse);

        $this->assertEquals($expectedResponse, $client->arbitraryMethod());
    }

    private function getClient($response = null)
    {
        $adapter = new MockNoopAdapter($response);
        $resource = 'resource';

        return new MockClient($adapter, $resource);
    }
}
