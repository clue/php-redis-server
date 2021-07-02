# clue/redis-server 

[![Build Status](https://travis-ci.org/clue/php-redis-server.svg?branch=master)](https://travis-ci.org/clue/php-redis-server)
[![installs on Packagist](https://img.shields.io/packagist/dt/clue/redis-server?color=blue&label=installs%20on%20Packagist)](https://packagist.org/packages/clue/redis-server)

A Redis server implementation in pure PHP. *Not for the faint-hearted.*

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Introduction

### Motivation

[Redis](http://redis.io/) is a fast in-memory key-value database.
This project aims to provide a simple alternative to the official Redis server
implementation if installing it is not an option.

Why would I use this project if I already have the official Redis server
installed? Simply put, you wouldn't. Ever.

### Project goals

* ✓ Implement an in-memory datastore using the Redis protocol
* ✓ Compatiblity with common Redis clients and tools
  * ✓ redis-cli
  * ✓ redis-benchmark
* ✓ SOLID and modern design, tested and modular components
* ✗ Implement *all* commands (see below for list of supported commands)

### Supported commands

Eventually, this project aims to replicate *all* commands of
the [official Redis server](http://redis.io/) implementation and their exact
behavior.

So far, the following list of commands shows what's already implemented:

* Keys
  * DEL
  * EXISTS
  * EXPIRE
  * EXPIREAT
  * KEYS
  * PERSIST
  * PEXPIRE
  * PEXPIREAT
  * PTTL
  * RANDOMKEY
  * RENAME
  * RENAMENX
  * SORT
  * TTL
  * TYPE
* Strings
  * APPEND
  * DECR
  * DECRBY
  * GET
  * GETRANGE
  * GETSET
  * INCR
  * INCRBY
  * MGET
  * MSET
  * MSETNX
  * PSETEX
  * SET
  * SETEX
  * SETNX
  * SETRANGE
  * STRLEN
* Lists
  * LINDEX
  * LLEN
  * LPOP
  * LPUSH
  * LPUSHX
  * LRANGE
  * RPOP
  * RPOPLPUSH
  * RPUSH
  * RPUSHX
* Connection
  * ECHO
  * PING
  * QUIT
  * SELECT
* Server
  * AUTH
  * CLIENT KILL
  * CLIENT LIST
  * CLIENT GETNAME
  * CLIENT SETNAME
  * CONFIG GET
  * CONFIG SET
  * DBSIZE
  * FLUSHALL
  * FLUSHDB
  * SHUTDOWN
  * TIME
  
For details, refer to the excellent official documentation of
[Redis commands](http://redis.io/commands).

All available commands are expected to behave just like their counterparts in
Redis v2.6+, unless otherwise noted. If you find a command to misbehave, don't
hesitate to file a bug.

Obviously, this list is incomplete in that it does not include *every* command
supported by Redis. If you find a command is missing, please submit a PR :)

### Benchmarking performance

> As usual, just about *every* benchmark is biased - you've been warned.

You can use the `redis-benchmark` script that is included when installing the
official Redis server.

```bash
$ redis-benchmark -p 1337 -q
```

Some benchmarking results:

```
# official redis-server
$ redis-server --port 1338
$ redis-benchmark -t set,get -p 1338 -q
SET: 121951.22 requests per second
GET: 151515.16 requests per second

# clue/redis-server PHP 5.5
$ php bin/redis-server.php
$ redis-benchmark -t set,get -p 1337 -q
SET: 18761.73 requests per second
GET: 22172.95 requests per second

# clue/redis-server HHVM
$ hhvm -vEval.Jit=true bin/redis-server.php
$ redis-benchmark -t set,get -p 1337 -q
SET: 49019.61 requests per second
GET: 57142.86 requests per second
```

So depending on your configuration, expect the original implementation to be
2x to 5x as fast. Some thoughts that have a significant effect on the
performance:

- HHVM is significantly faster than standard PHP (2.5x)
- Installing `ext-libevent` (not available for HHVM unfortunately) will
  significantly improve the performance for concurrent connections.
  This is not a hard requirement, but `redis-benchmark` defaults to 50
  concurrent connections which slows down the whole server process due to
  relying on a `stream_select()` call otherwise.
- The `bin/redis-server.php` includes a `$debug` flag (which defaults to `false`).
  Disabled debugging output significantly improves performance (3x)
- The benchmark should not be run from within a virtual machine. Running this on
  the host machine instead shows significant improvements (8x). For comparision,
  the same applies to official Redis, although it shows a smaller impact (3x).

## Quickstart example

Once [installed](#install), you can start the Redis server by running the provided
bin file:

```bash
$ php bin/redis-server.php
```

Alternatively, you can also use this project as a lib in order to build your
own server like this:

```php
$factory = new Factory($loop);
$factory->createServer('localhost:1337')->then(function (Server $server) use ($loop) {
    $server->on('connection', function(Client $client) {
        echo $client->getRemoteAddr() .' connected' . PHP_EOL;    
    });
});

$loop->run();
```

## Install

The recommended way to install this library cloning this repo and installing
its dependencies [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```bash
$ sudo apt-get install php5-cli git curl
$ git clone https://github.com/clue/php-redis-server.git
$ cd php-redis-server/
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

## Docker

This project is also available as a [docker](https://www.docker.com/) image.
Using the [clue/php-redis-server](https://registry.hub.docker.com/u/clue/php-redis-server/) image is as easy as running this:

```bash
$ docker run -d clue/php-redis-server
```

## License

MIT
