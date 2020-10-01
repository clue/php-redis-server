<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Business;

use Clue\Redis\Protocol\Serializer\SerializerInterface;
use Clue\Redis\Server\AuthInvoker;
use Clue\Redis\Server\Client;
use Clue\Redis\Server\Config;
use Clue\Redis\Server\Server;
use Clue\Redis\Server\Storage;
use OutOfBoundsException;
use UnexpectedValueException;

class Connection
{
    private Server $server;

    private ?Client $client = null;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    // StatusReply
    public function auth(string $password): bool
    {
        if ($this->getConfig()->get('requirepass') === '') {
            throw new UnexpectedValueException('ERR Client sent AUTH, but no password is set');
        }

        if ($this->getConfig()->get('requirepass') !== $password) {
            throw new UnexpectedValueException('ERR invalid password');
        }

        $business = $this->getClient()->getBusiness();
        if ($business instanceof AuthInvoker) {
            $this->getClient()->setBusiness($business->getSuccessfulInvoker());
        }

        return true;
    }

    public function x_echo(string $message): string
    {
        return $message;
    }

    // StatusReply
    public function ping(): string
    {
        return 'PONG';
    }

    // StatusReply
    public function select(string $index): bool
    {
        $this->getClient()->setDatabase($this->getDatabaseById($index));

        return true;
    }

    // StatusReply
    public function quit(): bool
    {
        // this command will end the connection and therefor not send any more
        // messages (not even the return code). For this reason we have to send
        // an OK message manually.
        $this->getClient()->write($this->getSerializer()->getStatusMessage('OK'));
        $this->getClient()->end();

        return true;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            throw new UnexpectedValueException('Invalid state');
        }

        return $this->client;
    }

    private function getSerializer(): SerializerInterface
    {
        return $this->getClient()->getBusiness()->getSerializer();
    }

    private function getServer(): Server
    {
        return $this->server;
    }

    private function getDatabases(): array
    {
        return $this->getServer()->getDatabases();
    }

    private function getDatabaseById($id): Storage
    {
        foreach ($this->getDatabases() as $database) {
            if ($database->getId() === $id) {
                return $database;
            }
        }
        throw new OutOfBoundsException('ERR invalid DB index');
    }

    private function getConfig(): Config
    {
        return $this->server->getConfig();
    }
}
