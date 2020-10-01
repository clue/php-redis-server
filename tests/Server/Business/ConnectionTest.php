<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server\Business;

use Clue\Redis\Server\Business\Connection;
use Clue\Redis\Server\Server;
use Clue\Redis\Server\Tests\TestCase;

class ConnectionTest extends TestCase
{
    private $business;

    public function setUp(): void
    {
        /** @var Server $server */
        $server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->getMock();
        $this->business = new Connection($server);
    }

    public function testPing(): void
    {
        static::assertEquals('PONG', $this->business->ping());
    }
}
