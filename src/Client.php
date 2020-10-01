<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\Request;
use InvalidArgumentException;
use React\Stream\Stream;

class Client
{
    private Stream $connection;

    private Invoker $business;

    private Storage $database;

    private ?string $name = null;

    private float $timeConnect;

    private float $timeLast;

    private ?Request $lastRequest = null;

    private int $multi = -1;

    private bool $blocking = false;

    private bool $watchTouched = false;

    public function __construct(Stream $connection, Invoker $business, Storage $database)
    {
        $this->connection = $connection;
        $this->business = $business;
        $this->database = $database;

        $this->timeConnect = $this->timeLast = microtime(true);
    }

    public function getRemoteAddress(): string
    {
        return (string) stream_socket_get_name($this->connection->stream, true);
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function end(): void
    {
        $this->connection->end();
    }

    public function write(string $data): void
    {
        $this->connection->write($data);
    }

    public function getRequestDebug(ModelInterface $request): string
    {
        $ret = sprintf('%.06f', microtime(true)) . ' [' . $this->database->getId() . ' ' . $this->getRemoteAddress() . ']';

        foreach ($request->getValueNative() as $one) {
            $ret .= ' "' . addslashes($one) . '"';
        }

        return $ret;
    }

    public function getMeta(): array
    {
        // addr=127.0.0.1:38468 fd=5 name= age=2748 idle=0 flags=N db=0 sub=0 psub=0 multi=-1 qbuf=0 qbuf-free=32768 obl=0 oll=0 omem=0 events=r cmd=client

        $command = 'NULL';
        if ($this->lastRequest !== null) {
            $command = mb_strtolower($this->lastRequest->getCommand());
        }

        $events = '';
        if (!$this->isEnding()) {
            $events = 'r';
        }
        if ($this->isWriting()) {
            $events .= 'w';
        }

        return [
            'addr' => $this->getRemoteAddress(),
            'fd' => (int) $this->connection->stream,
            'name' => $this->name,
            'age' => (int) (microtime(true) - $this->timeConnect),
            'idle' => (int) (microtime(true) - $this->timeLast),
            'flags' => $this->getFlags(),
            'db' => $this->database->getId(),
            'sub' => 0,
            'psub' => 0,
            'multi' => $this->multi,
            'qbuf' => 0,
            'qbuf-free' => 0,
            'obl' => 0,
            'oll' => 0,
            'omem' => 0,
            'events' => $events,
            'cmd' => $command,
        ];
    }

    public function getDescription(): string
    {
        $ret = '';
        foreach ($this->getMeta() as $name => $value) {
            $ret .= $name . '=' . $value . ' ';
        }

        return mb_substr($ret, 0, -1);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        if (!preg_match('/[a-z]/', $name)) {
            throw new InvalidArgumentException('ERR Client names cannot contain spaces, newlines or special characters.');
        }
        $this->name = $name;
    }

    public function getDatabase(): Storage
    {
        return $this->database;
    }

    public function setDatabase(Storage $database): void
    {
        $this->database = $database;
    }

    public function getBusiness(): Invoker
    {
        return $this->business;
    }

    public function setBusiness(Invoker $business): void
    {
        $this->business = $business;
    }

    public function handleRequest(Request $request): void
    {
        $this->lastRequest = $request;
        $this->timeLast = microtime(true);

        $ret = $this->business->invoke($request, $this);
        if ($ret !== null) {
            $this->write($ret);
        }
    }

    private function getFlags(): string
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

        if ($this->isEnding()) {
            $flags .= 'c';
        }

        if ($flags === '') {
            $flags = 'N';
        }

        return $flags;
    }

    private function isEnding(): bool
    {
        return !$this->connection->isWritable();
    }

    private function isWriting(): bool
    {
        return $this->connection->getBuffer()->listening;
    }
}
