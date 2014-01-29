<?php

namespace Clue\Redis\Server\Business;

use InvalidArgumentException;
use OutOfBoundsException;
use Clue\Redis\Server\Server as ServerInstance;
use Clue\Redis\Server\Client;

class Server
{
    private $server;
    private $client = null;

    public function __construct(ServerInstance $server)
    {
        $this->server = $server;
    }

    // StatusReply
    public function client($subcommand)
    {
        $n = func_num_args();
        $subcommand = strtolower($subcommand);

        if ($subcommand === 'list' && $n === 1) {
            $ret = '';
            foreach ($this->getAllClients() as $client) {
                $ret .= $client->getDescription() . "\n";
            }
            return $ret;
        } elseif ($subcommand === 'kill' && $n === 2) {
            $this->getClientByIp(func_get_arg(1))->end();
            return true;
        } elseif ($subcommand === 'getname' && $n === 1) {
            return $this->getClient()->getName();
        } elseif ($subcommand === 'setname' && $n === 2) {
            $this->getClient()->setName(func_get_arg(1));

            return true;
        }

        throw new InvalidArgumentException('ERR Syntax error, try CLIENT (LIST | KILL ip:port | GETNAME | SETNAME connection-name)');
    }

    private function getAllClients()
    {
        return $this->getServer()->getClients();
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

    private function getClientByIp($ip)
    {
        foreach ($this->getAllClients() as $client) {
            if ($client->getRemoteAddress() === $ip) {
                return $client;
            }
        }
        throw new OutOfBoundsException('ERR No such client');
    }

    private function getDatabases()
    {
        return $this->getServer()->getDatabases();
    }

    private function getDatabase()
    {
        return $this->getClient()->getDatabase();
    }

    private function getServer()
    {
        return $this->server;
    }
}
