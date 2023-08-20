#!/usr/local/bin/php -f
<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/DiscordClient.php");
require_once(__DIR__ . "/SqlClient.php");
$sql = new SqlClient;

$q = isset($argv[1]) ? $sql->escape($argv[1]) : 1111870294529941625;
$result = $sql->query("SELECT `bot_id`, `bot_token` FROM `discord_bots` WHERE `bot_name` LIKE '$q' OR `bot_id` = '$q'");
if ($sql->count($result)) {
    extract($sql->assoc($result));
    new DiscordClient($bot_id, $bot_token);
} else echo ("No bot found\n");
