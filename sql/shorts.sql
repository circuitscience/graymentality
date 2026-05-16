-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 10, 2026 at 06:33 PM
-- Server version: 10.6.25-MariaDB-cll-lve
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jerrybil_graymentality`
--

-- --------------------------------------------------------

--
-- Table structure for table `shorts`
--

CREATE TABLE `shorts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shorts`
--

INSERT INTO `shorts` (`id`, `title`, `text`, `created_at`, `asset_url`) VALUES
(1, 'The Day We Stop Exploring', 'There isn’t a day marked on a calendar.\r\n\r\nNo notification.\r\nNo ceremony.\r\n\r\nJust a quiet shift.\r\n\r\nThe questions slow down.\r\n\r\nThe need to know fades into the comfort of already knowing enough.\r\n\r\nYou stop wandering—not because there’s nothing left to find, but because wandering begins to feel unnecessary.\r\n\r\nEffortful.\r\n\r\nEven indulgent.\r\n\r\nYou begin to prefer certainty over discovery.\r\n\r\nAnd without noticing it, your world stops expanding.\r\n\r\nNot all at once.\r\n\r\nJust… gradually.\r\n\r\nUntil everything you do exists inside a radius you no longer question.', '2026-05-09 11:23:46', NULL),
(2, 'Comfort Has a Cost', 'Comfort is rarely presented as a trade.\r\n\r\nIt feels like a reward.\r\n\r\nYou worked. You struggled. You earned this.\r\n\r\nSo you settle into what’s easy.\r\n\r\nWhat’s predictable.\r\n\r\nWhat doesn’t ask anything more from you.\r\n\r\nAnd slowly, discomfort becomes something to avoid instead of something to pursue.\r\n\r\nBut discomfort was never the enemy.\r\n\r\nIt was the mechanism.\r\n\r\nThe friction that forced adaptation.\r\n\r\nThe signal that something was still happening.\r\n\r\nRemove it—and life doesn’t become better.\r\n\r\nIt becomes quieter.\r\n\r\nFlatter.\r\n\r\nLess demanding.\r\n\r\nLess alive.', '2026-05-09 11:23:46', NULL),
(3, 'Tiny Permissions', 'It doesn’t take a decision to begin shrinking.\r\n\r\nJust permission.\r\n\r\nSmall ones.\r\n\r\nBarely noticeable.\r\n\r\n“I don’t need to do that anymore.”\r\n“That’s for younger people.”\r\n“I’ve already tried that.”\r\n“I’m fine where I am.”\r\n\r\nEach one feels reasonable.\r\n\r\nLogical, even.\r\n\r\nAnd each one removes a possibility.\r\n\r\nNot dramatically.\r\n\r\nJust enough.\r\n\r\nUntil the range of what you’re willing to do becomes narrower than what you’re capable of doing.\r\n\r\nAnd that gap keeps growing.', '2026-05-09 11:25:04', NULL),
(4, 'When Curiosity Quietly Leaves', 'Curiosity doesn’t disappear all at once.\r\n\r\nIt fades.\r\n\r\nYou stop asking questions you don’t already have answers to.\r\n\r\nYou stop pursuing things you might not be good at.\r\n\r\nYou begin to filter experience through efficiency:\r\n\r\nIs this useful?\r\nIs this necessary?\r\n\r\nAnd if the answer is no—you move on.\r\n\r\nBut curiosity was never efficient.\r\n\r\nIt was messy.\r\n\r\nUncertain.\r\n\r\nOften pointless.\r\n\r\nAnd that’s exactly what made it valuable.\r\n\r\nWhen it leaves, nothing feels urgent anymore.\r\n\r\nBecause nothing feels unknown.', '2026-05-09 11:25:04', NULL),
(5, 'The Slow Negotiation', 'You don’t give things up.\r\n\r\nYou negotiate them away.\r\n\r\nOne by one.\r\n\r\nYou lower expectations—not all at once, just enough to make things easier.\r\n\r\nYou redefine effort.\r\n\r\nYou adjust what “enough” means.\r\n\r\nAnd every adjustment makes sense.\r\n\r\nThat’s the problem.\r\n\r\nBecause over time, those small negotiations accumulate.\r\n\r\nUntil the life you’re living no longer resembles the one you were once reaching for.\r\n\r\nBut it didn’t feel like loss.\r\n\r\nIt felt like adjustment.', '2026-05-09 11:26:36', NULL),
(6, 'Psychological Retirement', 'Long before the body slows down, the mind often does.\r\n\r\nIt stops seeking.\r\n\r\nStops building.\r\n\r\nStops imagining something beyond what already exists.\r\n\r\nNot because it can’t—\r\n\r\nbut because it decides it doesn’t need to.\r\n\r\nThis is psychological retirement.\r\n\r\nNo announcement.\r\n\r\nNo visible shift.\r\n\r\nJust the quiet acceptance that this is as far as things will go.\r\n\r\nAnd once that belief settles in…\r\n\r\nit becomes difficult to move beyond it.', '2026-05-09 11:26:36', NULL),
(7, 'The Seduction of Predictability', 'Predictability feels safe.\r\n\r\nControlled.\r\n\r\nManageable.\r\n\r\nYou know what will happen.\r\nYou know how it will feel.\r\n\r\nAnd eventually, you begin to prefer that.\r\n\r\nYou design your days around what won’t surprise you.\r\n\r\nWhat won’t challenge you.\r\n\r\nWhat won’t disrupt the rhythm you’ve built.\r\n\r\nBut predictability comes at a cost.\r\n\r\nIt removes the unknown.\r\n\r\nAnd without the unknown—\r\n\r\nthere’s nothing left to engage with.', '2026-05-09 11:27:42', NULL),
(8, 'Entertainment Replacing Experience', 'It’s easier to watch than to do.\r\n\r\nEasier to follow than to lead.\r\n\r\nEasier to consume than to create.\r\n\r\nAnd at some point, the balance shifts.\r\n\r\nYou spend more time observing life than participating in it.\r\n\r\nNot intentionally.\r\n\r\nJust… progressively.\r\n\r\nUntil experience becomes something you witness instead of something you generate.\r\n\r\nAnd that shift is subtle.\r\n\r\nBut it changes everything.', '2026-05-09 11:27:42', NULL),
(9, 'The Shrinking Radius of Life', 'At first, your life is wide.\r\n\r\nUnstructured.\r\nExpansive.\r\nUncertain.\r\n\r\nOver time, it becomes organized.\r\n\r\nEfficient.\r\n\r\nContained.\r\n\r\nYou go to the same places.\r\nTalk to the same people.\r\nThink the same thoughts.\r\n\r\nNot because you have to—\r\n\r\nbut because it’s easier.\r\n\r\nAnd slowly, your world contracts.\r\n\r\nNot physically.\r\n\r\nBut mentally.\r\n\r\nUntil everything you do exists within a space that feels manageable.\r\n\r\nBut smaller than it used to be.', '2026-05-09 11:29:06', NULL),
(10, 'Die Living', 'There is no rebellion here.\r\n\r\nNo dramatic refusal.\r\n\r\nJust a quiet resistance.\r\n\r\nTo stop concluding life early.\r\n\r\nTo stop negotiating away possibility.\r\n\r\nTo remain engaged—even when disengagement would be easier.\r\n\r\nDie Living is not about intensity.\r\n\r\nIt’s about continuity.\r\n\r\nContinuing to reach.\r\n\r\nContinuing to question.\r\n\r\nContinuing to step into the unknown—long after it would be acceptable not to.\r\n\r\nBecause the alternative isn’t failure.\r\n\r\nIt’s fading.', '2026-05-09 11:29:06', NULL),
(11, 'The Kingdom of God is within you', 'That line—“the kingdom of God is within you”—comes from The Bible (Luke 17:21), and it’s often read as a spiritual statement about divine presence. But if you strip away the supernatural layer and look at it through an agnostic or atheistic lens, it becomes something surprisingly grounded—and arguably more actionable.\r\n\r\nFrom that perspective, “God” doesn’t have to mean a literal external being. It can be understood as a symbolic placeholder for the highest human capacities: awareness, moral reasoning, imagination, restraint, compassion, and the ability to create meaning.\r\n\r\nSo “the kingdom of God is within you” becomes less about a heavenly realm and more about an internal state.\r\n\r\nNot a place—\r\na condition.\r\n\r\nIt suggests that what religions describe as “divine order” might actually be a psychological and behavioral alignment inside the human mind.', '2026-05-09 12:01:54', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `shorts`
--
ALTER TABLE `shorts`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `shorts`
--
ALTER TABLE `shorts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
