<?php
try {
    $has_attachments = 0;
    $allowed_extensions = ["pdf", "txt", "jpg", "jpeg", "png", "webp"];
    if (isset($message["attachments"])) {
        foreach ($message["attachments"] as $attachment) {
            $has_attachments++;
            $file_extension = strtolower(pathinfo($attachment["filename"], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $this->sendMessage($message, ["content" => "❌ I'm sorry, but I can't accept attachments of type $file_extension (yet)\nPlease try PDF, TXT, JPG, JPEG, PNG, or WEBP"]);
                return true;
            }
            $file_name = $attachment["filename"];
            $file_url = $attachment["url"];
            $message2["t"] = "HANDLE_ATTACHMENT";
            $message2["d"] = $message;
            $message2["d"]["file_name"] = $file_name;
            $attachment_names[] = $file_name;
            $message2["d"]["file_url"] = $file_url;
            $this->bunny->publish("ai_inbox", $message2);
        }
    }
    // if $message[content] contains a URL that starts with an allowed attachment type then extract the file name and url and send it to HANDLE_ATTACHMENT
    $allowed_extensions_regex = implode("|", $allowed_extensions);
    $regex = "/(https?:\/\/[^\s]+\.($allowed_extensions_regex))/i";
    if (preg_match_all($regex, $message["content"], $matches)) {
        foreach ($matches[1] as $match) {
            $has_attachments++;
            $file_name = basename($match);
            $file_url = $match;
            $message2["t"] = "HANDLE_ATTACHMENT";
            $message2["d"] = $message;
            $message2["d"]["file_name"] = $file_name;
            $attachment_names[] = $file_name;
            $message2["d"]["file_url"] = $file_url;
            $this->bunny->publish("ai_inbox", $message2);
        }
    }
    $bad_link = false;
    // check for the presence of any links that are not supported file types and inform the user
    $regex = "/(https?:\/\/[^\s]+)/i";
    if (preg_match_all($regex, $message["content"], $matches)) {
        foreach ($matches[1] as $match) {
            $file_extension = strtolower(pathinfo($match, PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $bad_link = true;
                break;
            }
        }
    }
    if ($bad_link) {
        $this->sendMessage($message, ["content" => "❌ I'm sorry, but I can't accept links to files of type $file_extension (yet)\nPlease try PDF, TXT, JPG, JPEG, PNG, or WEBP"]);
        return true;
    }
    if ($has_attachments) $attachment_names = implode(", ", $attachment_names);
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
    if ($has_attachments) $messages[] = ["role" => "system", "content" => "$attachment_names\nIf any of the attachments are not PDF, TXT, JPEG, PNG, or WEBP, Inform them that these are the only formats we currently support.  Otherwise, Inform them you have received their $has_attachments file attachment(s) and will review them shortly.  Do not ask begin asking any questions yet!  Just ask them to wait patiently while their resume is processed, or they can continue to add more files if they wish."];
    $model = 'gpt-3.5-turbo-0613';
    if ($this->promptwriter->last_token_count > 3596) $model = 'gpt-3.5-turbo-16k-0613';
    $prompt = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.0,
        'top_p' => 0.0,
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

if (strlen($full_response)) {
    $this->sendMessage($message, ["content" => $full_response]);
    if (strpos(strtolower($full_response), "quick background check") !== false) {
        $this->sendMessage($message, ["content" => "✅ Interview Concluded!\n🕵️ Starting Background Check... (this may take a few minutes)\n\nWhile we wait for the check to complete, feel free to provide any additional information you think might be helpful, and feel free to ask me any questions you may have."]);
        $new_message["t"] = "BACKGROUND_CHECK";
        $new_message["d"] = $message;
        $this->bunny->publish("ai_inbox", $new_message);
    }
}
sleep(2);
return true;
