-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Generation Time: Apr 27, 2026 at 01:01 PM
-- Server version: 10.6.24-MariaDB-ubu2204
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jerry_bil_graymentality`
--
CREATE DATABASE IF NOT EXISTS `jerry_bil_graymentality` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jerry_bil_graymentality`;

-- --------------------------------------------------------

--
-- Table structure for table `audio_tracks`
--

CREATE TABLE `audio_tracks` (
  `id` int(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `artist` varchar(128) DEFAULT NULL,
  `bpm` int(11) NOT NULL,
  `intensity_min` tinyint(4) NOT NULL DEFAULT 1,
  `intensity_max` tinyint(4) NOT NULL DEFAULT 3,
  `lift_type` enum('DEADLIFT','SQUAT','BENCH','VOLUME','ACCESSORY','CONDITIONING') NOT NULL DEFAULT 'VOLUME',
  `is_minor_key` tinyint(1) NOT NULL DEFAULT 1,
  `energy_score` tinyint(4) NOT NULL DEFAULT 7,
  `file_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_sessions`
--

CREATE TABLE `auth_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_sessions`
--

INSERT INTO `auth_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(1, 1, 'daa15c844249eaab9521dbc0607cec2500e97de70c56c28d3195654d4191541f', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 23:02:02', '2026-04-18 19:02:02'),
(2, 1, '0360931446cc317c3451926224ee6a5f2333a94654846542844af234c364ba4b', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 23:12:18', '2026-04-18 19:12:18'),
(3, 1, '794a7fd71ce7d686cbc9ff7f922fd9e2ee88fe0e361492965ef605ecf6dba5cc', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 23:12:28', '2026-04-18 19:12:28'),
(4, 1, 'e8859b477cb5da87ad477030b2e19d8e16a1e7cf28e5baabc16d872ae3c83f17', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 16:49:40', '2026-04-19 12:49:40'),
(5, 1, '77d1d2af637acca0a87c11e9762630cb983392d1ed14db6966175d1c3dd537ee', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 19:11:40', '2026-04-19 15:11:40'),
(22, 1, '0dc52ad931a308c7a04fcaa4955189871ccf0440967d5bab3a3dc6f180b66b01', '172.19.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 21:16:57', '2026-04-25 17:16:57');

-- --------------------------------------------------------

--
-- Table structure for table `bmr_logs`
--

CREATE TABLE `bmr_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sex` enum('male','female') NOT NULL,
  `age` int(11) NOT NULL,
  `height_cm` decimal(6,2) NOT NULL,
  `weight_kg` decimal(6,2) NOT NULL,
  `pal` decimal(4,2) NOT NULL,
  `bmr_kcal` int(11) NOT NULL,
  `maintenance_kcal` int(11) NOT NULL,
  `goal_weight_kg` decimal(6,2) DEFAULT NULL,
  `goal_days` int(11) DEFAULT NULL,
  `goal_daily_delta_kcal` int(11) DEFAULT NULL,
  `goal_intake_kcal` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `body_comp_logs`
--

CREATE TABLE `body_comp_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp(),
  `weight_kg` decimal(6,2) NOT NULL DEFAULT 60.00,
  `height_cm` decimal(5,2) NOT NULL DEFAULT 160.00,
  `waist_cm` decimal(5,2) DEFAULT NULL,
  `hips_cm` decimal(5,2) DEFAULT NULL,
  `neck_cm` decimal(5,2) DEFAULT NULL,
  `chest_cm` decimal(5,2) DEFAULT NULL,
  `shoulders_cm` decimal(5,2) DEFAULT NULL,
  `thigh_cm` decimal(5,2) DEFAULT NULL,
  `calf_cm` decimal(5,2) DEFAULT NULL,
  `arm_cm` decimal(5,2) DEFAULT NULL,
  `forearm_cm` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `adj_bmi` decimal(5,2) DEFAULT NULL,
  `waist_hip_ratio` decimal(5,3) DEFAULT NULL,
  `whr_category` varchar(20) DEFAULT NULL,
  `bodyfat_percent` decimal(5,2) DEFAULT NULL,
  `lean_mass_kg` decimal(6,2) DEFAULT NULL,
  `fat_mass_kg` decimal(6,2) DEFAULT NULL,
  `daily_calorie_target` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calories_log`
--

CREATE TABLE `calories_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `is_training_day` tinyint(1) NOT NULL DEFAULT 0,
  `bmr` decimal(8,2) NOT NULL,
  `tdee` decimal(8,2) NOT NULL,
  `target_calories` decimal(8,2) NOT NULL,
  `actual_calories` decimal(8,2) DEFAULT NULL,
  `body_weight_kg` decimal(6,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `creatine_logs`
--

CREATE TABLE `creatine_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `log_date` date NOT NULL,
  `grams_taken` decimal(4,1) NOT NULL,
  `goal_grams` decimal(4,1) NOT NULL,
  `doses` tinyint(3) UNSIGNED NOT NULL,
  `timing` varchar(20) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `adherence_score` tinyint(3) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frame_potential_logs`
--

CREATE TABLE `frame_potential_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `height_cm` decimal(5,2) NOT NULL,
  `weight_kg` decimal(6,2) NOT NULL,
  `wrist_cm` decimal(5,2) NOT NULL,
  `ankle_cm` decimal(5,2) NOT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `arm_current_cm` decimal(5,2) DEFAULT NULL,
  `calf_current_cm` decimal(5,2) DEFAULT NULL,
  `chest_current_cm` decimal(5,2) DEFAULT NULL,
  `arm_pred_cm` decimal(5,2) NOT NULL,
  `calf_pred_cm` decimal(5,2) NOT NULL,
  `chest_pred_cm` decimal(5,2) NOT NULL,
  `frame_score` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gm_slides`
--

CREATE TABLE `gm_slides` (
  `id` int(11) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 10,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `kicker` varchar(60) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `subtitle` varchar(160) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `bg_image` varchar(255) DEFAULT NULL,
  `bg_overlay` tinyint(1) NOT NULL DEFAULT 1,
  `cta1_label` varchar(60) DEFAULT NULL,
  `cta1_href` varchar(255) DEFAULT NULL,
  `cta2_label` varchar(60) DEFAULT NULL,
  `cta2_href` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gm_slides`
--

INSERT INTO `gm_slides` (`id`, `slug`, `sort_order`, `active`, `kicker`, `title`, `subtitle`, `body`, `bg_image`, `bg_overlay`, `cta1_label`, `cta1_href`, `cta2_label`, `cta2_href`, `created_at`, `updated_at`) VALUES
(1, 'welcome', 10, 1, 'WELCOME', 'GRAY MENTALITY', 'Strength is not loud. It’s consistent.', 'A framework for capability, clarity, and long-term resilience — built through discipline, not extremes.', 'assets/gm_1.jpg', 1, 'Enter', 'index.php#start', 'Read the Philosophy', 'index.php#philosophy', '2025-12-15 15:46:14', NULL),
(2, 'gray-zone', 20, 1, 'THE IDEA', 'THE GRAY ZONE', 'Not maximal. Not minimal. Intentional.', 'Most people live in black and white. Gray Mentality lives in adaptation, awareness, and sustainable effort.', 'assets/gm_2.jpg', 1, 'Why “Gray”?', 'index.php#philosophy', 'Next', '#', '2025-12-15 15:46:14', NULL),
(3, 'capability', 30, 1, 'THE BODY', 'CAPABILITY > APPEARANCE', 'Train for what life demands.', 'Strength is being able to do what matters today, tomorrow, and decades from now. Training is the tool. Longevity is the goal.', 'assets/gm_3.jpg', 1, 'Start Training', 'index.php#start', 'Explore', 'index.php#system', '2025-12-15 15:46:14', NULL),
(4, 'discipline', 40, 1, 'THE MIND', 'DISCIPLINE WITHOUT DOGMA', 'No hype. No punishment. No ego.', 'Just systems that work when motivation doesn’t — and habits that don’t break when life gets busy.', 'assets/gm_4.jpg', 1, 'The System', 'index.php#system', 'Next', '#', '2025-12-15 15:46:14', NULL),
(5, 'system', 50, 1, 'THE SYSTEM', 'STRUCTURE CREATES FREEDOM', 'Remove friction. Keep momentum.', 'Programs, tracking, reflection — not to control you, but to make progress automatic.', 'assets/gm_5.jpg', 1, 'What’s inside', 'index.php#system', 'Next', '#', '2025-12-15 15:46:14', NULL),
(6, 'begin', 60, 1, 'BEGIN', 'ENTER THE GRAY', 'Start where you are. Train for life.', 'Small actions, repeated, become identity. This is the long game — and it’s winnable.', 'assets/gm_6.jpg', 1, 'Get Started', 'index.php#start', 'Contact', 'index.php#contact', '2025-12-15 15:46:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grip_logs`
--

CREATE TABLE `grip_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `test_date` date NOT NULL,
  `test_type` enum('dynamometer','dead_hang','farmer_carry') NOT NULL,
  `bodyweight_kg` decimal(6,2) DEFAULT NULL,
  `bodyweight_lbs` decimal(6,2) DEFAULT NULL,
  `grip_left_lbs` decimal(6,2) DEFAULT NULL,
  `grip_right_lbs` decimal(6,2) DEFAULT NULL,
  `avg_grip_lbs` decimal(6,2) DEFAULT NULL,
  `dead_hang_seconds` int(11) DEFAULT NULL,
  `farmer_weight_lbs` decimal(6,2) DEFAULT NULL,
  `category` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hydration_logs`
--

CREATE TABLE `hydration_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `log_date` date NOT NULL,
  `intake_liters` decimal(4,1) NOT NULL,
  `goal_liters` decimal(4,1) NOT NULL,
  `urine_color` tinyint(3) UNSIGNED NOT NULL,
  `thirst_level` tinyint(3) UNSIGNED NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `hydration_score` tinyint(3) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_queue`
--

CREATE TABLE `mail_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` mediumtext NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_attempt_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mail_queue`
--

INSERT INTO `mail_queue` (`id`, `recipient_email`, `subject`, `body_text`, `status`, `attempts`, `available_at`, `last_attempt_at`, `sent_at`, `last_error`, `created_at`, `updated_at`) VALUES
(1, 'gray@graymentality.ca', 'Gray Mentality password reset', 'A password reset was requested for your Gray Mentality account.\n\nReset link: http://localhost:8088/reset_password.php?token=88ead2a1f34ad28abf7f0c3aa3e1168873df01e3c018516838e0a10d5ddf770d\nThis link expires in 1 hour.\n\nIf you did not request this change, you can ignore this message.', 'pending', 1, '2026-04-18 19:05:01', '2026-04-18 14:50:01', NULL, 'Unable to connect to SMTP server smtp.example.com:587: php_network_getaddresses: getaddrinfo for smtp.example.com failed: Name or service not known', '2026-04-18 18:03:55', '2026-04-18 18:50:01'),
(2, 'gray@graymentality.ca', 'Gray Mentality password reset', 'A password reset was requested for your Gray Mentality account.\n\nReset link: http://localhost:8088/reset_password.php?token=483d25dfd1b7b1b010e40ddd666259a53ff32a48d983d99440da0d1aaa865def\nThis link expires in 1 hour.\n\nIf you did not request this change, you can ignore this message.', 'pending', 1, '2026-04-18 19:05:01', '2026-04-18 14:50:01', NULL, 'Unable to connect to SMTP server smtp.example.com:587: php_network_getaddresses: getaddrinfo for smtp.example.com failed: Name or service not known', '2026-04-18 18:23:41', '2026-04-18 18:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `motivation_chants`
--

CREATE TABLE `motivation_chants` (
  `id` int(11) NOT NULL,
  `mode` enum('ANGRY','FOCUS','CALM') NOT NULL DEFAULT 'ANGRY',
  `intensity` tinyint(4) NOT NULL DEFAULT 2,
  `category` varchar(32) NOT NULL DEFAULT 'DISCIPLINE',
  `phrase` varchar(140) NOT NULL,
  `cadence_ms` int(11) NOT NULL DEFAULT 900,
  `weight` tinyint(4) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `motivation_chants`
--

INSERT INTO `motivation_chants` (`id`, `mode`, `intensity`, `category`, `phrase`, `cadence_ms`, `weight`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ANGRY', 1, 'DISCIPLINE', 'DO IT ANYWAY.', 850, 7, 1, '2026-04-19 12:57:29', NULL),
(2, 'ANGRY', 1, 'DISCIPLINE', 'SHOW UP. SHUT UP. LIFT.', 900, 6, 1, '2026-04-19 12:57:29', NULL),
(3, 'ANGRY', 2, 'URGENCY', 'MOVE THE WEIGHT.', 800, 7, 1, '2026-04-19 12:57:29', NULL),
(4, 'ANGRY', 2, 'HATE_WEAKNESS', 'NOT TODAY.', 750, 8, 1, '2026-04-19 12:57:29', NULL),
(5, 'ANGRY', 2, 'URGENCY', 'NO MERCY. NO EXCUSES.', 850, 6, 1, '2026-04-19 12:57:29', NULL),
(6, 'ANGRY', 3, 'WAR', 'KILL THE QUIT.', 650, 7, 1, '2026-04-19 12:57:29', NULL),
(7, 'ANGRY', 3, 'WAR', 'TAKE IT BACK.', 650, 7, 1, '2026-04-19 12:57:29', NULL),
(8, 'ANGRY', 3, 'WAR', 'EARN IT. REP BY REP.', 700, 6, 1, '2026-04-19 12:57:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `motivation_sessions`
--

CREATE TABLE `motivation_sessions` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wo_id` bigint(20) DEFAULT NULL,
  `mode` enum('ANGRY','FOCUS','CALM') NOT NULL,
  `intensity` tinyint(4) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motivation_session_chants`
--

CREATE TABLE `motivation_session_chants` (
  `id` bigint(20) NOT NULL,
  `session_id` bigint(20) NOT NULL,
  `chant_id` int(11) NOT NULL,
  `ordinal` tinyint(4) NOT NULL,
  `shown_at` datetime NOT NULL DEFAULT current_timestamp(),
  `repeats` int(11) NOT NULL DEFAULT 6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motivation_session_tracks`
--

CREATE TABLE `motivation_session_tracks` (
  `id` bigint(20) NOT NULL,
  `session_id` bigint(20) NOT NULL,
  `track_id` int(11) NOT NULL,
  `ordinal` tinyint(4) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `muscle_growth_logs`
--

CREATE TABLE `muscle_growth_logs` (
  `id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `strength_progress` tinyint(4) NOT NULL,
  `recovery_score` tinyint(4) NOT NULL,
  `soreness_score` tinyint(4) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nutrition_profiles`
--

CREATE TABLE `nutrition_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `goal` enum('fat_loss','recomp','muscle_gain') NOT NULL,
  `activity_level` enum('sedentary','light','moderate','high','athlete') NOT NULL,
  `calorie_target` int(11) NOT NULL,
  `protein_min_g` decimal(6,2) NOT NULL,
  `protein_max_g` decimal(6,2) NOT NULL,
  `fat_min_g` decimal(6,2) DEFAULT NULL,
  `fat_max_g` decimal(6,2) DEFAULT NULL,
  `carb_target_g` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'gray@graymentality.ca', '483d25dfd1b7b1b010e40ddd666259a53ff32a48d983d99440da0d1aaa865def', '2026-04-18 19:23:41', '2026-04-18 17:27:17');

-- --------------------------------------------------------

--
-- Table structure for table `protein_intake_logs`
--

CREATE TABLE `protein_intake_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `hit_target` tinyint(1) NOT NULL DEFAULT 0,
  `meals` int(11) NOT NULL DEFAULT 0,
  `est_protein_g` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `protein_logs`
--

CREATE TABLE `protein_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `body_weight` decimal(6,2) NOT NULL,
  `weight_unit` enum('kg','lb') NOT NULL DEFAULT 'kg',
  `goal` varchar(50) NOT NULL,
  `protein_min_g` decimal(6,2) NOT NULL,
  `protein_max_g` decimal(6,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_prompts`
--

CREATE TABLE `recovery_prompts` (
  `id` int(11) NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'DOWNSHIFT',
  `prompt` varchar(200) NOT NULL,
  `weight` tinyint(4) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recovery_prompts`
--

INSERT INTO `recovery_prompts` (`id`, `category`, `prompt`, `weight`, `is_active`) VALUES
(1, 'DOWNSHIFT', 'Drop your shoulders. Unclench your jaw.', 8, 1),
(2, 'DOWNSHIFT', 'Long exhale. Slow everything down.', 8, 1),
(3, 'REFLECTION', 'One win from today. Name it.', 6, 1),
(4, 'REFLECTION', 'What did you prove to yourself?', 6, 1),
(5, 'RECOVERY', 'Hydrate. Walk 2 minutes. Breathe.', 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_sessions`
--

CREATE TABLE `recovery_sessions` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `motivation_id` bigint(20) DEFAULT NULL,
  `duration_sec` int(11) NOT NULL DEFAULT 180,
  `breath_pattern` enum('4-2-6','4-4-4','box') NOT NULL DEFAULT '4-2-6',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'user', 'Regular user', '2026-04-15 18:26:36'),
(10, 'admin', 'Administrator', '2026-04-15 18:26:36');

-- --------------------------------------------------------

--
-- Table structure for table `sleep_logs`
--

CREATE TABLE `sleep_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `sleep_date` date NOT NULL,
  `bedtime` time DEFAULT NULL,
  `wake_time` time DEFAULT NULL,
  `minutes_awake` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `naps_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `caffeine_after_2pm` tinyint(1) NOT NULL DEFAULT 0,
  `alcohol` tinyint(1) NOT NULL DEFAULT 0,
  `screen_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `room_temp_c` decimal(4,1) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sleep_recovery_logs`
--

CREATE TABLE `sleep_recovery_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `log_date` date NOT NULL,
  `hours_slept` decimal(4,1) NOT NULL,
  `sleep_quality` tinyint(3) UNSIGNED NOT NULL,
  `energy_am` tinyint(3) UNSIGNED NOT NULL,
  `mood` tinyint(3) UNSIGNED NOT NULL,
  `soreness` tinyint(3) UNSIGNED NOT NULL,
  `motivation` tinyint(3) UNSIGNED NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `recovery_score` tinyint(3) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role_id`, `is_active`, `email_verified`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'gray', 'gray@graymentality.ca', '$2y$12$5rZbco3Upn8hUed3cRUtF.jfjGxt5qPMJeQYXPHblACLi3g4RXIX.', 'jerry', 'bilous', 10, 1, 1, '2026-04-25 17:16:57', '2026-04-15 18:26:36', '2026-04-25 17:16:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_calorie_profiles`
--

CREATE TABLE `user_calorie_profiles` (
  `user_id` int(11) NOT NULL,
  `activity_level` enum('sedentary','light','moderate','heavy','athlete') NOT NULL DEFAULT 'moderate',
  `goal` enum('loss','maintain','gain') NOT NULL DEFAULT 'maintain',
  `body_type` enum('ectomorph','mesomorph','endomorph','unknown') NOT NULL DEFAULT 'unknown',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audio_tracks`
--
ALTER TABLE `audio_tracks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_filter` (`lift_type`,`bpm`,`is_minor_key`,`is_active`);

--
-- Indexes for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bmr_logs`
--
ALTER TABLE `bmr_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `body_comp_logs`
--
ALTER TABLE `body_comp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`logged_at`);

--
-- Indexes for table `calories_log`
--
ALTER TABLE `calories_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `creatine_logs`
--
ALTER TABLE `creatine_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `frame_potential_logs`
--
ALTER TABLE `frame_potential_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gm_slides`
--
ALTER TABLE `gm_slides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `active` (`active`,`sort_order`);

--
-- Indexes for table `grip_logs`
--
ALTER TABLE `grip_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grip_user_date` (`user_id`,`test_date`);

--
-- Indexes for table `hydration_logs`
--
ALTER TABLE `hydration_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mail_queue`
--
ALTER TABLE `mail_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_queue_status_available` (`status`,`available_at`);

--
-- Indexes for table `motivation_chants`
--
ALTER TABLE `motivation_chants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_phrase` (`mode`,`intensity`,`category`,`phrase`);

--
-- Indexes for table `motivation_sessions`
--
ALTER TABLE `motivation_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`user_id`,`started_at`);

--
-- Indexes for table `motivation_session_chants`
--
ALTER TABLE `motivation_session_chants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_ordinal` (`session_id`,`ordinal`),
  ADD KEY `chant_id` (`chant_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `motivation_session_tracks`
--
ALTER TABLE `motivation_session_tracks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_track` (`session_id`,`ordinal`),
  ADD KEY `track_id` (`track_id`);

--
-- Indexes for table `muscle_growth_logs`
--
ALTER TABLE `muscle_growth_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nutrition_profiles`
--
ALTER TABLE `nutrition_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD UNIQUE KEY `uq_password_resets_email` (`email`);

--
-- Indexes for table `protein_intake_logs`
--
ALTER TABLE `protein_intake_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `protein_logs`
--
ALTER TABLE `protein_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_prompts`
--
ALTER TABLE `recovery_prompts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_sessions`
--
ALTER TABLE `recovery_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`user_id`,`started_at`),
  ADD KEY `motivation_id` (`motivation_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sleep_logs`
--
ALTER TABLE `sleep_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_day` (`user_id`,`sleep_date`),
  ADD KEY `idx_user_date` (`user_id`,`sleep_date`);

--
-- Indexes for table `sleep_recovery_logs`
--
ALTER TABLE `sleep_recovery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_calorie_profiles`
--
ALTER TABLE `user_calorie_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audio_tracks`
--
ALTER TABLE `audio_tracks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `bmr_logs`
--
ALTER TABLE `bmr_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `body_comp_logs`
--
ALTER TABLE `body_comp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `calories_log`
--
ALTER TABLE `calories_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `creatine_logs`
--
ALTER TABLE `creatine_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frame_potential_logs`
--
ALTER TABLE `frame_potential_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `gm_slides`
--
ALTER TABLE `gm_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `grip_logs`
--
ALTER TABLE `grip_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hydration_logs`
--
ALTER TABLE `hydration_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mail_queue`
--
ALTER TABLE `mail_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `motivation_chants`
--
ALTER TABLE `motivation_chants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `motivation_sessions`
--
ALTER TABLE `motivation_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motivation_session_chants`
--
ALTER TABLE `motivation_session_chants`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motivation_session_tracks`
--
ALTER TABLE `motivation_session_tracks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `muscle_growth_logs`
--
ALTER TABLE `muscle_growth_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nutrition_profiles`
--
ALTER TABLE `nutrition_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `protein_intake_logs`
--
ALTER TABLE `protein_intake_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `protein_logs`
--
ALTER TABLE `protein_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_prompts`
--
ALTER TABLE `recovery_prompts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recovery_sessions`
--
ALTER TABLE `recovery_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sleep_logs`
--
ALTER TABLE `sleep_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sleep_recovery_logs`
--
ALTER TABLE `sleep_recovery_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD CONSTRAINT `auth_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `body_comp_logs`
--
ALTER TABLE `body_comp_logs`
  ADD CONSTRAINT `fk_bcl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `calories_log`
--
ALTER TABLE `calories_log`
  ADD CONSTRAINT `fk_cl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `motivation_session_chants`
--
ALTER TABLE `motivation_session_chants`
  ADD CONSTRAINT `motivation_session_chants_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `motivation_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `motivation_session_chants_ibfk_2` FOREIGN KEY (`chant_id`) REFERENCES `motivation_chants` (`id`);

--
-- Constraints for table `motivation_session_tracks`
--
ALTER TABLE `motivation_session_tracks`
  ADD CONSTRAINT `motivation_session_tracks_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `motivation_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `motivation_session_tracks_ibfk_2` FOREIGN KEY (`track_id`) REFERENCES `audio_tracks` (`id`);

--
-- Constraints for table `recovery_sessions`
--
ALTER TABLE `recovery_sessions`
  ADD CONSTRAINT `recovery_sessions_ibfk_1` FOREIGN KEY (`motivation_id`) REFERENCES `motivation_sessions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_calorie_profiles`
--
ALTER TABLE `user_calorie_profiles`
  ADD CONSTRAINT `fk_ucp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
