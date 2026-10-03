-- Apply once to the existing Gray Mentality database before enabling Gray Today.
CREATE TABLE IF NOT EXISTS gray_daily_entries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 entry_date DATE NOT NULL,
 timezone VARCHAR(64) NOT NULL,
 concept_title VARCHAR(160) NOT NULL,
 concept_text TEXT NOT NULL,
 question_text TEXT NOT NULL,
 reality TEXT NOT NULL,
 action VARCHAR(500) NOT NULL,
 body_choice ENUM('train','move','recover') NOT NULL,
 drift_status ENUM('forward','maintaining','drifting') NOT NULL,
 outcome ENUM('done','partly','not_done','changed') DEFAULT NULL,
 reflection TEXT NOT NULL,
 question_answer TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY gray_user_day (user_id, entry_date),
 CONSTRAINT gray_daily_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS gray_weekly_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 week_start DATE NOT NULL,
 reality TEXT NOT NULL,
 body TEXT NOT NULL,
 standards TEXT NOT NULL,
 drift TEXT NOT NULL,
 decision TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY gray_user_week (user_id, week_start),
 CONSTRAINT gray_weekly_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
