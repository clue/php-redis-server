<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Config;
use Clue\Redis\Server\Server as ServerInstance;
use Clue\Redis\Server\Storage;
use InvalidArgumentException;
use OutOfBoundsException;

class Server
{
    private ServerInstance $server;

    private ?Client $client = null;

    public function __construct(ServerInstance $server)
    {
        $this->server = $server;
    }

    // StatusReply
    public function client(string $subcommand): bool
    {
        $n = func_num_args();
        $subcommand = mb_strtolower($subcommand);

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
    public function config(string $subcommand)
    {
        $n = func_num_args();
        $subcommand = mb_strtolower($subcommand);

        if ($subcommand === 'get') {
            if ($n !== 2) {
                throw new InvalidArgumentException('ERR Wrong number of arguments for CONFIG get');
            }
            $pattern = func_get_arg(1);
            $ret = [];
            foreach ($this->getConfig() as $name => $value) {
                if (fnmatch($pattern, $name)) {
                    $ret[] = $name;
                    $ret[] = $value;
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

    public function dbsize(): int
    {
        return $this->getDatabase()->count();
    }

    // StatusReply
    public function flushdb(): bool
    {
        $this->getDatabase()->reset();

        return true;
    }

    // StatusReply
    public function flushall(): bool
    {
        foreach ($this->getDatabases() as $database) {
            $database->reset();
        }

        return true;
    }

    public function shutdown(): void
    {
        // save/nosave doesn't matter

        // this command disconnects all clients and closes the server socket,
        // so there's no need to return a reply

        foreach ($this->getAllClients() as $client) {
            $client->close();
        }

        $this->getServer()->close();
    }

    public function time(): array
    {
        $time = array_reverse(explode(' ', microtime(false)));
        $time[1] = trim($time[1], '.0');

        return $time;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    private function getAllClients(): \SplObjectStorage
    {
        return $this->getServer()->getClients();
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            throw new \UnexpectedValueException('Invalid state');
        }

        return $this->client;
    }

    private function getClientByIp(string $ip): Client
    {
        /** @var Client $client */
        foreach ($this->getAllClients() as $client) {
            if ($client->getRemoteAddress() === $ip) {
                return $client;
            }
        }
        throw new OutOfBoundsException('ERR No such client');
    }

    private function getConfig(): Config
    {
        return $this->server->getConfig();
    }

    private function getDatabases(): array
    {
        return $this->getServer()->getDatabases();
    }

    private function getDatabase(): Storage
    {
        return $this->getClient()->getDatabase();
    }

    private function getServer(): ServerInstance
    {
        return $this->server;
    }
}
