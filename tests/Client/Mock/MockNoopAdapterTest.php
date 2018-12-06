<?php

namespace PetsDeli\QueueBundle\Tests\Client\Mock;

use PetsDeli\QueueBundle\Client\Mock\MockNoopAdapter;

class MockNoopAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function testResponseNull()
    {
        $adapter = new MockNoopAdapter();
        $this->assertNull($adapter->test());
    }

    /**
     * @param $response
     *
     * @dataProvider dataProvider
     */
    public function testResponse($response)
    {
        $adapter = new MockNoopAdapter($response);
        $this->assertEquals($response, $adapter->test());
    }

    public function dataProvider()
    {
        return [
            [123],
            ["test"],
            [new \DateTime()],
            [new \stdClass()],
        ];
    }
}
