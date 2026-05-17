CREATE TABLE IF NOT EXISTS `email_confirmations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(128) NOT NULL,
  `mail_queue_id` int(11) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_email_confirmations_expires_at` (`expires_at`),
  KEY `fk_email_confirmations_mail_queue` (`mail_queue_id`),
  CONSTRAINT `fk_email_confirmations_mail_queue`
    FOREIGN KEY (`mail_queue_id`) REFERENCES `mail_queue` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_email_confirmations_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
