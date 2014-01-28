<?php

use Clue\Redis\Server\Business\Connection;

class ConnectionTest extends TestCase
{
    private $business;

    public function setUp()
    {
        $this->business = new Connection();
    }

    public function testPing()
    {
        $this->assertEquals('PONG', $this->business->ping());
    }
}
