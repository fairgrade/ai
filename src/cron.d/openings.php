#!/usr/local/bin/php -f
<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/../PromptWriter.php");
$pw = new PromptWriter();

try {
    extract($pw->single("SELECT `server_prompt` FROM `discord_servers` WHERE `server_id` = 1142328958399025253"));
    $messages[] = ["role" => "system", "content" => $server_prompt];
    $messages[] = ["role" => "system", "content" => "
    Randomly pick one of the current openings randomly to write a discord post in about 1500 characters.
    Randomly pick 2 others to mention briefly.
    use markdown and emojis in your post.
    the call to action is to ask for you more details in their private interview channel."];
    $model = 'gpt-3.5-turbo-0613';
    $prompt = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.986,
        'top_p' => 0.986,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];
    $log_id = $pw->startChatGPT("Discord Bots", $prompt, 1142616719630803116);
    $stream = $pw->openai->chat()->createStreamed($prompt);
    $full_response = "";
    echo (6);
    foreach ($stream as $response) {
        $reply = $response->choices[0]->toArray();
        if (isset($reply["delta"]["content"])) {
            $delta_content = $reply["delta"]["content"];
            $full_response .= $delta_content;
        }
    }
    echo (7);
    $pw->endChatGPT($log_id, $full_response);
    echo (8);
} catch (\Exception $e) {
    echo (9);
    $pw->errorChatGPT($log_id, print_r($e, true));
    print_r($e);
    if (!isset($full_response) || $full_response == "") $full_response = "I'm sorry, but " . $e->getMessage() . "\n";
    else $full_response .= "\n\nAlso, I'm sorry, but " . $e->getMessage() . "\n";
}

echo ($full_response . "\n");
$message["channel_id"] = 1142706861733318707;
$message["bot_id"] = 1142616719630803116;
$message["content"] = $full_response;
publish("ai_" . $message["bot_id"], ["function" => "MESSAGE_CREATE", "channel_id" => $message["channel_id"], "content" => $message["content"]]);

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
