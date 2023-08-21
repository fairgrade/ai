<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/SqlClient.php");

class PromptWriter extends SqlClient
{
    private $encoder;
    public $last_token_count = 0;
    public $bot_names = [];
    public $bot_jobs = [];
    public $discord_roles = [];
    public $openai;
    public function __construct()
    {
        parent::__construct();
        $this->encoder = new \TikToken\Encoder();
        $result = $this->query("SELECT `bot_id`,`bot_name`,`job_title` FROM `discord_bots` ORDER BY `bot_id` ASC");
        while ($row = $result->fetch_assoc()) {
            $this->bot_names[$row["bot_id"]] = $row["bot_name"];
            $this->bot_jobs[$row["bot_name"]] = $row["job_title"];
        }
    }

    public function write($message)
    {
        $debug = isset($message["debug"]) && $message["debug"];
        date_default_timezone_set("America/New_York");

        $discord = $message["context"] == "discord";
        if ($discord) {
            $callbackQueue = $this->generate_random_queue_name();
            $channel_request["function"] = "GET_CHANNEL";
            $channel_request["guild_id"] = $message["guild_id"];
            $channel_request["channel_id"] = $message["channel_id"];
            $channel_request["bot_id"] = $message["bot_id"];
            $channel_request["queue"] = $callbackQueue;
            $bunny = new \Bunny\Client($this->config['bunny']);
            $bunny->connect();
            $channel = $bunny->channel();
            if ($debug) echo ("MESSAGE_CREATE: channel created\n");
            $channel->queueDeclare($callbackQueue, false, true, false, false);
            if ($debug) echo ("MESSAGE_CREATE: queue declared\n");
            $this->publish('ai_' . $message["bot_id"], $channel_request);
            if ($debug) echo ("MESSAGE_CREATE: message published\n");
            $channel_info = [];
            $retry = 0;
            if ($debug) echo ("MESSAGE_CREATE: start_typing\n");
            while ($retry < 500) {
                $retry++;
                $callbackReply = $channel->get($callbackQueue);
                if ($callbackReply != null) {
                    if ($debug) echo ("MESSAGE_CREATE: callbackReply received\n");
                    $channel_info = json_decode($callbackReply->content, true);
                    $channel->ack($callbackReply);
                    $retry = 500;
                } else {
                    if ($debug) echo ("MESSAGE_CREATE: callbackReply not received\n");
                    usleep(100000);
                }
            }
            $channel->queueDelete($callbackQueue);
            if ($debug) echo ("MESSAGE_CREATE: queue deleted\n");
            $channel->close();
            unset($channel);
            $bunny->disconnect();
            $bunny->__destruct();
            unset($bunny);
            if ($debug) echo ("MESSAGE_CREATE: bunny disconnected\n");
            $message = array_merge($message, $channel_info);
            if ($discord && !isset($message["roles"])) {
                $message["roles"] = [];
                print_r($channel_info);
                print_r($message);
            }
            $history = [];
            foreach ($message["history"] as $historic_message) {
                if ($historic_message["content"] == "!wipe") break;
                if ($historic_message["content"] == "!stop") return false;
                if (isset($historic_message["author"]["id"])) {
                    $historic_author = isset($this->bot_names[$historic_message["author"]["id"]]) ? $this->bot_names[$historic_message["author"]["id"]] : $historic_message["author"]["username"];
                } else {
                    $historic_author = "";
                }
                $timestamp = strtotime($historic_message["timestamp"]);
                $ts = date("H:i", $timestamp);
                $historic_content = "[$ts] $historic_author: " . $historic_message["content"];
                if (isset($historic_message["author"]["id"]) && $historic_message["author"]["id"] == $message["bot_id"]) $history[$historic_message['id']] = ["role" => "assistant", "content" => $historic_content];
                else $history[$historic_message['id']] = ["role" => "user", "content" => $historic_content];
            }
            $result = $this->query("SELECT `message_id` as `history_message_id`,`microtime`,`content` as `sql_content`,`sender_id`,`token_count` FROM `web_context` WHERE (`sender_id` = '{$message["bot_id"]}' OR `receiver_ids` LIKE '%{$message["bot_id"]}%') AND `summary_complete` = 0 AND `discord` = '0' ORDER BY `message_id` DESC LIMIT 0,120");
            while ($row = $result->fetch_assoc()) {
                extract($row);
                if ($sql_content == "!wipe") break;
                $ts = date("H:i", $microtime);
                if ($sender_id == $message["bot_id"]) $role = "assistant";
                else $role = "user";
                $history[$history_message_id] = ["role" => $role, "content" => "[$ts] " . $this->bot_names[$sender_id] . ": " . $sql_content];
            }
            // sort $history by key ascending
            krsort($history);
            $message["history"] = $history;
        }

        $system_prompt = "1. expect parts:\n```\n" .
            "2. background/situation context/prompts\n" .
            "3. building context/prompts\n" .
            "4. room context/prompts\n" .
            "5. people in this room context/prompts\n" .
            "6. roles (including people not in this room) context/prompts\n" .
            "7. focus context/prompts\n" .
            "8. person specific context/prompts\n" .
            "9. focus specific person specific context/prompts\n" .
            "10. conversation/situation context/prompts\n" .
            "```\n2. background/situation context/prompts:\n```\n";


        $system_prompt .= "====================\n";
        if ($discord) {
            // Common Prompt
            $server_prompt = $this->single("SELECT `server_prompt` FROM `discord_servers` WHERE `server_id` = '{$message["guild_id"]}';");
            if (!is_null($server_prompt)) {
                extract($server_prompt);
                $system_prompt .= "\n3. building context/prompts: ```$server_prompt```\n";
            }
            // Channel Prompt
            $system_prompt .= "\n4. room context/prompts: ```{$message["channel_name"]}:\n{$message["channel_topic"]}\n```\n";
            extract($this->single("SELECT `prompt` as `channel_prompt` FROM `discord_channels` WHERE `channel_id` = '{$message["channel_id"]}' AND `bot_id` = '{$message["bot_id"]}';"));
            if (!is_null($channel_prompt)) $system_prompt .= $channel_prompt . "\n";
            $system_prompt .= "\n5. people in this room context/prompts: ```\n";
            $result = $this->query("SELECT `bot_id`,`bot_name`,`job_title`,`bot_intro` FROM `discord_bots` WHERE `bot_id` = '363853952749404162' OR `bot_id` IN (SELECT `bot_id` FROM `discord_channels` WHERE `channel_id` = '{$message["channel_id"]}') ORDER BY `bot_id` ASC");
            while ($row = $result->fetch_assoc()) $system_prompt .= "[ tag: <@{$row['bot_id']}>, name: {$row['bot_name']}, job: {$row['job_title']}, other_info: {$row['bot_intro']} ],\n";
            $system_prompt .= "```, example: write '<@363853952749404162>' to tag Russell.  Substitute ID to reach other people.\n";
            // Roles List
            $system_prompt .= "\n6. roles (including people not in this room) context/prompts: ```\n";
            foreach ($message["roles"] as $role) {
                extract($role);
                $this->discord_roles[$id] = $name;
                if (isset($this->bot_jobs[$name])) $system_prompt .= "[id: <@&$id>, name: $name type: person, title: " . $this->bot_jobs[$name] . "],\n";
                else $system_prompt .= "[id: <@&$id>, name: $name, type: group],\n";
            }
            $system_prompt .= "```\n";
        }
        // This Bot's Prompt
        extract($this->single("SELECT `bot_name`,`job_title`,`job_description`,`job_boundaries` FROM `discord_bots` WHERE `bot_id` = '{$message["bot_id"]}';"));
        $system_prompt .= "\n8. $bot_name specific context/prompts: ```\n";
        if ($discord) {
            $system_prompt .= "ID: {$message["bot_id"]}\n";
            $system_prompt .= "Aliases/Role IDs:";
            foreach ($message["bot_roles"] as $role_id) {
                $system_prompt .= " $role_id";
            }
        }
        $system_prompt .= "\n$bot_name's Title: $job_title\n";
        $system_prompt .= "\n$bot_name's Description: $job_description\n```\n";

        $system_prompt .= "\n====================\nCurrent Date/Time:\n```" . date("D Y-m-d H:i:s T") . "```\n====================\nMessage History:\n```\n";
        $system_prompt = $this->minify_prompt($system_prompt, true);
        $prompt_token_count = $this->token_count($system_prompt);
        $return_messages[] = ["role" => "system", "content" => $system_prompt];

        $jumbo_prompt = true;
        $history_space = ((isset($jumbo_prompt) && $jumbo_prompt) || $message["bot_id"] == 1112694320957505607) ? 16384 - 2048 - $prompt_token_count : min(16384 - 2048 - $prompt_token_count, 4096);

        // History
        $history = [];
        $total_history_tokens = 0;
        foreach ($message["history"] as $historic_message) {
            $historic_content = $historic_message["content"];
            $message_tokens = $this->token_count($historic_content);
            $total_history_tokens += $message_tokens;
            if ($total_history_tokens > $history_space) $total_history_tokens -= $message_tokens;
            else $history[] = $historic_message;
        }
        if ($debug) {
            echo ("Prompt Token Count: $prompt_token_count\n");
            echo ("History Space: $history_space\n");
            echo ("Total History Tokens: $total_history_tokens\n");
            echo ("Number of History Messages: " . count($history) . "\n");
            echo ("MESSAGE_CREATE: history\n");
        }
        $history = array_reverse($history);
        if (!sizeof($history)) $history[] = ["role" => "system", "content" => "No recent message history... Start a new interview."];
        foreach ($history as $historic_message) $return_messages[] = $historic_message;
        $system_prompt = "\n``` End message history.\n";
        if ($historic_message["role"] == "assistant") {
            $system_prompt .= "Write (from $bot_name) a double-text / follow-up / continuation to $bot_name's last message above.\n";
            // change last return message to be a user role
            $return_messages[count($return_messages) - 1]["role"] = "user";
        }
        $ts = date("H:i");
        if (!is_null($job_boundaries)) $system_prompt .= "\n$job_boundaries\n";
        $system_prompt .= "Expected response is: one reaction/response from $bot_name. 
			it should include any text, lists, or code requested from you. tag other members using '<@member_id>' or roles '<@&role_id>' if you think their perspective would be helpful.
            Don't start your response with the timestamp/sender name, just write the content. Include Markdown (except for URLs) and Emojis whenever possible.
            Don't end your message with any markers similar to [End of response] or [End of conversation]
			Just one direct-to-the-point natural continuation of the conversation until $bot_name is finished speaking, then stop. 
            Don't reuse the same phrases from the chat history and don't repeat anything that's already been said unless asked to repeat.
            Respond in the language of the users last message.  If they switch languages, you must switch languages too.
            [$ts] $bot_name: ";
        $system_prompt = $this->minify_prompt($system_prompt, true);
        $return_messages[] = ["role" => "system", "content" => $system_prompt];
        $total_input_tokens = $prompt_token_count + $total_history_tokens + $this->token_count($system_prompt);
        if ($debug) {
            echo ("Total Input Tokens: $total_input_tokens\n");
            echo ("Remaining Tokens: " . (16384 - $total_input_tokens) . "\n");
        }
        $this->last_token_count = $total_input_tokens;
        return $return_messages;
    }

    private function generate_random_queue_name(): string
    {
        return 'ai_' . bin2hex(random_bytes(16));
    }

    protected function minify_prompt($json, $final = false)
    {
        $json = str_replace("\t", " ", $json);
        $json = str_replace("\r", "", $json);
        $json = str_replace("\\", "", $json);
        $json = str_replace("\"", "", $json);
        $json = str_replace("'", "", $json);
        if ($final) {
            $json = str_replace("“", "\"", $json);
            $json = str_replace("”", "\n", $json);
        }
        while (strpos($json, "  ") !== false) $json = str_replace("  ", " ", $json);
        return $json;
    }

    public function publish($queue, $data)
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

    public function token_count($text)
    {
        return count($this->encoder->encode($text));
    }

    public function startChatGPT($module_name, $prompt, $bot_id = null)
    {
        extract($this->single("SELECT `api_key` FROM `module_api_keys` WHERE `module_name` = '$module_name'"));
        $this->openai = \OpenAI::client($api_key);
        $models["gpt-3.5-turbo-0613"] = ["p" => 0.0015 / 1000, "r" => 0.002 / 1000];
        $models["gpt-3.5-turbo-16k-0613"] = ["p" => 0.003 / 1000, "r" => 0.004 / 1000];
        $model_name = $prompt["model"];
        $p_cost = $models[$model_name]["p"];
        $prompt_tokens = 0;
        foreach ($prompt['messages'] as $message) $prompt_tokens += $this->token_count($message['content']);
        $status = 'new';
        $start_time = number_format(microtime(true), 6, '.', '');
        $prompt_text = $this->escape(print_r($prompt, true));
        $prompt_cost = $prompt_tokens * $p_cost;
        if (is_null($bot_id)) $bot_id = 'null';
        $this->query("INSERT INTO `openai_log` (`module_name`,`model_name`,`prompt_text`,`prompt_tokens`,`bot_id`,`status`,`start_time`,`prompt_cost`) VALUES ('$module_name','$model_name','$prompt_text','$prompt_tokens',$bot_id,'$status','$start_time','$prompt_cost')");
        return $this->insert_id();
    }

    public function endChatGPT($log_id, $response)
    {
        $models["gpt-3.5-turbo-0613"] = ["p" => 0.0015 / 1000, "r" => 0.002 / 1000];
        $models["gpt-3.5-turbo-16k-0613"] = ["p" => 0.003 / 1000, "r" => 0.004 / 1000];
        extract($this->single("SELECT `model_name`,`start_time`,`prompt_cost` FROM `openai_log` WHERE `log_id` = '$log_id'"));
        $r_cost = $models[$model_name]["r"];
        $response_tokens = $this->token_count($response);
        $response_cost = $response_tokens * $r_cost;
        $response_text = $this->escape($response);
        $status = 'complete';
        $end_time = number_format(microtime(true), 6, '.', '');
        $duration = $end_time - $start_time;
        $total_cost = $prompt_cost + $response_cost;
        $this->query("UPDATE `openai_log` SET `response_text` = '$response_text', `status` = '$status', `end_time` = '$end_time', `duration` = '$duration', `response_tokens` = '$response_tokens', `response_cost` = '$response_cost', `total_cost` = '$total_cost' WHERE `log_id` = '$log_id'");
    }

    public function errorChatGPT($log_id, $response)
    {
        $result = $this->query("SELECT `start_time` FROM `openai_log` WHERE `log_id` = '$log_id'");
        if ($result->num_rows == 0) return;
        extract($result->fetch_assoc());
        $response_tokens = $this->token_count($response);
        $response_text = $this->escape($response);
        $status = 'error';
        $end_time = number_format(microtime(true), 6, '.', '');
        $duration = $end_time - $start_time;
        $this->query("UPDATE `openai_log` SET `response_text` = '$response_text', `response_tokens` = '$response_tokens', `status` = '$status', `end_time` = '$end_time', `duration` = '$duration', `prompt_cost` = 0, `response_cost` = 0, `total_cost` = 0 WHERE `log_id` = '$log_id'");
    }
}
