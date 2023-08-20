#!/usr/local/bin/php -f
<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/../PromptWriter.php");
$pw = new PromptWriter();

try {
    extract($pw->single("SELECT `server_prompt` FROM `discord_servers` WHERE `server_id` = 1142328958399025253"));
    $messages[] = ["role" => "system", "content" => $server_prompt];
    $messages[] = ["role" => "user", "content" => "Pick one of the current openings to write a discord post in about 1600 characters.  mention 2 others briefly. use markdown and emojis."];
    $model = 'gpt-3.5-turbo-0613';
    $prompt = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.986,
        'top_p' => 0.986,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];
    $log_id = $this->promptwriter->startChatGPT("Discord Bots", $prompt, 1142616719630803116);
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

echo ($full_respone . "\n");

function publish($queue, $data)
{
    $json_string = json_encode($data);
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );
    $process = proc_open("/usr/bin/publish '$queue'", $descriptorspec, $pipes);
    if (is_resource($process)) {
        fwrite($pipes[0], $json_string);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}
