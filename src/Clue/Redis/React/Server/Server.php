<?php

namespace Clue\Redis\React\Server;

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
use Clue\Redis\React\Client\Request;
use ReflectionMethod;
use ReflectionException;

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
            $business = new Business();
        }

        if (!($business instanceof Invoker)) {
            $business = new Invoker($business, $protocol->createSerializer());
        }

        $this->socket = $socket;
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->business = $business;
        $this->clients = new SplObjectStorage();

        $socket->on('connection', array($this, 'handleConnection'));
    }

    public function handleConnection(Connection $connection)
    {
        $parser = $this->protocol->createParser();
        $that = $this;

        $client = new Client($connection);
        $this->clients->attach($client);

        $connection->on('data', function ($data) use ($parser, $that, $client) {
            try {
                $parser->pushIncoming($data);
            }
            catch (ParserException $e) {
                $connection->emit('error', array($e));
                $connection->close();
                return;
            }
            while ($parser->hasIncomingModel()) {
                $that->handleRequest($parser->popIncomingModel(), $client);
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

    public function handleRequest(ModelInterface $request, Client $client)
    {
        $this->emit('request', array($client, $request));

        if (!($request instanceof MultiBulkReply) || !$request->isRequest()) {
            $model = new ErrorReply('ERR Malformed request. Bye!');
            $client->write($model);
            $client->end();
            return;
        }

        $args = $request->getValueNative();
        $method = strtolower(array_shift($args));

        $ret = $this->business->invoke($method, $args);
        if ($ret !== null) {
            $client->write($ret);
        }
    }

    public function close()
    {
        $this->socket->shutdown();
    }
}
