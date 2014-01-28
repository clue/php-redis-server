<?php

namespace Clue\Redis\Server;

use React\Socket\Connection;
use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\Request;

class Client
{
    private $connection;
    private $business;
    private $database;

    public function __construct(Connection $connection, Invoker $business, Storage $database)
    {
        $this->connection = $connection;
        $this->business = $business;
        $this->database = $database;
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

    public function write($data)
    {
        $this->connection->write($data);
    }

    public function getRequestDebug(ModelInterface $request)
    {
        $ret = sprintf('%.06f', microtime(true)) . ' [' . $this->database->getId() . ' ' . $this->getRemoteAddress() . ']';

        foreach($request->getValueNative() as $one) {
            $ret .= ' "' . addslashes($one) . '"';
        }

        return $ret;
    }

    public function handleRequest(Request $request)
    {
        $ret = $this->business->invoke($request);
        if ($ret !== null) {
            $this->write($ret);
        }
    }
}