-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Generation Time: Apr 19, 2026 at 11:18 AM
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mail_queue`
--
ALTER TABLE `mail_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_queue_status_available` (`status`,`available_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mail_queue`
--
ALTER TABLE `mail_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
