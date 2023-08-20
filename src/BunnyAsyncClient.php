<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/ConfigLoader.php");

class BunnyAsyncClient extends ConfigLoader
{
    private $queue = null;
    private $channel = null;
    private $callback = null;

    function __construct($loop, $queue, $callback)
    {
        parent::__construct();
        $this->queue = $queue;
        $this->callback = $callback;
        $client = new \Bunny\Async\Client($loop, $this->config["bunny"]);
        $client->connect()->then($this->getChannel(...))->then($this->consume(...));
    }

    private function getChannel($client)
    {
        return $client->channel();
    }

    private function consume($channel)
    {
        $this->channel = $channel;
        $channel->qos(0, 1);
        $channel->consume($this->process(...), $this->queue);
    }

    private function close()
    {
        $this->channel->close();
    }

    private function process($message, $channel, $client)
    {
        if (($this->callback)(json_decode($message->content, true))) $channel->ack($message);
        else $channel->nack($message);
    }

    public static function publish($queue, $data)
    {
        $json_string = json_encode($data);
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = proc_open(__DIR__ . "/publish.php '$queue'", $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $json_string);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }
}
