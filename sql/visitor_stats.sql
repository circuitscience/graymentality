CREATE TABLE IF NOT EXISTS `visitor_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visitor_hash` char(64) NOT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT 'UNK',
  `country_name` varchar(100) NOT NULL DEFAULT 'Unknown',
  `visits` int(10) unsigned NOT NULL DEFAULT 1,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `visitor_hash` (`visitor_hash`),
  KEY `idx_visitor_stats_country` (`country_code`),
  KEY `idx_visitor_stats_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
