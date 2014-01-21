# clue/redis-server [![Build Status](https://travis-ci.org/clue/redis-server.png?branch=master)](https://travis-ci.org/clue/redis-server)

A redis server implementation in pure PHP.

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Introduction


### Motivation

This project aims to provide a simple alternative to the official redis server
implementation if installing it is not an option.

Why would I use this project if I already have the official redis server
installed? Simply put, you wouldn't. Ever.

### Project goals

* [x] Implement an in-memory datastore using the redis protocol
* [x] Compatiblity with common redis clients
  * [x] redis-cli
  * [x] redis-benchmark* (only a subset of its commands)
* [x] SOLID and modern design, tested and modular components
* [ ] Implement *all* commands (see below for list of supported commands)
* [ ] Compatibility with common tools resque(-php) etc.

### Supported commands

Eventually, this project aims to replicate *all* commands of
the [official redis server](http://redis.io/) implementation and their exact
behavior.

So far, the following list of commands shows what's already implemented:

* Keys
  * DEL
  * EXISTS
  * EXPIRE
  * EXPIREAT
  * PERSIST
  * PEXPIRE
  * PEXPIREAT
  * PTTL
  * RENAME
  * RENAMENX
  * TTL
  * TYPE
* Strings
  * DECR
  * DECRBY
  * GET
  * INCR
  * INCRBY
  * MGET
  * MSET
  * MSETNX
  * PSETEX
  * SET
  * SETEX
  * SETNX
  * STRLEN
* Lists
  * LLEN
  * LPOP
  * LPUSH
  * RPOP
  * RPUSH
* Connection
  * ECHO
  * PING
  
For details, refer to the excellent official documentation of
[redis commands](http://redis.io/commands).

All available commands are expected to behave just like their counterparts in
redis v2.6, unless otherwise noted. If you find a command to misbehave, don't
hesitate to file a bug.

Obviously, this list is incomplete in that it does not include *every* command
supported by redis. If you find a command is missing, please submit a PR :)

### Benchmarking performance

> As usual, just about *every* benchmark is biased - you've been warned.

You can use the `redis-benchmark` script that is included when installing the
official redis server. However, this project does not *yet* implement all
commands, so you should usually limit them like this:

```bash
$ redis-benchmark -p 1337 -t get,set
```

## Quickstart example

Once [installed](#install), you can use the following code to connect to your
local redis server and send some requests:

```php

$factory = new Factory($loop, $connector);
$factory->createServer()->then(function (Server $server) use ($loop) {
    $server->on('connection', function(Client $client) {
        echo $client->getRemoteAddr() .' connected' . PHP_EOL;    
    });
});

$loop->run();
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/redis-server": "dev-master"
    }
}
```

## License

MIT

