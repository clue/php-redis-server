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
    private $serializer;
    private $business;
    private $clients;

    public function __construct(ServerSocket $socket, LoopInterface $loop, ProtocolFactory $protocol = null, Business $business = null)
    {
        if ($protocol === null) {
            $protocol = new ProtocolFactory();
        }

        if ($business === null) {
            $business = new Business();
        }

        $this->socket = $socket;
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->serializer = $protocol->createSerializer();
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

        if (!is_callable(array($this->business, $method))) {
            $model = new ErrorReply('ERR Unknown or disabled command \'' . $method . '\'');
            $client->write($model);
            return;
        }

        try {
            $ret = call_user_func_array(array($this->business, $method), $args);
        }
        catch (Exception $e) {
            $client->write($this->serializer->createReplyModel($e));
            return;
        }

        if (!($ret instanceof ModelInterface)) {
            $ret = $this->serializer->createReplyModel($ret);
        }

        $client->write($ret);
    }

    public function close()
    {
        $this->socket->shutdown();
    }
}
