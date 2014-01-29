<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Server;
use OutOfBoundsException;
use UnexpectedValueException;
use Clue\Redis\Server\Client;

class Connection
{
    private $server;
    private $client = null;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function x_echo($message)
    {
        return $message;
    }

    // StatusReply
    public function ping()
    {
        return 'PONG';
    }

    // StatusReply
    public function select($index)
    {
        $this->getClient()->setDatabase($this->getDatabaseById($index));

        return true;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    private function getClient()
    {
        if ($this->client === null) {
            throw new UnexpectedValueException('Invalid state');
        }
        return $this->client;
    }

    private function getServer()
    {
        return $this->server;
    }

    private function getDatabases()
    {
        return $this->getServer()->getDatabases();
    }

    private function getDatabaseById($id)
    {
        foreach ($this->getDatabases() as $database) {
            if ($database->getId() === $id) {
                return $database;
            }
        }
        throw new OutOfBoundsException('ERR invalid DB index');
    }
}
