<?php

namespace Clue\Redis\React\Server;

use React\Socket\Connection;
use Clue\Redis\Protocol\Model\ModelInterface;

class Client
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getRemoteAddress()
    {
        return $this->connection->getRemoteAddress();
    }

    public function close()
    {
        $this->connection->close();
    }

    public function end()
    {
        $this->connection->end();
    }

    public function write(ModelInterface $response)
    {
        $this->connection->write($response->getMessageSerialized());
    }

    public function getRequestDebug(ModelInterface $request)
    {
        $ret = sprintf('%.06f', microtime(true)) . ' [0 ' . $this->getRemoteAddress() . ']';

        foreach($request->getValueNative() as $one) {
            $ret .= ' "' . addslashes($one) . '"';
        }

        return $ret;
    }
}