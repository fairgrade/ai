<?php
try {
    $has_attachments = false;
    if (isset($message["attachments"])) {
        $allowed_extensions = ["pdf", "txt", "jpg", "jpeg", "png", "webp"];
        foreach ($message["attachments"] as $attachment) {
            $has_attachments = true;
            $file_extension = strtolower(pathinfo($attachment["filename"], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $this->sendMessage($message, ["content" => "I'm sorry, but I can't accept attachments of type $file_extension (yet)\nPlease try PDF, TXT, JPG, JPEG, PNG, or WEBP"]);
                return true;
            }
            $file_name = $attachment["filename"];
            $file_url = $attachment["url"];
            $message2["t"] = "HANDLE_ATTACHMENT";
            $message2["d"] = $message;
            $message2["d"]["file_name"] = $file_name;
            $message2["d"]["file_url"] = $file_url;
            $this->bunny->publish("ai_inbox", $message2);
        }
    }
    $this->log_incomming($message);
    $message["context"] = "discord";
    extract($this->promptwriter->single("SELECT `microtime` FROM `discord_channels` WHERE `channel_id` = {$message["channel_id"]} AND `bot_id` = {$message["bot_id"]}"));
    if ($microtime != $message["microtime"] && $message["microtime"] != -1) {
        return true;
    }
    $this->start_typing($message);
    $typing_time = microtime(true) + 4;
    extract($this->promptwriter->single("SELECT `bot_name` FROM `discord_bots` WHERE `bot_id` = {$message["bot_id"]}"));
    $messages = $this->promptwriter->write($message);
    if ($has_attachments) $messages[] = ["role" => "system", "content" => "Inform them you have received their attachment(s) and will review them shortly."];
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
sleep(2);
return true;
