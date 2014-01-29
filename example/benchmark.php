<?php

$a = 3;
$args = '';

$commands = array();
$commands []= 'php ' . __DIR__ . '/server.php';
$commands []= 'hhvm -vEval.Jit=true ' . __DIR__ . '/server.php';
$commands []= 'echo "port 1337" | redis-server -';

foreach($commands as $command) {
    $process =
    $pid = exec('bash -c ' . escapeshellarg($command . ' & ; echo $!'));

    for ($i = 0; $i < $a; ++$i) {
        exec('redis-benchmark -p 1337 ' . $args);
    }

    exec('kill ' . $pid);
}
