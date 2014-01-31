<?php

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Invoker;
use Clue\Redis\Protocol\Serializer\RecursiveSerializer;
use Clue\Redis\Server\Storage;

class ClientTest extends TestCase
{
    private $business;
    private $database;
    private $client;

    public function setUp()
    {
        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->getMock();

        $this->business = new Invoker(new RecursiveSerializer());
        $this->database = new Storage();
        $this->client = new Client($connection, $this->business, $this->database);
    }

    public function testBusiness()
    {
        $this->assertSame($this->business, $this->client->getBusiness());

        $business = new Invoker(new RecursiveSerializer());

        $this->client->setBusiness($business);
        $this->assertSame($business, $this->client->getBusiness());
    }

    public function testDatabase()
    {
        $this->assertSame($this->database, $this->client->getDatabase());

        $database = new Storage();

        $this->client->setDatabase($database);
        $this->assertSame($database, $this->client->getDatabase());
    }

    public function testName()
    {
        $this->assertNull($this->client->getName());
        $this->client->setName('name');
        $this->assertEquals('name', $this->client->getName());
    }
}