<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/PromptWriter.php");
require_once(__DIR__ . "/BunnyAsyncClient.php");

class InboxHandler extends ConfigLoader
{
    private $promptwriter = null;
    private $bunny = null;

    function __construct()
    {
        parent::__construct();
        $this->promptwriter = new PromptWriter();
        $loop = \React\EventLoop\Loop::get();
        $this->bunny = new BunnyAsyncClient($loop, "ai_inbox", $this->process(...));
        $loop->run();
    }

    private function process($message)
    {
        $this->promptwriter->query("SELECT 1");
        if (file_exists(__DIR__ . "/functions.d/" . $message["t"] . ".php")) include(__DIR__ . "/functions.d/" . $message["t"] . ".php");
        else echo ("No function found for " . $message["t"] . "\n");
        return true;
    }

    private function start_typing($message)
    {
        echo ("START_TYPING\n");
        $reply["function"] = "START_TYPING";
        $reply["channel_id"] = $message["channel_id"];
        $this->bunny->publish("ai_" . $message["bot_id"], $reply);
        return true;
    }

    private function sendMessage($message, $reply)
    {
        $reply["function"] = "MESSAGE_CREATE";
        $reply["channel_id"] = $message["channel_id"];
        $this->bunny->publish("ai_" . $message["bot_id"], $reply);
        return true;
    }

    private function log_incomming($message)
    {
        $message_id = $message['id'];
        $microtime = number_format(microtime(true), 6, '.', '');
        $bot_id = $message['bot_id'];
        $user_id = $message['author']['id'];
        $role = 'user';
        $content = $message['content'];
        foreach ($this->promptwriter->discord_roles as $key => $value) $content = str_replace("<@&" . $key . ">", $value, $content);
        foreach ($this->promptwriter->bot_names as $key => $value) $content = str_replace("<@" . $key . ">", $value, $content);
        $token_count = $this->promptwriter->token_count($content);
        $content = $this->promptwriter->escape($content);
        $this->promptwriter->query("INSERT INTO `web_context` (`message_id`,`microtime`,`sender_id`,`receiver_ids`,`role`,`content`,`token_count`,`discord`) VALUES ('$message_id','$microtime','$user_id','$bot_id','$role','$content','$token_count','1') ON DUPLICATE KEY UPDATE `receiver_ids` = CONCAT(`receiver_ids`,' $bot_id'), `microtime` = '$microtime'");
        return true;
    }
}
