<?php

namespace Clue\Redis\React\Server;

use Evenement\EventEmitter;
use React\Socket\Server as ServerSocket;
use React\EventLoop\LoopInterface;
use Clue\Redis\Protocol\Factory as ProtocolFactory;
use React\Socket\Connection;
use Clue\Redis\Protocol\Model\ErrorReply;

/**
 * Dummy redis server implementation
 *
 * @event connection(ConnectionInterface $connection, Server $thisServer)
 * @event request($requestData, ConnectionInterface $connection)
 */
class Server extends EventEmitter
{
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

        $socket->on('connection', array($this, 'handleConnection'));
    }

    public function handleConnection(Connection $connection)
    {
        $parser = $this->protocol->createParser();
        $that = $this;

        $connection->on('data', function ($data) use ($parser, $that, $connection) {
            try {
                $parser->pushIncoming($data);
            }
            catch (ParserException $e) {
                $connection->emit('error', $e);
                $connection->close();
                return;
            }
            while ($parser->hasIncoming()) {
                $that->handleRequest($parser->popIncoming(), $connection);
            }
        });

        $this->emit('connection', array($connection, $this));
    }

    public function handleRequest($request, Connection $connection)
    {
        $this->emit('request', array($request, $connection));

        if (!is_array($request)) {
            $model = new ErrorReply('ERR Malformed request. Bye!');
            $connection->write($model->getMessageSerialized());
            $connection->end();
            return;
        }

        $method = strtolower(array_shift($request));
        if (!is_callable(array($this->business, $method))) {
            $model = new ErrorReply('ERR Unknown or disabled command \'' . $method . '\'');
            $connection->write($model->getMessageSerialized());
            return;
        }

        try {
            $ret = call_user_func_array(array($this->business, $method), $request);
        }
        catch (Exception $e) {
            $connection->write($this->serializer->createReplyModel($e)->getMessageSerialized());
            return;
        }

        $connection->write($this->serializer->createReply($ret));
    }

    public function close()
    {
        $this->socket->shutdown();
    }
}
