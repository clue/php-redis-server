<?php

namespace Clue\Redis\Server;

use Clue\Redis\Server\Storage;
use Clue\Redis\Protocol\Model\StatusReply;
use Clue\Redis\Protocol\Model\ErrorReply;
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

    public function append($key, $value)
    {
        $string =& $this->storage->getStringRef($key);
        $string .= $value;

        return strlen($string);
    }

    public function keys($pattern)
    {
        $ret = array();

        foreach ($this->storage->getAllKeys() as $key) {
            if(fnmatch($pattern, $key)) {
                $ret []= $key;
            }
        }

        return $ret;
    }

    public function randomkey()
    {
        return $this->storage->getRandomKey();
    }

    public function sort($key)
    {
        if ($this->storage->hasKey($key)) {
            $list = iterator_to_array($this->storage->getOrCreateList($key), false);
        } else {
            // no need to sort, but validate arguments in order to be consistent with reference implementation
            $list = array();
        }

        $by     = null;
        $offset = null;
        $count  = null;
        $get    = array();
        $desc   = false;
        $sort   = SORT_NUMERIC;
        $store  = null;

        $args = func_get_args();
        $next = function() use (&$args, &$i) {
            if (!isset($args[$i + 1])) {
                throw new Exception('ERR syntax error');
            }
            return $args[++$i];
        };
        for ($i = 1, $l = count($args); $i < $l; ++$i) {
            $arg = strtoupper($args[$i]);

            if ($arg === 'BY') {
                $by = $next();
            } elseif ($arg === 'LIMIT') {
                $offset = $this->coerceInteger($next());
                $count  = $this->coerceInteger($next());
            } elseif ($arg === 'GET') {
                $get []= $next();
            } elseif ($arg === 'ASC' || $arg === 'DESC') {
                $desc = ($arg === 'DESC');
            } elseif ($arg === 'ALPHA') {
                $sort = SORT_STRING;
            } elseif ($arg === 'STORE') {
                $store = $next();
            } else {
                throw new Exception('ERR syntax error');
            }
        }

        if ($by !== null) {
            $lookup = array();
            foreach (array_unique($list) as $v) {
                $key = str_replace('*', $v, $by);
                $lookup[$v] = $this->storage->getStringOrNull($key);
                if ($lookup[$v] === null) {
                    $lookup[$v] = $v;
                }
            }

            if ($sort === SORT_NUMERIC) {
                $cmp = function($a, $b) {
                    $a = (float)$a;
                    $b = (float)$b;
                    return ($a < $b) ? 1 : (($a > $b) ? -1 : 0);
                };
            } else {
                $cmp = 'strcmp';
            }

            usort($list, function($a, $b) use ($lookup, $cmp) {
                return $cmp($lookup[$a], $lookup[$b]);
            });
        }

        sort($list, $sort);

        if ($desc) {
            $list = array_reverse($list);
        }

        if ($offset !== null) {
            $list = array_slice($list, $offset, $count, false);
        }

        if ($get) {
            $storage = $this->storage;
            $lookup = function($key, $pattern) use ($storage) {
                if ($pattern === '#') {
                    return $key;
                }

                $l = str_replace('*', $key, $pattern);

                static $cached = array();

                if (!array_key_exists($l, $cached)) {
                    $cached[$l] = $storage->getStringOrNull($l);
                }

                return $cached[$l];
            };
            $keys = $list;
            $list = array();

            foreach ($keys as $key) {
                foreach ($get as $pattern) {
                    $list []= $lookup($key, $pattern);
                }
            }
        }

        if ($store !== null) {
            $this->storage->unsetKey($store);

            if (!$list) {
                return 0;
            }

            array_unshift($list, $store);
            return call_user_func_array(array($this, 'rpush'), $list);
        }

        return $list;
    }

    public function get($key)
    {
        return $this->storage->getStringOrNull($key);
    }

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
                        throw new ErrorReply('ERR syntax error');
                    }
                    $num = $this->coerceInteger($args[++$i]);
                    if ($num <= 0) {
                        throw new ErrorReply('ERR invalid expire time in SETEX');
                    }

                    if ($arg === 'EX') {
                        $ex = $num;
                    } else {
                        $px = $num;
                    }
                } else {
                    throw new ErrorReply('ERR syntax error');
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

        return new StatusReply('OK');
    }

    public function setex($key, $seconds, $value)
    {
        return $this->psetex($key, $this->coerceInteger($seconds) * 1000, $value);
    }

    public function psetex($key, $milliseconds, $value)
    {
        $milliseconds = $this->coerceInteger($milliseconds);

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
        return $this->pexpireat($key, 1000 * (microtime(true) + $this->coerceInteger($seconds)));
    }

    public function expireat($key, $timestamp)
    {
        return $this->pexpireat($key, 1000 * $this->coerceInteger($timestamp));
    }

    public function pexpire($key, $milliseconds)
    {
        return $this->pexpireat($key, (1000 * microtime(true)) + $this->coerceInteger($milliseconds));
    }

    public function pexpireat($key, $millisecondTimestamp)
    {
        $millisecondTimestamp = $this->coerceInteger($millisecondTimestamp);

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
        } elseif ($value instanceof \SplDoublyLinkedList) {
            return new StatusReply('list');
        } else {
            throw new UnexpectedValueException('Unknown datatype encountered');
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

        return $list->count();
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

        return $list->count();
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

    private function coerceInteger($value)
    {
        $int = (int)$value;
        if ((string)$int !== (string)$value) {
            throw new Exception('ERR value is not an integer or out of range');
        }
        return $int;
    }
}
