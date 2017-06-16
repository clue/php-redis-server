<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Type;
use Exception;
use InvalidArgumentException;
use Clue\Redis\Server\Client;
use Clue\React\Block;
use React\EventLoop\LoopInterface;

class Lists
{
    private $storage;
    private $loop;
    private $blockers = [];

    public function __construct(Storage $storage = null, LoopInterface $loop)
    {
        if ($storage === null) {
            $storage = new Storage();
        }
        $this->storage = $storage;
        $this->loop = $loop;
    }

    protected function wakeUpBlocker($key)
    {
        if (isset($this->blockers[$key])) {
            $cb = array_shift($this->blockers[$key]);
            if (count($this->blockers[$key]) <= 0) unset($this->blockers[$key]);
            $cb();
        }
    }

    public function lpush($key, $value0)
    {
        $list = $this->storage->getOrCreateList($key);

        $values = func_get_args();
        unset($values[0]);

        foreach ($values as $value) {
            $list->unshift($value);
        }
        $rc = $list->count();
        foreach ($values as $value) {
            $this->wakeUpBlocker($key);
        }

        return $rc;
    }

    public function lpushx($key, $value)
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->lpush($key, $value);
    }

    public function rpush($key, $value0)
    {
        $list = $this->storage->getOrCreateList($key);

        $values = func_get_args();
        unset($values[0]);

        foreach ($values as $value) {
            $list->push($value);
        }
        $rc = $list->count();
        foreach ($values as $value) {
            $this->wakeUpBlocker($key);
        }

        return $rc;
    }

    public function rpushx($key, $value)
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->rpush($key, $value);
    }

    public function lpop($key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        $value = $list->shift();

        if ($list->isEmpty()) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function rpop($key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        $value = $list->pop();

        if ($list->isEmpty()) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function rpoplpush($source, $destination)
    {
        if (!$this->storage->hasKey($source)) {
            return null;
        }
        $sourceList      = $this->storage->getOrCreateList($source);
        $destinationList = $this->storage->getOrCreateList($destination);

        $value = $sourceList->pop();
        $destinationList->unshift($value);
        $this->wakeUpBlocker($destination);

        if ($sourceList->isEmpty()) {
            $this->storage->unsetKey($source);
        }

        return $value;
    }

    public function llen($key)
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->storage->getOrCreateList($key)->count();
    }

    public function lindex($key, $index)
    {
        $len = $this->llen($key);
        if ($len === 0) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        // LINDEX actually checks the integer *after* checking the list and type
        $index = $this->coerceInteger($index);

        if ($index < 0) {
            $index += $len;
        }
        if ($index < 0 || $index >= $len) {
            return null;
        }

        return $list->offsetGet($index);
    }

    public function lrange($key, $start, $stop)
    {
        if (!$this->storage->hasKey($key)) {
            return array();
        }

        $start = $this->coerceInteger($start);
        $stop  = $this->coerceInteger($stop);

        $list = $this->storage->getOrCreateList($key);

        $len = $this->llen($key);
        if ($start < 0) {
            $start += $len;
        }
        if ($stop < 0) {
            $stop += $len;
        }
        if (($stop + 1) > $len) {
            $stop = $len - 1;
        }

        if ($stop < $start || $stop < 0 || $start > $len) {
            return array();
        }

        $list->rewind();
        for ($i = 0; $i < $start; ++$i) {
            $list->next();
        }

        $ret = array();

        while($i <= $stop) {
            $ret []= $list->current();
            $list->next();
            ++$i;
        }

        return $ret;
    }

    // MultiBulkReply
    public function blpop($key0, $timeout)
    {
        return $this->bpop('lpop', func_get_args());
    }

    // MultiBulkReply
    public function brpop($key0, $timeout)
    {
        return $this->bpop('rpop', func_get_args());
    }

    public function brpoplpush($source, $destination, $timeout = 0)
    {
        $that = $this;
        return $this->bpop('rpop', [$source, $timeout])
            ->then(function ($data) use ($that, $destination) {
                $that->lpush($destination, $data[1]);
                return $data[1];
            });
    }

    private function listenToList($key)
    {
        $pushed = new \React\Promise\Deferred;
        if (!isset($this->blockers[$key])) $this->blockers[$key] = [];
        $cb = function() use (&$pushed, $key) {
            $pushed->resolve($key);
        };
        $this->blockers[$key][] = $cb;
        return $pushed->promise();
    }

    private function bpop($command, $keys)
    {
        $timeout = $this->coerceTimeout(array_pop($keys));

        foreach ($keys as $key) {
            $ret = $this->$command($key);
            if ($ret !== null) {
                return array($key, $ret);
            }
        }

        $pushes = [];
        foreach ($keys as $key) {
            $pushes[] = $this->listenToList($key);
        }

        if ($timeout) {
            $dtimeout = new \React\Promise\Deferred;
            $pushes[] = $dtimeout->promise();
        }
        
        $pushed = \React\Promise\any($pushes)
            ->then(function($key) use ($pushes, $command) {
                foreach ($pushes as $push) {
                    $push->cancel();
                }
                $ret = $this->$command($key);
                return new \React\Promise\FulfilledPromise([$key, $ret]);
            });
        
        if ($timeout) {
            $this->loop->addTimer($timeout, function() use ($pushes, $dtimeout) {
                $dtimeout->resolve(null);
                foreach ($pushes as $push) {
                    $push->cancel();
                }
            });
        }
        return $pushed;
    }

    private function getClient()
    {

    }
    
    public function setClient(Client $client)
    {
        $this->storage = $client->getDatabase();
    }

    private function coerceInteger($value)
    {
        $int = (int)$value;
        if ((string)$int !== (string)$value) {
            throw new Exception('ERR value is not an integer or out of range');
        }
        return $int;
    }

    private function coerceTimeout($value)
    {
        $value = $this->coerceInteger($value);
        if ($value < 0) {
            throw new InvalidArgumentException('ERR timeout is negative');
        }
        return $value;
    }
}
