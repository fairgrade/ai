#!/usr/local/bin/php -f
<?php
if (!isset($argv[1])) die("Usage: php src/publish.php <queue>\n");
$queue = $argv[1];
$json_string = file_get_contents("php://stdin");
publish($queue, $json_string);

function publish($queue, $json_string)
{
    require_once(__DIR__ . "/vendor/autoload.php");
    $bunny = new \Bunny\Client(json_decode(file_get_contents(__DIR__ . "/conf.d/bunny.json"), true));
    $bunny->connect();
    $channel = $bunny->channel();
    $channel->publish($json_string, [], '', $queue);
    $channel->close();
    $bunny->disconnect();
}
