<?php

namespace Clue\Redis\Server;

use Evenement\EventEmitter;
use React\Socket\Server as ServerSocket;
use React\EventLoop\LoopInterface;
use Clue\Redis\Protocol\Factory as ProtocolFactory;
use React\Socket\Connection;
use Clue\Redis\Protocol\Model\ErrorReply;
use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\MultiBulkReply;
use Clue\Redis\Protocol\Parser\ParserException;
use SplObjectStorage;
use Exception;
use Clue\Redis\Protocol\Model\Request;
use ReflectionMethod;
use ReflectionException;
use Clue\Redis\Protocol\Parser\RequestParser;

/**
 * Dummy redis server implementation
 *
 * @event connection(ConnectionInterface $connection, Server $thisServer)
 * @event request($requestData, ConnectionInterface $connection)
 */
class Server extends EventEmitter
{
    private $socket;
    private $loop;
    private $protocol;
    private $business;
    private $clients;

    public function __construct(ServerSocket $socket, LoopInterface $loop, ProtocolFactory $protocol = null, $business = null)
    {
        if ($protocol === null) {
            $protocol = new ProtocolFactory();
        }

        if ($business === null) {
            //$business = new Business();
        }

        if (!($business instanceof Invoker)) {
            $business = new Invoker($business, $protocol->createSerializer());
        }

        $this->socket = $socket;
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->business = $business;
        $this->clients = new SplObjectStorage();

        $this->on('error', function ($error, Client $client) {
            $client->end();
        });

        $socket->on('connection', array($this, 'handleConnection'));
    }

    public function handleConnection(Connection $connection)
    {
        $parser = $this->protocol->createResponseParser();
        $parser = new RequestParser();
        $that = $this;

        $client = new Client($connection, $this->business);
        $this->clients->attach($client);

        $connection->on('data', function ($data) use ($parser, $that, $client) {
            try {
                $messages = $parser->pushIncoming($data);
            }
            catch (ParserException $e) {
                $that->emit('error', array($e, $client));
                return;
            }
            foreach ($messages as $message) {
                $that->handleRequest($message, $client);
            }
        });

        $connection->on('close', function() use ($that, $client) {
            $that->handleDisconnection($client);
        });

        $this->emit('connection', array($client, $this));
    }

    public function handleDisconnection(Client $client)
    {
        $this->clients->detach($client);

        $this->emit('disconnection', array($client, $this));
    }

    public function handleRequest(Request $request, Client $client)
    {
        $this->emit('request', array($request, $client));

        $client->handleRequest($request);
    }

    public function close()
    {
        $this->socket->shutdown();
    }

    public function getLocalAddress()
    {
        return stream_socket_get_name($this->socket->master, false);
    }
}
