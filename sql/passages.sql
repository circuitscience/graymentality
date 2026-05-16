CREATE TABLE IF NOT EXISTS `passages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `depth` enum('short','deep','long') NOT NULL DEFAULT 'deep',
  `category` varchar(80) NOT NULL DEFAULT 'general',
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'active',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_passages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `passage_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `passage_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `step_type` enum('interrupt','recognition','tension','observation','expansion','release') NOT NULL,
  `body` text NOT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_passage_step` (`passage_id`,`step_order`),
  CONSTRAINT `fk_passage_steps_passage`
    FOREIGN KEY (`passage_id`) REFERENCES `passages` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_passage_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `passage_id` int(11) NOT NULL,
  `event_type` enum('start','step','complete') NOT NULL,
  `step_order` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_passage_created` (`user_id`,`passage_id`,`created_at`),
  KEY `idx_user_passage_event` (`passage_id`,`event_type`,`created_at`),
  CONSTRAINT `fk_user_passage_events_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_user_passage_events_passage`
    FOREIGN KEY (`passage_id`) REFERENCES `passages` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `passages` (`title`, `slug`, `depth`, `category`, `status`)
SELECT 'The Narrowing', 'the-narrowing-short', 'short', 'continuity', 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM `passages` WHERE `slug` = 'the-narrowing-short'
);

INSERT INTO `passages` (`title`, `slug`, `depth`, `category`, `status`)
SELECT 'The Narrowing', 'the-narrowing', 'deep', 'continuity', 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM `passages` WHERE `slug` = 'the-narrowing'
);

INSERT INTO `passages` (`title`, `slug`, `depth`, `category`, `status`)
SELECT 'The Narrowing', 'the-narrowing-long', 'long', 'continuity', 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM `passages` WHERE `slug` = 'the-narrowing-long'
);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 1, 'interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-short'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 1);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 2, 'tension', 'Experience creates wisdom.\n\nIt also creates avoidance.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-short'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 2);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 3, 'release', 'Notice where routine speaks louder than curiosity today.', 'Return to Dashboard'
FROM `passages` p
WHERE p.slug = 'the-narrowing-short'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 3);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 1, 'interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 1);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 2, 'recognition', 'The mind often preserves identity long after behavior changes.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 2);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 3, 'tension', 'Experience creates wisdom.\n\nIt also creates avoidance.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 3);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 4, 'observation', 'Which parts of your current personality were discovered...\nand which were constructed?', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 4);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 5, 'expansion', 'Some people continue expanding long after society expects contraction.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 5);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 6, 'release', 'Pay attention today to the moments where routine speaks louder than curiosity.', 'Return to Dashboard'
FROM `passages` p
WHERE p.slug = 'the-narrowing'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 6);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 1, 'interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 1);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 2, 'recognition', 'A routine can begin as discipline and end as protection.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 2);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 3, 'recognition', 'The mind often preserves identity long after behavior changes.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 3);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 4, 'tension', 'Experience creates wisdom.\n\nIt also creates avoidance.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 4);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 5, 'observation', 'Which parts of your current personality were discovered...\nand which were constructed?', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 5);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 6, 'expansion', 'Curiosity may be one of the last forms of resistance.', 'Continue'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 6);

INSERT INTO `passage_steps` (`passage_id`, `step_order`, `step_type`, `body`, `cta_label`)
SELECT p.id, 7, 'release', 'Pay attention today to the moments where routine speaks louder than curiosity.', 'Return to Dashboard'
FROM `passages` p
WHERE p.slug = 'the-narrowing-long'
  AND NOT EXISTS (SELECT 1 FROM `passage_steps` s WHERE s.passage_id = p.id AND s.step_order = 7);
