<?php

namespace Clue\Redis\React\Server;

use Clue\Redis\React\Server\Storage;
use Clue\Redis\Protocol\Model\StatusReply;
use Exception;

class Business
{
    private $storage;

    public function __construct(Storage $storage = null)
    {
        if ($storage === null) {
            $storage = new Storage();
        }
        $this->storage = $storage;
    }

    public function x_echo($message)
    {
        return $message;
    }

    public function ping()
    {
        return new StatusReply('PONG');
    }

    public function get($key)
    {
        return $this->storage->getStringOrNull($key);
    }

    public function set($key, $value)
    {
        $this->storage->setString($key, $value);

        return new StatusReply('OK');
    }

    public function setex($key, $seconds, $value)
    {
        return $this->psetex($key, $seconds * 1000, $value);
    }

    public function psetex($key, $milliseconds, $value)
    {
        $this->storage->setString($key, $value);
        $this->storage->setTimeout($key, microtime(true) + ($milliseconds / 1000));

        return new StatusReply('OK');
    }

    public function setnx($key, $value)
    {
        if ($this->storage->hasKey($key)) {
            return 0;
        }

        $this->storage->setString($key, $value);

        return 1;
    }

    public function incr($key)
    {
        return $this->incrby($key, 1);
    }

    public function incrby($key, $increment)
    {
        $value = $this->storage->getIntegerOrNull($key);
        $value += $increment;

        $this->storage->setString($key, $value);

        return $value;
    }

    public function decr($key)
    {
        return $this->incrby($key, -1);
    }

    public function decrby($key, $decrement)
    {
        return $this->incrby($key, -$decrement);
    }

    public function persist($key)
    {
        if ($this->storage->hasKey($key)) {
            $timeout = $this->storage->getTimeout($key);

            if ($timeout !== null) {
                $this->storage->setTimeout($key, null);
                return 1;
            }
        }
        return 0;
    }

    public function expire($key, $seconds)
    {
        return $this->pexpireat($key, 1000 * (microtime(true) + $seconds));
    }

    public function expireat($key, $timestamp)
    {
        return $this->pexpireat($key, 1000 * $timestamp);
    }

    public function pexpire($key, $milliseconds)
    {
        return $this->pexpireat($key, (1000 * microtime(true)) + $milliseconds);
    }

    public function pexpireat($key, $millisecondTimestamp)
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }
        $this->storage->setTimeout($key, $millisecondTimestamp / 1000);
        return 1;
    }

    public function ttl($key)
    {
        $pttl = $this->pttl($key);
        if ($pttl > 0) {
            $pttl = (int)($pttl / 1000);
        }
        return $pttl;
    }

    public function pttl($key)
    {
        if (!$this->storage->hasKey($key)) {
            return -2;
        }

        $timeout = $this->storage->getTimeout($key);
        if ($timeout === null) {
            return -1;
        }

        $milliseconds = 1000 * ($timeout - microtime(true));
        if ($milliseconds < 0) {
            $milliseconds = 0;
        }

        return (int)$milliseconds;
    }

    public function mget($key0)
    {
        $keys = func_get_args();
        $ret  = array();

        foreach ($keys as $key) {
            try {
                $ret []= $this->storage->getStringOrNull($key);
            }
            catch (Exception $ignore) {
                $ret []= null;
            }
        }

        return $ret;
    }

    public function mset($key0, $value0)
    {
        $n = func_num_args();
        if ($n & 1) {
            throw new Exception('ERR wrong number of arguments for \'mset\' command');
        }

        $args = func_get_args();
        for ($i = 0; $i < $n; $i += 2) {
            $this->storage->setString($args[$i], $args[$i + 1]);
        }

        return new StatusReply('OK');
    }

    public function msetnx($key0, $value0)
    {
        for ($i = 0, $n = func_num_args(); $i < $n; $i += 2) {
            if ($this->storage->hasKey(func_get_arg($i))) {
                return 0;
            }
        }

        call_user_func_array(array($this, 'mset'), func_get_args());

        return 1;
    }

    public function del($key0)
    {
        $n = 0;

        foreach (func_get_args() as $key) {
            if ($this->storage->hasKey($key)) {
                $this->storage->unsetKey($key);
                ++$n;
            }
        }

        return $n;
    }

    public function strlen($key)
    {
        return strlen($this->storage->getStringOrNull($key));
    }

    public function exists($key)
    {
        return (int)$this->storage->hasKey($key);
    }

    public function rename($key, $newkey)
    {
        if ($key === $newkey) {
            throw new Exception('ERR source and destination objects are the same');
        } elseif (!$this->storage->hasKey($key)) {
            throw new Exception('ERR no such key');
        }

        if ($this->storage->hasKey($newkey)) {
            $this->del($newkey);
        }

        $this->storage->rename($key, $newkey);

        return new StatusReply('OK');
    }

    public function renamenx($key, $newkey)
    {
        if ($key === $newkey) {
            throw new Exception('ERR source and destination objects are the same');
        } elseif (!$this->storage->hasKey($key)) {
            throw new Exception('ERR no such key');
        }

        if ($this->storage->hasKey($newkey)) {
            return 0;
        }

        $this->storage->rename($key, $newkey);

        return 1;
    }

    public function type($key)
    {
        if (!$this->storage->hasKey($key)) {
            return new StatusReply('none');
        }

        $value = $this->storage->get($key);
        if (is_string($value)) {
            return new StatusReply('string');
        } elseif (is_array($value)) {
            return new StatusReply('list');
        } else {
            throw new UnexpectedValueException('Unknown datatype encountered');
        }
    }

    public function lpush($key, $value0)
    {
        $list =& $this->storage->getListRef($key);

        $values = func_get_args();
        array_shift($values);

        $list = array_merge($values, $list);

        return count($list);
    }

    public function rpush($key, $value0)
    {
        $list =& $this->storage->getListRef($key);

        $values = func_get_args();
        array_shift($values);

        $list = array_merge($list, $values);

        return count($list);
    }

    public function lpop($key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list =& $this->storage->getListRef($key);

        $value = array_shift($list);

        if (!$list) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function rpop($key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list =& $this->storage->getListRef($key);

        $value = array_pop($list);

        if (!$list) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function llen($key)
    {
        return count($this->storage->getListOrNull($key));
    }
}
