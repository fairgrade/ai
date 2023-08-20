#!/usr/local/bin/php -f
<?php

namespace fairgrade\ai;

$publish_message["t"] = "MESSAGE_CREATE";
$microtime = number_format(microtime(true), 6, '.', '');
$publish_message["d"]["microtime"] = $microtime;
$publish_message["d"]["id"] = 1142616719630803116;
$publish_message["d"]["author"]["id"] = 1142616719630803116;
$publish_message["d"]["bot_id"] = 1142616719630803116;
$publish_message["d"]["channel_id"] = 1142706861733318707;
$publish_message["d"]["channel_name"] = "#openings";
$publish_message["d"]["channel_topic"] = "Current Job Openings";
$publish_message["d"]["content"] = "Pick one of the current openings to write a discord post about.  mention 2 others briefly.";
publish("ai_inbox", $publish_message);

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
