<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Factory as ProtocolFactory;
use Clue\Redis\Protocol\Model\Request;
use Clue\Redis\Protocol\Parser\ParserException;
use Clue\Redis\Protocol\Parser\RequestParser;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Stream\Stream;
use SplObjectStorage;

/**
 * Dummy redis server implementation.
 *
 * @event connection(ConnectionInterface $connection, Server $thisServer)
 * @event request($requestData, ConnectionInterface $connection)
 */
class Server extends EventEmitter
{
    private ServerInterface $socket;

    private LoopInterface $loop;

    private ProtocolFactory $protocol;

    private Invoker $business;

    private \SplObjectStorage $clients;

    private array $databases;

    private Config $config;

    public function __construct(ServerInterface $socket, LoopInterface $loop, ?ProtocolFactory $protocol = null, ?Invoker $business = null)
    {
        $this->databases = [
            new Storage('0'),
            new Storage('1'),
        ];
        $db = reset($this->databases);

        $this->socket = $socket;
        $this->loop = $loop;
        $this->protocol = $protocol ?? new ProtocolFactory();
        $this->business = $business ?? new Invoker($protocol->createSerializer());

        if ($business === null) {
            $this->business->addCommands(new Business\Connection($this));
            $this->business->addCommands(new Business\Keys($db));
            $this->business->addCommands(new Business\Lists($db));
            $this->business->addCommands(new Business\Server($this));
            $this->business->addCommands(new Business\Strings($db));
            $this->business->renameCommand('x_echo', 'echo');
        }

        $this->clients = new SplObjectStorage();
        $this->config = new Config();

        $this->on('error', function (\Throwable $error, Client $client): void {
            $client->end();
        });

        $socket->on('connection', [$this, 'handleConnection']);
    }

    public function handleConnection(Stream $connection): void
    {
        $parser = new RequestParser();
        $that = $this;

        $business = $this->business;
        if ($this->config->get('requirepass') !== '') {
            $business = new AuthInvoker($business);
        }

        $client = new Client($connection, $business, reset($this->databases));
        $this->clients->attach($client);

        $connection->on('data', function (string $data) use ($parser, $that, $client): void {
            try {
                $messages = $parser->pushIncoming($data);
            } catch (ParserException $e) {
                $that->emit('error', [$e, $client]);

                return;
            }
            foreach ($messages as $message) {
                $that->handleRequest($message, $client);
            }
        });

        $connection->on('close', function () use ($that, $client): void {
            $that->handleDisconnection($client);
        });

        $this->emit('connection', [$client, $this]);
    }

    public function handleDisconnection(Client $client): void
    {
        $this->clients->detach($client);

        $this->emit('disconnection', [$client, $this]);
    }

    public function handleRequest(Request $request, Client $client): void
    {
        $this->emit('request', [$request, $client]);

        $client->handleRequest($request);
    }

    public function close(): void
    {
        $this->socket->shutdown();
    }

    public function getLocalAddress(): string
    {
        if (isset($this->socket->master)) {
            return (string) stream_socket_get_name($this->socket->master, false);
        }

        return (string) $this->socket->getPort();
    }

    public function getDatabases(): array
    {
        return $this->databases;
    }

    public function getClients(): \SplObjectStorage
    {
        return $this->clients;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
