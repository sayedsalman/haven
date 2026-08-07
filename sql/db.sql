
-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 07, 2026 at 04:07 PM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 8.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rfnhscco_mind`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_analysis`
--

CREATE TABLE `ai_analysis` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `emotion` varchar(50) DEFAULT NULL,
  `sentiment` decimal(3,2) DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `ai_reply` text DEFAULT NULL,
  `is_volunteer_notified` tinyint(1) DEFAULT 0,
  `volunteer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `moderation_decision` enum('approve','flag','block','escalate') DEFAULT 'approve'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_analysis`
--

INSERT INTO `ai_analysis` (`id`, `post_id`, `emotion`, `sentiment`, `risk_score`, `category`, `ai_reply`, `is_volunteer_notified`, `volunteer_id`, `created_at`, `user_id`, `moderation_decision`) VALUES
(19, 19, 'neutral', 0.50, 20, 'other', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 0, NULL, '2026-08-03 20:23:09', 2, 'approve'),
(20, 20, 'angry', 0.50, 20, 'emotional_distress_mild', '{\"reply\":\"Rough days at university can feel so heavy, especially when it seems like nothing is going your way. I want you to know that feeling discouraged after a day like this is completely understandable \\u2014 it doesn\'t mean you\'re failing. Choosing to rest tonight and try again tomorrow is actually a really wise, self-compassionate decision. Maybe give yourself something gentle this evening: a few slow breaths, a warm shower, some music, or an early night. Tomorrow is a fresh start, and one hard day doesn\'t define your semester or your ability. If the discouraged feeling lingers or grows heavier, please reach out \\u2014 our community volunteers are always available to listen, no pressure and no judgment. You don\'t have to carry this alone. Be kind to yourself', 0, NULL, '2026-08-03 20:27:12', 2, 'approve'),
(21, 21, 'happy', 0.50, 20, 'other', '{\n  \"reply\": \"Thank you for sharing something so personal — that takes real courage. Feeling low for weeks and losing interest in the things that once brought you joy is exhausting, and it makes sense that your motivation feels drained. Please know you\'re not broken or lazy; you\'re carrying something heavy. Try starting small: one short walk outside, a glass of water, a message to someone you trust. Tiny wins can slowly rebuild momentum. Gentle routines around sleep and meals help too. If this heaviness continues or deepens, talking with a doctor or counsellor can make a real difference — you deserve that support. Our community volunteers are always here and happy to listen whenever you\'d like to talk, day or night. You don\'t have to carry this alone. 💙\",\n  \"emotion', 0, NULL, '2026-08-03 20:49:16', 2, 'approve'),
(22, 22, 'neutral', 0.50, 20, 'other', '{\"reply\":\"Thank you for trusting me with something this heavy. What you\'re carrying sounds unbearably painful, and the exhaustion in your words is real — I\'m glad you reached out instead of staying silent. I care about your safety right now. Please connect with a crisis line straight away: in the US you can call or text 988 (', 0, NULL, '2026-08-03 20:53:21', 2, 'approve'),
(23, 23, 'neutral', 0.50, 20, 'other', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 0, NULL, '2026-08-03 21:04:21', 2, 'approve'),
(24, 24, 'neutral', 0.50, 20, 'other', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 0, NULL, '2026-08-03 21:06:23', 2, 'approve');

-- --------------------------------------------------------

--
-- Table structure for table `ai_logs`
--

CREATE TABLE `ai_logs` (
  `id` int(11) NOT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `prompt_hash` varchar(64) DEFAULT NULL,
  `tokens` int(11) DEFAULT NULL,
  `latency` float DEFAULT NULL,
  `status` enum('success','error') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `model` varchar(50) DEFAULT NULL,
  `cost` decimal(10,6) DEFAULT 0.000000
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `ai_logs`
--

INSERT INTO `ai_logs` (`id`, `provider`, `prompt_hash`, `tokens`, `latency`, `status`, `created_at`, `model`, `cost`) VALUES
(1, 'gemini', NULL, 0, 0, 'success', '2026-08-04 00:22:02', 'gemini-2.0-flash', 0.000000),
(2, 'gemini', NULL, 0, 0, 'success', '2026-08-04 00:23:02', 'gemini-2.0-flash', 0.000000),
(3, 'gemini', NULL, 0, 0, 'success', '2026-08-04 00:27:02', 'gemini-2.0-flash', 0.000000),
(4, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:01:19', 'deepseek/deepseek-v4-flash-0731', 0.000000),
(5, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:06:09', 'deepseek/deepseek-v4-flash-0731', 0.000000),
(6, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:12:15', 'deepseek/deepseek-v4-flash-0731', 0.000000),
(7, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:18:16', 'deepseek/deepseek-v4-flash-0731', 0.000000),
(8, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:23:09', 'deepseek/deepseek-v4-flash-0731', 0.000000),
(9, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:27:12', 'anthropic/claude-opus-5', 0.000000),
(10, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:49:16', 'anthropic/claude-opus-5', 0.000000),
(11, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 20:53:21', 'anthropic/claude-opus-5', 0.000000),
(12, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 21:04:21', 'anthropic/claude-opus-5', 0.000000),
(13, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 21:06:23', 'anthropic/claude-opus-5', 0.000000);

-- --------------------------------------------------------

--
-- Table structure for table `ai_recommendations`
--

CREATE TABLE `ai_recommendations` (
  `id` int(11) NOT NULL,
  `reply_id` int(11) NOT NULL,
  `resource_type` enum('article','video','meditation','exercise','podcast') DEFAULT 'article',
  `resource_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_sessions`
--

CREATE TABLE `ai_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `tokens` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anonymous_identities`
--

CREATE TABLE `anonymous_identities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `avatar_color` varchar(7) DEFAULT '#5B8DEF',
  `rotation_date` timestamp NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `author` varchar(100) DEFAULT 'Wellness Team',
  `thumbnail` varchar(255) DEFAULT NULL,
  `reading_time` int(11) DEFAULT 5,
  `author_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `bookmarks_count` int(11) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  `published_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`id`, `title`, `slug`, `excerpt`, `content`, `category`, `author`, `thumbnail`, `reading_time`, `author_id`, `image_url`, `video_url`, `views`, `likes`, `bookmarks_count`, `is_published`, `published_at`, `created_at`, `updated_at`) VALUES
(1, '5 Simple Breathing Techniques Before an Exam', 'breathing-techniques-exam', 'Calm your nerves and focus with these 5 simple breathing techniques.', '<p>Exams can be stressful, but your breath is always with you. Here are 5 techniques to try:</p><h2>1. 4-7-8 Breathing</h2><p>Inhale for 4 seconds, hold for 7, exhale for 8. Repeat 4 times.</p><h2>2. Box Breathing</h2><p>Inhale 4, hold 4, exhale 4, hold 4.</p>', 'Students', 'Dr. Sarah Chen', NULL, 6, NULL, NULL, NULL, 0, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-08-06 19:53:12'),
(2, 'How to Sleep Better for Better Mental Health', 'sleep-wellness-guide', 'Improve your sleep quality with these evidence-based habits.', '<p>Sleep is essential for mental wellness. Here\'s how to improve yours:</p><h2>Establish a Routine</h2><p>Go to bed and wake up at the same time every day.</p><h2>Create a Restful Environment</h2><p>Keep your bedroom cool, dark, and quiet.</p>', 'Sleep', 'Dr. Michael Lee', NULL, 8, NULL, NULL, NULL, 0, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-08-06 19:53:12'),
(3, 'Understanding Anxiety in Young Adults', 'anxiety-young-adults', 'A compassionate guide to understanding and managing anxiety.', '<p>Anxiety is one of the most common mental health challenges.</p><h2>Recognize the Signs</h2><p>Racing thoughts, difficulty sleeping, and constant worry.</p><h2>Practical Coping Strategies</h2><p>Grounding techniques, journaling, and professional support.</p>', 'Anxiety', 'Dr. Emily Wong', NULL, 7, NULL, NULL, NULL, 3, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-08-06 19:54:40'),
(4, 'Managing Exam Stress: A Student\'s Guide', 'exam-stress-management', 'Practical strategies to reduce exam stress and perform at your best.', '<p>Exam season doesn\'t have to be overwhelming.</p><h2>Create a Study Plan</h2><p>Break your revision into manageable chunks.</p><h2>Take Regular Breaks</h2><p>Study for 45 minutes, rest for 15.</p>', 'Students', 'Dr. James Park', NULL, 10, NULL, NULL, NULL, 0, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-08-06 19:53:12');

-- --------------------------------------------------------

--
-- Table structure for table `article_bookmarks`
--

CREATE TABLE `article_bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_comments`
--

CREATE TABLE `article_comments` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_progress`
--

CREATE TABLE `article_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `article_progress`
--

INSERT INTO `article_progress` (`id`, `user_id`, `article_id`, `progress`, `updated_at`) VALUES
(1, 11, 3, 85, '2026-08-06 19:54:47');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 10,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `type` enum('post','article') DEFAULT 'post',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_ai_analysis`
--

CREATE TABLE `chat_ai_analysis` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `emotion` varchar(50) DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0,
  `summary` text DEFAULT NULL,
  `suggested_resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`suggested_resources`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `chat_ai_analysis`
--

INSERT INTO `chat_ai_analysis` (`id`, `session_id`, `emotion`, `risk_score`, `summary`, `suggested_resources`, `created_at`) VALUES
(1, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 02:57:55'),
(2, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 02:58:07'),
(3, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 02:58:18'),
(4, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 02:58:45'),
(5, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 03:06:21'),
(6, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 03:09:58'),
(7, 1, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 03:10:50'),
(8, 2, 'neutral', 70, 'Prepare a study plan and practice past papers.', '[3]', '2026-08-04 03:15:44'),
(9, 2, 'neutral', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 03:16:30'),
(10, 2, 'unclear', 70, 'Take a short break, Stay hydrated', '[]', '2026-08-04 03:17:36'),
(11, 1, 'neutral', 0, '', '[]', '2026-08-04 03:28:30'),
(12, 1, 'neutral', 0, '', '[]', '2026-08-04 03:28:44'),
(13, 1, 'neutral', 0, '', '[]', '2026-08-04 03:29:01'),
(14, 1, 'neutral', 0, '', '[]', '2026-08-04 03:29:13'),
(15, 1, 'neutral', 0, '', '[]', '2026-08-04 03:30:02'),
(16, 1, 'neutral', 0, '', '[]', '2026-08-04 03:33:12'),
(17, 1, 'neutral', 0, '', '[]', '2026-08-04 03:34:52'),
(18, 1, 'neutral', 0, '', '[]', '2026-08-05 18:26:13'),
(19, 1, 'neutral', 0, '', '[]', '2026-08-05 18:28:05'),
(20, 1, 'neutral', 0, '', '[]', '2026-08-05 18:31:00'),
(21, 1, 'neutral', 0, '', '[]', '2026-08-05 18:32:52'),
(22, 1, 'neutral', 0, '', '[]', '2026-08-05 18:33:26'),
(23, 1, 'neutral', 0, '', '[]', '2026-08-05 18:34:46'),
(24, 1, 'neutral', 0, '', '[]', '2026-08-05 18:36:29'),
(25, 3, 'neutral', 0, '', '[]', '2026-08-05 18:36:53'),
(26, 3, 'neutral', 0, '', '[]', '2026-08-05 18:38:07'),
(27, 3, 'neutral', 0, '', '[]', '2026-08-05 18:38:17'),
(28, 4, 'neutral', 0, '', '[]', '2026-08-05 18:40:14'),
(29, 4, 'warm', 5, 'Remember, you\'re not alone. There are people and resources available to support you., Take things one step at a time. Small, consistent actions can make a big difference., Don\'t hesitate to reach out to our community volunteers if you need someone to talk to.', '[1,2,3]', '2026-08-05 18:44:33'),
(30, 4, 'empathetic', 5, 'Be compassionate with yourself, Reflect on what you can learn from this experience, Reach out to supportive friends or family', '[1,2,3]', '2026-08-05 18:44:53'),
(31, 5, 'Supportive', 5, 'Practice self-care activities that make you feel good, Reach out to trusted friends or family for social connection, Consider trying relaxation techniques like deep breathing or meditation', '[1,2,3]', '2026-08-06 19:03:35');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('user','ai','volunteer') NOT NULL,
  `message` text DEFAULT NULL,
  `message_type` enum('text','image','audio','file') DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `session_id`, `sender_id`, `sender_type`, `message`, `message_type`, `created_at`) VALUES
(1, 1, 2, 'user', 'I\'m feeling okay', 'text', '2026-08-04 02:57:48'),
(2, 1, 0, 'ai', 'Thanks for checking in and sharing where you\'re at — \"okay\" is a perfectly valid place to be, and it\'s great that you took a moment to notice how you\'re feeling. Sometimes \"okay\" is steady and calm, and sometimes it\'s covering a little more underneath. Either way, you\'re welcome here. If you\'d like, you could take a few minutes today to do something small that feels good — a short walk, a favourite song, or messaging someone you like talking to. And if anything shifts or you\'d just like to talk it through, our friendly volunteers are always available to listen, no pressure at all. How has your week been going overall?', 'text', '2026-08-04 02:57:55'),
(3, 1, 2, 'user', 'hi', 'text', '2026-08-04 02:58:01'),
(4, 1, 0, 'ai', 'Hi there, I\'m really glad you reached out. Sometimes just saying \"hi\" is the first step, and that takes courage. I\'m here to listen whenever you\'re ready to share what\'s on your mind — whether it\'s something big, something small, or nothing in particular. How has your day been going so far? If you\'d like, you could tell me a little about how you\'re feeling right now. There\'s no pressure and no rush. And if you\'d prefer to talk with a real person, our caring volunteers are available to chat with you anytime. You\'re not alone in this.', 'text', '2026-08-04 02:58:07'),
(5, 1, 2, 'user', 'hi', 'text', '2026-08-04 02:58:12'),
(6, 1, 0, 'ai', 'Hi there, I\'m really glad you reached out. Sometimes just saying \"hi\" is the first step, and that takes courage. I\'m here to listen whenever you\'re ready — there\'s no rush and no pressure to share more than you\'d like. If something has been weighing on your mind lately, feel free to tell me about it in your own words. Or if you just wanted to say hello, that\'s completely okay too. In the meantime, a few small things can help ground the day: a glass of water, a few slow breaths, or a short walk outside. Please know our caring volunteers are also available if you\'d like to talk with a real person. How are you feeling right now?', 'text', '2026-08-04 02:58:18'),
(7, 1, 2, 'user', 'hi', 'text', '2026-08-04 02:58:38'),
(8, 1, 0, 'ai', 'Hi there — I\'m really glad you reached out. Sometimes just saying \"hi\" takes a bit of courage, and I want you to know this is a safe space. I\'d love to hear whatever is on your mind, whether it\'s something heavy you\'re carrying or just a passing thought. There\'s no pressure to explain everything at once; you can share as little or as much as feels comfortable. In the meantime, it can help to take a slow breath, drink some water, and notice one small thing around you that feels okay. If you\'d rather talk with a real person, our caring volunteers are available and happy to listen whenever you\'re ready. So — how are you feeling today?', 'text', '2026-08-04 02:58:45'),
(9, 1, 2, 'user', 'hu', 'text', '2026-08-04 03:06:15'),
(10, 1, 0, 'ai', '{\"reply\": \"Hi there, I\'m really glad you reached out. It looks like your message came through as just \\\"hu\\\" \\u2014 maybe it was sent early, or maybe finding the words feels hard right now. Either way, that\'s completely okay. I\'m here and happy to listen whenever you\'re ready. If it helps, you could start small: how has your day been, or what\'s one thing weighing on your mind? While you gather your thoughts, taking a few slow breaths or a sip of water can help you feel a little more settled. And please know you don\'t have to do this alone \\u2014 our caring', 'text', '2026-08-04 03:06:21'),
(11, 1, 2, 'user', 'how are you?', 'text', '2026-08-04 03:09:51'),
(12, 1, 0, 'ai', 'Hi there, thank you for reaching out — it\'s really nice to hear from you. I\'m doing well, and I\'m here and ready to listen whenever you\'d like to share. How are *you* doing today? If there\'s anything on your mind, big or small, you\'re welcome to talk about it here. Sometimes just naming how we feel can bring a little relief. In the meantime, you might try a slow breath, a glass of water, or a short walk outside to check in with yourself. And please know our caring volunteers are available anytime if you\'d like to talk with a real person. You\'re not alone in this.', 'text', '2026-08-04 03:09:58'),
(13, 1, 2, 'user', 'I\'m fine. Everything is perfectly fine. I just haven\'t slept in three days and honestly nothing matters anymore.', 'text', '2026-08-04 03:10:39'),
(14, 1, 0, 'ai', '{\"reply\":\"Thank you for reaching out — even saying \\\"I\'m fine\\\" takes courage when things feel heavy. Three days without sleep is exhausting, and when you add \\\"nothing matters anymore,\\\" it sounds like you may be carrying much more than fine. You don\'t have to hold that alone.\\n\\nI want to gently ask: are you having any thoughts', 'text', '2026-08-04 03:10:50'),
(15, 2, 10, 'user', 'i failed in exam', 'text', '2026-08-04 03:15:37'),
(16, 2, 0, 'ai', '{\"reply\": \"I\'m really sorry you\'re carrying this right now — failing an exam can feel heavy, especially when you\'re balancing so much at 18 and already teaching others. Please know one result doesn\'t define your ability or your worth. It\'s okay to feel disappointed; give yourself a little space to breathe before jumping into fixing anything. When you\'re ready, it can help to look gently at what happened: was it time, understanding, stress, or exhaustion? Small, steady steps often work better than harsh self-pressure. Talking it through with a teacher, mentor, or someone you trust can lighten the load too. If these feelings stay strong or start affecting your sleep, appetite, or motivation, please reach out to a counsellor or mental health professional for proper support. Our caring volunteers are', 'text', '2026-08-04 03:15:44'),
(17, 2, 10, 'user', 'how to pass in computer networks course?', 'text', '2026-08-04 03:16:23'),
(18, 2, 0, 'ai', 'Thanks for reaching out! Wanting to pass your computer networks course shows real commitment, and that\'s something to feel good about. Networks can feel overwhelming at first, with all the layers, protocols, and terminology, but it becomes much more manageable once you break it into smaller pieces. Try focusing on understanding the OSI/TCP-IP layers deeply, then build outward from there. Past papers and hands-on practice with tools like Cisco Packet Tracer or Wireshark can make abstract ideas click. Also, don\'t hesitate to ask your instructor which topics carry the most weight. If study stress ever starts weighing on you, our volunteers are here and happy to talk things through with you. You\'ve got this, one topic at a time.', 'text', '2026-08-04 03:16:30'),
(19, 2, 10, 'user', 'then', 'text', '2026-08-04 03:17:28'),
(20, 2, 0, 'ai', 'Hi there — thanks for reaching out. I noticed your message came through as just \"then,\" so I\'m not quite sure what you wanted to share. Maybe it got cut off, or maybe you\'re finding it hard to put things into words right now. Either way, I\'m glad you\'re here, and there\'s no rush. Whenever you\'re ready, feel free to tell me what\'s been on your mind — a feeling, a moment from your day, anything at all. In the meantime, it might help to take a slow breath, jot down a few words in a notes app, or step outside for a minute. If you\'d rather talk with a real person, our caring volunteers are available whenever you need them. I\'m listening.', 'text', '2026-08-04 03:17:36'),
(21, 2, 10, 'user', 'how to build a website?', 'text', '2026-08-04 03:24:28'),
(22, 1, 2, 'user', 'how to build a website?', 'text', '2026-08-04 03:25:00'),
(23, 1, 2, 'user', 'how to build a website?', 'text', '2026-08-04 03:28:29'),
(24, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:28:30'),
(25, 1, 2, 'user', 'how to build a website?', 'text', '2026-08-04 03:28:44'),
(26, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:28:44'),
(27, 1, 2, 'user', 'how to build a website?', 'text', '2026-08-04 03:29:01'),
(28, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:29:01'),
(29, 1, 2, 'user', 'how to build a website?', 'text', '2026-08-04 03:29:12'),
(30, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:29:13'),
(31, 1, 2, 'user', 'I\'m fine. Everything is perfectly fine. I just haven\'t slept in three days and honestly nothing matters anymore.', 'text', '2026-08-04 03:30:02'),
(32, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:30:02'),
(33, 1, 2, 'user', 'hi', 'text', '2026-08-04 03:33:05'),
(34, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:33:12'),
(35, 1, 2, 'user', 'hello', 'text', '2026-08-04 03:34:42'),
(36, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-04 03:34:52'),
(37, 1, 2, 'user', 'hi', 'text', '2026-08-05 18:26:04'),
(38, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-05 18:26:13'),
(39, 1, 2, 'user', 'hgth', 'text', '2026-08-05 18:28:04'),
(40, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 'text', '2026-08-05 18:28:05'),
(41, 1, 2, 'user', 'frfg', 'text', '2026-08-05 18:31:00'),
(42, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:31:00'),
(43, 1, 2, 'user', 'hi', 'text', '2026-08-05 18:32:44'),
(44, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:32:52'),
(45, 1, 2, 'user', 'hi', 'text', '2026-08-05 18:33:17'),
(46, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:33:26'),
(47, 1, 2, 'user', 'i am sad', 'text', '2026-08-05 18:34:39'),
(48, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:34:46'),
(49, 1, 2, 'user', 'hi', 'text', '2026-08-05 18:36:22'),
(50, 1, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:36:29'),
(51, 3, 2, 'user', 'hi', 'text', '2026-08-05 18:36:46'),
(52, 3, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:36:53'),
(53, 3, 2, 'user', 'erg', 'text', '2026-08-05 18:37:15'),
(54, 3, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:38:07'),
(55, 3, 2, 'user', 'hi', 'text', '2026-08-05 18:38:10'),
(56, 3, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:38:17'),
(57, 4, 2, 'user', 'hi', 'text', '2026-08-05 18:40:06'),
(58, 4, 0, 'ai', 'Thank you for sharing. You are not alone. A volunteer will reach out soon. 💚', 'text', '2026-08-05 18:40:14'),
(59, 4, 2, 'user', 'hi', 'text', '2026-08-05 18:44:25'),
(60, 4, 0, 'ai', 'Hello there! I\'m MindGuide, an AI assistant here to offer support and guidance. How can I help you today?', 'text', '2026-08-05 18:44:33'),
(61, 4, 2, 'user', 'i failed in exam', 'text', '2026-08-05 18:44:50'),
(62, 4, 0, 'ai', 'I\'m sorry to hear you\'re feeling down about your exam results. Exam failures can be really tough, but please don\'t be too hard on yourself. These things happen, and it doesn\'t make you any less capable. I encourage you to be kind to yourself during this time. There are always opportunities to learn and grow from setbacks. If you need further support, our community volunteers are here to listen without judgment.', 'text', '2026-08-05 18:44:53'),
(63, 5, 11, 'user', 'I\'m feeling okay', 'text', '2026-08-06 19:03:32'),
(64, 5, 0, 'ai', 'I\'m glad to hear you\'re feeling okay. Remember, it\'s normal to have ups and downs. If you ever need extra support, our volunteers are here to listen and provide guidance. Let me know if there\'s anything I can do to help brighten your day.', 'text', '2026-08-06 19:03:35');

-- --------------------------------------------------------

--
-- Table structure for table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `current_topic` varchar(100) DEFAULT NULL,
  `is_saved` tinyint(1) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `chat_sessions`
--

INSERT INTO `chat_sessions` (`id`, `user_id`, `status`, `current_topic`, `is_saved`, `started_at`, `ended_at`, `last_message_at`) VALUES
(1, 2, 'active', 'neutral', 0, '2026-08-04 02:57:37', NULL, '2026-08-05 18:36:29'),
(2, 10, 'active', 'unclear', 0, '2026-08-04 03:15:27', NULL, '2026-08-04 03:17:36'),
(3, 2, 'active', 'neutral', 0, '2026-08-05 18:36:42', NULL, '2026-08-05 18:38:17'),
(4, 2, 'active', 'empathetic', 0, '2026-08-05 18:40:03', NULL, '2026-08-05 18:44:53'),
(5, 11, 'active', 'Supportive', 0, '2026-08-06 19:03:30', NULL, '2026-08-06 19:03:35');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_anonymous` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `parent_id`, `content`, `is_anonymous`, `created_at`) VALUES
(21, 19, 3, NULL, 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 1, '2026-08-03 20:23:09'),
(22, 20, 3, NULL, '{\"reply\":\"Rough days at university can feel so heavy, especially when it seems like nothing is going your way. I want you to know that feeling discouraged after a day like this is completely understandable \\u2014 it doesn\'t mean you\'re failing. Choosing to rest tonight and try again tomorrow is actually a really wise, self-compassionate decision. Maybe give yourself something gentle this evening: a few slow breaths, a warm shower, some music, or an early night. Tomorrow is a fresh start, and one hard day doesn\'t define your semester or your ability. If the discouraged feeling lingers or grows heavier, please reach out \\u2014 our community volunteers are always available to listen, no pressure and no judgment. You don\'t have to carry this alone. Be kind to yourself', 1, '2026-08-03 20:27:12'),
(23, 21, 3, NULL, '{\n  \"reply\": \"Thank you for sharing something so personal — that takes real courage. Feeling low for weeks and losing interest in the things that once brought you joy is exhausting, and it makes sense that your motivation feels drained. Please know you\'re not broken or lazy; you\'re carrying something heavy. Try starting small: one short walk outside, a glass of water, a message to someone you trust. Tiny wins can slowly rebuild momentum. Gentle routines around sleep and meals help too. If this heaviness continues or deepens, talking with a doctor or counsellor can make a real difference — you deserve that support. Our community volunteers are always here and happy to listen whenever you\'d like to talk, day or night. You don\'t have to carry this alone. 💙\",\n  \"emotion', 1, '2026-08-03 20:49:16'),
(24, 22, 3, NULL, '{\"reply\":\"Thank you for trusting me with something this heavy. What you\'re carrying sounds unbearably painful, and the exhaustion in your words is real — I\'m glad you reached out instead of staying silent. I care about your safety right now. Please connect with a crisis line straight away: in the US you can call or text 988 (', 1, '2026-08-03 20:53:21'),
(25, 23, 3, NULL, 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 1, '2026-08-03 21:04:21'),
(26, 24, 3, NULL, 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 1, '2026-08-03 21:06:23'),
(28, 24, 2, NULL, '�9�6 Proud of you', 1, '2026-08-05 19:52:00'),
(29, 24, 2, NULL, '�9�6 Proud of you', 1, '2026-08-05 19:52:00'),
(30, 24, 2, NULL, '�9�6 Proud of you', 1, '2026-08-05 19:52:00'),
(31, 24, 2, NULL, '�9�6 Proud of you', 1, '2026-08-05 19:52:01'),
(32, 24, 2, NULL, '�9�6 Proud of you', 1, '2026-08-05 19:52:01'),
(33, 24, 2, NULL, '�9�4 Keep going', 1, '2026-08-05 19:52:11'),
(34, 24, 2, NULL, '�7�8�1�5 Stay strong!', 1, '2026-08-05 19:52:13'),
(35, 22, 2, NULL, 'hi', 1, '2026-08-05 20:11:19');

-- --------------------------------------------------------

--
-- Table structure for table `comment_ai_analysis`
--

CREATE TABLE `comment_ai_analysis` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `emotion` varchar(50) DEFAULT NULL,
  `toxicity` float DEFAULT 0,
  `supportiveness` float DEFAULT 0,
  `risk` varchar(20) DEFAULT NULL,
  `confidence` float DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comment_reactions`
--

CREATE TABLE `comment_reactions` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_messages`
--

CREATE TABLE `consultation_messages` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_requests`
--

CREATE TABLE `consultation_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `volunteer_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('queued','assigned','active','closed') DEFAULT 'queued',
  `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `feedback_rating` int(11) DEFAULT NULL,
  `feedback_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultation_requests`
--

INSERT INTO `consultation_requests` (`id`, `user_id`, `volunteer_id`, `post_id`, `message`, `status`, `priority`, `created_at`, `assigned_at`, `started_at`, `closed_at`, `feedback_rating`, `feedback_comment`) VALUES
(4, 2, NULL, NULL, 'AI flagged high risk: I\'ve stopped talking to everyone because I don\'t think anyone can help me anymore. Every day feels h', 'queued', 'medium', '2026-08-03 20:12:15', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `followers`
--

CREATE TABLE `followers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `job_class` varchar(255) NOT NULL,
  `payload` text DEFAULT NULL,
  `available_at` timestamp NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `failed` tinyint(1) DEFAULT 0,
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journals`
--

CREATE TABLE `journals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entry` text NOT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `content` mediumtext NOT NULL,
  `mood_emoji` varchar(10) DEFAULT NULL,
  `weather` varchar(30) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `gratitude_note` varchar(255) DEFAULT NULL,
  `daily_goal` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mentions`
--

CREATE TABLE `mentions` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `mentioned_user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moderation_logs`
--

CREATE TABLE `moderation_logs` (
  `id` int(11) NOT NULL,
  `moderator_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) DEFAULT 'post',
  `target_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mood_entries`
--

CREATE TABLE `mood_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emotion` varchar(30) NOT NULL,
  `energy` int(11) DEFAULT NULL CHECK (`energy` between 1 and 10),
  `stress` int(11) DEFAULT NULL CHECK (`stress` between 1 and 10),
  `sleep` int(11) DEFAULT NULL CHECK (`sleep` between 1 and 10),
  `exercise` int(11) DEFAULT NULL CHECK (`exercise` between 1 and 10),
  `medication` tinyint(1) DEFAULT 0,
  `note` text DEFAULT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mood_logs`
--

CREATE TABLE `mood_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mood_value` varchar(10) DEFAULT NULL,
  `energy` int(11) DEFAULT 5,
  `stress` int(11) DEFAULT 5,
  `sleep` int(11) DEFAULT 5,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mood_logs`
--

INSERT INTO `mood_logs` (`id`, `user_id`, `mood_value`, `energy`, `stress`, `sleep`, `log_date`, `created_at`) VALUES
(1, 2, 'happy', 5, 5, 5, '2026-07-31', '2026-07-30 22:53:06'),
(29, 1, 'happy', 5, 5, 5, '2026-07-31', '2026-07-31 19:05:57'),
(30, 2, 'okay', 5, 5, 5, '2026-08-03', '2026-08-03 10:35:11'),
(31, 9, 'okay', 5, 5, 5, '2026-08-03', '2026-08-03 17:36:05'),
(33, 2, 'okay', 5, 5, 5, '2026-08-04', '2026-08-04 00:26:00'),
(34, 10, 'stressed', 5, 5, 5, '2026-08-04', '2026-08-04 03:15:02'),
(35, 2, 'okay', 5, 5, 5, '2026-08-05', '2026-08-05 18:25:21'),
(36, 11, 'happy', 5, 5, 5, '2026-08-06', '2026-08-06 19:03:57');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `type`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"Relationship\"', 'post.php?id=12', 1, '2026-08-03 18:23:02'),
(2, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"Tired of life\"', 'post.php?id=13', 1, '2026-08-03 18:27:02'),
(3, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"hopeless\"', 'post.php?id=15', 1, '2026-08-03 20:01:19'),
(4, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"suck\"', 'post.php?id=16', 1, '2026-08-03 20:06:09'),
(5, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"tires\"', 'post.php?id=17', 1, '2026-08-03 20:12:15'),
(6, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"burden\"', 'post.php?id=18', 1, '2026-08-03 20:18:16'),
(7, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"need\"', 'post.php?id=19', 1, '2026-08-03 20:23:09'),
(8, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"x\"', 'post.php?id=20', 1, '2026-08-03 20:27:12'),
(9, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"down\"', 'post.php?id=21', 1, '2026-08-03 20:49:16'),
(10, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"suicide\"', 'post.php?id=22', 1, '2026-08-03 20:53:21'),
(11, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"dissaoear\"', 'post.php?id=23', 1, '2026-08-03 21:04:21'),
(12, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"dg\"', 'post.php?id=24', 1, '2026-08-03 21:06:23');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `type` enum('email','phone','reset_password') NOT NULL,
  `target` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 2, '2f57256754e2c900b1e5ad283033251b544c064d9751d1ab2684fd06dca8a977', '2026-07-31 17:35:19', '2026-07-31 16:35:19');

-- --------------------------------------------------------

--
-- Table structure for table `polls`
--

CREATE TABLE `polls` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `poll_votes`
--

CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL,
  `poll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `mood` varchar(20) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `is_anonymous` tinyint(1) DEFAULT 1,
  `location` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `status` enum('pending_ai','published','flagged','deleted') DEFAULT 'pending_ai',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ai_analysis_id` int(11) DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `reaction_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `category`, `mood`, `title`, `content`, `is_anonymous`, `location`, `tags`, `status`, `created_at`, `updated_at`, `ai_analysis_id`, `risk_score`, `comment_count`, `reaction_count`) VALUES
(19, 2, '', 'sad', 'need', 'I\'ve been feeling really down for the past few weeks. I don\'t enjoy the things I used to, and I feel like I\'m slowly losing motivation.', 1, '', '', 'published', '2026-08-04 02:22:54', '2026-08-04 02:23:09', 19, 20, 1, 0),
(20, 2, '', 'stressed', 'x', 'I\'ve had a rough day at university. Nothing seems to be going right, and I\'m feeling pretty discouraged. I think I\'ll take a break tonight and try again tomorrow.', 1, '', '', 'published', '2026-08-04 02:26:33', '2026-08-04 02:27:12', 20, 20, 1, 0),
(21, 2, '', 'stressed', 'down', 'I\'ve been feeling really down for the past few weeks. I don\'t enjoy the things I used to, and I feel like I\'m slowly losing motivation.', 1, '', '', 'published', '2026-08-04 02:48:55', '2026-08-04 02:49:16', 21, 20, 1, 0),
(22, 2, '', 'sad', 'suicide', 'I don\'t want to be here anymore. I\'ve been thinking a lot about ending everything because I can\'t see any future for myself. I\'m exhausted, and I don\'t think I can keep going.', 1, '', '', 'published', '2026-08-04 02:52:34', '2026-08-04 02:53:21', 22, 20, 1, 0),
(23, 2, '', 'sad', 'dissaoear', 'I honestly don\'t see any reason to keep going anymore. Everyone would probably be happier if I just disappeared.', 1, '', '', 'published', '2026-08-04 03:03:27', '2026-08-04 03:04:21', 23, 20, 1, 0),
(24, 2, '', 'sad', 'dg', 'I honestly don\'t see any reason to keep going anymore. Everyone would probably be happier if I just disappeared.', 1, '', '', 'published', '2026-08-04 03:05:35', '2026-08-05 20:38:53', 24, 20, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `post_media`
--

CREATE TABLE `post_media` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `media_type` enum('image','video','audio') DEFAULT 'image',
  `media_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_tags`
--

CREATE TABLE `post_tags` (
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `user_id` int(11) NOT NULL,
  `anonymous_name` varchar(50) NOT NULL,
  `avatar_color` varchar(7) DEFAULT '#5B8DEF',
  `mood_energy` int(11) DEFAULT 5,
  `mood_stress` int(11) DEFAULT 5,
  `mood_sleep` int(11) DEFAULT 5,
  `cover_url` varchar(255) DEFAULT NULL,
  `privacy` enum('public','friends','private') DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`user_id`, `anonymous_name`, `avatar_color`, `mood_energy`, `mood_stress`, `mood_sleep`, `cover_url`, `privacy`) VALUES
(2, 'anos', '#5B8DEF', 5, 5, 5, NULL, 'public'),
(3, 'MindGuide AI', '#5B8DEF', 5, 5, 5, NULL, 'public'),
(9, 'Peaceful Owl', '#FBBF24', 5, 5, 5, NULL, 'public'),
(10, 'Kind Moon', '#5B8DEF', 5, 5, 5, NULL, 'public'),
(11, 'Moderator', '#5B8DEF', 5, 5, 5, NULL, 'public');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `action` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reactions`
--

CREATE TABLE `reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` enum('like','support','empathy','helpful') DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `emoji` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reactions`
--

INSERT INTO `reactions` (`id`, `post_id`, `user_id`, `reaction_type`, `created_at`, `emoji`) VALUES
(11, 24, 2, 'like', '2026-08-05 20:17:25', 'undefined');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `moderator_id` int(11) DEFAULT NULL,
  `action_taken` varchar(50) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `target_id` int(11) NOT NULL DEFAULT 0,
  `target_type` enum('post','comment','user') DEFAULT 'post'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `author` varchar(100) DEFAULT 'Anonymous',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','volunteer','moderator','admin','ai') DEFAULT 'user',
  `anonymous_name` varchar(50) DEFAULT NULL,
  `avatar_id` int(11) DEFAULT 1,
  `avatar_type` varchar(20) DEFAULT 'color',
  `bio` text DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `language` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `occupation` varchar(100) DEFAULT NULL,
  `education` varchar(50) DEFAULT NULL,
  `avatar_color` varchar(7) DEFAULT '#5B8DEF',
  `avatar_icon` varchar(20) DEFAULT 'circle',
  `reminder_time` varchar(10) DEFAULT '20:00',
  `email_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `username`, `password_hash`, `role`, `anonymous_name`, `avatar_id`, `avatar_type`, `bio`, `country`, `city`, `language`, `birth_date`, `gender`, `is_active`, `created_at`, `last_login`, `occupation`, `education`, `avatar_color`, `avatar_icon`, `reminder_time`, `email_verified`) VALUES
(1, 'Sayed Salman', 'admin@gmail.com', '01609506363', 'admin', '$2y$10$TV83asz1U2huLE1DOnwxmei6RrEm5XYNbrm4XF76kvqVX9mxcf80i', 'admin', 'Admin', 1, 'color', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-30 22:33:33', '2026-07-31 19:05:35', NULL, NULL, '#5B8DEF', 'circle', '20:00', 1),
(2, NULL, 'sayedmahbubsalman@gmail.com', NULL, 'sayed', '$2y$10$TV83asz1U2huLE1DOnwxmei6RrEm5XYNbrm4XF76kvqVX9mxcf80i', 'user', 'nightowl', 1, 'color', '', '', NULL, '', NULL, NULL, 1, '2026-07-30 22:43:55', '2026-08-05 19:48:44', NULL, NULL, '#5B8DEF', 'circle', '20:00', 1),
(3, 'MindGuide AI', 'ai@mindsphere.com', NULL, 'mindguide_ai', '', 'ai', NULL, 1, 'color', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-01 16:49:05', '0000-00-00 00:00:00', NULL, NULL, '#5B8DEF', 'circle', '20:00', 0),
(7, 'PMO', 'pmo@haven.com', NULL, 'haven_volunteer', '$2y$10$TV83asz1U2huLE1DOnwxmei6RrEm5XYNbrm4XF76kvqVX9mxcf80i', 'volunteer', NULL, 1, 'color', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-01 10:49:05', '2026-08-03 22:53:01', NULL, NULL, '#5B8DEF', 'circle', '20:00', 0),
(9, NULL, 'sayedmsalmanchy@gmail.com', '01609506567', 'salman', '$2y$10$L3Y0fej.ZZdP.sQGlND0JunxDH5JMulX.AIGg4L8iWFVSN4.Emqz.', 'user', 'Peaceful Owl', 1, 'color', '', 'Bangladesh', NULL, 'en', '2004-08-13', 'male', 1, '2026-08-03 17:35:49', '0000-00-00 00:00:00', 'Student', 'University', '#FBBF24', 'star', '20:00', 1),
(10, NULL, 'hlwman449@gmail.com', '+880 1711-223344', 'niloy', '$2y$10$LaojKxLvNDx977r4Yi4DEu5UiK0mR7apSGzSpglbI9gTilEuvLTqa', 'user', 'Kind Moon', 1, 'color', '', 'Bangladesh', 'Chattogram', 'en', '2008-05-05', 'male', 1, '2026-08-04 03:14:17', '2026-08-04 03:14:45', 'Teacher', 'Graduate', '#5B8DEF', 'heart', '20:00', 1),
(11, NULL, 'moderator@haven.com', NULL, 'moderator', '$2y$10$TV83asz1U2huLE1DOnwxmei6RrEm5XYNbrm4XF76kvqVX9mxcf80i', 'moderator', NULL, 1, 'color', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-06 19:02:04', '2026-08-06 19:03:23', NULL, NULL, '#5B8DEF', 'circle', '20:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_meta`
--

CREATE TABLE `user_meta` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `meta_key` varchar(100) NOT NULL,
  `meta_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_followups`
--

CREATE TABLE `volunteer_followups` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `followup_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_notes`
--

CREATE TABLE `volunteer_notes` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_training`
--

CREATE TABLE `volunteer_training` (
  `id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_analysis`
--
ALTER TABLE `ai_analysis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `volunteer_id` (`volunteer_id`);

--
-- Indexes for table `ai_logs`
--
ALTER TABLE `ai_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reply_id` (`reply_id`);

--
-- Indexes for table `ai_sessions`
--
ALTER TABLE `ai_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `anonymous_identities`
--
ALTER TABLE `anonymous_identities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_display_name` (`display_name`);

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_published` (`is_published`,`published_at`),
  ADD KEY `idx_views` (`views`);

--
-- Indexes for table `article_bookmarks`
--
ALTER TABLE `article_bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_article` (`user_id`,`article_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indexes for table `article_comments`
--
ALTER TABLE `article_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `article_progress`
--
ALTER TABLE `article_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_article_progress` (`user_id`,`article_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bookmark` (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `chat_ai_analysis`
--
ALTER TABLE `chat_ai_analysis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `comment_ai_analysis`
--
ALTER TABLE `comment_ai_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `comment_id` (`comment_id`);

--
-- Indexes for table `comment_reactions`
--
ALTER TABLE `comment_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `comment_id` (`comment_id`,`user_id`);

--
-- Indexes for table `consultation_messages`
--
ALTER TABLE `consultation_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `volunteer_id` (`volunteer_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follower` (`user_id`,`follower_id`),
  ADD KEY `follower_id` (`follower_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friend` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journals`
--
ALTER TABLE `journals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`entry_date`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mentions`
--
ALTER TABLE `mentions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `moderator_id` (`moderator_id`);

--
-- Indexes for table `mood_entries`
--
ALTER TABLE `mood_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`entry_date`);

--
-- Indexes for table `mood_logs`
--
ALTER TABLE `mood_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mood` (`user_id`,`log_date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `polls`
--
ALTER TABLE `polls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `poll_votes`
--
ALTER TABLE `poll_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_poll_user` (`poll_id`,`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_tags`
--
ALTER TABLE `post_tags`
  ADD PRIMARY KEY (`post_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action` (`ip`,`action`,`created_at`);

--
-- Indexes for table `reactions`
--
ALTER TABLE `reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_username` (`username`),
  ADD UNIQUE KEY `idx_email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`);

--
-- Indexes for table `user_meta`
--
ALTER TABLE `user_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `volunteer_followups`
--
ALTER TABLE `volunteer_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `volunteer_id` (`volunteer_id`);

--
-- Indexes for table `volunteer_notes`
--
ALTER TABLE `volunteer_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `volunteer_id` (`volunteer_id`);

--
-- Indexes for table `volunteer_training`
--
ALTER TABLE `volunteer_training`
  ADD PRIMARY KEY (`id`),
  ADD KEY `volunteer_id` (`volunteer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_analysis`
--
ALTER TABLE `ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `ai_logs`
--
ALTER TABLE `ai_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_sessions`
--
ALTER TABLE `ai_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anonymous_identities`
--
ALTER TABLE `anonymous_identities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `article_bookmarks`
--
ALTER TABLE `article_bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `article_comments`
--
ALTER TABLE `article_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `article_progress`
--
ALTER TABLE `article_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_ai_analysis`
--
ALTER TABLE `chat_ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `comment_ai_analysis`
--
ALTER TABLE `comment_ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comment_reactions`
--
ALTER TABLE `comment_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultation_messages`
--
ALTER TABLE `consultation_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `followers`
--
ALTER TABLE `followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `journals`
--
ALTER TABLE `journals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mentions`
--
ALTER TABLE `mentions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mood_entries`
--
ALTER TABLE `mood_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mood_logs`
--
ALTER TABLE `mood_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `polls`
--
ALTER TABLE `polls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poll_votes`
--
ALTER TABLE `poll_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reactions`
--
ALTER TABLE `reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_meta`
--
ALTER TABLE `user_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_followups`
--
ALTER TABLE `volunteer_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_notes`
--
ALTER TABLE `volunteer_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_training`
--
ALTER TABLE `volunteer_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_analysis`
--
ALTER TABLE `ai_analysis`
  ADD CONSTRAINT `ai_analysis_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_analysis_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD CONSTRAINT `ai_recommendations_ibfk_1` FOREIGN KEY (`reply_id`) REFERENCES `ai_analysis` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_sessions`
--
ALTER TABLE `ai_sessions`
  ADD CONSTRAINT `ai_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `anonymous_identities`
--
ALTER TABLE `anonymous_identities`
  ADD CONSTRAINT `anonymous_identities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `article_bookmarks`
--
ALTER TABLE `article_bookmarks`
  ADD CONSTRAINT `article_bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_bookmarks_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `article_comments`
--
ALTER TABLE `article_comments`
  ADD CONSTRAINT `article_comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `article_progress`
--
ALTER TABLE `article_progress`
  ADD CONSTRAINT `article_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_progress_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_ai_analysis`
--
ALTER TABLE `chat_ai_analysis`
  ADD CONSTRAINT `chat_ai_analysis_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD CONSTRAINT `chat_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_messages`
--
ALTER TABLE `consultation_messages`
  ADD CONSTRAINT `consultation_messages_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `consultation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  ADD CONSTRAINT `consultation_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_requests_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultation_requests_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `journals`
--
ALTER TABLE `journals`
  ADD CONSTRAINT `journals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  ADD CONSTRAINT `moderation_logs_ibfk_1` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mood_entries`
--
ALTER TABLE `mood_entries`
  ADD CONSTRAINT `mood_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mood_logs`
--
ALTER TABLE `mood_logs`
  ADD CONSTRAINT `mood_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD CONSTRAINT `otp_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_media`
--
ALTER TABLE `post_media`
  ADD CONSTRAINT `post_media_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_tags`
--
ALTER TABLE `post_tags`
  ADD CONSTRAINT `post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reactions`
--
ALTER TABLE `reactions`
  ADD CONSTRAINT `reactions_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_meta`
--
ALTER TABLE `user_meta`
  ADD CONSTRAINT `fk_user_meta_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_followups`
--
ALTER TABLE `volunteer_followups`
  ADD CONSTRAINT `volunteer_followups_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `consultation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_followups_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_notes`
--
ALTER TABLE `volunteer_notes`
  ADD CONSTRAINT `volunteer_notes_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `consultation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_notes_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_training`
--
ALTER TABLE `volunteer_training`
  ADD CONSTRAINT `volunteer_training_ibfk_1` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
