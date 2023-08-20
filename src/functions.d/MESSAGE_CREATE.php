<?php
try {
    $this->log_incomming($message);
    echo (1);
    $message["context"] = "discord";
    extract($this->promptwriter->single("SELECT `microtime` FROM `discord_channels` WHERE `channel_id` = {$message["channel_id"]} AND `bot_id` = {$message["bot_id"]}"));
    echo (2);
    if ($microtime != $message["microtime"]) {
        return true;
    }
    echo (3);
    $this->start_typing($message);
    echo (4);
    $typing_time = microtime(true) + 4;
    extract($this->promptwriter->single("SELECT `bot_name` FROM `discord_bots` WHERE `bot_id` = {$message["bot_id"]}"));
    echo (5);
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
    echo (6);
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
    echo (7);
    $this->promptwriter->endChatGPT($log_id, $full_response);
    echo (8);
    if (substr($full_response, 0, 1) == "[" && strpos($full_response, "]") == 6) $full_response = substr($full_response, 8);
    if (substr($full_response, 0, strlen($bot_name) + 2) == $bot_name . ": ") $full_response = substr($full_response, strlen($bot_name) + 2);
} catch (\Exception $e) {
    echo (9);
    $this->promptwriter->errorChatGPT($log_id, print_r($e, true));
    print_r($e);
    if (!isset($full_response) || $full_response == "") $full_response = "I'm sorry, but " . $e->getMessage() . "\n";
    else $full_response .= "\n\nAlso, I'm sorry, but " . $e->getMessage() . "\n";
}
echo (10);
if (strlen($full_response)) $this->sendMessage($message, ["content" => $full_response]);

$followup = "";
if (strlen($followup)) $this->sendMessage($message, ["content" => $followup]);
sleep(2);
return true;
