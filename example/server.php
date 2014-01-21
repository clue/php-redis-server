<?php

use Clue\Redis\React\Client;
use Clue\Redis\React\Factory;
use Clue\Redis\React\Server\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$resolver = $factory->create('6.6.6.6', $loop);
$connector = new React\SocketClient\Connector($loop, $resolver);
$factory = new Factory($loop, $connector);

$factory->createServer('localhost:1337')->then(function(Server $server) {
    echo 'server started!' . PHP_EOL;
});

$loop->run();