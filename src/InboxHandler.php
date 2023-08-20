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
        echo ("Processing " . print_r($message, true) . "\n");
        $this->promptwriter->query("SELECT 1");
        try {
            $this->log_incomming($message);
            $message["context"] = "discord";
            extract($this->promptwriter->single("SELECT `microtime` FROM `discord_channels` WHERE `channel_id` = {$message["channel_id"]} AND `bot_id` = {$message["bot_id"]}"));
            if ($microtime != $message["microtime"]) {
                return true;
            }
            $this->start_typing($message);
            $typing_time = microtime(true) + 4;
            extract($this->promptwriter->single("SELECT `bot_name` FROM `discord_bots` WHERE `bot_id` = {$message["bot_id"]}"));
            $messages = $this->promptwriter->write($message);
            $model = 'gpt-3.5-turbo-0613';
            if ($this->promptwriter->last_token_count > 3596) $model = 'gpt-3.5-turbo-16k-0613';
            $prompt = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.986,
                'top_p' => 0.986,
                'frequency_penalty' => 0,
                'presence_penalty' => 0
            ];
            $log_id = $this->promptwriter->startChatGPT("Discord Bots", $prompt, $message["bot_id"]);
            $stream = $this->promptwriter->openai->chat()->createStreamed($prompt);
            $full_response = "";
            foreach ($stream as $response) {
                $reply = $response->choices[0]->toArray();
                if (isset($reply["delta"]["content"])) {
                    $delta_content = $reply["delta"]["content"];
                    $full_response .= $delta_content;
                    if (microtime(true) > $typing_time) {
                        $this->start_typing($message);
                        $typing_time = microtime(true) + 4;
                    }
                }
            }
            $this->promptwriter->endChatGPT($log_id, $full_response);
            if (substr($full_response, 0, 1) == "[" && strpos($full_response, "]") == 6) $full_response = substr($full_response, 8);
            if (substr($full_response, 0, strlen($bot_name) + 2) == $bot_name . ": ") $full_response = substr($full_response, strlen($bot_name) + 2);
        } catch (\Exception $e) {
            $this->promptwriter->errorChatGPT($log_id, print_r($e, true));
            print_r($e);
            if (!isset($full_response) || $full_response == "") $full_response = "I'm sorry, but " . $e->getMessage() . "\n";
            else $full_response .= "\n\nAlso, I'm sorry, but " . $e->getMessage() . "\n";
        }
        if (strlen($full_response)) $this->sendMessage($message, ["content" => $full_response]);

        $followup = "";
        if (strlen($followup)) $this->sendMessage($message, ["content" => $followup]);
        sleep(2);
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
