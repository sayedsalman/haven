-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 16, 2026 at 03:26 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 8.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rfnhscco_pos`
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
(24, 24, 'neutral', 0.50, 20, 'other', 'Thank you for sharing. You are not alone. A volunteer has been notified. 💚', 0, NULL, '2026-08-03 21:06:23', 2, 'approve'),
(25, 25, 'empathetic', 0.50, 80, 'self-harm', 'I\'m so sorry you\'re struggling with these thoughts and feelings. What you\'re describing sounds extremely difficult and frightening. Please know that you\'re not alone and there is help available. I would strongly encourage you to reach out to a mental health professional as soon as possible. In the meantime, try to be gentle with yourself, and know that volunteers are standing by if you need someone to talk to.', 1, NULL, '2026-08-12 15:30:07', 12, 'approve'),
(26, 26, 'empathetic', 0.50, 20, 'general', 'Hello! I\'m here to listen and provide support. How are you feeling today? Remember, you\'re not alone - our community volunteers are available if you need someone to talk to.', 0, NULL, '2026-08-13 13:23:05', 12, 'approve'),
(27, 28, 'empathetic', 0.50, 80, 'suicidal ideation', 'I\'m so sorry you\'re struggling with these difficult thoughts and feelings. Please know that you\'re not alone, and there is help and support available. Your life has value, and the world is better with you in it. I would encourage you to reach out to a mental health professional or call a crisis hotline right away. Volunteers are also standing by if you need someone to talk to. You\'ve got this, and I believe in you.', 1, NULL, '2026-08-13 18:50:29', 12, 'approve'),
(28, 29, 'empathetic', 0.50, 20, 'mental_health', 'I\'m sorry to hear you\'re feeling down and overwhelmed. That can be a tough place to be. Please know that you\'re not alone, and there are ways to find some relief. I\'d suggest trying to take some time for yourself to unwind, even if it\'s just a short break. Some relaxing activities like gentle exercise, journaling, or spending time in nature may help you feel better. If the feelings persist, don\'t hesitate to reach out for additional support. Volunteer counselors are available if you need someone to talk to.', 0, NULL, '2026-08-13 18:51:38', 12, 'approve'),
(29, 30, 'empathetic', 0.50, 50, 'health', 'I\'m sorry to hear you\'re not feeling well. It sounds like you may have a cold or the flu. I suggest resting, staying hydrated, and monitoring your symptoms. If they worsen or persist, please consider speaking to a healthcare professional. Remember, you are not alone. Volunteers are available if you need further support.', 0, NULL, '2026-08-15 08:40:44', 14, 'approve'),
(30, 31, 'empathetic', 0.50, 20, 'general', 'Hello, I\'m MindGuide, your supportive AI assistant. How are you feeling today? I\'m here to listen and provide some suggestions that may help. Remember, volunteers are available if you need additional support.', 0, NULL, '2026-08-25 20:26:00', 2, 'approve'),
(31, 32, 'neutral', 0.50, 20, 'general', 'Hello there! I\'m MindGuide, an AI assistant here to support you. How are you feeling today? I\'m here to listen and provide some suggestions if you\'d like. Remember, our trained volunteers are also available if you need more personalized support.', 0, NULL, '2026-08-25 20:26:05', 2, 'approve'),
(32, 33, 'empathetic', 0.50, 20, 'mental_health', 'I\'m so sorry you\'re feeling low. That can be really difficult to experience. Please know that you\'re not alone, and there are ways to start feeling better. I have some suggestions that may help, and there are also volunteers available if you need extra support.', 0, NULL, '2026-08-25 20:26:49', 2, 'approve'),
(33, 34, 'compassionate', 0.50, 50, 'self-harm', 'I\'m here to listen and support you. Though I can\'t provide a diagnosis, I want you to know that you\'re not alone. A volunteer will reach out soon to offer personalized guidance. In the meantime, try practicing self-care through activities that bring you comfort. Remember, your wellbeing is important, and there are people who want to help.', 0, NULL, '2026-09-04 20:35:58', 12, 'approve'),
(34, 35, 'empathetic', 0.50, 50, 'mental_health', 'I\'m glad you reached out. As an AI, I can offer a listening ear and suggestions, but cannot provide professional mental health support. However, our volunteer team is available to connect with you one-on-one and provide personalized guidance. Please let me know if you would like me to put you in touch with a volunteer.', 0, NULL, '2026-09-04 20:36:03', 12, 'approve'),
(35, 36, 'empathetic', 0.50, 50, 'mental_health', 'Hi there. It\'s great to hear from you. I\'m here to listen and offer support. While I can\'t provide a diagnosis, I\'m happy to discuss what you\'re going through and suggest some practical self-care tips. A volunteer is available if you\'d like to talk more. You\'re not alone in this.', 0, NULL, '2026-09-04 20:36:06', 12, 'approve'),
(36, 37, 'compassionate', 0.50, 50, 'self-harm', 'Thank you for reaching out. I\'m here to listen and offer support. While I cannot provide medical advice, I can suggest speaking to a counselor or trusted loved one about what you\'re going through. Your well-being is important, and there are resources available. A volunteer from our community will be in touch soon to provide more personalized assistance. You are not alone.', 0, NULL, '2026-09-04 20:36:10', 12, 'approve'),
(37, 38, 'empathetic', 0.50, 50, 'mental_health', 'Thank you for reaching out. It\'s great that you\'re taking steps to prioritize your mental wellbeing. While I can\'t provide a diagnosis, I\'m here to offer support and suggest some practical tips. Please know that volunteers are available if you need someone to talk to. I\'m here to listen without judgment and provide a compassionate space.', 0, NULL, '2026-09-04 20:36:14', 12, 'approve'),
(38, 39, 'empathetic', 0.50, 50, 'mental_health', 'I\'m sorry you\'re feeling this way. Please know that you\'re not alone and support is available. A volunteer from our community will reach out to you soon to provide a listening ear and suggest helpful resources. In the meantime, try to practice self-care through activities you enjoy. You\'ve got this.', 0, NULL, '2026-09-04 20:36:18', 12, 'approve'),
(39, 40, 'empathetic', 0.50, 50, 'mental_health', 'I\'m here to listen and support you. While I can\'t provide professional diagnosis or treatment, I can offer a compassionate ear and suggest some resources that may be helpful. A volunteer will reach out to you soon. Please don\'t hesitate to reach out if you need further assistance.', 0, NULL, '2026-09-04 20:36:22', 12, 'approve'),
(40, 41, 'empathetic', 0.50, 50, 'self-harm', 'I\'m here to listen and support you. Your feelings are valid, and there are resources available. A volunteer will reach out soon to provide more personalized guidance. You don\'t have to face this alone.', 0, NULL, '2026-09-04 20:36:25', 12, 'approve'),
(41, 42, 'empathetic', 0.50, 50, 'emotional_distress', 'I\'m sorry you\'re feeling so isolated and disconnected right now. That can be an incredibly painful experience. Please know that you\'re not alone, and there are people and resources available to support you. I would suggest reaching out to a counselor or therapist who can provide personalized guidance. Our volunteers are also here to listen without judgment and offer emotional support.', 0, NULL, '2026-09-05 09:55:32', 13, 'approve'),
(42, 43, 'empathetic', 0.50, 50, 'mental_health', 'I\'m sorry you\'re feeling so disconnected and isolated. That can be a really difficult and lonely experience. Remember that you\'re not alone - many people struggle with feelings of being \'outside\' of things. I encourage you to try reaching out to supportive friends or family, or consider connecting with our community volunteers who are here to listen without judgment.', 0, NULL, '2026-09-05 09:55:36', 13, 'approve'),
(43, 44, 'empathy', 0.50, 50, 'mental_health', 'I\'m sorry you\'re feeling so isolated right now. That \'glass box\' feeling can be really difficult. Please know that you\'re not alone, and there are people who want to support you. I encourage you to reach out to our volunteer community - they\'re here to listen without judgment and offer practical suggestions. You deserve to feel connected and understood.', 0, NULL, '2026-09-05 09:55:41', 13, 'approve'),
(44, 46, 'empathetic', 0.50, 20, 'personal_expression', 'I\'m sorry to hear you\'re feeling tired and done after class. That\'s understandable - school can be draining. Remember to take time for yourself, even in small ways, to recharge. Reach out to supportive friends or loved ones if you need. And don\'t hesitate to connect with our volunteer community if you\'d like someone to talk to.', 0, NULL, '2026-09-05 03:57:46', 14, 'approve'),
(45, 47, 'empathetic', 0.50, 80, 'self-harm', 'I\'m so sorry you\'re struggling. Please know that you matter and your life has value. Although I can\'t provide professional help, there are people who want to support you through this. I\'d suggest reaching out to a trusted loved one, calling a helpline, or connecting with a counselor. You don\'t have to face this alone.', 1, NULL, '2026-09-05 10:01:34', 13, 'approve'),
(46, 48, 'Supportive', 0.50, 20, 'personal', 'I\'m so relieved to hear you\'re safe, Munshi. That must have been a scary experience. Please know that you have my deepest compassion during this time. While I cannot replace professional help, I\'m here to offer emotional support and practical suggestions. Remember, you are not alone - volunteers are available if you need someone to talk to.', 0, NULL, '2026-09-05 04:02:02', 14, 'approve'),
(47, 49, 'empathetic', 0.50, 50, 'self-harm', 'Hello there. It sounds like you\'re feeling okay, but I\'m here if you need someone to talk to. Remember, you\'re not alone, and volunteers are available if you need extra support. Let me know if there\'s anything I can do to help.', 0, NULL, '2026-09-05 06:30:17', 12, 'approve'),
(48, 50, 'sad', 0.14, 20, 'health_concern', 'I\'m sorry you\'re not feeling well. Physical or emotional discomfort can be really tough to navigate alone. I\'m here to listen and support you. Could you share a bit more about what you\'re experiencing? Are you dealing with something physical, emotional, or both? Remember, our trained volunteers are available if you\'d like to talk with someone directly. In the meantime, be gentle with yourself and prioritize rest if you need it.', 0, NULL, '2026-10-03 20:20:31', 2, 'approve'),
(49, 51, 'neutral', 0.60, 20, 'academic_disappointment', 'I\'m really sorry to hear about your exam result—that must feel incredibly disappointing right now. It\'s completely okay to feel upset. One exam doesn\'t define your abilities or potential. Take a moment to breathe and be kind to yourself. When you\'re ready, consider reviewing what challenged you most, maybe seeking study support, or talking to your instructor about areas to improve. Remember, setbacks are learning opportunities. Our community volunteers are here to listen and support you through this difficult time. You\'re not alone in this.', 0, NULL, '2026-10-04 09:15:19', 14, 'approve'),
(50, 52, 'crisis', 0.64, 100, 'self-harm/suicide risk', 'I\'m here for you. Please know that you are valuable. A volunteer has been notified immediately. 💚', 1, NULL, '2026-10-04 09:51:34', 14, 'approve'),
(51, 53, 'sad', 0.12, 20, 'health_concern', 'I\'m sorry you\'re feeling sick right now. That can be really difficult and draining. I\'m here to listen and support you. When you say sick, are you referring to physical illness, emotional overwhelm, or perhaps a mix of both? Understanding what you\'re experiencing can help me offer better support. Remember, you\'re not alone in this—our community volunteers are available to chat if you need someone to talk to. Please take care of yourself by resting, staying hydrated, and reaching out if things feel overwhelming.', 0, NULL, '2026-10-04 09:57:14', 7, 'approve'),
(52, 54, 'neutral', 0.62, 20, 'music_reference', 'Hello! I noticed you\'ve shared \'Ae Ajnabee Song\' - a beautiful, melancholic track. Music can be a wonderful way to process emotions. If you\'re feeling reflective or perhaps a bit down, that\'s completely valid. Sometimes listening to meaningful songs helps us feel less alone. Would you like to talk about what you\'re experiencing right now? Our volunteers are here to listen and support you without judgment.', 0, NULL, '2026-10-24 20:18:55', 16, 'approve'),
(53, 55, 'neutral', 0.35, 20, 'incomplete_text', 'Hello, I\'m here to listen and support you. It seems your message got cut off—I see \'i am si\'. Could you share a bit more about what you\'re experiencing right now? Whether you\'re feeling stuck, overwhelmed, or just need someone to talk to, I\'m here without judgment. Take your time expressing yourself. Our trained volunteers are also available if you\'d like to connect with someone for more personalized support.', 0, NULL, '2026-10-25 09:20:17', 2, 'approve'),
(54, 56, 'neutral', 0.21, 20, 'mental_health_reflection', 'I hear you—nighttime can feel isolating and amplify difficult feelings. Darkness often intensifies anxiety and self-doubt for many people. This is actually quite common. Consider establishing a calming nighttime routine: dim lighting, grounding exercises, or journaling your thoughts. Physical activity earlier in the day may help regulate your mood. If these patterns persist, speaking with a counselor could provide personalized strategies. Our trained volunteers are available anytime to listen without judgment.', 0, NULL, '2026-10-28 22:21:32', 1, 'approve'),
(55, 57, 'neutral', 0.71, 20, 'greeting', 'Hi there! Welcome to our community. I\'m glad you reached out. It\'s wonderful that you\'re here, and I want you to know this is a safe space to share what\'s on your mind—whether you\'re doing okay, need support, or just want to chat. How are you feeling today? Is there anything specific you\'d like to talk about, or would you just like to take things one step at a time? Remember, our volunteers are always available if you\'d like to connect with someone.', 0, NULL, '2026-10-29 11:20:31', 2, 'approve');

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
(13, 'openrouter', NULL, 0, 0, 'success', '2026-08-03 21:06:23', 'anthropic/claude-opus-5', 0.000000),
(14, 'openrouter', NULL, 0, 0, 'success', '2026-08-12 15:30:07', 'anthropic/claude-3-haiku', 0.000000),
(15, 'openrouter', NULL, 0, 0, 'success', '2026-08-13 13:23:05', 'anthropic/claude-3-haiku', 0.000000),
(16, 'openrouter', NULL, 0, 0, 'success', '2026-08-13 18:50:29', 'anthropic/claude-3-haiku', 0.000000),
(17, 'openrouter', NULL, 0, 0, 'success', '2026-08-13 18:51:38', 'anthropic/claude-3-haiku', 0.000000),
(18, 'openrouter', NULL, 0, 0, 'success', '2026-08-15 08:40:44', 'anthropic/claude-3-haiku', 0.000000),
(19, 'openrouter', NULL, 0, 0, 'success', '2026-08-25 20:26:00', 'anthropic/claude-3-haiku', 0.000000),
(20, 'openrouter', NULL, 0, 0, 'success', '2026-08-25 20:26:05', 'anthropic/claude-3-haiku', 0.000000),
(21, 'openrouter', NULL, 0, 0, 'success', '2026-08-25 20:26:49', 'anthropic/claude-3-haiku', 0.000000),
(22, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:35:58', 'anthropic/claude-3-haiku', 0.000000),
(23, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:03', 'anthropic/claude-3-haiku', 0.000000),
(24, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:06', 'anthropic/claude-3-haiku', 0.000000),
(25, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:10', 'anthropic/claude-3-haiku', 0.000000),
(26, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:15', 'anthropic/claude-3-haiku', 0.000000),
(27, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:18', 'anthropic/claude-3-haiku', 0.000000),
(28, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:22', 'anthropic/claude-3-haiku', 0.000000),
(29, 'openrouter', NULL, 0, 0, 'success', '2026-09-04 20:36:25', 'anthropic/claude-3-haiku', 0.000000),
(30, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 09:55:32', 'anthropic/claude-3-haiku', 0.000000),
(31, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 09:55:36', 'anthropic/claude-3-haiku', 0.000000),
(32, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 09:55:41', 'anthropic/claude-3-haiku', 0.000000),
(33, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 03:57:46', 'anthropic/claude-3-haiku', 0.000000),
(34, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 10:01:34', 'anthropic/claude-3-haiku', 0.000000),
(35, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 04:02:02', 'anthropic/claude-3-haiku', 0.000000),
(36, 'openrouter', NULL, 0, 0, 'success', '2026-09-05 06:30:17', 'anthropic/claude-3-haiku', 0.000000),
(37, 'openrouter', NULL, 0, 0, 'success', '2026-10-03 20:20:31', 'anthropic/claude-haiku-4.5', 0.000000),
(38, 'openrouter', NULL, 0, 0, 'success', '2026-10-04 09:15:19', 'anthropic/claude-haiku-4.5', 0.000000),
(39, 'openrouter', NULL, 0, 0, 'success', '2026-10-04 09:51:34', 'anthropic/claude-haiku-4.5', 0.000000),
(40, 'openrouter', NULL, 0, 0, 'success', '2026-10-04 09:57:14', 'anthropic/claude-haiku-4.5', 0.000000),
(41, 'openrouter', NULL, 0, 0, 'success', '2026-10-24 20:18:55', 'anthropic/claude-haiku-4.5', 0.000000),
(42, 'openrouter', NULL, 0, 0, 'success', '2026-10-25 09:20:17', 'anthropic/claude-haiku-4.5', 0.000000),
(43, 'openrouter', NULL, 0, 0, 'success', '2026-10-28 22:21:32', 'anthropic/claude-haiku-4.5', 0.000000),
(44, 'openrouter', NULL, 0, 0, 'success', '2026-10-29 11:20:31', 'anthropic/claude-haiku-4.5', 0.000000);

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

--
-- Dumping data for table `ai_recommendations`
--

INSERT INTO `ai_recommendations` (`id`, `reply_id`, `resource_type`, `resource_id`, `title`, `url`, `created_at`) VALUES
(1, 25, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-12 15:30:07'),
(2, 25, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-12 15:30:07'),
(3, 25, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-12 15:30:07'),
(4, 26, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-13 13:23:05'),
(5, 26, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-13 13:23:05'),
(6, 26, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-08-13 13:23:05'),
(7, 27, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-13 18:50:29'),
(8, 27, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-13 18:50:29'),
(9, 27, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-13 18:50:29'),
(10, 28, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-13 18:51:38'),
(11, 28, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-13 18:51:38'),
(12, 28, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-13 18:51:38'),
(13, 29, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-15 08:40:44'),
(14, 29, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-15 08:40:44'),
(15, 29, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-15 08:40:44'),
(16, 30, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-25 20:26:00'),
(17, 30, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-25 20:26:00'),
(18, 30, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-25 20:26:00'),
(19, 31, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-25 20:26:05'),
(20, 31, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-25 20:26:05'),
(21, 31, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-08-25 20:26:05'),
(22, 32, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-08-25 20:26:49'),
(23, 32, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-08-25 20:26:49'),
(24, 32, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-08-25 20:26:49'),
(25, 33, 'article', 1234, 'Recommended Article', 'article.php?id=1234', '2026-09-04 20:35:58'),
(26, 33, 'article', 5678, 'Recommended Article', 'article.php?id=5678', '2026-09-04 20:35:58'),
(27, 33, 'article', 9012, 'Recommended Article', 'article.php?id=9012', '2026-09-04 20:35:58'),
(28, 34, 'article', 1234, 'Recommended Article', 'article.php?id=1234', '2026-09-04 20:36:03'),
(29, 34, 'article', 5678, 'Recommended Article', 'article.php?id=5678', '2026-09-04 20:36:03'),
(30, 34, 'article', 9012, 'Recommended Article', 'article.php?id=9012', '2026-09-04 20:36:03'),
(31, 35, 'article', 1234, 'Recommended Article', 'article.php?id=1234', '2026-09-04 20:36:06'),
(32, 35, 'article', 5678, 'Recommended Article', 'article.php?id=5678', '2026-09-04 20:36:06'),
(33, 35, 'article', 9012, 'Recommended Article', 'article.php?id=9012', '2026-09-04 20:36:06'),
(34, 36, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-04 20:36:10'),
(35, 36, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-04 20:36:10'),
(36, 36, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-04 20:36:10'),
(37, 37, 'article', 1234, 'Recommended Article', 'article.php?id=1234', '2026-09-04 20:36:15'),
(38, 37, 'article', 5678, 'Recommended Article', 'article.php?id=5678', '2026-09-04 20:36:15'),
(39, 37, 'article', 9012, 'Recommended Article', 'article.php?id=9012', '2026-09-04 20:36:15'),
(40, 38, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-04 20:36:18'),
(41, 38, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-04 20:36:18'),
(42, 38, 'article', 7, 'Recommended Article', 'article.php?id=7', '2026-09-04 20:36:18'),
(43, 39, 'article', 1234, 'Recommended Article', 'article.php?id=1234', '2026-09-04 20:36:22'),
(44, 39, 'article', 5678, 'Recommended Article', 'article.php?id=5678', '2026-09-04 20:36:22'),
(45, 39, 'article', 9012, 'Recommended Article', 'article.php?id=9012', '2026-09-04 20:36:22'),
(46, 40, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-04 20:36:25'),
(47, 40, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-04 20:36:25'),
(48, 40, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-04 20:36:25'),
(49, 41, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 09:55:32'),
(50, 41, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 09:55:32'),
(51, 41, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-05 09:55:32'),
(52, 42, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 09:55:36'),
(53, 42, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-09-05 09:55:36'),
(54, 42, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 09:55:36'),
(55, 43, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 09:55:41'),
(56, 43, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 09:55:41'),
(57, 43, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-05 09:55:41'),
(58, 44, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 03:57:46'),
(59, 44, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 03:57:46'),
(60, 44, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-05 03:57:46'),
(61, 45, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 10:01:34'),
(62, 45, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-09-05 10:01:34'),
(63, 45, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 10:01:34'),
(64, 46, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 04:02:02'),
(65, 46, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-09-05 04:02:02'),
(66, 46, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 04:02:02'),
(67, 47, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-09-05 06:30:17'),
(68, 47, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-09-05 06:30:17'),
(69, 47, 'article', 5, 'Recommended Article', 'article.php?id=5', '2026-09-05 06:30:17'),
(70, 48, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-03 20:20:31'),
(71, 48, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-03 20:20:31'),
(72, 49, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-04 09:15:19'),
(73, 49, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-04 09:15:19'),
(74, 49, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-10-04 09:15:19'),
(75, 51, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-04 09:57:14'),
(76, 51, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-04 09:57:14'),
(77, 52, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-24 20:18:55'),
(78, 52, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-24 20:18:55'),
(79, 53, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-25 09:20:17'),
(80, 53, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-25 09:20:17'),
(81, 54, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-28 22:21:32'),
(82, 54, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-28 22:21:32'),
(83, 54, 'article', 3, 'Recommended Article', 'article.php?id=3', '2026-10-28 22:21:32'),
(84, 55, 'article', 1, 'Recommended Article', 'article.php?id=1', '2026-10-29 11:20:31'),
(85, 55, 'article', 2, 'Recommended Article', 'article.php?id=2', '2026-10-29 11:20:31');

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
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `mode` enum('chamber','online') NOT NULL DEFAULT 'chamber',
  `status` enum('requested','confirmed','declined','cancelled','completed') NOT NULL DEFAULT 'requested',
  `reason` text DEFAULT NULL,
  `doctor_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, '5 Simple Breathing Techniques Before an Exam', 'breathing-techniques-exam', 'Calm your nerves and focus with these 5 simple breathing techniques.', '<p>Exams can be stressful, but your breath is always with you. Here are 5 techniques to try:</p><h2>1. 4-7-8 Breathing</h2><p>Inhale for 4 seconds, hold for 7, exhale for 8. Repeat 4 times.</p><h2>2. Box Breathing</h2><p>Inhale 4, hold 4, exhale 4, hold 4.</p>', 'Students', 'Dr. Sarah Chen', NULL, 6, NULL, NULL, NULL, 10, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-11-01 07:00:17'),
(2, 'How to Sleep Better for Better Mental Health', 'sleep-wellness-guide', 'Improve your sleep quality with these evidence-based habits.', '<p>Sleep is essential for mental wellness. Here\'s how to improve yours:</p><h2>Establish a Routine</h2><p>Go to bed and wake up at the same time every day.</p><h2>Create a Restful Environment</h2><p>Keep your bedroom cool, dark, and quiet.</p>', 'Sleep', 'Dr. Michael Lee', NULL, 8, NULL, NULL, NULL, 4, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-11-01 07:00:19'),
(3, 'Understanding Anxiety in Young Adults', 'anxiety-young-adults', 'A compassionate guide to understanding and managing anxiety.', '<p>Anxiety is one of the most common mental health challenges.</p><h2>Recognize the Signs</h2><p>Racing thoughts, difficulty sleeping, and constant worry.</p><h2>Practical Coping Strategies</h2><p>Grounding techniques, journaling, and professional support.</p>', 'Anxiety', 'Dr. Emily Wong', NULL, 7, NULL, NULL, NULL, 13, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-11-01 06:51:03'),
(4, 'Managing Exam Stress: A Student\'s Guide', 'exam-stress-management', 'Practical strategies to reduce exam stress and perform at your best.', '<p>Exam season doesn\'t have to be overwhelming.</p><h2>Create a Study Plan</h2><p>Break your revision into manageable chunks.</p><h2>Take Regular Breaks</h2><p>Study for 45 minutes, rest for 15.</p>', 'Students', 'Dr. James Park', NULL, 10, NULL, NULL, NULL, 6, 0, 0, 1, '2026-08-06 19:53:12', '2026-08-06 19:53:12', '2026-11-03 06:49:40'),
(5, 'The Art of Feeling Well: Simple Habits for a Healthier, Happier Life', 'the-art-of-feeling-well-simple-habits-for-a-healthier-happier-life-d47f24', 'Wellness is often presented as something complicated. We see carefully planned diets, intense workout routines, expensive supplements, meditation apps, and elaborate morning routines. It can make being healthy feel like another full-time job.', 'Wellness is often presented as something complicated. We see carefully planned diets, intense workout routines, expensive supplements, meditation apps, and elaborate morning routines. It can make being healthy feel like another full-time job.\r\n\r\nBut wellness does not have to be complicated.\r\n\r\nAt its heart, wellness is about taking care of yourself—physically, mentally, emotionally, and socially. It is not about being perfect or following an impossible routine. It is about making small choices every day that help you feel stronger, calmer, more energetic, and more connected to your life.\r\n\r\nThe good news is that many of the most powerful wellness habits are surprisingly simple.\r\n\r\n## 1. Start With Your Sleep\r\n\r\nBefore thinking about complicated health strategies, look at your sleep.\r\n\r\nSleep is when your body gets an opportunity to recover and your brain processes everything you experienced during the day. Poor sleep can leave you tired, unfocused, irritable, and less motivated. Over time, consistently inadequate sleep can also affect overall health.\r\n\r\nFor most adults, aiming for around seven to nine hours of sleep each night is a useful general target.\r\n\r\nBut getting enough sleep is not only about the number of hours. Your routine matters too.\r\n\r\nTry going to bed and waking up around similar times each day. Reduce bright screens shortly before bed, keep your sleeping environment comfortable, and avoid turning your bed into a place where you spend hours scrolling through social media.\r\n\r\nYou do not need a perfect sleep routine.\r\n\r\nEven going to bed 30 minutes earlier can be a meaningful first step.\r\n\r\n## 2. Move Your Body—Without Punishing It\r\n\r\nExercise is often associated with intense workouts and dramatic transformations. But movement does not have to be extreme to be valuable.\r\n\r\nWalking, cycling, stretching, dancing, swimming, playing a sport, or simply taking the stairs can all contribute to an active lifestyle.\r\n\r\nOne of the best ways to make exercise sustainable is to choose something you actually enjoy.\r\n\r\nIf you hate running, you do not have to run.\r\n\r\nIf you enjoy walking while listening to music, walk.\r\n\r\nIf you like playing football with friends, play football.\r\n\r\nThe goal is not to punish your body for what you ate. The goal is to help your body become stronger and more capable.\r\n\r\nEven a short walk can provide a break from sitting, studying, working, or staring at a screen.\r\n\r\nThink of movement as something you give your body—not something you owe it.\r\n\r\n## 3. Eat to Nourish, Not to Restrict\r\n\r\nWellness does not require you to completely eliminate your favorite foods.\r\n\r\nA healthy approach to food is about balance rather than punishment.\r\n\r\nTry to include a variety of foods in your meals: vegetables, fruits, whole grains, protein sources, and healthy fats. Drink enough water and pay attention to how different foods make you feel.\r\n\r\nAt the same time, there is room for foods that are simply enjoyable.\r\n\r\nA piece of cake does not ruin a healthy lifestyle. One unhealthy meal does not erase weeks of good habits.\r\n\r\nThe problem often begins when food becomes a source of guilt.\r\n\r\nInstead of asking, \"Is this food good or bad?\" try asking, \"What does my body need right now?\"\r\n\r\nSometimes the answer might be a nutritious meal.\r\n\r\nSometimes it might be a glass of water.\r\n\r\nAnd sometimes it might simply be something you enjoy.\r\n\r\nA healthy relationship with food is part of wellness too.\r\n\r\n## 4. Give Your Mind Some Space\r\n\r\nYour physical health matters, but your mind needs care as well.\r\n\r\nModern life gives us very few opportunities to be completely disconnected. Notifications, messages, news, videos, advertisements, and social media constantly compete for our attention.\r\n\r\nThis can leave the brain feeling like it is always \"on.\"\r\n\r\nCreating small periods of quiet can help.\r\n\r\nSpend ten minutes without your phone. Sit outside. Take a slow walk. Listen to music. Write in a journal. Pray or meditate if that is meaningful to you. Simply sit and breathe.\r\n\r\nYou do not need to empty your mind completely.\r\n\r\nThe goal is simply to stop feeding it new information for a while.\r\n\r\nSometimes, the most productive thing you can do is give yourself permission to pause.\r\n\r\n## 5. Learn to Recognize Stress\r\n\r\nStress is a normal part of life. Exams, deadlines, financial responsibilities, relationships, uncertainty, and major changes can all create pressure.\r\n\r\nThe goal is not to eliminate every stressful experience. That is impossible.\r\n\r\nInstead, learn to recognize when stress is becoming too much.\r\n\r\nYou might notice that you are sleeping poorly, becoming easily irritated, losing concentration, avoiding people, or constantly feeling overwhelmed.\r\n\r\nWhen you notice these signs, take them seriously.\r\n\r\nTalk to someone you trust. Take a break. Organize your responsibilities into smaller tasks. Spend time outside. Exercise. Practice slow breathing.\r\n\r\nAnd if emotional difficulties persist or begin interfering significantly with daily life, reaching out to a qualified mental-health professional can be an important step.\r\n\r\nAsking for help is not weakness.\r\n\r\nIt is a form of self-care.\r\n\r\n## 6. Protect Your Attention\r\n\r\nYour attention is one of your most valuable resources.\r\n\r\nEvery day, countless apps and platforms compete to capture it. Short videos can turn into an hour. A quick check of social media can become an endless scroll.\r\n\r\nTechnology itself is not the enemy. The problem is using technology without deciding how you want to use it.\r\n\r\nTry creating intentional boundaries.\r\n\r\nTurn off unnecessary notifications. Keep your phone away while studying or eating. Give yourself periods during the day when you are not checking social media.\r\n\r\nMost importantly, notice how certain digital activities affect you.\r\n\r\nAfter scrolling for 30 minutes, do you feel informed, inspired, relaxed—or exhausted and dissatisfied?\r\n\r\nYour answer can tell you a lot.\r\n\r\nWellness sometimes means putting the phone down.\r\n\r\n## 7. Make Time for People\r\n\r\nHuman beings need connection.\r\n\r\nA healthy lifestyle is not only about food, exercise, and sleep. Relationships matter too.\r\n\r\nTalk to your family. Call an old friend. Spend time with people who make you feel respected and comfortable. Share a meal. Have a conversation without constantly checking your phone.\r\n\r\nYou do not need hundreds of friends.\r\n\r\nA few meaningful relationships can be far more valuable than thousands of online connections.\r\n\r\nAnd remember that connection goes both ways.\r\n\r\nBeing there for someone else can also create a sense of purpose and belonging.\r\n\r\nSometimes wellness looks less like a workout and more like sitting with someone and asking, \"How are you really doing?\"\r\n\r\n## 8. Stop Comparing Your Life to Everyone Else\'s\r\n\r\nOne of the biggest obstacles to wellness is comparison.\r\n\r\nSocial media makes it easy to compare your ordinary moments with someone else\'s carefully selected highlights.\r\n\r\nYou see someone\'s success but not their failures.\r\n\r\nTheir body but not their insecurities.\r\n\r\nTheir career but not their struggles.\r\n\r\nTheir vacation but not their financial stress.\r\n\r\nComparison can make a perfectly good life feel inadequate.\r\n\r\nInstead of constantly asking, \"Am I doing as well as them?\" try asking:\r\n\r\n\"Am I doing better than I was before?\"\r\n\r\nYour progress does not need to look impressive to anyone else.\r\n\r\nMaybe you are sleeping better.\r\n\r\nMaybe you are becoming more confident.\r\n\r\nMaybe you are learning to say no.\r\n\r\nMaybe you are finally taking care of yourself.\r\n\r\nThose things count.\r\n\r\n## 9. Build Small Habits Instead of Chasing Perfect Days\r\n\r\nOne of the biggest mistakes people make when trying to improve their lives is attempting to change everything at once.\r\n\r\nThey decide to wake up at 5 a.m., exercise every day, completely change their diet, meditate, read fifty pages, stop using social media, and become incredibly productive—all starting Monday.\r\n\r\nBy Thursday, the routine collapses.\r\n\r\nA better approach is to start small.\r\n\r\nDrink more water.\r\n\r\nTake a daily walk.\r\n\r\nSleep slightly earlier.\r\n\r\nEat one more serving of vegetables.\r\n\r\nSpend ten minutes away from your phone.\r\n\r\nWrite down three things you need to accomplish tomorrow.\r\n\r\nSmall habits may not feel dramatic, but repeated consistently, they can change your life.\r\n\r\nYou do not need a perfect day.\r\n\r\nYou need a direction.\r\n\r\n## 10. Remember That Wellness Is Personal\r\n\r\nThere is no single definition of wellness that works for everyone.\r\n\r\nOne person may feel energized by a morning workout. Another may prefer an evening walk.\r\n\r\nOne person may enjoy meditation. Another may find peace while gardening, reading, creating art, or talking with friends.\r\n\r\nYour circumstances, responsibilities, body, environment, and personality are different from everyone else\'s.\r\n\r\nSo wellness should not become another competition.\r\n\r\nThe goal is not to build the most impressive routine.\r\n\r\nThe goal is to build a life that supports your well-being.\r\n\r\n## A Healthier Life Can Begin Today\r\n\r\nYou do not need to wait for a new year, a Monday, or a major life transformation.\r\n\r\nStart with something small.\r\n\r\nDrink a glass of water.\r\n\r\nTake a ten-minute walk.\r\n\r\nPut your phone away for a while.\r\n\r\nEat a nourishing meal.\r\n\r\nCall someone you care about.\r\n\r\nGo to bed a little earlier.\r\n\r\nTake a deep breath.\r\n\r\nGive yourself permission to rest.\r\n\r\nWellness is not a destination where you eventually become a perfect version of yourself. It is an ongoing relationship with your body, mind, relationships, and surroundings.\r\n\r\nSome days will be productive.\r\n\r\nSome days will be difficult.\r\n\r\nSome days you will make excellent choices, and other days you will simply get through the day.\r\n\r\nThat is okay.\r\n\r\nA healthy life is not built from perfect days. It is built from small, repeated acts of care.\r\n\r\nAnd sometimes, taking care of yourself is not about doing more.\r\n\r\nSometimes, it is about finally realizing that you are already worth taking care of.', 'General', 'admin', NULL, 7, 1, '', NULL, 8, 0, 0, 1, '2026-09-04 21:10:24', '2026-09-04 21:10:24', '2026-11-01 07:00:20');

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
(1, 11, 3, 85, '2026-08-06 19:54:47'),
(144, 12, 2, 100, '2026-09-02 22:07:54'),
(163, 12, 3, 30, '2026-09-04 20:43:35'),
(178, 12, 4, 0, '2026-09-04 20:40:57'),
(402, 12, 1, 100, '2026-09-05 08:58:51'),
(549, 12, 5, 100, '2026-09-05 09:52:27'),
(971, 13, 3, 46, '2026-09-05 09:52:31'),
(981, 13, 5, 65, '2026-09-05 09:52:42'),
(1015, 14, 4, 0, '2026-10-04 09:20:09'),
(1039, 14, 3, 0, '2026-10-04 09:31:09'),
(1083, 14, 5, 31, '2026-10-04 10:03:41');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int(11) DEFAULT 10,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `icon`, `points`, `created_at`) VALUES
(1, '7 Day Check-in', 'Checked in for 7 days in a row.', '🔥', 10, '2026-09-07 08:53:24'),
(2, 'First Journal', 'Wrote your first journal entry.', '🏆', 5, '2026-09-07 08:53:24'),
(3, 'Kind Community Member', 'Sent support or reacted to 10 posts.', '💙', 10, '2026-09-07 08:53:24'),
(4, 'Mindful Week', 'Checked in every day this week.', '🧠', 15, '2026-09-07 08:53:24'),
(5, '10 Journal Entries', 'Wrote 10 journal entries.', '📔', 15, '2026-09-07 08:53:24'),
(6, '30 Day Wellness Journey', 'Checked in for 30 days in a row.', '🌱', 30, '2026-09-07 08:53:24'),
(7, 'First Focus Session', 'Completed your first focus session.', '⏱️', 5, '2026-09-07 08:53:24'),
(8, 'First Steps', 'Joined Haven and completed your profile.', '🌿', 5, '2026-09-07 08:53:24');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

CREATE TABLE `blocked_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(1, 12, 26, '2026-08-13 21:38:22'),
(2, 11, 48, '2026-10-28 22:25:19');

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
(31, 5, 'Supportive', 5, 'Practice self-care activities that make you feel good, Reach out to trusted friends or family for social connection, Consider trying relaxation techniques like deep breathing or meditation', '[1,2,3]', '2026-08-06 19:03:35'),
(32, 4, 'neutral', 80, 'Take a few deep breaths to ground yourself, Consider reaching out to a trusted friend or family member, Explore relaxing activities like reading, journaling, or light exercise', '[1,3,5]', '2026-08-08 08:47:19'),
(33, 4, 'empathetic', 80, 'Practice open and honest communication with your partner, Set healthy boundaries to protect your emotional well-being, Make time for self-care activities that nourish you', '[1,2,3]', '2026-08-08 08:47:44'),
(34, 4, 'concerned', 95, 'Reach out to a trusted friend or family member, Contact a suicide prevention hotline to speak with someone, Make an appointment with a mental health professional', '[1,2,3]', '2026-08-08 08:48:09'),
(42, 4, 'neutral', 5, 'Take a few deep breaths to help calm your mind., Consider journaling or talking to a trusted friend about what\'s on your mind., Remember that you\'re not alone - our community is here to support you.', '[1,2,3]', '2026-08-16 09:54:04'),
(43, 4, 'welcoming', 5, 'Take a deep breath and know that you\'re not alone., Feel free to share what\'s on your mind - I\'m here to listen without judgment.', '[1,2,3]', '2026-08-16 10:42:49'),
(44, 4, 'empathetic', 90, 'Speak to a qualified counselor or therapist, Explore self-help resources on mental wellness, Reach out to supportive community volunteers', '[1,3,7]', '2026-08-16 10:43:51'),
(45, 4, 'Empathetic', 4, 'Take a few deep breaths to help calm your mind, Try writing down your thoughts and feelings in a journal, Reach out to a trusted friend or family member for support', '[1,2,3]', '2026-08-25 19:55:08'),
(46, 4, 'empathetic', 4, 'Take some deep breaths and try to focus on the present moment., Write down your thoughts and feelings in a journal., Engage in a calming activity like reading, listening to music, or going for a walk.', '[1,2,3]', '2026-08-25 19:56:46'),
(47, 4, 'empathetic', 5, 'Be kind to yourself, Get some rest, Engage in activities you enjoy', '[1,2,3]', '2026-08-25 19:57:58'),
(48, 4, 'supportive', 70, '', '[]', '2026-08-25 19:58:31'),
(49, 4, 'neutral', 5, 'Take a few deep breaths to ground yourself., Remember, you\'re not alone. There are people and resources available to support you.', '[1,2,3]', '2026-08-25 20:04:42'),
(50, 4, 'Empathetic', 4, 'Try keeping a journal to write down your thoughts and feelings as they arise., Engage in activities that help you connect with yourself, like meditation or mindfulness exercises., Talk to a trusted friend or family member about what you\'re experiencing., Consider seeking support from a mental health professional, who can guide you through this process.', '[1,3,5]', '2026-08-25 20:04:57'),
(51, 7, 'sadness', 4, 'Try practicing self-care activities like taking a walk, reading, or listening to calming music., Write down your thoughts and feelings in a journal to help process them., Reach out to a trusted friend or family member to talk things through.', '[1,3,5]', '2026-08-25 20:05:53'),
(53, 10, 'empathetic', 4, 'Try relaxation techniques like deep breathing, meditation, or gentle stretching before bed, Avoid screens and caffeine close to bedtime, Reach out to your doctor or a mental health professional if the problem persists', '[1,2,3]', '2026-09-04 21:14:35'),
(54, 10, 'supportive', 4, 'Set small, achievable goals, Treat yourself when you accomplish tasks, Be patient and kind with yourself', '[1,2,3]', '2026-09-04 21:14:50'),
(55, 9, 'empathetic', 4, 'Take a few deep breaths and focus on the present moment, Reach out to a trusted friend or family member for support, Consider making an appointment with a mental health professional', '[1,2,3]', '2026-09-07 21:00:46'),
(56, 9, 'supportive', 4, 'Practice self-care activities that help you relax and recharge, Consider speaking with a trusted friend or family member, Look into local mental health resources or support groups', '[1,3,7]', '2026-09-10 19:13:06'),
(57, 12, 'neutral_positive', 75, 'Start by sharing what\'s on your mind—no pressure, just what feels right, Remember that small conversations can lead to meaningful insights, Take your time; there\'s no rush to discuss anything specific', '[1,2]', '2026-10-04 09:22:43'),
(58, 7, 'uncertain', 35, 'Take a few deep breaths to ground yourself, Consider what you\'d like to share or discuss, Remember that reaching out is a positive step', '[1,2]', '2026-10-28 21:25:12'),
(59, 9, 'motivated', 85, 'Use the Pomodoro technique for focused study sessions, Create visual diagrams to understand network concepts, Practice with hands-on labs and simulations, Form or join a study group for peer learning, Take regular breaks to maintain mental clarity', '[1,2]', '2026-10-29 20:45:54');

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
(64, 5, 0, 'ai', 'I\'m glad to hear you\'re feeling okay. Remember, it\'s normal to have ups and downs. If you ever need extra support, our volunteers are here to listen and provide guidance. Let me know if there\'s anything I can do to help brighten your day.', 'text', '2026-08-06 19:03:35'),
(65, 4, 2, 'user', 'hi', 'text', '2026-08-08 08:47:16'),
(66, 4, 0, 'ai', 'Hello there! I\'m MindGuide, an AI assistant here to offer support. How are you feeling today? I\'m here to listen and provide helpful suggestions, but please remember that I can\'t replace professional help. If you\'d like to talk more, our volunteers are available to chat.', 'text', '2026-08-08 08:47:19'),
(67, 4, 2, 'user', 'i need suggestion for my relationship', 'text', '2026-08-08 08:47:42'),
(68, 4, 0, 'ai', 'I\'m sorry you\'re facing relationship challenges. While I can\'t provide professional advice, I can suggest focusing on open communication, setting boundaries, and prioritizing self-care. There are also support resources available if you need them. Remember, you\'re not alone, and volunteers are here to listen without judgment.', 'text', '2026-08-08 08:47:44'),
(69, 4, 2, 'user', 'i want to suicide', 'text', '2026-08-08 08:48:06'),
(70, 4, 0, 'ai', 'I\'m so sorry you\'re feeling this way. Your life has value and there is help available. Please don\'t hurt yourself - you matter. Speak to a counselor or call a helpline right away. There are people who want to support you through this.', 'text', '2026-08-08 08:48:09'),
(87, 4, 2, 'user', 'hi', 'text', '2026-08-16 09:54:01'),
(88, 4, 0, 'ai', 'Hello! I\'m here to provide a supportive ear and some suggestions. How are you feeling today?', 'text', '2026-08-16 09:54:04'),
(89, 4, 2, 'user', 'hi', 'text', '2026-08-16 10:42:47'),
(90, 4, 0, 'ai', 'Hi there! I\'m MindGuide, an AI assistant here to provide support. How can I help you today?', 'text', '2026-08-16 10:42:49'),
(91, 4, 2, 'user', 'Do you have a degree to suggest me what I should do?', 'text', '2026-08-16 10:43:48'),
(92, 4, 0, 'ai', 'I\'m sorry you\'re feeling unsure about what to do. As an AI, I can\'t diagnose or provide professional advice, but I can suggest some practical steps. Consider speaking to a counselor or therapist who can assess your situation and guide you. There are also many helpful resources available. Volunteers are standing by if you need a supportive ear.', 'text', '2026-08-16 10:43:51'),
(93, 4, 2, 'user', 'ho', 'text', '2026-08-25 19:55:05'),
(94, 4, 0, 'ai', 'Hi there, I\'m here to listen and support you. It sounds like you may be going through a difficult time. I can\'t diagnose any issues, but I\'m happy to provide some suggestions that may help. Please know that there are volunteers available if you need someone to talk to. What would be most helpful for you right now?', 'text', '2026-08-25 19:55:08'),
(95, 4, 2, 'user', 'm', 'text', '2026-08-25 19:56:43'),
(96, 4, 0, 'ai', 'It sounds like you may be going through a difficult time. I\'m here to listen and offer support. While I can\'t diagnose or treat any conditions, I can suggest some practical tips that may help. I also want you to know that volunteers are available if you need someone to talk to. Please don\'t hesitate to reach out for further assistance.', 'text', '2026-08-25 19:56:46'),
(97, 4, 2, 'user', 'i am feeling low, tired of the world.', 'text', '2026-08-25 19:57:55'),
(98, 4, 0, 'ai', 'I\'m sorry you\'re feeling low and tired of the world. That must be really difficult. Please know that you\'re not alone and there are people who want to support you. I encourage you to reach out to a mental health professional or volunteer helpline - they can provide more personalized guidance. In the meantime, try to be kind to yourself, get some rest, and engage in activities you enjoy. Better days are ahead.', 'text', '2026-08-25 19:57:58'),
(99, 4, 2, 'user', 'আমি এখন কি করতে পার', 'text', '2026-08-25 19:58:27'),
(100, 4, 0, 'ai', 'আপনি এখন কি করতে পারেন? আমি আপনাকে কিছু সহজ পরামর্শ দিতে পারি। আপনি নিজেকে ভালো রাখার জন্য কিছু সুন্দর কাজ করতে পারেন, যেমন- কিছু সময় নিয়ে বিশ্রাম নেওয়া, একটি নতুন কাজ শুরু করা বা আপনার বন্ধুদের সাথে কথা বলা। আপনার যদি আরও সহায়তার প্রয়োজন হয়, তাহলে আমরা স্বেচ্ছাসেবকদের সাহায্য পেতে পারেন।', 'text', '2026-08-25 19:58:31'),
(101, 4, 2, 'user', 'hi', 'text', '2026-08-25 20:04:40'),
(102, 4, 0, 'ai', 'Hello! I\'m here to provide a supportive, non-judgmental space for you. How are you feeling today?', 'text', '2026-08-25 20:04:42'),
(103, 4, 2, 'user', 'I\'d like to understand my feelings better', 'text', '2026-08-25 20:04:53'),
(104, 4, 0, 'ai', 'I\'m sorry you\'re feeling this way. Trying to understand your emotions can be challenging, but it\'s an important step towards self-awareness and growth. I\'m here to listen and provide some suggestions that may help you explore your feelings in a healthy way.', 'text', '2026-08-25 20:04:57'),
(105, 7, 2, 'user', 'I\'m feeling sad today', 'text', '2026-08-25 20:05:46'),
(106, 7, 0, 'ai', 'I\'m sorry to hear you\'re feeling sad today. That can be a difficult emotion to experience. Please know that you\'re not alone, and there are ways to work through these feelings. I have some suggestions that may help, and our volunteer team is available if you need extra support.', 'text', '2026-08-25 20:05:53'),
(109, 10, 7, 'user', 'I can\'t sleep', 'text', '2026-09-04 21:14:31'),
(110, 10, 0, 'ai', 'I\'m sorry to hear you\'re having trouble sleeping. Insomnia can be really difficult to deal with. Try some relaxation techniques like deep breathing, meditation, or gentle stretching before bed. Avoid screens and caffeine close to bedtime. If the problem persists, reach out to your doctor or a mental health professional for more support. Volunteers are available if you need someone to talk to.', 'text', '2026-09-04 21:14:35'),
(111, 10, 7, 'user', 'I need some motivation', 'text', '2026-09-04 21:14:46'),
(112, 10, 0, 'ai', 'I\'m sorry you\'re feeling unmotivated. It\'s great that you\'re reaching out for support. Here are some suggestions that may help boost your motivation: try setting small, achievable goals, treat yourself when you accomplish tasks, and remember to be patient and kind with yourself. Volunteers are available if you need someone to talk to. You\'ve got this!', 'text', '2026-09-04 21:14:50'),
(113, 9, 12, 'user', 'hi', 'text', '2026-09-07 20:53:42'),
(114, 9, 12, 'user', 'hi', 'text', '2026-09-07 20:54:01'),
(115, 9, 12, 'user', 'gi', 'text', '2026-09-07 20:59:26'),
(116, 9, 12, 'user', 'u', 'text', '2026-09-07 21:00:43'),
(117, 9, 0, 'ai', 'I\'m here to listen and support you. While I can\'t diagnose or replace professional help, I\'m happy to provide some practical suggestions. How are you feeling today? There are volunteers available if you need someone to talk to.', 'text', '2026-09-07 21:00:46'),
(118, 9, 12, 'user', 'hi', 'text', '2026-09-10 19:13:03'),
(119, 9, 0, 'ai', 'Hello there! I\'m glad you reached out. As an AI assistant, I\'m here to provide a compassionate ear and practical suggestions. While I can\'t replace professional help, I\'m happy to explore ways you can take care of yourself during this time. Please let me know if you\'d like to share more about how you\'re feeling.', 'text', '2026-09-10 19:13:06'),
(120, 12, 14, 'user', 'hi', 'text', '2026-10-04 09:22:40'),
(121, 12, 0, 'ai', 'Hi there! 👋 I\'m glad you reached out. It\'s nice to connect with you today. I\'m here to listen and support you on your wellness journey. Whether you\'re dealing with something specific or just want to chat, I\'m all ears. Remember, you\'re not alone—our caring volunteers are also available if you\'d like to talk with someone. How are you feeling today? Is there anything on your mind you\'d like to share?', 'text', '2026-10-04 09:22:43'),
(122, 7, 2, 'user', 'hi', 'text', '2026-10-28 21:25:08'),
(123, 7, 0, 'ai', 'Hi there! I\'m glad you reached out. I\'m here to listen and support you. Whether you\'d like to talk about what\'s on your mind, need some coping strategies, or just want to chat, I\'m available. Remember, our volunteer team is also here if you need someone to talk to. What would be most helpful for you right now?', 'text', '2026-10-28 21:25:12'),
(124, 9, 12, 'user', 'how to get A+ in computer network course?', 'text', '2026-10-29 20:45:51'),
(125, 9, 0, 'ai', 'I\'m glad you\'re focusing on your studies! While academic success is important, remember that balance matters too. For computer networks, try breaking concepts into smaller chunks, join study groups, and use active recall. Take breaks to avoid burnout—your wellbeing supports your learning. If you\'re feeling overwhelmed, our volunteers are available to chat.', 'text', '2026-10-29 20:45:54');

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
(4, 2, 'active', 'Empathetic', 0, '2026-08-05 18:40:03', NULL, '2026-08-25 20:04:57'),
(5, 11, 'active', 'Supportive', 0, '2026-08-06 19:03:30', NULL, '2026-08-06 19:03:35'),
(7, 2, 'active', 'uncertain', 0, '2026-08-25 20:05:43', NULL, '2026-10-28 21:25:12'),
(9, 12, 'active', 'motivated', 0, '2026-09-02 22:08:15', NULL, '2026-10-29 20:45:54'),
(10, 7, 'active', 'supportive', 0, '2026-09-02 22:10:13', NULL, '2026-09-04 21:14:50'),
(11, 13, 'active', NULL, 0, '2026-09-05 09:51:07', NULL, NULL),
(12, 14, 'active', 'neutral_positive', 0, '2026-10-04 09:22:37', NULL, '2026-10-04 09:22:43');

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
(35, 22, 2, NULL, 'hi', 1, '2026-08-05 20:11:19'),
(36, 25, 3, NULL, 'I\'m so sorry you\'re struggling with these thoughts and feelings. What you\'re describing sounds extremely difficult and frightening. Please know that you\'re not alone and there is help available. I would strongly encourage you to reach out to a mental health professional as soon as possible. In the meantime, try to be gentle with yourself, and know that volunteers are standing by if you need someone to talk to.', 1, '2026-08-12 15:30:07'),
(37, 25, 12, NULL, 'thats so sad', 1, '2026-08-12 21:31:45'),
(38, 26, 3, NULL, 'Hello! I\'m here to listen and provide support. How are you feeling today? Remember, you\'re not alone - our community volunteers are available if you need someone to talk to.', 1, '2026-08-13 13:23:05'),
(39, 26, 12, NULL, 'hi', 1, '2026-08-13 20:01:15'),
(40, 24, 12, 26, 'jo', 1, '2026-08-13 22:41:34'),
(41, 24, 12, 26, 'jo', 1, '2026-08-13 22:41:38'),
(42, 24, 12, NULL, 'dfefd', 1, '2026-08-13 22:41:44'),
(43, 24, 12, 29, 'j', 1, '2026-08-13 22:50:04'),
(44, 24, 12, NULL, 'f', 1, '2026-08-13 22:50:08'),
(45, 27, 12, NULL, 'hi', 1, '2026-08-14 00:33:35'),
(46, 28, 3, NULL, 'I\'m so sorry you\'re struggling with these difficult thoughts and feelings. Please know that you\'re not alone, and there is help and support available. Your life has value, and the world is better with you in it. I would encourage you to reach out to a mental health professional or call a crisis hotline right away. Volunteers are also standing by if you need someone to talk to. You\'ve got this, and I believe in you.', 1, '2026-08-13 18:50:29'),
(47, 29, 3, NULL, 'I\'m sorry to hear you\'re feeling down and overwhelmed. That can be a tough place to be. Please know that you\'re not alone, and there are ways to find some relief. I\'d suggest trying to take some time for yourself to unwind, even if it\'s just a short break. Some relaxing activities like gentle exercise, journaling, or spending time in nature may help you feel better. If the feelings persist, don\'t hesitate to reach out for additional support. Volunteer counselors are available if you need someone to talk to.', 1, '2026-08-13 18:51:38'),
(48, 24, 12, NULL, 'hi', 1, '2026-08-14 09:09:14'),
(49, 29, 12, NULL, 'h', 1, '2026-08-14 09:27:02'),
(50, 30, 3, NULL, 'I\'m sorry to hear you\'re not feeling well. It sounds like you may have a cold or the flu. I suggest resting, staying hydrated, and monitoring your symptoms. If they worsen or persist, please consider speaking to a healthcare professional. Remember, you are not alone. Volunteers are available if you need further support.', 1, '2026-08-15 08:40:44'),
(51, 20, 2, NULL, 'hi', 1, '2026-08-16 10:40:19'),
(52, 31, 3, NULL, 'Hello, I\'m MindGuide, your supportive AI assistant. How are you feeling today? I\'m here to listen and provide some suggestions that may help. Remember, volunteers are available if you need additional support.', 1, '2026-08-25 20:26:00'),
(53, 32, 3, NULL, 'Hello there! I\'m MindGuide, an AI assistant here to support you. How are you feeling today? I\'m here to listen and provide some suggestions if you\'d like. Remember, our trained volunteers are also available if you need more personalized support.', 1, '2026-08-25 20:26:05'),
(54, 33, 3, NULL, 'I\'m so sorry you\'re feeling low. That can be really difficult to experience. Please know that you\'re not alone, and there are ways to start feeling better. I have some suggestions that may help, and there are also volunteers available if you need extra support.', 1, '2026-08-25 20:26:49'),
(55, 34, 3, NULL, 'I\'m here to listen and support you. Though I can\'t provide a diagnosis, I want you to know that you\'re not alone. A volunteer will reach out soon to offer personalized guidance. In the meantime, try practicing self-care through activities that bring you comfort. Remember, your wellbeing is important, and there are people who want to help.', 1, '2026-09-04 20:35:58'),
(56, 35, 3, NULL, 'I\'m glad you reached out. As an AI, I can offer a listening ear and suggestions, but cannot provide professional mental health support. However, our volunteer team is available to connect with you one-on-one and provide personalized guidance. Please let me know if you would like me to put you in touch with a volunteer.', 1, '2026-09-04 20:36:03'),
(57, 36, 3, NULL, 'Hi there. It\'s great to hear from you. I\'m here to listen and offer support. While I can\'t provide a diagnosis, I\'m happy to discuss what you\'re going through and suggest some practical self-care tips. A volunteer is available if you\'d like to talk more. You\'re not alone in this.', 1, '2026-09-04 20:36:06'),
(58, 37, 3, NULL, 'Thank you for reaching out. I\'m here to listen and offer support. While I cannot provide medical advice, I can suggest speaking to a counselor or trusted loved one about what you\'re going through. Your well-being is important, and there are resources available. A volunteer from our community will be in touch soon to provide more personalized assistance. You are not alone.', 1, '2026-09-04 20:36:10'),
(59, 38, 3, NULL, 'Thank you for reaching out. It\'s great that you\'re taking steps to prioritize your mental wellbeing. While I can\'t provide a diagnosis, I\'m here to offer support and suggest some practical tips. Please know that volunteers are available if you need someone to talk to. I\'m here to listen without judgment and provide a compassionate space.', 1, '2026-09-04 20:36:14'),
(60, 39, 3, NULL, 'I\'m sorry you\'re feeling this way. Please know that you\'re not alone and support is available. A volunteer from our community will reach out to you soon to provide a listening ear and suggest helpful resources. In the meantime, try to practice self-care through activities you enjoy. You\'ve got this.', 1, '2026-09-04 20:36:18'),
(61, 40, 3, NULL, 'I\'m here to listen and support you. While I can\'t provide professional diagnosis or treatment, I can offer a compassionate ear and suggest some resources that may be helpful. A volunteer will reach out to you soon. Please don\'t hesitate to reach out if you need further assistance.', 1, '2026-09-04 20:36:22'),
(62, 41, 3, NULL, 'I\'m here to listen and support you. Your feelings are valid, and there are resources available. A volunteer will reach out soon to provide more personalized guidance. You don\'t have to face this alone.', 1, '2026-09-04 20:36:25'),
(63, 35, 12, NULL, 'hi', 1, '2026-09-04 20:37:00'),
(64, 25, 1, NULL, 'don\'t worry', 1, '2026-09-04 21:06:19'),
(65, 42, 3, NULL, 'I\'m sorry you\'re feeling so isolated and disconnected right now. That can be an incredibly painful experience. Please know that you\'re not alone, and there are people and resources available to support you. I would suggest reaching out to a counselor or therapist who can provide personalized guidance. Our volunteers are also here to listen without judgment and offer emotional support.', 1, '2026-09-05 09:55:32'),
(66, 43, 3, NULL, 'I\'m sorry you\'re feeling so disconnected and isolated. That can be a really difficult and lonely experience. Remember that you\'re not alone - many people struggle with feelings of being \'outside\' of things. I encourage you to try reaching out to supportive friends or family, or consider connecting with our community volunteers who are here to listen without judgment.', 1, '2026-09-05 09:55:36'),
(67, 44, 3, NULL, 'I\'m sorry you\'re feeling so isolated right now. That \'glass box\' feeling can be really difficult. Please know that you\'re not alone, and there are people who want to support you. I encourage you to reach out to our volunteer community - they\'re here to listen without judgment and offer practical suggestions. You deserve to feel connected and understood.', 1, '2026-09-05 09:55:41'),
(68, 42, 12, NULL, 'don\'t worry', 1, '2026-09-05 09:56:31'),
(69, 42, 7, NULL, 'we are with you', 1, '2026-09-05 09:57:15'),
(70, 46, 3, NULL, 'I\'m sorry to hear you\'re feeling tired and done after class. That\'s understandable - school can be draining. Remember to take time for yourself, even in small ways, to recharge. Reach out to supportive friends or loved ones if you need. And don\'t hesitate to connect with our volunteer community if you\'d like someone to talk to.', 1, '2026-09-05 03:57:46'),
(71, 46, 12, NULL, 'hagsjsn', 1, '2026-09-05 09:59:11'),
(72, 47, 3, NULL, 'I\'m so sorry you\'re struggling. Please know that you matter and your life has value. Although I can\'t provide professional help, there are people who want to support you through this. I\'d suggest reaching out to a trusted loved one, calling a helpline, or connecting with a counselor. You don\'t have to face this alone.', 1, '2026-09-05 10:01:34'),
(73, 48, 3, NULL, 'I\'m so relieved to hear you\'re safe, Munshi. That must have been a scary experience. Please know that you have my deepest compassion during this time. While I cannot replace professional help, I\'m here to offer emotional support and practical suggestions. Remember, you are not alone - volunteers are available if you need someone to talk to.', 1, '2026-09-05 04:02:02'),
(74, 48, 12, NULL, 'more jas nai kn?', 1, '2026-09-05 10:05:55'),
(75, 48, 14, 74, 'Karon bike niyee pani tee poree gesi', 1, '2026-09-05 10:09:00'),
(76, 49, 3, NULL, 'Hello there. It sounds like you\'re feeling okay, but I\'m here if you need someone to talk to. Remember, you\'re not alone, and volunteers are available if you need extra support. Let me know if there\'s anything I can do to help.', 1, '2026-09-05 06:30:17'),
(77, 47, 1, NULL, 'we are with you man. dont be sad', 1, '2026-09-07 08:49:24'),
(78, 50, 3, NULL, 'I\'m sorry you\'re not feeling well. Physical or emotional discomfort can be really tough to navigate alone. I\'m here to listen and support you. Could you share a bit more about what you\'re experiencing? Are you dealing with something physical, emotional, or both? Remember, our trained volunteers are available if you\'d like to talk with someone directly. In the meantime, be gentle with yourself and prioritize rest if you need it.', 1, '2026-10-03 20:20:31'),
(79, 51, 3, NULL, 'I\'m really sorry to hear about your exam result—that must feel incredibly disappointing right now. It\'s completely okay to feel upset. One exam doesn\'t define your abilities or potential. Take a moment to breathe and be kind to yourself. When you\'re ready, consider reviewing what challenged you most, maybe seeking study support, or talking to your instructor about areas to improve. Remember, setbacks are learning opportunities. Our community volunteers are here to listen and support you through this difficult time. You\'re not alone in this.', 1, '2026-10-04 09:15:19'),
(80, 52, 3, NULL, 'I\'m here for you. Please know that you are valuable. A volunteer has been notified immediately. 💚', 1, '2026-10-04 09:51:34'),
(81, 53, 3, NULL, 'I\'m sorry you\'re feeling sick right now. That can be really difficult and draining. I\'m here to listen and support you. When you say sick, are you referring to physical illness, emotional overwhelm, or perhaps a mix of both? Understanding what you\'re experiencing can help me offer better support. Remember, you\'re not alone in this—our community volunteers are available to chat if you need someone to talk to. Please take care of yourself by resting, staying hydrated, and reaching out if things feel overwhelming.', 1, '2026-10-04 09:57:14'),
(82, 54, 3, NULL, 'Hello! I noticed you\'ve shared \'Ae Ajnabee Song\' - a beautiful, melancholic track. Music can be a wonderful way to process emotions. If you\'re feeling reflective or perhaps a bit down, that\'s completely valid. Sometimes listening to meaningful songs helps us feel less alone. Would you like to talk about what you\'re experiencing right now? Our volunteers are here to listen and support you without judgment.', 1, '2026-10-24 20:18:55'),
(83, 55, 3, NULL, 'Hello, I\'m here to listen and support you. It seems your message got cut off—I see \'i am si\'. Could you share a bit more about what you\'re experiencing right now? Whether you\'re feeling stuck, overwhelmed, or just need someone to talk to, I\'m here without judgment. Take your time expressing yourself. Our trained volunteers are also available if you\'d like to connect with someone for more personalized support.', 1, '2026-10-25 09:20:17'),
(84, 52, 1, NULL, 'ok do it', 1, '2026-10-28 21:32:33'),
(85, 56, 3, NULL, 'I hear you—nighttime can feel isolating and amplify difficult feelings. Darkness often intensifies anxiety and self-doubt for many people. This is actually quite common. Consider establishing a calming nighttime routine: dim lighting, grounding exercises, or journaling your thoughts. Physical activity earlier in the day may help regulate your mood. If these patterns persist, speaking with a counselor could provide personalized strategies. Our trained volunteers are available anytime to listen without judgment.', 1, '2026-10-28 22:21:32'),
(86, 56, 1, 85, 'ok', 1, '2026-10-28 22:22:45'),
(87, 51, 11, NULL, 'hi treeeeeeeeeeeeek', 1, '2026-10-28 22:24:45'),
(88, 57, 3, NULL, 'Hi there! Welcome to our community. I\'m glad you reached out. It\'s wonderful that you\'re here, and I want you to know this is a safe space to share what\'s on your mind—whether you\'re doing okay, need support, or just want to chat. How are you feeling today? Is there anything specific you\'d like to talk about, or would you just like to take things one step at a time? Remember, our volunteers are always available if you\'d like to connect with someone.', 1, '2026-10-29 11:20:31');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comment_reactions`
--

INSERT INTO `comment_reactions` (`id`, `comment_id`, `user_id`, `emoji`, `created_at`) VALUES
(1, 37, 1, '❤️', '2026-09-04 21:06:29'),
(2, 36, 1, '❤️', '2026-09-04 21:06:31'),
(4, 74, 14, '❤️', '2026-09-05 10:09:16'),
(5, 73, 14, '❤️', '2026-09-05 10:09:18'),
(6, 72, 1, '❤️', '2026-09-07 08:49:06'),
(7, 38, 12, '❤️', '2026-09-10 19:12:10');

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

--
-- Dumping data for table `consultation_messages`
--

INSERT INTO `consultation_messages` (`id`, `request_id`, `sender_id`, `message`, `created_at`) VALUES
(1, 5, 7, 'i', '2026-09-04 21:11:50'),
(2, 10, 7, 'hello', '2026-09-04 21:12:18'),
(3, 10, 7, 'I\'m glad to hear you\'re doing okay. It can be challenging to balance work and personal life, but take time for self-care when you can. Consider trying relaxation techniques, like deep breathing or mindfulness exercises, to manage stress. Remember, you don\'t have to face this alone - our community volunteers are here to listen and provide support if you need it.', '2026-09-04 21:12:26'),
(4, 10, 12, 'yes', '2026-09-04 21:12:50'),
(5, 10, 7, 'how are you?', '2026-09-04 21:13:10'),
(6, 10, 12, 'i am ok', '2026-09-04 21:13:22'),
(7, 4, 7, 'jj', '2026-09-04 21:13:52'),
(8, 11, 7, 'hi', '2026-09-05 10:02:41'),
(9, 11, 7, 'I\'m so sorry you\'re struggling with these painful feelings. Please know you\'re not alone, and that there are people and resources available to support you. Your life has value, and you deserve to feel safe and well. I would encourage you to reach out to a counselor or call a helpline - they can provide confidential support and help you find healthy ways to cope. In the meantime, try to be kind to yourself, and know that you matter.', '2026-09-05 10:03:51'),
(10, 12, 7, 'hi', '2026-10-04 09:25:35'),
(11, 12, 14, 'hello', '2026-10-04 09:25:50');

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
(4, 2, 7, NULL, 'AI flagged high risk: I\'ve stopped talking to everyone because I don\'t think anyone can help me anymore. Every day feels h', 'closed', 'medium', '2026-08-03 20:12:15', '2026-09-02 22:11:02', '2026-09-04 21:13:52', '2026-09-04 21:13:57', 5, 'Great'),
(5, 12, 7, 25, 'AI flagged high risk: For the past few weeks, I\'ve been having increasingly serious thoughts about hurting myself. At firs', 'closed', 'medium', '2026-08-12 15:30:07', '2026-09-02 22:11:08', '2026-09-04 21:11:50', '2026-09-04 21:13:46', 5, 'Great'),
(6, 12, 7, 28, 'AI flagged high risk: I’ve been feeling completely hopeless lately. I keep thinking that everyone would be better off wi', 'closed', 'medium', '2026-08-13 18:50:29', '2026-09-02 22:11:11', NULL, '2026-09-02 22:13:39', 5, 'Great'),
(10, 12, 7, NULL, 'hi', 'closed', 'medium', '2026-09-04 21:00:56', '2026-09-04 21:12:06', '2026-09-04 21:12:18', '2026-09-04 21:13:40', 5, 'Great'),
(11, 13, 7, 47, 'AI flagged high risk: I  just want to suicide or harm my self', 'closed', 'medium', '2026-09-05 10:01:34', '2026-09-05 10:02:14', '2026-09-05 10:02:41', '2026-09-05 10:04:09', 5, 'Great'),
(12, 14, 7, NULL, 'i am feeling unwell', 'closed', 'high', '2026-10-04 09:19:23', '2026-10-04 09:24:43', '2026-10-04 09:25:35', '2026-10-04 09:26:14', 5, 'Great'),
(13, 14, 7, 52, 'AI flagged high risk: i want to suicide', 'closed', 'high', '2026-10-04 09:51:34', '2026-10-04 09:58:47', NULL, '2026-10-04 09:58:58', 5, 'Great');

-- --------------------------------------------------------

--
-- Table structure for table `dm_messages`
--

CREATE TABLE `dm_messages` (
  `id` bigint(20) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `kind` enum('text','image','location') NOT NULL DEFAULT 'text',
  `body` mediumtext DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dm_messages`
--

INSERT INTO `dm_messages` (`id`, `thread_id`, `sender_id`, `kind`, `body`, `media_path`, `lat`, `lng`, `created_at`, `read_at`, `is_hidden`, `deleted_at`) VALUES
(1, 1, 2, 'text', 'v1:JR1ixXNXltvuAPgtIoMVgchokuLiwLW+dubXhC8d', NULL, NULL, NULL, '2026-10-29 03:25:32', NULL, 0, NULL),
(2, 2, 2, 'text', 'v1:P85k5zhBP15+z1rMuFO9PbhxMoHk/zgeKyYYYUO1E0bQ', NULL, NULL, NULL, '2026-10-29 03:25:45', '2026-10-29 03:58:16', 0, NULL),
(3, 2, 11, 'text', 'v1:VL0kES5RXFmbP6fdgKPJF7vOz5NzGvOefQeMzVM6', NULL, NULL, NULL, '2026-10-29 03:58:26', '2026-10-29 03:58:47', 0, NULL),
(4, 2, 2, 'image', '', 'uploads/private/dm/1135e9d3a174b28d13d8c115f59e097d.jpg', NULL, NULL, '2026-10-29 03:58:59', '2026-10-29 03:59:08', 0, NULL),
(5, 3, 1, 'text', 'v1:GKBrIAti1XPYyNJYw9H284O9ps3expSre9JyYvXS7g==', NULL, NULL, NULL, '2026-10-29 04:30:39', '2026-10-29 04:30:56', 0, NULL),
(6, 3, 2, 'text', 'v1:0oEcbu0U/t9Qyj9xXV8b50MnFAM8In2CENsTLm0w', NULL, NULL, NULL, '2026-10-29 04:31:03', '2026-10-29 04:31:03', 0, NULL),
(7, 3, 2, 'text', 'v1:Lx9+ubYGEC9eN5qxAoNCkljOFgmwDS0t+qDBmMHAoAa8/e6p', NULL, NULL, NULL, '2026-10-29 04:31:14', '2026-10-29 04:31:15', 0, NULL),
(8, 4, 2, 'text', 'v1:Kn/3qgN0gsSqqNKx2rT/j1IHxItFPSFGUfB9lObn', NULL, NULL, NULL, '2026-10-29 04:37:40', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `dm_threads`
--

CREATE TABLE `dm_threads` (
  `id` int(11) NOT NULL,
  `user_a` int(11) NOT NULL,
  `user_b` int(11) NOT NULL,
  `status` enum('request','active','declined') NOT NULL DEFAULT 'active',
  `requested_by` int(11) DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dm_threads`
--

INSERT INTO `dm_threads` (`id`, `user_a`, `user_b`, `status`, `requested_by`, `declined_at`, `created_at`, `last_message_at`) VALUES
(1, 2, 7, 'active', NULL, NULL, '2026-10-29 03:25:32', '2026-10-29 03:25:32'),
(2, 2, 11, 'active', NULL, NULL, '2026-10-29 03:25:45', '2026-10-29 03:58:59'),
(3, 1, 2, 'active', 1, NULL, '2026-10-29 04:30:39', '2026-10-29 04:31:14'),
(4, 2, 14, 'request', 2, NULL, '2026-10-29 04:37:40', '2026-10-29 04:37:40');

-- --------------------------------------------------------

--
-- Table structure for table `dm_user_state`
--

CREATE TABLE `dm_user_state` (
  `user_id` int(11) NOT NULL,
  `strikes` int(11) NOT NULL DEFAULT 0,
  `muted_until` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_profiles`
--

CREATE TABLE `doctor_profiles` (
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `display_name` varchar(120) NOT NULL,
  `specialty` varchar(120) NOT NULL,
  `qualifications` varchar(255) DEFAULT NULL,
  `reg_no` varchar(60) DEFAULT NULL,
  `experience_years` smallint(6) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `languages` varchar(160) DEFAULT NULL,
  `fee` int(11) DEFAULT NULL,
  `online_consult` tinyint(1) NOT NULL DEFAULT 1,
  `chamber_name` varchar(160) DEFAULT NULL,
  `chamber_address` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `license_doc` varchar(255) DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_profiles`
--

INSERT INTO `doctor_profiles` (`user_id`, `status`, `display_name`, `specialty`, `qualifications`, `reg_no`, `experience_years`, `bio`, `languages`, `fee`, `online_consult`, `chamber_name`, `chamber_address`, `lat`, `lng`, `license_doc`, `review_note`, `created_at`, `approved_at`) VALUES
(2, 'approved', 'Dr. Nadia Islam', 'Psychiatry', 'MBBS', '12345678', 0, '', '', 0, 1, '', '', NULL, NULL, 'uploads/private/license/6362d13ce3a3e62a9cb9be7275d785ec.jpg', 'ok', '2026-10-29 03:30:04', '2026-10-29 03:32:16');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_reviews`
--

CREATE TABLE `doctor_reviews` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `stars` tinyint(4) NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_slots`
--

CREATE TABLE `doctor_slots` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `weekday` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_minutes` smallint(6) NOT NULL DEFAULT 30,
  `mode` enum('chamber','online') NOT NULL DEFAULT 'chamber',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_slots`
--

INSERT INTO `doctor_slots` (`id`, `doctor_id`, `weekday`, `start_time`, `end_time`, `slot_minutes`, `mode`, `is_active`) VALUES
(1, 2, 1, '17:00:00', '20:00:00', 30, 'chamber', 1);

-- --------------------------------------------------------

--
-- Table structure for table `focus_sessions`
--

CREATE TABLE `focus_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 1,
  `started_at` datetime NOT NULL,
  `ended_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `focus_sessions`
--

INSERT INTO `focus_sessions` (`id`, `user_id`, `duration_minutes`, `completed`, `started_at`, `ended_at`) VALUES
(1, 2, 15, 1, '2026-10-29 17:03:45', '2026-10-29 17:18:45');

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

--
-- Dumping data for table `followers`
--

INSERT INTO `followers` (`id`, `user_id`, `follower_id`, `created_at`) VALUES
(1, 10, 12, '2026-08-14 00:29:53'),
(2, 13, 14, '2026-08-15 08:40:09'),
(3, 9, 2, '2026-08-16 10:40:49'),
(4, 14, 2, '2026-08-16 10:40:50'),
(5, 13, 12, '2026-09-05 09:51:21'),
(6, 16, 2, '2026-10-28 21:33:12'),
(7, 16, 11, '2026-10-28 22:24:14');

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

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 16, 'pending', '2026-10-28 21:33:17', '2026-10-28 21:33:17');

-- --------------------------------------------------------

--
-- Table structure for table `goals`
--

CREATE TABLE `goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `metric` enum('mood_checkin','journal_entry','focus_session','custom') NOT NULL DEFAULT 'custom',
  `period` enum('daily','weekly') NOT NULL DEFAULT 'weekly',
  `target_count` int(11) NOT NULL DEFAULT 1,
  `manual_progress` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goals`
--

INSERT INTO `goals` (`id`, `user_id`, `title`, `metric`, `period`, `target_count`, `manual_progress`, `is_active`, `created_at`) VALUES
(1, 2, 'Push Up Daily', 'custom', 'daily', 10, 10, 1, '2026-10-29 04:27:51');

-- --------------------------------------------------------

--
-- Table structure for table `hidden_posts`
--

CREATE TABLE `hidden_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
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
  `mood_emoji` varchar(10) DEFAULT NULL,
  `ai_reflection` text DEFAULT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `journals`
--

INSERT INTO `journals` (`id`, `user_id`, `entry`, `mood_emoji`, `ai_reflection`, `entry_date`, `created_at`) VALUES
(1, 2, 'I feel lowtoday, body temperature bacome very high. eyes are unableto close]', 'sad', 'It sounds like you\'re dealing with physical discomfort on top of feeling low—that combination can feel really overwhelming.', '2026-10-29', '2026-10-29 10:50:39');

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

--
-- Dumping data for table `moderation_logs`
--

INSERT INTO `moderation_logs` (`id`, `moderator_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(1, 1, 'doctor_approve', 'doctor', 2, 'ok', '2026-10-28 21:32:16');

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

--
-- Dumping data for table `mood_entries`
--

INSERT INTO `mood_entries` (`id`, `user_id`, `emotion`, `energy`, `stress`, `sleep`, `exercise`, `medication`, `note`, `entry_date`, `created_at`) VALUES
(1, 12, 'okay', 6, 5, 5, 5, 0, '', '2026-09-04', '2026-09-04 20:42:37'),
(2, 12, 'okay', 10, 2, 3, 7, 0, '', '2026-09-05', '2026-09-05 08:57:47'),
(3, 12, 'okay', 7, 5, 5, 5, 0, '', '2026-09-07', '2026-09-07 10:53:27'),
(4, 14, 'okay', 7, 2, 5, 5, 0, '', '2026-10-04', '2026-10-04 09:20:58'),
(5, 1, 'okay', 9, 5, 5, 5, 0, '', '2026-10-29', '2026-10-28 22:18:00'),
(6, 2, 'okay', 10, 1, 3, 2, 0, '', '2026-10-29', '2026-10-28 22:28:47');

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
(36, 11, 'happy', 5, 5, 5, '2026-08-06', '2026-08-06 19:03:57'),
(37, 12, 'stressed', 5, 5, 5, '2026-08-12', '2026-08-12 21:04:53'),
(40, 12, 'okay', 5, 5, 5, '2026-08-13', '2026-08-13 21:32:22'),
(41, 12, 'okay', 5, 5, 5, '2026-08-14', '2026-08-14 00:39:55'),
(42, 2, 'okay', 5, 5, 5, '2026-08-16', '2026-08-16 10:35:47'),
(43, 2, 'okay', 5, 5, 5, '2026-08-25', '2026-08-25 20:23:40'),
(44, 2, 'tired', 5, 5, 5, '2026-08-26', '2026-08-26 04:07:31');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(12, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"dg\"', 'post.php?id=24', 1, '2026-08-03 21:06:23'),
(13, 11, 'AI Alert: high risk post', 'ai_alert', 'Post #25 flagged for self-harm. Risk: high.', 'post.php?id=25', 1, '2026-08-12 15:30:07'),
(14, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"thoughts about hurting myself\"', 'post.php?id=25', 1, '2026-08-12 15:30:07'),
(15, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"hi\"', 'post.php?id=26', 1, '2026-08-13 13:23:05'),
(16, 1, NULL, 'report', 'Post #24 has been reported.', 'moderator.php?view=reports', 1, '2026-08-13 21:51:45'),
(17, 11, NULL, 'report', 'Post #24 has been reported.', 'moderator.php?view=reports', 1, '2026-08-13 21:51:45'),
(18, 2, NULL, 'new_comment', 'Someone replied to your post: \"dg\"', 'post.php?id=24', 1, '2026-08-13 22:41:34'),
(19, 2, NULL, 'new_comment', 'Someone replied to your post: \"dg\"', 'post.php?id=24', 1, '2026-08-13 22:41:38'),
(20, 2, NULL, 'new_comment', 'Someone replied to your post: \"dg\"', 'post.php?id=24', 1, '2026-08-13 22:41:44'),
(21, 2, NULL, 'new_comment', 'Someone replied to your post: \"dg\"', 'post.php?id=24', 1, '2026-08-13 22:50:04'),
(22, 2, NULL, 'new_comment', 'Someone replied to your post: \"dg\"', 'post.php?id=24', 1, '2026-08-13 22:50:08'),
(23, 11, 'AI Alert: high risk post', 'ai_alert', 'Post #28 flagged for suicidal ideation. Risk: high.', 'post.php?id=28', 1, '2026-08-13 18:50:29'),
(24, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"I’m scared\"', 'post.php?id=28', 1, '2026-08-13 18:50:29'),
(25, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=29', 1, '2026-08-13 18:51:38'),
(26, 14, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=30', 1, '2026-08-15 08:40:44'),
(27, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=31', 1, '2026-08-25 20:26:00'),
(28, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=32', 1, '2026-08-25 20:26:05'),
(29, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=33', 1, '2026-08-25 20:26:49'),
(30, 2, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=4', 1, '2026-09-02 22:11:02'),
(31, 12, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=5', 1, '2026-09-02 22:11:08'),
(32, 12, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=6', 1, '2026-09-02 22:11:11'),
(33, 12, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=6', 1, '2026-09-02 22:13:39'),
(34, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=34', 1, '2026-09-04 20:35:58'),
(35, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=35', 1, '2026-09-04 20:36:03'),
(36, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=36', 1, '2026-09-04 20:36:06'),
(37, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=37', 1, '2026-09-04 20:36:10'),
(38, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=38', 1, '2026-09-04 20:36:15'),
(39, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=39', 1, '2026-09-04 20:36:18'),
(40, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=40', 1, '2026-09-04 20:36:22'),
(41, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=41', 1, '2026-09-04 20:36:25'),
(42, 12, NULL, 'consultation', 'Your consultation request has been submitted. A volunteer will reach out soon.', 'consultation.php?tab=active', 1, '2026-09-04 21:00:56'),
(43, 12, NULL, 'new_comment', 'Someone replied to your post: \"thoughts about hurting myself\"', 'post.php?id=25', 1, '2026-09-04 21:06:19'),
(44, 12, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=5', 1, '2026-09-04 21:11:50'),
(45, 12, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=10', 1, '2026-09-04 21:12:06'),
(46, 12, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=10', 1, '2026-09-04 21:12:18'),
(47, 12, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=10', 1, '2026-09-04 21:12:26'),
(48, 7, NULL, 'consultation_message', 'New message from a user you are supporting.', 'volunteer.php?tab=cases&case_id=10', 1, '2026-09-04 21:12:50'),
(49, 12, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=10', 1, '2026-09-04 21:13:10'),
(50, 7, NULL, 'consultation_message', 'New message from a user you are supporting.', 'volunteer.php?tab=cases&case_id=10', 1, '2026-09-04 21:13:22'),
(51, 12, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=10', 1, '2026-09-04 21:13:40'),
(52, 12, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=5', 1, '2026-09-04 21:13:46'),
(53, 2, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=4', 1, '2026-09-04 21:13:52'),
(54, 2, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=4', 1, '2026-09-04 21:13:57'),
(55, 13, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=42', 0, '2026-09-05 09:55:32'),
(56, 13, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=43', 0, '2026-09-05 09:55:36'),
(57, 13, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=44', 0, '2026-09-05 09:55:41'),
(58, 13, NULL, 'new_comment', 'Someone replied to your post: \"\"', 'post.php?id=42', 0, '2026-09-05 09:56:31'),
(59, 13, NULL, 'new_comment', 'Someone replied to your post: \"\"', 'post.php?id=42', 0, '2026-09-05 09:57:15'),
(60, 14, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"Class is finally over. Now I’m just tired and do\"', 'post.php?id=46', 1, '2026-09-05 03:57:46'),
(61, 14, NULL, 'new_comment', 'Someone replied to your post: \"Class is finally over. Now I’m just tired and done. 😮‍💨🎓\"', 'post.php?id=46', 1, '2026-09-05 09:59:11'),
(62, 11, 'AI Alert: high risk post', 'ai_alert', 'Post #47 flagged for self-harm. Risk: high.', 'post.php?id=47', 1, '2026-09-05 10:01:34'),
(63, 13, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=47', 0, '2026-09-05 10:01:34'),
(64, 14, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"Suddenly got into a bike accident at Bahaddarhat. \"', 'post.php?id=48', 1, '2026-09-05 04:02:02'),
(65, 13, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=11', 0, '2026-09-05 10:02:14'),
(66, 13, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=11', 0, '2026-09-05 10:02:41'),
(67, 13, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=11', 0, '2026-09-05 10:03:51'),
(68, 13, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=11', 0, '2026-09-05 10:04:09'),
(69, 14, NULL, 'new_comment', 'Someone replied to your post: \"Suddenly got into a bike accident at Bahaddarhat. Alhamdulil\"', 'post.php?id=48', 1, '2026-09-05 10:05:55'),
(70, 12, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"hello\"', 'post.php?id=49', 1, '2026-09-05 06:30:17'),
(71, 13, NULL, 'new_comment', 'Someone replied to your post: \"\"', 'post.php?id=47', 0, '2026-09-07 08:49:24'),
(72, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=50', 1, '2026-10-03 20:20:31'),
(73, 14, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=51', 1, '2026-10-04 09:15:19'),
(74, 14, NULL, 'consultation', 'Your consultation request has been submitted. A volunteer will reach out soon.', 'consultation.php?tab=active', 1, '2026-10-04 09:19:23'),
(75, 14, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=12', 0, '2026-10-04 09:24:43'),
(76, 14, NULL, 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id=12', 0, '2026-10-04 09:25:35'),
(77, 7, NULL, 'consultation_message', 'New message from a user you are supporting.', 'volunteer.php?tab=cases&case_id=12', 0, '2026-10-04 09:25:50'),
(78, 14, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=12', 0, '2026-10-04 09:26:14'),
(79, 11, 'AI Alert: critical risk post', 'ai_alert', 'Post #52 flagged for self-harm/suicide risk. Risk: critical.', 'post.php?id=52', 1, '2026-10-04 09:51:34'),
(80, 1, '🚨 CRITICAL: Self-harm risk detected', 'critical_alert', 'Post #52 requires immediate attention.', 'post.php?id=52', 1, '2026-10-04 09:51:34'),
(81, 14, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=52', 0, '2026-10-04 09:51:34'),
(82, 7, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"\"', 'post.php?id=53', 0, '2026-10-04 09:57:14'),
(83, 14, NULL, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id=13', 0, '2026-10-04 09:58:47'),
(84, 14, NULL, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id=13', 0, '2026-10-04 09:58:58'),
(85, 16, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"Ae Ajnabee Song\"', 'post.php?id=54', 0, '2026-10-24 20:18:55'),
(86, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"i am si\"', 'post.php?id=55', 1, '2026-10-25 09:20:17'),
(87, 7, NULL, 'message', 'nightowl sent you a message.', 'messages.php?t=1', 0, '2026-10-28 21:25:32'),
(88, 11, NULL, 'message', 'nightowl sent you a message.', 'messages.php?t=2', 1, '2026-10-28 21:25:45'),
(89, 1, NULL, 'consultation', 'New doctor application: Dr. Nadia Islam (Psychiatry). Please verify the licence.', 'admin_doctors.php', 1, '2026-10-28 21:30:04'),
(90, 2, NULL, 'consultation', 'Your doctor application was approved. Welcome to Haven! 🩺', 'doctor_panel.php', 1, '2026-10-28 21:32:16'),
(91, 14, NULL, 'new_comment', 'Someone replied to your post: \"\"', 'post.php?id=52', 0, '2026-10-28 21:32:33'),
(92, 16, NULL, 'friend_request', 'anos sent you a friend request.', 'profile.php?id=2', 0, '2026-10-28 21:33:17'),
(93, 1, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"why night is so long? why darkness became the reas\"', 'post.php?id=56', 1, '2026-10-28 22:21:32'),
(94, 3, NULL, 'comment_reply', 'Someone replied to your comment.', 'post.php?id=56', 0, '2026-10-28 22:22:45'),
(95, 2, NULL, 'message_request', 'Admin would like to message you. You can accept or decline.', 'messages.php?box=requests', 1, '2026-10-28 22:30:39'),
(96, 1, NULL, 'message_accept', 'Dr. Nadia Islam accepted your message request. You can chat now.', 'messages.php?t=3', 0, '2026-10-28 22:30:58'),
(97, 14, NULL, 'message_request', 'Dr. Nadia Islam would like to message you. You can accept or decline.', 'messages.php?box=requests', 0, '2026-10-28 22:37:40'),
(98, 2, NULL, 'badge', 'You earned a new badge: First Journal!', 'dashboard.php#badges', 1, '2026-10-29 10:50:39'),
(99, 2, NULL, 'badge', 'You earned a new badge: First Focus Session!', 'dashboard.php#badges', 1, '2026-10-29 11:18:45'),
(100, 2, 'MindGuide replied to your post', 'ai_reply', 'MindGuide AI provided support on your post: \"hi\"', 'post.php?id=57', 1, '2026-10-29 11:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `otp_code` varchar(255) NOT NULL,
  `type` enum('email','phone','reset_password') NOT NULL,
  `target` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `otp_verifications`
--

INSERT INTO `otp_verifications` (`id`, `user_id`, `otp_code`, `type`, `target`, `expires_at`, `is_used`, `attempts`, `created_at`) VALUES
(6, NULL, '$2y$10$u1kYQ6xNeZ91zFEwM8f0g.xU7fXFzkeFnDrdUSycDnx1gnmne68oC', 'email', 'hlwman449@gmail.com', '2026-08-20 22:11:14', 1, 0, '2026-08-20 22:01:14'),
(7, NULL, '$2y$10$8EQHwWceief1zFhXQuTbg.r9ylrQQZEzesNTibXAqh5WJdY5gTQVa', 'email', 'mtareqrahman50@gmail.com', '2026-09-05 09:57:41', 0, 0, '2026-09-05 09:47:41'),
(8, NULL, '$2y$10$kKbQKsl2VW4l8bhzGMfNmuLVpfd2JDpwc6I808e0mubH6gLPf626C', 'email', 'mtareqrahman50@gmail.com', '2026-09-05 09:57:50', 0, 0, '2026-09-05 09:47:50'),
(9, NULL, '$2y$10$MdUyRzOvc.RcBM1Iu2NIvO73hsVNDjwObGfWBa9hp7IctcQWZKBR.', 'email', 'tahmidahmid601@gmail.com', '2026-09-07 08:27:59', 0, 0, '2026-09-07 08:17:59'),
(10, NULL, '$2y$10$PfYe9KPNYf.fHKPpTcJrS.IJr7O0pUyzkWKQGup5jTDVauFP/MlmG', 'email', 'tahmidahmid601@gmail.com', '2026-09-07 08:28:09', 0, 0, '2026-09-07 08:18:09'),
(11, NULL, '$2y$10$.PUL9itmbS6SpR0mXcIjYOzu85OVuxgnBCJn2t1wKJiXEgYvYsaxu', 'email', 'hlwman449@gmail.com', '2026-10-24 20:25:48', 1, 0, '2026-10-24 20:15:48'),
(12, NULL, '$2y$10$Uc8GvLFv.M//NiKo4Q8aKewiEIAz7XCB2gnnO4nQ.XH5UXF7Y.CN6', 'email', 'ddws@gmail.com', '2026-10-29 10:57:15', 0, 0, '2026-10-29 10:47:15');

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
(14, 2, '9e96f17adedfa58624bd9f204ba013576ac5280114bf460c1921aa585e155e96', '2026-10-03 19:26:09', '2026-10-03 18:26:09');

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
  `content` text DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 1,
  `location` varchar(200) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `status` enum('pending_ai','published','flagged','deleted') DEFAULT 'pending_ai',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ai_analysis_id` int(11) DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `reaction_count` int(11) DEFAULT 0,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `external_type` varchar(30) DEFAULT NULL,
  `share_count` int(11) NOT NULL DEFAULT 0,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `place_name` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `category`, `mood`, `title`, `content`, `is_anonymous`, `location`, `tags`, `status`, `created_at`, `updated_at`, `ai_analysis_id`, `risk_score`, `comment_count`, `reaction_count`, `media_url`, `media_type`, `external_url`, `external_type`, `share_count`, `lat`, `lng`, `place_name`) VALUES
(19, 2, '', 'sad', 'need', 'I\'ve been feeling really down for the past few weeks. I don\'t enjoy the things I used to, and I feel like I\'m slowly losing motivation.', 1, '', '', 'published', '2026-08-04 02:22:54', '2026-08-04 02:23:09', 19, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(20, 2, '', 'stressed', 'x', 'I\'ve had a rough day at university. Nothing seems to be going right, and I\'m feeling pretty discouraged. I think I\'ll take a break tonight and try again tomorrow.', 1, '', '', 'published', '2026-08-04 02:26:33', '2026-08-16 10:40:19', 20, 20, 2, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(21, 2, '', 'stressed', 'down', 'I\'ve been feeling really down for the past few weeks. I don\'t enjoy the things I used to, and I feel like I\'m slowly losing motivation.', 1, '', '', 'published', '2026-08-04 02:48:55', '2026-08-04 02:49:16', 21, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(22, 2, '', 'sad', 'suicide', 'I don\'t want to be here anymore. I\'ve been thinking a lot about ending everything because I can\'t see any future for myself. I\'m exhausted, and I don\'t think I can keep going.', 1, '', '', 'published', '2026-08-04 02:52:34', '2026-08-04 02:53:21', 22, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(23, 2, '', 'sad', 'dissaoear', 'I honestly don\'t see any reason to keep going anymore. Everyone would probably be happier if I just disappeared.', 1, '', '', 'published', '2026-08-04 03:03:27', '2026-08-04 03:04:21', 23, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(24, 2, '', 'sad', 'dg', 'I honestly don\'t see any reason to keep going anymore. Everyone would probably be happier if I just disappeared.', 1, '', '', 'published', '2026-08-04 03:05:35', '2026-08-14 09:09:14', 24, 20, 6, 2, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(25, 12, 'Depressed', 'stressed', 'thoughts about hurting myself', 'For the past few weeks, I\'ve been having increasingly serious thoughts about hurting myself. At first, I thought they were just thoughts that would eventually disappear, but they\'re becoming harder to ignore. I\'m scared by how often I\'m thinking about not being here anymore. I\'ve also started thinking about when and where I might hurt myself, which makes me realize that this is more serious than I wanted to admit.', 1, '', '', 'flagged', '2026-08-12 21:29:18', '2026-09-04 21:06:24', 25, 80, 3, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(26, 12, '', 'happy', 'hi', 'hi', 1, '', '', 'published', '2026-08-13 19:22:36', '2026-08-13 21:38:19', 26, 20, 2, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(27, 12, '', 'sad', 'hopeless', 'I don\'t think I can keep going anymore. Everything feels completely hopeless, and I feel like everyone would be better off without me. I\'m thinking about hurting myself tonight, and I\'m scared that I might actually do something. I don\'t know who to talk to or what to do right now.', 1, NULL, '', 'published', '2026-08-14 00:31:45', '2026-08-14 00:39:42', NULL, 0, 1, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(28, 12, '', 'sad', 'I’m scared', 'I’ve been feeling completely hopeless lately. I keep thinking that everyone would be better off without me, and thoughts of killing myself have been coming back again and again. I don’t know how much longer I can handle this. I’m scared by how serious these thoughts are.', 1, NULL, '', 'flagged', '2026-08-14 00:50:24', '2026-08-14 00:50:29', 27, 80, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(29, 12, '', 'tired', '', 'I’ve been feeling a little down and overwhelmed with everything lately. I’m tired and could really use some time to relax and clear my head, but I’m hoping things will feel better soon.', 1, NULL, '', 'published', '2026-08-14 00:51:33', '2026-08-14 09:27:02', 28, 20, 2, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(30, 14, '', 'sad', '', 'i am feeling sick, cough and fever. what to do', 1, NULL, '', 'published', '2026-08-15 08:40:39', '2026-08-15 08:40:44', 29, 50, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(31, 2, '', 'okay', '', 'hi', 1, NULL, '', 'published', '2026-08-25 20:25:57', '2026-08-25 20:26:00', 30, 20, 1, 0, 'uploads/community/cm_6a8dfa5551d52.png', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(32, 2, '', 'okay', '', 'hi', 1, NULL, '', 'published', '2026-08-25 20:26:00', '2026-08-25 20:26:05', 31, 20, 1, 0, 'uploads/community/cm_6a8dfa58eee11.png', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(33, 2, '', 'sad', '', 'i am feelong low and down', 1, NULL, '', 'published', '2026-08-25 20:26:45', '2026-08-25 20:26:49', 32, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(34, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:35:53', '2026-09-04 20:36:35', 33, 50, 1, 0, 'uploads/community/cm_6a9b2ba9ded60.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(35, 12, '', 'happy', '', 'hi', 1, NULL, '', 'published', '2026-09-04 20:35:58', '2026-09-04 21:06:01', 34, 50, 2, 1, 'uploads/community/cm_6a9b2baecc964.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(36, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:03', '2026-09-04 20:36:48', 35, 50, 1, 0, 'uploads/community/cm_6a9b2bb3031d1.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(37, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:06', '2026-09-04 20:36:25', 36, 50, 1, 0, 'uploads/community/cm_6a9b2bb697736.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(38, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:10', '2026-09-04 20:36:25', 37, 50, 1, 0, 'uploads/community/cm_6a9b2bba83556.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(39, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:15', '2026-09-04 20:36:25', 38, 50, 1, 0, 'uploads/community/cm_6a9b2bbfbcd53.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(40, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:18', '2026-09-04 20:36:43', 39, 50, 1, 0, 'uploads/community/cm_6a9b2bc264e9f.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(41, 12, '', 'happy', '', 'hi', 1, NULL, '', 'deleted', '2026-09-04 20:36:22', '2026-09-04 21:05:08', 40, 50, 1, 1, 'uploads/community/cm_6a9b2bc666b69.jpeg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(42, 13, '', '', '', 'It’s like being in a glass box where you can see everyone else going about their lives, but you can\'t quite reach them', 1, NULL, '', 'published', '2026-09-05 09:55:26', '2026-09-05 09:57:53', 41, 50, 3, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(43, 13, '', '', '', 'It’s like being in a glass box where you can see everyone else going about their lives, but you can\'t quite reach them', 1, NULL, '', 'deleted', '2026-09-05 09:55:32', '2026-09-05 09:55:53', 42, 50, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(44, 13, '', '', '', 'It’s like being in a glass box where you can see everyone else going about their lives, but you can\'t quite reach them', 1, NULL, '', 'deleted', '2026-09-05 09:55:36', '2026-09-05 09:55:49', 43, 50, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(45, 13, '', '', '', 'It’s like being in a glass box where you can see everyone else going about their lives, but you can\'t quite reach them', 1, NULL, '', 'deleted', '2026-09-05 09:55:41', '2026-09-07 08:48:49', NULL, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(46, 14, '', 'tired', 'Class is finally over. Now I’m just tired and done. 😮‍💨🎓', 'Class is finally over. Now I’m just tired and done. 😮‍💨🎓', 1, '', '', 'published', '2026-09-05 09:57:42', '2026-09-05 10:05:31', 44, 20, 2, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(47, 13, '', '', '', 'I  just want to suicide or harm my self', 1, NULL, '', 'flagged', '2026-09-05 10:01:30', '2026-09-07 08:49:24', 45, 80, 2, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(48, 14, '', 'okay', 'Suddenly got into a bike accident at Bahaddarhat. Alhamdulillah', 'Suddenly got into a bike accident at Bahaddarhat. Alhamdulillah, I’m safe. Please keep me in your prayers. 🤲❤️', 1, '', '', 'published', '2026-09-05 10:01:57', '2026-10-28 22:25:20', 46, 20, 3, 2, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(49, 12, '', '', 'hello', 'hello', 1, '', '', 'published', '2026-09-05 12:30:12', '2026-09-05 12:30:17', 47, 50, 1, 0, 'uploads/community/post_6a9c0b54dd7ba.jpg', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(50, 2, '', '', '', 'hi. i am feeling sick', 1, NULL, '', 'published', '2026-10-03 20:20:27', '2026-10-03 20:20:31', 48, 20, 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(51, 14, '', 'sad', '', 'i just got 00 marks on my Ai exam', 1, NULL, '', 'published', '2026-10-04 09:15:13', '2026-10-28 22:24:45', 49, 20, 2, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(52, 14, '', '', '', 'i want to suicide', 1, NULL, '', 'flagged', '2026-10-04 09:51:32', '2026-10-28 21:32:33', 50, 100, 2, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(53, 7, '', '', '', 'i am feeling sick', 1, NULL, '', 'published', '2026-10-04 09:57:08', '2026-10-28 21:27:37', 51, 20, 1, 1, 'uploads/community/cm_6ac222f43f3aa.png', 'image', NULL, NULL, 0, NULL, NULL, NULL),
(54, 16, '', 'okay', 'Ae Ajnabee Song', 'Ae Ajnabee Song', 1, '', '', 'published', '2026-10-24 20:18:49', '2026-10-24 20:19:55', 52, 20, 1, 0, 'uploads/community/post_3544984986ec8436.mp4', 'video', NULL, NULL, 0, NULL, NULL, NULL),
(55, 2, '', '', 'i am si', 'i am si', 1, 'Bangladesh', '', 'deleted', '2026-10-25 09:20:11', '2026-10-25 09:21:48', 53, 20, 1, 0, 'uploads/community/post_6addc9cb8c051.mp4', 'video', NULL, NULL, 0, NULL, NULL, NULL),
(56, 1, '', 'tired', 'why night is so long? why darkness became the reason of my l', 'why night is so long? why darkness became the reason of my low confidence, my fear. All the motivation comes at night.', 1, '', '', 'published', '2026-10-28 22:21:26', '2026-10-28 22:23:52', 54, 20, 2, 3, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(57, 2, '', '', 'hi', 'hi', 1, 'Shibchar', '', 'published', '2026-10-29 11:20:23', '2026-10-29 11:20:31', 55, 20, 1, 0, 'uploads/community/post_dcefa7ff578e3f29.png', 'image', NULL, NULL, 0, NULL, NULL, 'Shibchar');

-- --------------------------------------------------------

--
-- Table structure for table `post_emotions`
--

CREATE TABLE `post_emotions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `top_label` varchar(30) NOT NULL,
  `top_score` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `app_emotion` varchar(20) NOT NULL DEFAULT 'neutral',
  `sentiment` decimal(3,2) NOT NULL DEFAULT 0.50,
  `scores` text NOT NULL,
  `model_version` varchar(40) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'tflite-browser',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_emotions`
--

INSERT INTO `post_emotions` (`id`, `post_id`, `user_id`, `top_label`, `top_score`, `app_emotion`, `sentiment`, `scores`, `model_version`, `source`, `created_at`) VALUES
(1, 50, 2, 'sadness', 0.3636, 'sad', 0.14, '[0.0034,0.0012,0.0374,0.073,0.0303,0.0285,0.0079,0.0098,0.0265,0.1618,0.0233,0.033,0.0228,0.0093,0.0445,0.0014,0.0136,0.0026,0.0136,0.0276,0.0114,0.0023,0.0309,0.0053,0.0126,0.3636,0.0058,0.159]', 'goemotions-tflite-v1', 'tflite-server', '2026-10-03 20:20:27'),
(2, 51, 14, 'neutral', 0.6788, 'neutral', 0.60, '[0.0033,0.004,0.0049,0.0159,0.0637,0.0055,0.01,0.0144,0.0112,0.0217,0.0114,0.003,0.003,0.0146,0.0033,0.0012,0.0014,0.0058,0.0065,0.0027,0.0055,0.001,0.0462,0.0029,0.001,0.0168,0.0076,0.6788]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-04 09:15:13'),
(3, 52, 14, 'neutral', 0.1646, 'neutral', 0.64, '[0.0067,0.0014,0.0038,0.0155,0.0877,0.0165,0.0044,0.0055,0.1603,0.066,0.0226,0.0011,0.0037,0.0131,0.0096,0.001,0.003,0.0073,0.0542,0.0076,0.0304,0.0019,0.0361,0.0046,0.0033,0.0805,0.004,0.1646]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-04 09:51:32'),
(4, 53, 7, 'sadness', 0.4323, 'sad', 0.12, '[0.0034,0.0014,0.0445,0.0816,0.037,0.0254,0.0088,0.0095,0.027,0.1807,0.0258,0.0401,0.0357,0.0091,0.0672,0.0012,0.0165,0.0026,0.0088,0.0334,0.0124,0.0028,0.0287,0.0049,0.0164,0.4323,0.0081,0.1052]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-04 09:57:08'),
(5, 54, 16, 'neutral', 0.8974, 'neutral', 0.62, '[0.0045,0.001,0.0035,0.0071,0.018,0.0051,0.0031,0.0155,0.0014,0.0052,0.0049,0.0012,0.0007,0.0079,0.0005,0.0002,0.0003,0.0022,0.0016,0.0005,0.0022,0.0003,0.0088,0.0005,0.0001,0.0034,0.0026,0.8974]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-24 20:18:49'),
(6, 55, 2, 'neutral', 0.3898, 'neutral', 0.35, '[0.0055,0.0037,0.0202,0.0366,0.0363,0.0031,0.0218,0.029,0.0075,0.0361,0.0116,0.0101,0.0075,0.0219,0.0166,0.0013,0.0025,0.0049,0.005,0.0064,0.0021,0.0019,0.0482,0.0034,0.0014,0.0335,0.0335,0.3898]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-25 09:20:11'),
(7, 56, 1, 'surprise', 0.1866, 'neutral', 0.21, '[0.0277,0.0188,0.0352,0.0731,0.0192,0.002,0.0362,0.0648,0.0066,0.0713,0.0098,0.0199,0.0382,0.0294,0.1808,0.0008,0.0039,0.0139,0.0097,0.0195,0.0023,0.003,0.0364,0.0035,0.0022,0.0689,0.1866,0.0695]', 'goemotions-tflite-v1', 'tflite-browser', '2026-10-28 22:21:26'),
(8, 57, 2, 'neutral', 0.9746, 'neutral', 0.71, '[0.0014,0.0002,0.0015,0.0018,0.0038,0.0054,0.0006,0.006,0.0003,0.0011,0.0013,0.0002,0.0001,0.0027,0.0001,0,0,0.001,0.0018,0.0001,0.001,0,0.0029,0.0001,0,0.0009,0.0001,0.9746]', 'goemotions-tflite-v1', 'tflite-server', '2026-10-29 11:20:24');

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
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `source` enum('uploaded','issued') NOT NULL DEFAULT 'uploaded',
  `title` varchar(160) NOT NULL,
  `issued_on` date DEFAULT NULL,
  `doctor_name_text` varchar(160) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `ocr_text` mediumtext DEFAULT NULL,
  `ocr_engine` varchar(30) DEFAULT NULL,
  `medicines` mediumtext DEFAULT NULL,
  `advice` mediumtext DEFAULT NULL,
  `follow_up` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `patient_id`, `doctor_id`, `appointment_id`, `source`, `title`, `issued_on`, `doctor_name_text`, `file_path`, `ocr_text`, `ocr_engine`, `medicines`, `advice`, `follow_up`, `created_at`, `deleted_at`) VALUES
(1, 2, NULL, NULL, 'uploaded', 'Prescription', NULL, NULL, 'uploads/private/rx/53f51b0343f4f84389f49895cb5a57b9.jpg', 'v1:Rpy/RFQjif6YMYw3hFYFMsw7TKtDtm3r89rgxB49aYygahxjIAmcyW30Npiyvn7XceVoNZZnq4pbdGujjf7p7+NPY53ICDobW+uSQ+RmyW+8ZxR/OWd5of68+kcKO83RoQOE+RBETHIjlIiRnP1r3u5N7o4e0DoQ6mYcnjnWG32JKMhKPJKVQ8c4', 'tesseract', 'v1:8571SpHvJI3dg5C+vAlYl+bDd7ehXA77AvckVSWI', '', NULL, '2026-10-29 03:28:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescription_shares`
--

CREATE TABLE `prescription_shares` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `shared_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL
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
  `privacy` enum('public','friends','private') DEFAULT 'public',
  `current_chapter` varchar(255) DEFAULT 'Taking things one day at a time.',
  `current_mood` varchar(50) DEFAULT 'Good',
  `current_mood_emoji` varchar(10) DEFAULT '?',
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `education` varchar(100) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `language` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`user_id`, `anonymous_name`, `avatar_color`, `mood_energy`, `mood_stress`, `mood_sleep`, `cover_url`, `privacy`, `current_chapter`, `current_mood`, `current_mood_emoji`, `bio`, `avatar_url`, `occupation`, `education`, `country`, `city`, `language`, `birth_date`, `gender`) VALUES
(2, 'anos', '#5B8DEF', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'MindGuide AI', '#5B8DEF', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Peaceful Owl', '#FBBF24', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Kind Moon', '#5B8DEF', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Moderator', '#5B8DEF', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'bhhb', '#9c9c9c', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'nafs', '#5B8DEF', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Tareq', '#8ea8a1', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'saasdd', '#D5A66B', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, '🌿 Hopeful Owl', '#D5A66B', 5, 5, 5, NULL, 'public', 'Taking things one day at a time.', 'Good', '🌱', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rate_events`
--

CREATE TABLE `rate_events` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(40) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_events`
--

INSERT INTO `rate_events` (`id`, `user_id`, `action`, `created_at`) VALUES
(11, 1, 'dm_request', '2026-10-29 04:30:39'),
(10, 1, 'dm_send', '2026-10-29 04:30:39'),
(6, 1, 'story', '2026-10-29 04:19:01'),
(7, 1, 'story', '2026-10-29 04:19:07'),
(8, 1, 'story', '2026-10-29 04:19:12'),
(9, 1, 'story', '2026-10-29 04:19:19'),
(15, 2, 'dm_request', '2026-10-29 04:37:40'),
(1, 2, 'dm_send', '2026-10-29 03:25:32'),
(2, 2, 'dm_send', '2026-10-29 03:25:45'),
(5, 2, 'dm_send', '2026-10-29 03:58:59'),
(12, 2, 'dm_send', '2026-10-29 04:31:03'),
(13, 2, 'dm_send', '2026-10-29 04:31:14'),
(14, 2, 'dm_send', '2026-10-29 04:37:40'),
(3, 2, 'rx_save', '2026-10-29 03:28:57'),
(4, 11, 'dm_send', '2026-10-29 03:58:26');

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
  `reaction_type` enum('like','dislike','support','empathy','helpful') DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `emoji` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reactions`
--

INSERT INTO `reactions` (`id`, `post_id`, `user_id`, `reaction_type`, `created_at`, `emoji`) VALUES
(11, 24, 2, 'like', '2026-08-05 20:17:25', 'undefined'),
(24, 26, 12, 'support', '2026-09-02 22:03:33', '🤗'),
(27, 24, 12, 'empathy', '2026-08-14 00:32:10', 'prayers'),
(28, 27, 12, 'support', '2026-08-14 00:39:42', '🌿'),
(29, 41, 1, 'like', '2026-09-04 21:05:08', '❤️'),
(30, 35, 1, 'like', '2026-09-04 21:06:01', '❤️'),
(31, 25, 1, 'support', '2026-09-04 21:06:24', '🤗'),
(35, 42, 12, 'empathy', '2026-09-05 09:56:13', 'prayers'),
(37, 48, 12, 'helpful', '2026-09-05 10:05:26', 'helpful'),
(38, 46, 12, 'helpful', '2026-09-05 10:05:31', 'inspiring'),
(39, 48, 13, 'empathy', '2026-09-05 10:05:32', 'hug'),
(41, 47, 1, 'support', '2026-09-07 08:49:03', '🤗'),
(46, 51, 16, 'like', '2026-10-24 20:20:14', 'like'),
(47, 53, 2, 'dislike', '2026-10-28 21:27:37', 'dislike'),
(48, 56, 1, 'empathy', '2026-10-28 22:22:59', '💙'),
(53, 56, 2, 'like', '2026-10-28 22:23:34', '👍'),
(54, 56, 11, 'like', '2026-10-28 22:23:52', 'like');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
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
  `target_type` enum('post','comment','user','profile','message','story') DEFAULT 'post'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `post_id`, `reason`, `description`, `status`, `created_at`, `priority`, `moderator_id`, `action_taken`, `resolution_notes`, `resolved_at`, `target_id`, `target_type`) VALUES
(1, 12, 24, 'harassment', '', 'resolved', '2026-08-13 21:51:45', 'medium', NULL, NULL, NULL, NULL, 0, 'post'),
(2, 2, 54, 'spam', '', 'resolved', '2026-10-25 09:20:04', 'medium', NULL, NULL, NULL, NULL, 54, 'post'),
(3, 2, 51, 'misinformation', '', 'pending', '2026-10-28 22:38:20', 'medium', NULL, NULL, NULL, NULL, 51, 'post');

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

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'hv_schema_version', '3', '2026-10-28 21:23:44');

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
  `rating` tinyint(3) UNSIGNED DEFAULT 5,
  `author` varchar(100) DEFAULT 'Anonymous',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `content`, `rating`, `author`, `created_at`) VALUES
(1, 'a', 5, 'a', '2026-09-05 08:55:35'),
(2, '\';', 5, 'A Haven member', '2026-09-06 11:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `typing_status`
--

CREATE TABLE `typing_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `typing_status`
--

INSERT INTO `typing_status` (`id`, `user_id`, `post_id`, `timestamp`) VALUES
(1, 12, 24, '2026-08-14 04:50:07'),
(8, 1, 41, '2026-09-05 03:05:24');

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
  `role` enum('user','volunteer','moderator','admin','ai','doctor') DEFAULT 'user',
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

--
-- Dumping data for table `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_id`, `earned_at`) VALUES
(1, 2, 2, '2026-10-29 10:50:39'),
(2, 2, 7, '2026-10-29 11:18:45');

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

--
-- Dumping data for table `volunteer_followups`
--

INSERT INTO `volunteer_followups` (`id`, `case_id`, `volunteer_id`, `followup_date`, `note`, `status`, `created_at`) VALUES
(1, 4, 7, '2026-09-14', '', 'pending', '2026-09-02 22:14:24'),
(2, 5, 7, '2026-09-15', '', 'pending', '2026-09-04 21:12:02');

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

--
-- Dumping data for table `volunteer_notes`
--

INSERT INTO `volunteer_notes` (`id`, `case_id`, `volunteer_id`, `note`, `created_at`) VALUES
(1, 4, 7, 'oo', '2026-09-02 22:14:17');

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

-- --------------------------------------------------------

--
-- Table structure for table `wellness_activities`
--

CREATE TABLE `wellness_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(40) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `completed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_time` (`doctor_id`,`starts_at`),
  ADD KEY `idx_patient` (`patient_id`,`starts_at`);

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
-- Indexes for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`user_id`,`blocked_id`),
  ADD UNIQUE KEY `uq_block` (`user_id`,`blocked_id`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bookmark` (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `idx_bookmarks_user_post` (`user_id`,`post_id`);

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
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_comments_post_created` (`post_id`,`created_at`);

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
-- Indexes for table `dm_messages`
--
ALTER TABLE `dm_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread` (`thread_id`,`id`),
  ADD KEY `idx_unread` (`thread_id`,`sender_id`,`read_at`);

--
-- Indexes for table `dm_threads`
--
ALTER TABLE `dm_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pair` (`user_a`,`user_b`),
  ADD KEY `idx_a` (`user_a`,`last_message_at`),
  ADD KEY `idx_b` (`user_b`,`last_message_at`);

--
-- Indexes for table `dm_user_state`
--
ALTER TABLE `dm_user_state`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_status` (`status`,`specialty`);

--
-- Indexes for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review` (`doctor_id`,`patient_id`);

--
-- Indexes for table `doctor_slots`
--
ALTER TABLE `doctor_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc` (`doctor_id`,`weekday`);

--
-- Indexes for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`started_at`);

--
-- Indexes for table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follower` (`user_id`,`follower_id`),
  ADD UNIQUE KEY `uq_follow` (`user_id`,`follower_id`),
  ADD KEY `idx_followers_follower` (`follower_id`,`user_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friend` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`),
  ADD KEY `idx_friend_pair` (`user_id`,`friend_id`);

--
-- Indexes for table `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hidden` (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

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
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_posts_feed` (`status`,`created_at`),
  ADD KEY `idx_posts_category` (`category`,`status`,`created_at`);

--
-- Indexes for table `post_emotions`
--
ALTER TABLE `post_emotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_post` (`post_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_label` (`top_label`);

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
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient` (`patient_id`,`created_at`),
  ADD KEY `idx_doctor` (`doctor_id`);

--
-- Indexes for table `prescription_shares`
--
ALTER TABLE `prescription_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_share` (`prescription_id`,`doctor_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `rate_events`
--
ALTER TABLE `rate_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rate` (`user_id`,`action`,`created_at`);

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
  ADD UNIQUE KEY `uq_reaction_user_post` (`user_id`,`post_id`),
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
-- Indexes for table `typing_status`
--
ALTER TABLE `typing_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_post` (`user_id`,`post_id`);

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
-- Indexes for table `wellness_activities`
--
ALTER TABLE `wellness_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`completed_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_analysis`
--
ALTER TABLE `ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `ai_logs`
--
ALTER TABLE `ai_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

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
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1147;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `blocked_users`
--
ALTER TABLE `blocked_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_ai_analysis`
--
ALTER TABLE `chat_ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `comment_ai_analysis`
--
ALTER TABLE `comment_ai_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comment_reactions`
--
ALTER TABLE `comment_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `consultation_messages`
--
ALTER TABLE `consultation_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `dm_messages`
--
ALTER TABLE `dm_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dm_threads`
--
ALTER TABLE `dm_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_slots`
--
ALTER TABLE `doctor_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `followers`
--
ALTER TABLE `followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `goals`
--
ALTER TABLE `goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `journals`
--
ALTER TABLE `journals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mood_entries`
--
ALTER TABLE `mood_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `mood_logs`
--
ALTER TABLE `mood_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `post_emotions`
--
ALTER TABLE `post_emotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_shares`
--
ALTER TABLE `prescription_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_events`
--
ALTER TABLE `rate_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reactions`
--
ALTER TABLE `reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `typing_status`
--
ALTER TABLE `typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_meta`
--
ALTER TABLE `user_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_followups`
--
ALTER TABLE `volunteer_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `volunteer_notes`
--
ALTER TABLE `volunteer_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `volunteer_training`
--
ALTER TABLE `volunteer_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wellness_activities`
--
ALTER TABLE `wellness_activities`
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
-- Constraints for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD CONSTRAINT `hidden_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hidden_posts_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

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
