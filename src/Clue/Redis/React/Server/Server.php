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
use Exception;

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
                $connection->emit('error', array($e));
                $connection->close();
                return;
            }
            while ($parser->hasIncomingModel()) {
                $that->handleRequest($parser->popIncomingModel(), $connection);
            }
        });

        $this->emit('connection', array($connection, $this));
    }

    public function handleRequest(ModelInterface $request, Connection $connection)
    {
        $this->emit('request', array($request, $connection));

        if (!($request instanceof MultiBulkReply) || !$request->isRequest()) {
            $model = new ErrorReply('ERR Malformed request. Bye!');
            $connection->write($model->getMessageSerialized());
            $connection->end();
            return;
        }

        $args = $request->getValueNative();
        $method = strtolower(array_shift($args));

        if (!is_callable(array($this->business, $method))) {
            $model = new ErrorReply('ERR Unknown or disabled command \'' . $method . '\'');
            $connection->write($model->getMessageSerialized());
            return;
        }

        try {
            $ret = call_user_func_array(array($this->business, $method), $args);
        }
        catch (Exception $e) {
            $connection->write($this->serializer->createReplyModel($e)->getMessageSerialized());
            return;
        }

        if (!($ret instanceof ModelInterface)) {
            $ret = $this->serializer->createReplyModel($ret);
        }

        $connection->write($ret->getMessageSerialized());
    }

    public function close()
    {
        $this->socket->shutdown();
    }
}
