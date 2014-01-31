<?php

use Clue\Redis\Server\Business\Connection;

class ConnectionTest extends TestCase
{
    private $business;

    public function setUp()
    {
        $server = $this->getMockBuilder('Clue\Redis\Server\Server')->disableOriginalConstructor()->getMock();
        $this->business = new Connection($server);
    }

    public function testPing()
    {
        $this->assertEquals('PONG', $this->business->ping());
    }
}
