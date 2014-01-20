<?php

namespace Clue\Redis\React\Server;

use Clue\Redis\React\Server\Storage;
use Clue\Redis\Protocol\Model\Status;
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
        return new Status('PONG');
    }

    public function get($key)
    {
        return $this->storage->getStringOrNull($key);
    }

    public function set($key, $value)
    {
        $this->storage->setString($key, $value);

        return new Status('OK');
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
        $value =& $this->storage->getIntegerRef($key);

        $value += $increment;

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

        return new Status('OK');
    }

    public function msetnx($key0, $value0)
    {
        for ($i = 0, $n = func_num_args(); $i < $n; $i += 2) {
            if ($this->storage->hasKey(func_get_arg($i))) {
                return 0;
            }
        }

        call_user_func_array(array($this, 'mset'), $args);

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

        $value = $this->storage->get($key);
        $this->storage->unsetKey($key);
        $this->storage->set($newkey, $value);

        return new Status('OK');
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

        $value = $this->storage->get($key);
        $this->storage->unsetKey($key);
        $this->storage->set($newkey, $value);

        return 1;
    }

    public function type($key)
    {
        if (!$this->storage->hasKeys($key)) {
            return new Status('none');
        }

        $value = $this->storage->get($key);
        if (is_string($value)) {
            return new Status('string');
        } elseif (is_array($value)) {
            return new Status('list');
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
        $list =& $this->storage->getListRef($key);

        return array_shift($list);
    }

    public function rpop($key)
    {
        $list =& $this->storage->getListRef($key);

        return array_pop($list);
    }

    public function llen($key)
    {
        return count($this->storage->getListOrNull($key));
    }
}
