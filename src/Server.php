<?php

namespace Clue\Redis\Server;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use React\EventLoop\LoopInterface;
use Clue\Redis\Protocol\Factory as ProtocolFactory;
use React\Socket\ConnectionInterface;
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
use Clue\Redis\Server\Business;

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
    private $databases;
    private $config;

    public function __construct(ServerInterface $socket, LoopInterface $loop, ProtocolFactory $protocol = null, Invoker $business = null)
    {
        if ($protocol === null) {
            $protocol = new ProtocolFactory();
        }

        $this->databases = array(
            new Storage('0'),
            new Storage('1'),
        );
        $db = reset($this->databases);

        if ($business === null) {
            $business = new Invoker($protocol->createSerializer());
            $business->addCommands(new Business\Connection($this));
            $business->addCommands(new Business\Keys($db));
            $business->addCommands(new Business\Lists($db));
            $business->addCommands(new Business\Server($this));
            $business->addCommands(new Business\Strings($db));
            $business->renameCommand('x_echo', 'echo');
        }

        $this->socket = $socket;
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->business = $business;
        $this->clients = new SplObjectStorage();
        $this->config = new Config();

        $this->on('error', function ($error, Client $client) {
            $client->end();
        });

        $socket->on('connection', array($this, 'handleConnection'));
    }

    public function handleConnection(ConnectionInterface $connection)
    {
        $parser = $this->protocol->createRequestParser();
        $that = $this;

        $business = $this->business;
        if ($this->config->get('requirepass') !== '') {
            $business = new AuthInvoker($business);
        }

        $client = new Client($connection, $business, reset($this->databases));
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
        if (isset($this->socket->master)) {
            return stream_socket_get_name($this->socket->master, false);
        }
        return $this->socket->getPort();
    }

    public function getDatabases()
    {
        return $this->databases;
    }

    public function getClients()
    {
        return $this->clients;
    }

    public function getConfig()
    {
        return $this->config;
    }
}
