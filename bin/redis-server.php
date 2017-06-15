<?php

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Factory;
use Clue\Redis\Server\Server;
use Clue\Redis\Protocol\Model\ModelInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

// read port definition from command args
$port = 6379;
if (isset($argv[2]) && $argv[1] === '--port') {
    $port = (int)$argv[2];
}

$address = '0.0.0.0:' . $port;
$debug = false;

$factory->createServer($address)->then(function(Server $server) use ($address, $debug) {
    echo 'server listening on ' . $address . '!' . PHP_EOL;

    echo 'you can now connect to this server via redis-cli, redis-benchmark or any other redis client' . PHP_EOL;

    if ($debug) {
        echo 'Debugging is turned on, this will significantly decrease performance' . PHP_EOL;

        $server->on('connection', function ($client) {
            echo 'client connected' . PHP_EOL;
        });

        $server->on('error', function ($error, $client) {
            echo 'ERROR: ' . $error->getMessage() . PHP_EOL;
        });

        $server->on('request', function (ModelInterface $request, Client $client) {
            echo $client->getRequestDebug($request) . PHP_EOL;
        });

        $server->on('disconnection', function ($client) {
            echo 'client disconnected' . PHP_EOL;
        });
    } else {
        echo 'Debugging is turned off, so you should not see any further output' . PHP_EOL;
    }
}, function ($e) {
    fwrite(STDERR, 'ERROR: Unable to start listening server: ' . $e->getMessage() . PHP_EOL);
});

while (1) $loop->run();
