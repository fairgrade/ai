CREATE TABLE `attachments` (
  `attachment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sha256` varchar(64) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `has_thumb` tinyint(1) NOT NULL,
  `file_content` mediumtext DEFAULT NULL,
  `file_translated` text DEFAULT NULL,
  `token_count` int(11) DEFAULT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `audits_2023-08-21` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `request_uri` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_id` (`ip_id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `ip_id_2` (`ip_id`,`user_id`),
  KEY `ip_id_3` (`ip_id`,`session_id`),
  KEY `user_id_2` (`user_id`,`session_id`),
  KEY `ip_id_4` (`ip_id`,`user_id`,`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bias_audit` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `auditor_name` varchar(128) NOT NULL,
  `audit_results` varchar(128) NOT NULL,
  PRIMARY KEY (`audit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
CREATE TABLE `discord_bot2server` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_id` bigint(20) NOT NULL,
  `server_id` bigint(20) NOT NULL,
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;
CREATE TABLE `discord_bots` (
  `bot_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bot_token` varchar(255) NOT NULL,
  `bot_name` varchar(32) NOT NULL,
  `job_title` varchar(64) DEFAULT NULL,
  `bot_intro` text DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `job_boundaries` text DEFAULT NULL,
  `bot_category` varchar(32) DEFAULT NULL,
  `bot_tags` varchar(255) DEFAULT NULL,
  `bot_author` varchar(64) DEFAULT NULL,
  `bot_version` varchar(16) DEFAULT NULL,
  `bot_avatar` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`bot_id`),
  UNIQUE KEY `bot_name` (`bot_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1143024873946497035 DEFAULT CHARSET=utf8mb4;
CREATE TABLE `discord_channels` (
  `channel_id` bigint(20) NOT NULL,
  `bot_id` bigint(20) NOT NULL,
  `microtime` decimal(20,6) NOT NULL,
  `dedicated` tinyint(1) NOT NULL DEFAULT 0,
  `prompt` text DEFAULT NULL,
  UNIQUE KEY `channel_id` (`channel_id`,`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `discord_secrets` (
  `client_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `client_secret` varchar(32) NOT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `discord_servers` (
  `server_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `server_name` varchar(255) NOT NULL,
  `server_prompt` text NOT NULL,
  `server_avatar` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1142328958399025254 DEFAULT CHARSET=utf8mb4;
CREATE TABLE `discord_users` (
  `discord_id` bigint(20) NOT NULL,
  `discord_username` varchar(128) NOT NULL,
  `discord_avatar` varchar(32) NOT NULL,
  `discord_discriminator` smallint(4) NOT NULL,
  `discord_email` varchar(128) DEFAULT NULL,
  `discord_verified` tinyint(1) DEFAULT NULL,
  `discord_access_token` varchar(128) DEFAULT NULL,
  `discord_refresh_token` varchar(128) DEFAULT NULL,
  `discord_expires_at` int(11) DEFAULT NULL,
  `login_first` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_last` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_type` enum('FREE','BASIC','TRADER','LEADER') NOT NULL DEFAULT 'FREE',
  `billing_interval` enum('WEEK','MONTH','YEAR') NOT NULL DEFAULT 'WEEK',
  `trial_expires` int(11) NOT NULL DEFAULT 0,
  `earnings` decimal(10,2) NOT NULL DEFAULT 0.00,
  `logins` int(11) NOT NULL DEFAULT 1,
  `views` int(11) NOT NULL DEFAULT 1,
  `ip_app` int(11) DEFAULT NULL,
  `ip_api` int(11) DEFAULT NULL,
  `api_token` varchar(128) DEFAULT NULL,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`discord_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(39) NOT NULL,
  `last_user_id` bigint(20) NOT NULL DEFAULT 0,
  `first_login` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `first_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lat` float DEFAULT NULL,
  `lon` float DEFAULT NULL,
  `city` varchar(64) DEFAULT NULL,
  `country` varchar(32) DEFAULT NULL,
  `region` varchar(16) DEFAULT NULL,
  `language` text NOT NULL,
  `user_agent` text NOT NULL,
  `logins` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 1,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ip_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `logins` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`history_id`),
  UNIQUE KEY `ip_id` (`ip_id`,`user_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `module_api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(255) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_module_name` (`module_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
CREATE TABLE `openai_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('new','complete','error','') NOT NULL,
  `start_time` decimal(20,6) NOT NULL,
  `module_name` varchar(32) NOT NULL,
  `bot_id` bigint(20) DEFAULT NULL,
  `model_name` varchar(32) NOT NULL,
  `prompt_text` mediumtext NOT NULL,
  `prompt_tokens` int(11) NOT NULL,
  `prompt_cost` float NOT NULL,
  `response_text` text DEFAULT NULL,
  `response_tokens` int(11) DEFAULT NULL,
  `response_cost` float DEFAULT NULL,
  `end_time` decimal(20,6) DEFAULT NULL,
  `total_cost` float DEFAULT NULL,
  `duration` decimal(20,6) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=451 DEFAULT CHARSET=utf8mb4;
CREATE TABLE `sessions_app` (
  `session_id` char(26) NOT NULL,
  `first_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_id` int(11) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `logins` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 1,
  `language` text DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `sessions_php` (
  `id` char(26) NOT NULL,
  `access` int(10) NOT NULL,
  `data` mediumblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `web_context` (
  `message_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `microtime` decimal(20,6) NOT NULL,
  `sender_id` bigint(20) DEFAULT NULL,
  `receiver_ids` text NOT NULL,
  `role` enum('assistant','user','generatedImage') NOT NULL,
  `content` text NOT NULL,
  `token_count` int(11) DEFAULT NULL,
  `summary_complete` tinyint(4) NOT NULL DEFAULT 0,
  `discord` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `discord` (`discord`),
  FULLTEXT KEY `receiver_ids` (`receiver_ids`),
  FULLTEXT KEY `content` (`content`)
) ENGINE=InnoDB AUTO_INCREMENT=1143061956199198792 DEFAULT CHARSET=utf8mb4;
