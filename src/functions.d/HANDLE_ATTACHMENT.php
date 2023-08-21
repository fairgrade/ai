<?php
echo ("HANDLE_ATTACHMENT\n");
try {
    $uploads_inbox = __DIR__ . "/../uploads.d/inbox";
    $user_id = $message["author"]["id"];
    $file_name = $message["file_name"];
    $file_url = $message["file_url"];
    echo (1);
    $file_extension = strtolower(substr($file_name, strrpos($file_name, ".") + 1));
    $random_file_name = $user_id . "_" . md5($file_name) . "." . $file_extension;
    $random_file_path = $uploads_inbox . "/" . $random_file_name;
    echo (2);
    if (!file_exists($uploads_inbox)) mkdir($uploads_inbox);
    echo (3);
    if (file_exists($random_file_path)) unlink($random_file_path);
    echo (4);
    file_put_contents($random_file_path, file_get_contents($file_url));
    echo (5);
    // get the sha256 hash of the file
    $sha256 = hash_file("sha256", $random_file_path);
    echo (6);
    $sha256_prefix1 = substr($sha256, 0, 2);
    $sha256_prefix2 = "/" . substr($sha256, 2, 2);
    $stored_path = __DIR__ . "/../uploads.d/stored/$sha256_prefix1/$sha256_prefix2/$sha256.$file_extension";
    if (!file_exists(dirname($stored_path))) mkdir(dirname($stored_path), 0777, true);
    echo (7);
    if (!file_exists($stored_path)) copy($random_file_path, $stored_path);
    echo (8);
    // get the stored files sha256 hash and compare to validate the file was uploaded correctly
    $stored_sha256 = hash_file("sha256", $stored_path);
    if ($stored_sha256 != $sha256) {
        echo (9);
        unlink($stored_path);
        unlink($random_file_path);
        $this->sendMessage($message, ["content" => "❌ I'm sorry, but an error occurred while uploading your file $file_name Please try again."]);
        return true;
    }
    unlink($random_file_path);
    if ($file_extension == "pdf") {
        echo ("PDF");
        $text = shell_exec("pdftotext -layout {$stored_path} -");
    } else {
        echo ("IMG");
        $text = shell_exec("tesseract {$stored_path} stdout");
    }
    echo (10);
    $token_count = $this->promptwriter->token_count($text);
    $file_type = mime_content_type($stored_path);
    $file_size = filesize($stored_path);
    $has_thumb = 0;
    $file_content = $this->promptwriter->escape($text);
    echo (11);
    $this->promptwriter->query("INSERT INTO `attachments` (`sha256`, `user_id`, `file_name`, `file_type`, `file_path`, `file_size`, `has_thumb`, `file_content`, `token_count`) VALUES ('$sha256', '$user_id', '$file_name', '$file_type', '$stored_path', '$file_size', '$has_thumb', '$file_content', '$token_count')");
    $attachment_id = $this->promptwriter->insert_id();
    echo (12);
    $this->sendMessage($message, ["content" => "✅ $file_name Uploaded! Analyzing, Please wait..."]);
    echo (13);
    $total_token_count = 0;
    // split text into lines
    $lines = explode("\n", $text);
    // loop through each line
    $text = "";
    foreach ($lines as $line) {
        $token_count = $this->promptwriter->token_count($line);
        $total_token_count += $token_count;
        if ($token_count > (14 * 1024)) break;
        $text .= $line . "\n";
    }
    $messages[] = ["role" => "system", "content" => "Uploaded file $file_name: Raw text content: $text === end of raw text content ==="];
    $messages[] = ["role" => "system", "content" => "This should be a resume.  If it doesn't appear to be a resume then return an error message and ask for it to be retried. 
Otherwise, Extract all key information from this resume/CV/document including
0) Language Preference
1) Position Applying For
2) Full Name
3) Contact Information
4) Location
5) Education
6) Most Recent Job
7) Past Job Experience
8) Skills
9) Availability
10) Seeking W2 or 1099
11) Salary or Hourly expectations
12) References (if available)
13) Work Authorization (citizenship/immigrant/visa status)
14) Availability for additional rounds of interviews or any required assessments.
If you are unsure if something is useful, include it anyway. Do not make up any information.  If it's not included in the docuement flag it as missing.
If any important information is missing please point that out and request it.
Write as long as you need to capture all the details.
Use markdown formatting to organize the information."];
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
// update file_translated in the db
$full_response_sql = $this->promptwriter->escape($full_response);
$this->promptwriter->query("UPDATE `attachments` SET `file_translated` = '$full_response_sql' WHERE `attachment_id` = '$attachment_id'");

if (strlen($full_response)) $this->sendMessage($message, ["content" => $full_response . "\n\n=============\n\n✅ $file_name Analyzed! Please confirm the information is accurate and provide any information which is missing before continuing."]);
unset($message["attachments"]);
$next_message["t"] = "MESSAGE_CREATE";
$next_message["d"] = $message;
$next_message["d"]["id"]++;
$next_message["d"]["content"] = "✅ $file_name Analyzed!
Provide feedback on the document and review the information from the document for missing items and begin asking one multiple-choice question to fill in the missing information.";
$this->bunny->publish("ai_inbox", $next_message);
sleep(2);
return true;
