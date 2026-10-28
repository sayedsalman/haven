-- Run once in phpMyAdmin (cPanel) on database rfnhscco_mind
CREATE TABLE IF NOT EXISTS `post_emotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `top_label` varchar(30) NOT NULL,            -- raw GoEmotions label, e.g. 'nervousness'
  `top_score` decimal(5,4) NOT NULL DEFAULT 0,
  `app_emotion` varchar(20) NOT NULL DEFAULT 'neutral', -- happy/sad/stressed/angry/calm/hopeful/neutral
  `sentiment` decimal(3,2) NOT NULL DEFAULT 0.50,       -- 0 negative … 1 positive
  `scores` text NOT NULL,                      -- JSON array of the 28 GoEmotions scores
  `model_version` varchar(40) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'tflite-browser',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_post` (`post_id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_label` (`top_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
