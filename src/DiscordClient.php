<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/PromptWriter.php");
require_once(__DIR__ . "/BunnyAsyncClient.php");

use React\Async;
use \Discord\WebSockets\Intents;

class DiscordClient extends ConfigLoader
{
    private $loop = null;
    private $discord = null;
    private $bunny = null;
    private $bot_id = null;
    private $promptwriter = null;
    private $discord_roles = [];

    function __construct(int $bot_id, string $bot_token)
    {
        parent::__construct();
        $this->bot_id = $bot_id;
        $this->promptwriter = new PromptWriter;
        $this->loop = \React\EventLoop\Loop::get();
        $discord_config["token"] = $bot_token;
        $discord_config["loop"] = $this->loop;
        $discord_config['intents'] = Intents::getDefaultIntents() | Intents::GUILD_MEMBERS;
        $discord_config['loadAllMembers'] = true;
        $this->discord = new \Discord\Discord($discord_config);
        $this->discord->on("ready", $this->ready(...));
        $this->discord->run();
    }

    private function ready()
    {
        $this->bunny = new BunnyAsyncClient($this->loop, "ai_" . $this->bot_id, $this->outbox(...));
        $this->discord->on("raw", $this->inbox(...));
        $bot_avatar_url = $this->promptwriter->escape($this->discord->user->avatar);
        $this->promptwriter->query("UPDATE `discord_bots` SET `bot_avatar` = '$bot_avatar_url' WHERE `bot_id` = '{$this->bot_id}'");
        extract($this->promptwriter->single("SELECT `job_title` FROM `discord_bots` WHERE `bot_id` = '{$this->bot_id}'"));
        $activity = $this->discord->factory(\Discord\Parts\User\Activity::class, [
            'name' => $job_title,
            'type' => \Discord\Parts\User\Activity::TYPE_PLAYING
        ]);
        $this->discord->updatePresence($activity);
        $this->promptwriter->query("DELETE FROM `discord_bot2server` WHERE `bot_id` = '{$this->bot_id}'");
        $query = "";
        foreach ($this->discord->guilds as $guild) {
            $query .= "INSERT INTO `discord_bot2server` (`bot_id`, `server_id`) VALUES ('{$this->bot_id}', '{$guild->id}');";
            $query .= "UPDATE `discord_servers` SET `server_avatar` = '{$guild->icon}' WHERE `server_id` = '{$guild->id}';";
        }
        $this->promptwriter->multi($query);
        echo ("bot_id: " . $this->bot_id . " is Ready!\n");
    }

    private function inbox($message, $discord)
    {
        //print_r($message);
        if ($message->op == 11) {
            $this->promptwriter->query("SELECT 1");
            $message = json_decode(json_encode($message), true);
            $message["t"] = "HEARTBEAT";
            return $this->bunny->publish("ai_inbox", $message);
        }
        if ($message->t == "GUILD_CREATE") {
            $guild_id = $message->d->id;
            $sql = "INSERT INTO `discord_bot2server` (`bot_id`, `server_id`) VALUES ('{$this->bot_id}', '{$guild_id}') ON DUPLICATE KEY UPDATE `server_id` = '{$guild_id}';";
            $sql .= "UPDATE `discord_servers` SET `server_avatar` = '{$message->d->icon}' WHERE `server_id` = '{$guild_id}';";
            $this->promptwriter->multi($sql);
            return true;
        }
        if ($message->t == "GUILD_DELETE") {
            $guild_id = $message->d->id;
            $this->promptwriter->query("DELETE FROM `discord_bot2server` WHERE `bot_id` = '{$this->bot_id}' AND `server_id` = '{$guild_id}'");
            return true;
        }
        if ($message->t == "MESSAGE_DELETE") {
            $this->promptwriter->query("DELETE FROM `web_context` WHERE `message_id` = '{$message->d->id}'");
            return true;
        }
        // on new member joining server
        if ($message->t == "GUILD_MEMBER_ADD") {
            $guild = $discord->guilds[$message->d->guild_id];
            $guild_id = $message->d->guild_id;
            $member_id = $message->d->user->id;
            $member_name = $message->d->user->username;
            $member_avatar = $message->d->user->avatar;
            $member_discriminator = $message->d->user->discriminator;
            $member_avatar_url = $this->promptwriter->escape($member_avatar);
            $member_name = $this->promptwriter->escape($member_name);
            $this->promptwriter->query("INSERT INTO discord_users
                (discord_id, discord_username, discord_avatar, discord_discriminator)
            VALUES
                ('$member_id', '$member_name', '$member_avatar_url', '$member_discriminator')
            ON DUPLICATE KEY UPDATE 
                discord_username = VALUES(discord_username),
                discord_avatar = VALUES(discord_avatar),
                discord_discriminator = VALUES(discord_discriminator);");
            // create the user their own private channel and set their username as the channel name and set the channel topic as "Artificial Interview with $member_name"
            $channel_topic = "Artificial Interview with $member_name";
            $channel_name = $member_name;
            // create the channel in discord
            $channel = $guild->channels->create([
                'name' => $channel_name,
                'topic' => $channel_topic,
                'permission_overwrites' => [
                    [
                        'id' => $member_id,
                        'type' => 'member',
                        'allow' => 1024,
                        'deny' => 0
                    ],
                    [
                        'id' => $this->bot_id,
                        'type' => 'member',
                        'allow' => 1024,
                        'deny' => 0
                    ],
                    [
                        'id' => $guild_id,
                        'type' => 'role',
                        'allow' => 0,
                        'deny' => 1024
                    ]
                ]
            ]);
            $channel = Async\await($guild->channels->save($channel));
            // send a Welcome message to the channel by tagging the user
            $welcome_message = "# Welcome to the interview, <@$member_id>!
Before we start the interview, we want to inform you about some important information:
- The interview will be recorded in accordance with legal requirements for record-keeping and evaluation purposes. Your participation indicates your consent to the recording.
- Our company is committed to being an Equal Opportunity Employer, which means we do not discriminate based on race, color, religion, sex, national origin, age, disability, or genetic information. Please note that we collect certain information as mandated by law for record-keeping and evaluation purposes.
- In compliance with NYC AEDT regulations, we use an Automated Employment Decision Making Tool (AEDT) during our hiring process. It is important for you to be aware of this.
- Lastly, we are dedicated to transparency, and therefore, we are obligated to share information about our most recent bias audit.";

            // get the distribution date from the database
            extract($this->promptwriter->single("SELECT min(`audit_date`) as `distribution_date` FROM `bias_audit`"));
            extract($this->promptwriter->single("SELECT * FROM `bias_audit` ORDER BY `audit_date` DESC LIMIT 1"));
            $audit_timestamp = strtotime($audit_date);
            // send a message to the channel with the distribution date
            // use the builder function to create an embed message
            $builder = \Discord\Builders\MessageBuilder::new();
            $builder->addEmbed(new \Discord\Parts\Embed\Embed($this->discord, [
                'title' => 'FairGrade Bias Audit Report',
                'description' => "AEDT Distribution Date: $distribution_date",
                'url' => 'https://fairgrade.ai/bias_audit',
                'color' => 0x00ff00,
                'fields' => [
                    [
                        'name' => '**__Last Audit Date__**',
                        'value' => $audit_date . "\n<t:$audit_timestamp:R>",
                        'inline' => true
                    ],
                    [
                        'name' => '**__Results__**',
                        'value' => $audit_results,
                        'inline' => true
                    ]
                ],
                'thumbnail' => [
                    'url' => 'https://fairgrade.ai/images/logo.png'
                ],
                'image' => [
                    'url' => 'https://fairgrade.ai/images/logo.png'
                ],
                'footer' => [
                    'text' => 'Powered by FairGrade.ai',
                    'icon_url' => 'https://fairgrade.ai/images/logo.png'
                ],
                'author' => [
                    'name' => "Independently Audited By:\n$auditor_name",
                    'url' => 'https://indiauditsllc.com',
                    'icon_url' => 'http://fairgrade.ai/wp-content/uploads/2023/08/auditor.jpeg'
                ]
            ]));
            $builder->setContent($welcome_message);
            $this->log_outgoing(Async\await($channel->sendMessage($builder)));
            sleep(2);
            $msg1 = "*...Please hold for the next available representative...*";
            $this->log_outgoing(Async\await($channel->sendMessage($msg1)));
            sleep(10);
            $utc = date("c");
            $time = time();
            $msg2 = "*...<@$bot_id> has joined the chat...*\n=========================\nInterview started at $utc <t:$time:R>";
            $this->log_outgoing(Async\await($channel->sendMessage($msg2)));
            sleep(2);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg3 = "Good day! I am <@$bot_id>, your Artificial Interviewer. I am pleased to make your acquaintance and hope you are having a productive day!";
            sleep(3);
            $this->log_outgoing(Async\await($channel->sendMessage($msg3)));
            sleep(1);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg4 = "Our interviews typically last between 5 to 10 minutes, but feel free to take your time in providing detailed and thoughtful responses. Your pace is entirely up to you.";
            sleep(4);
            $this->log_outgoing(Async\await($channel->sendMessage($msg4)));
            sleep(1);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg5 = "Throughout this interview, I will pose a series of questions, allowing you the flexibility to answer in a manner that suits you best. Whether you choose to type out your responses, share relevant links, or even upload files such as resumes and cover letters, the choice is entirely yours. If there are any questions you prefer not to answer, you can simply skip them.";
            sleep(6);
            $this->log_outgoing(Async\await($channel->sendMessage($msg5)));
            sleep(1);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg6 = "If you have any inquiries prior to commencing, please do not hesitate to ask. I am available to address any queries related to our current job openings, our organization, or any other topic you may be interested in.";
            sleep(5);
            $this->log_outgoing(Async\await($channel->sendMessage($msg6)));
            sleep(1);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg7 = "To expedite the process, it would be greatly appreciated if you could provide us with your resume and cover letter by uploading or linking them.";
            sleep(4);
            $this->log_outgoing(Async\await($channel->sendMessage($msg7)));
            sleep(1);
            $this->START_TYPING(["channel_id" => $channel->id]);
            $msg8 = "Now, without further ado, shall we begin <@$member_id>?";
            sleep(3);
            $this->log_outgoing(Async\await($channel->sendMessage($msg8)));

            // get the channel id
            $channel_id = $channel->id;
            // insert the channel id into the database
            $microtime = number_format(microtime(true), 6, '.', '');
            $this->promptwriter->query("INSERT INTO `discord_channels` (`channel_id`, `bot_id`, `dedicated`, `microtime`) VALUES ('$channel_id', '{$this->bot_id}', '1', '$microtime') ON DUPLICATE KEY UPDATE `dedicated` = '1'");
            return true;
        }
        if ($message->t != "MESSAGE_CREATE") {
            return true; // Skip processing the message
        }

        // Check if the message is from the bot itself
        if ($message->d->author->id == $this->bot_id) {
            return true; // Skip processing the message
        }

        // Check if the message is from the translator and if so ignore it 
        if ($message->d->author->id == 1073766516803260437) {
            return true; // Skip processing the message
        }

        // Check if the message starts with a !
        if (substr($message->d->content, 0, 1) == "!") {
            return true; // Skip processing the message
        }

        extract($this->promptwriter->single("SELECT count(1) as `relevant` FROM `discord_channels` WHERE `channel_id` = '{$message->d->channel_id}' AND `bot_id` = '{$this->bot_id}' AND `dedicated` = '1'"));
        if ($relevant) usleep(100000);

        if (isset($message->d->referenced_message)) {
            if ($message->d->referenced_message->author->id == $this->bot_id) {
                $relevant = true;
            }
        }

        $guild = $discord->guilds[$message->d->guild_id];
        $channel = $guild->channels[$message->d->channel_id];
        $bot_member = $guild->members[$this->bot_id];

        $bot_roles = [];
        foreach ($bot_member->roles as $role) {
            $bot_roles[] = $role->id;
        }

        if ($this->bot_id == 1125851451906850867) {
            if ($message->d->guild_id == 1125849549630603345) {
                if (substr($channel->name, 0, 7) == "ticket-") {
                    $relevant = true;
                }
            }
        }

        foreach ($message->d->mention_roles as $role_id) {
            if (in_array($role_id, $bot_roles)) {
                $relevant = true;
            }
        }
        if (strpos($message->d->content, "<@{$this->bot_id}>") !== false) {
            $relevant = true;
        }

        if (!$relevant) {
            return true; // Skip processing the message
        }
        $publish_message = json_decode(json_encode($message), true);
        $publish_message["d"]["bot_id"] = $this->bot_id;
        $publish_message["d"]["roles"] = $guild->roles;
        $publish_message["d"]["bot_roles"] = $bot_roles;
        $publish_message["d"]["channel_name"] = $channel->name;
        $publish_message["d"]["channel_topic"] = $channel->topic;
        $microtime = number_format(microtime(true), 6, '.', '');
        $publish_message["d"]["microtime"] = $microtime;
        $this->promptwriter->query("INSERT INTO `discord_channels` (`channel_id`, `bot_id`, `microtime`) VALUES ('{$message->d->channel_id}', '{$this->bot_id}', '{$microtime}') ON DUPLICATE KEY UPDATE `microtime` = '{$microtime}'");
        return $this->bunny->publish("ai_inbox", $publish_message);
        return true;
    }

    private function outbox($message)
    {
        switch ($message["function"]) {
            case "DIE":
                return $this->DIE();
            case "MESSAGE_CREATE":
                return $this->MESSAGE_CREATE($message);
            case "GET_CHANNEL":
                return $this->GET_CHANNEL($message);
            case "START_TYPING":
                return $this->START_TYPING($message);
        }
        return true;
    }

    private function DIE()
    {
        echo ("DiscordClient_{$this->bot_id} STOP cmd received.\n");
        $this->loop->addPeriodicTimer(1, function () {
            die();
        });
        return true;
    }

    private function START_TYPING($message)
    {
        $channel = $this->discord->getChannel($message["channel_id"]);
        if ($channel) Async\await($channel->broadcastTyping());
        return true;
    }

    private function GET_CHANNEL($message)
    {
        $guild = $this->discord->guilds[$message["guild_id"]];
        $channel = $guild->channels[$message["channel_id"]];
        $history = Async\await($channel->getMessageHistory(['limit' => 40]));
        $publish_message = $message;
        $publish_message["history"] = $history;
        $publish_message["channel_name"] = $channel->name;
        $publish_message["channel_topic"] = $channel->topic;
        $publish_message["roles"] = $guild->roles;
        foreach ($guild->roles as $key => $value) $this->discord_roles[$key] = $value->name;
        $bot_member = $guild->members[$this->bot_id];
        $bot_roles = [];
        foreach ($bot_member->roles as $role) {
            $bot_roles[] = $role->id;
        }
        $publish_message["bot_roles"] = $bot_roles;
        $this->bunny->publish($message["queue"], $publish_message);
        return true;
    }

    private function MESSAGE_CREATE($message)
    {
        if (strlen($message["content"]) < 2000) {
            $this->log_outgoing(Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message))));
            return true;
        }
        $content = $message["content"] . " ";
        $lines = explode("\n", $content);
        $mode = "by_line";
        $result = "";
        while (count($lines)) {
            $line = array_shift($lines);
            if (strlen($line) > 2000) {
                $sentences = explode(". ", $line);
                $mode = "by_sentence";
                foreach ($lines as $line) {
                    $sentences[] = $line;
                }
                $lines = $sentences;
                $line = array_shift($lines);
            }
            if (strlen($line) > 2000) {
                $words = explode(" ", $line);
                $mode = "by_word";
                foreach ($lines as $line) {
                    $words[] = $line;
                }
                $lines = $words;
                $line = array_shift($lines);
            }
            if (strlen($line) > 2000) {
                $chars = str_split($line);
                $mode = "by_char";
                foreach ($lines as $line) {
                    $chars[] = $line;
                }
                $lines = $chars;
                $line = array_shift($lines);
            }
            $old_result = $result;
            switch ($mode) {
                case "by_char":
                    $result .= $line;
                    break;
                case "by_word":
                    $result .= $line . " ";
                    break;
                case "by_sentence":
                    $result .= $line . ". ";
                    break;
                case "by_line":
                    $result .= $line . "\n";
                    break;
            }
            if (strlen($result) > 2000) {
                $result = $old_result;
                array_unshift($lines, $line);
                // if last char of result is a space then remove it
                if (substr($result, -1) == " ") $result = substr($result, 0, -1);
                $message["content"] = $result;

                $this->log_outgoing(Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message))));
                $result = "";
            }
        }
        if (strlen($result)) {
            $message["content"] = $result;
            $this->log_outgoing(Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message))));
        }
        return true;
    }

    private function builder($message)
    {
        $builder = \Discord\Builders\MessageBuilder::new();
        if (isset($message["content"])) {
            $builder->setContent($message["content"]);
        }
        if (isset($message["addFileFromContent"])) {
            foreach ($message["addFileFromContent"] as $attachment) {
                $builder->addFileFromContent($attachment["filename"], $attachment["content"]);
            }
        }
        if (isset($message["attachments"])) {
            foreach ($message["attachments"] as $attachment) {
                $embed = new \Discord\Parts\Embed\Embed($this->discord);
                $embed->setTitle($attachment["filename"]);
                $embed->setURL($attachment["url"]);
                $embed->setImage($attachment["url"]);
                $builder->addEmbed($embed);
            }
        }
        if (isset($message["embeds"])) foreach ($message["embeds"] as $old_embed) {
            if ($old_embed["type"] == "rich") {
                $new_embed = new \Discord\Parts\Embed\Embed($this->discord);
                $new_embed->fill($old_embed);
                $builder->addEmbed($new_embed);
            }
        }
        if (isset($message["mentions"])) {
            $allowed_users = array();
            foreach ($message["mentions"] as $mention) $allowed_users[] = $mention["id"];
            $allowed_mentions["parse"] = array("roles", "everyone");
            $allowed_mentions["users"] = $allowed_users;
            $builder->setAllowedMentions($allowed_mentions);
        }
        return $builder;
    }

    private function log_outgoing($message)
    {
        $message_id = $message->id;
        $microtime = number_format(microtime(true), 6, '.', '');
        $bot_id = $message->author->id;
        $user_id = 363853952749404162;
        $role = 'assistant';
        $content = $message->content;
        foreach ($this->discord_roles as $key => $value) $content = str_replace("<@&" . $key . ">", $value, $content);
        foreach ($this->promptwriter->bot_names as $key => $value) $content = str_replace("<@" . $key . ">", $value, $content);
        $token_count = $this->promptwriter->token_count($content);
        $content = $this->promptwriter->escape($content);
        $this->promptwriter->query("INSERT INTO `web_context` (`message_id`,`microtime`,`sender_id`,`receiver_ids`,`role`,`content`,`token_count`,`discord`) VALUES ('$message_id','$microtime','$bot_id','$user_id','$role','$content','$token_count','1')");
        return true;
    }
}
