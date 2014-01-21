# clue/redis-server [![Build Status](https://travis-ci.org/clue/redis-server.png?branch=master)](https://travis-ci.org/clue/redis-server)

A redis server implementation in pure PHP.

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

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

