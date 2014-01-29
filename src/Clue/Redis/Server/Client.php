<?php

namespace Clue\Redis\Server;

use React\Socket\Connection;
use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\Request;
use InvalidArgumentException;

class Client
{
    private $connection;
    private $business;
    private $database;
    private $name = null;
    private $timeConnect;
    private $timeLast;
    private $lastRequest = null;
    private $multi = -1;
    private $blocking = false;
    private $watchTouched = false;

    public function __construct(Connection $connection, Invoker $business, Storage $database)
    {
        $this->connection = $connection;
        $this->business = $business;
        $this->database = $database;

        $this->timeConnect = $this->timeLast = microtime(true);
    }

    public function getRemoteAddress()
    {
        return stream_socket_get_name($this->connection->stream, true);
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

    private function getFlags()
    {
        $flags = '';

        if ($this->multi !== -1) {
            $flags .= 'x';
        }

        if ($this->blocking) {
            $flags .= 'b';
        }

        if ($this->watchTouched) {
            $flags .= 'd';
        }

        if($this->isEnding()) {
            $flags .= 'c';
        }

        if ($flags === '') {
            $flags = 'N';
        }

        return $flags;
    }

    public function getMeta()
    {
        // addr=127.0.0.1:38468 fd=5 name= age=2748 idle=0 flags=N db=0 sub=0 psub=0 multi=-1 qbuf=0 qbuf-free=32768 obl=0 oll=0 omem=0 events=r cmd=client

        $command = 'NULL';
        if ($this->lastRequest !== null) {
            $command = strtolower($this->lastRequest->getCommand());
        }

        $events = '';
        if (!$this->isEnding()) {
            $events = 'r';
        }
        if ($this->isWriting()) {
            $events .= 'w';
        }

        return array(
            'addr'      => $this->getRemoteAddress(),
            'fd'        => (int)$this->connection->stream,
            'name'      => $this->name,
            'age'       => (int)(microtime(true) - $this->timeConnect),
            'idle'      => (int)(microtime(true) - $this->timeLast),
            'flags'     => $this->getFlags(),
            'db'        => $this->database->getId(),
            'sub'       => 0,
            'psub'      => 0,
            'multi'     => $this->multi,
            'qbuf'      => 0,
            'qbuf-free' => 0,
            'obl'       => 0,
            'oll'       => 0,
            'omem'      => 0,
            'events'    => $events,
            'cmd'       => $command,
        );
    }

    public function getDescription()
    {
        $ret = '';
        foreach ($this->getMeta() as $name => $value) {
            $ret .= $name . '=' . $value . ' ';
        }

        return substr($ret, 0, -1);
    }

    public function handleRequest(Request $request)
    {
        $this->lastRequest = $request;
        $this->timeLast = microtime(true);

        $ret = $this->business->invoke($request);
        if ($ret !== null) {
            $this->write($ret);
        }
    }

    private function isEnding()
    {
        return !$this->connection->isWritable();
    }

    private function isWriting()
    {
        return $this->connection->getBuffer()->listening;
    }
}