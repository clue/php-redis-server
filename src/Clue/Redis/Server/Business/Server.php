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

    // StatusReply
    public function config($subcommand)
    {
        $n = func_num_args();
        $subcommand = strtolower($subcommand);

        if ($subcommand === 'get') {
            if ($n !== 2) {
                throw new InvalidArgumentException('ERR Wrong number of arguments for CONFIG get');
            }
            $pattern = func_get_arg(1);
            $ret = array();
            foreach ($this->getConfig() as $name => $value) {
                if (fnmatch($pattern, $name)) {
                    $ret []= $name;
                    $ret []= $value;
                }
            }
            return $ret;
        } elseif ($subcommand === 'set') {
            if ($n !== 3) {
                throw new InvalidArgumentException('ERR Wrong number of arguments for CONFIG set');
            }
            $this->getConfig()->set(func_get_arg(1), func_get_arg(2));
            return true;
        }

        throw new InvalidArgumentException('ERR CONFIG subcommand must be one of GET, SET');
    }

    public function dbsize()
    {
        return $this->getDatabase()->count();
    }

    // StatusReply
    public function flushdb()
    {
        $this->getDatabase()->reset();

        return true;
    }

    // StatusReply
    public function flushall()
    {
        foreach ($this->getDatabases() as $database) {
            $database->reset();
        }

        return true;
    }

    public function shutdown()
    {
        // save/nosave doesn't matter

        // this command disconnects all clients and closes the server socket,
        // so there's no need to return a reply

        foreach ($this->getAllClients() as $client) {
            $client->close();
        }

        $this->getServer()->close();
    }

    public function time()
    {
        $time = array_reverse(explode(' ', microtime(false)));
        $time[1] = trim($time[1], ".0");

        return $time;
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

    private function getConfig()
    {
        return $this->server->getConfig();
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
