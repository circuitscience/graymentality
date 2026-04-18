CREATE TABLE IF NOT EXISTS muscle_growth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    strength_progress TINYINT NOT NULL,      -- 1-5
    recovery_score TINYINT NOT NULL,         -- 1-5
    soreness_score TINYINT NOT NULL,         -- 1-5
    notes TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
