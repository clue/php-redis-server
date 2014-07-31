<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Storage;
use Exception;
use InvalidArgumentException;
use Clue\Redis\Server\Client;

class Strings
{
    private $storage;

    public function __construct(Storage $storage = null)
    {
        if ($storage === null) {
            $storage = new Storage();
        }
        $this->storage = $storage;
    }

    public function append($key, $value)
    {
        $string =& $this->storage->getStringRef($key);
        $string .= $value;

        return strlen($string);
    }

    public function get($key)
    {
        return $this->storage->getStringOrNull($key);
    }

    // StatusReply
    public function set($key, $value)
    {
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            array_shift($args);

            $px = null;
            $ex = null;
            $xx = false;
            $nx = false;

            for ($i = 0, $n = count($args); $i < $n; ++$i) {
                $arg = strtoupper($args[$i]);

                if ($arg === 'XX') {
                    $xx = true;
                } elseif ($arg === 'NX') {
                    $nx = true;
                } elseif ($arg === 'EX' || $arg === 'PX') {
                    if (!isset($args[$i + 1])) {
                        throw new InvalidArgumentException('ERR syntax error');
                    }
                    $num = $this->coerceInteger($args[++$i]);
                    if ($num <= 0) {
                        throw new InvalidArgumentException('ERR invalid expire time in SETEX');
                    }

                    if ($arg === 'EX') {
                        $ex = $num;
                    } else {
                        $px = $num;
                    }
                } else {
                    throw new InvalidArgumentException('ERR syntax error');
                }
            }

            if ($nx && $this->storage->hasKey($key)) {
                return null;
            }

            if ($xx && !$this->storage->hasKey($key)) {
                return null;
            }

            if ($ex !== null) {
                $px += $ex * 1000;
            }

            if ($px !== null) {
                return $this->psetex($key, $px, $value);
            }
        }

        $this->storage->setString($key, $value);

        return true;
    }

    // StatusReply
    public function setex($key, $seconds, $value)
    {
        return $this->psetex($key, $this->coerceInteger($seconds) * 1000, $value);
    }

    // StatusReply
    public function psetex($key, $milliseconds, $value)
    {
        $milliseconds = $this->coerceInteger($milliseconds);

        $this->storage->setString($key, $value);
        $this->storage->setTimeout($key, microtime(true) + ($milliseconds / 1000));

        return true;
    }

    public function setnx($key, $value)
    {
        if ($this->storage->hasKey($key)) {
            return false;
        }

        $this->storage->setString($key, $value);

        return true;
    }

    public function incr($key)
    {
        return $this->incrby($key, 1);
    }

    public function incrby($key, $increment)
    {
        $increment = $this->coerceInteger($increment);

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
        return $this->incrby($key, -$this->coerceInteger($decrement));
    }

    public function getset($key, $value)
    {
        $old = $this->storage->getStringOrNull($key);
        $this->storage->setString($key, $value);

        return $old;
    }

    public function getrange($key, $start, $end)
    {
        $start = $this->coerceInteger($start);
        $end   = $this->coerceInteger($end);

        $string = $this->storage->getStringOrNull($key);

        if ($end > 0) {
            $end = $end - $start + 1;
        } elseif ($end < 0) {
            if ($start < 0) {
                $end = $end - $start + 1;
            } else {
                $end += 1;
                if ($end === 0) {
                    return $string;
                }
            }
        }

        return (string)substr($string, $start, $end);
    }

    public function setrange($key, $offset, $value)
    {
        $offset = $this->coerceInteger($offset);

        $string =& $this->storage->getStringRef($key);
        $slen = strlen($string);

        $post = '';

        if ($slen < $offset) {
            $string .= str_repeat("\0", $offset - $slen);
        } else {
            $post = (string)substr($string, $offset + strlen($value));
            $string = substr($string, 0, $offset);
        }

        $string .= $value . $post;

        return strlen($string);
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

    // StatusReply
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

        return true;
    }

    public function msetnx($key0, $value0)
    {
        for ($i = 0, $n = func_num_args(); $i < $n; $i += 2) {
            if ($this->storage->hasKey(func_get_arg($i))) {
                return false;
            }
        }

        call_user_func_array(array($this, 'mset'), func_get_args());

        return true;
    }

    public function strlen($key)
    {
        return strlen($this->storage->getStringOrNull($key));
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
}
