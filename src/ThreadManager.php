<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/BunnyAsyncClient.php");
require_once(__DIR__ . "/DiscordClient.php");
require_once(__DIR__ . "/InboxHandler.php");
require_once(__DIR__ . "/SqlClient.php");

class ThreadManager extends ConfigLoader
{
    private $threads = [];
    private $functions = [];
    private $futures = [];
    private $sql = null;
    private $bunny = null;
    private $admin_stopped = [];
    private $bot_tokens = [];

    function __construct()
    {
        parent::__construct();
        $this->sql = new SqlClient;
        if ($this->config["ai"]["DiscordClients"]) {
            $result = $this->sql->query("SELECT `bot_id`,`bot_token` FROM `discord_bots` WHERE `disabled` = 0 AND `bot_token` != 'Human' AND `bot_token` != ''");
            while ($row = $result->fetch_assoc()) {
                $this->bot_tokens[$row["bot_id"]] = $row["bot_token"];
                if (substr($row["bot_token"], 0, 3) == "MTE") {
                    $key = "DiscordClient_" . $row["bot_id"];
                    $this->threads[$key] = new \parallel\Runtime(__DIR__ . "/DiscordClient.php");
                    $this->functions[$key] = function ($bot_id, $bot_token) {
                        new DiscordClient($bot_id, $bot_token);
                    };
                    $this->futures[$key] = $this->threads[$key]->run($this->functions[$key], [$row["bot_id"], $row["bot_token"]]);
                }
            }
        }
        for ($id = 0; $id < $this->config["ai"]["InboxHandlers"]; $id++) {
            $key = "InboxHandler$id";
            $this->threads[$key] = new \parallel\Runtime(__DIR__ . "/InboxHandler.php");
            $this->functions[$key] = function () {
                new InboxHandler;
            };
            $this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
        }
        $loop = \React\EventLoop\Loop::get();
        $this->bunny = new BunnyAsyncClient($loop, "ai_threadmanager", $this->process(...));
        $loop->addPeriodicTimer(5, $this->timer(...));
        $loop->run();
    }

    private function timer()
    {
        foreach ($this->futures as $key => $future) {
            if ($future->done()) {
                if (!isset($this->admin_stopped[$key])) {
                    switch (true) {
                        case $key == "MemoryManager":
                            $this->threads[$key] = new \parallel\Runtime(__DIR__ . "/MemoryManager.php");
                            $this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
                            break;
                        case substr($key, 0, 12) == "InboxHandler":
                            $id = substr($key, 12);
                            $this->threads[$key] = new \parallel\Runtime(__DIR__ . "/InboxHandler.php");
                            $this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
                            break;
                        default:
                            $bot_id = substr($key, 14);
                            $this->threads[$key] = new \parallel\Runtime(__DIR__ . "/DiscordClient.php");
                            $this->futures[$key] = $this->threads[$key]->run($this->functions[$key], [$bot_id, $this->bot_tokens[$bot_id]]);
                            break;
                    }
                }
            }
        }
    }

    private function process($message)
    {
        $c = $message["c"];
        $i = $message["i"];
        echo ("ThreadManager :: " . $c . "ing thread " . $i . "\n");
        switch ($c) {
            case "start":
                try {
                    unset($this->admin_stopped[$i]);
                    if (isset($this->threads[$i])) try {
                        $this->admin_stopped[$i] = true;
                        $this->threads[$i]->kill();
                    } catch (\parallel\Runtime\Error\Closed $e) {
                        print_r($e);
                    }
                    $this->threads[$i] = new \parallel\Runtime(__DIR__ . "/DiscordClient.php");
                    $bot_id = substr($i, 14);
                    $this->futures[$i] = $this->threads[$i]->run($this->functions[$i], [$bot_id, $this->bot_tokens[$bot_id]]);
                } catch (\Exception $e) {
                    print_r($e);
                }
                echo ("ThreadManager :: started thread " . $i . "\n");
                return true;
            case "stop":
                try {
                    $this->admin_stopped[$i] = true;
                    if (isset($this->threads[$i])) $this->threads[$i]->kill();
                } catch (\parallel\Runtime\Error\Closed $e) {
                    print_r($e);
                }
                echo ("ThreadManager :: stopped thread " . $i . "\n");
                return true;
            case "status":
                $response = [];
                foreach ($this->futures as $id => $future) $response[$id] = ["done" => $future->done(), "admin_stopped" => isset($this->admin_stopped[$id])];
                $this->bunny->publish($i, $response);
                return true;
        }
        return true;
    }

    function __destruct()
    {
        foreach ($this->threads as $thread) $thread->kill();
    }
}
