-- ============================================================
-- HAVEN - requirements migration (safe to run more than once)
-- Run in phpMyAdmin on your live database (MariaDB).
-- Adds: dislike reaction, ratings, phone verification,
--       dependent dropdown data, coping items, report views,
--       post_emotions (stores the TensorFlow Lite results - missing on the live DB).
-- ============================================================
SET NAMES utf8mb4;

-- #3 Like / Dislike : extend the reaction enum (existing rows are kept)
ALTER TABLE reactions
  MODIFY reaction_type ENUM('like','dislike','support','empathy','helpful') DEFAULT 'like';

-- #7 Ratings (stars) for posts and for the whole site
CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  target_type ENUM('post','site') NOT NULL DEFAULT 'post',
  target_id INT NOT NULL DEFAULT 0,
  stars TINYINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rating (user_id, target_type, target_id),
  KEY idx_rating_target (target_type, target_id),
  CONSTRAINT fk_rating_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #5 Phone verification (OTP codes are stored hashed)
CREATE TABLE IF NOT EXISTS phone_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(25) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_phone (phone, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS city VARCHAR(80) NULL;

-- #6 Dependent drop-downs (country -> city) and multiple text boxes
CREATE TABLE IF NOT EXISTS countries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_city (country_id, name),
  CONSTRAINT fk_city_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_coping_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'activity',
  item VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_coping_user (user_id),
  CONSTRAINT fk_coping_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO countries (name) VALUES
 ('Bangladesh'),('India'),('Pakistan'),('Nepal'),('United States'),('United Kingdom'),('Canada'),('Australia');
INSERT IGNORE INTO cities (country_id, name)
 SELECT c.id, x.city FROM countries c JOIN (
  SELECT 'Bangladesh' country,'Dhaka' city UNION ALL SELECT 'Bangladesh','Chattogram' UNION ALL SELECT 'Bangladesh','Khulna'
  UNION ALL SELECT 'Bangladesh','Rajshahi' UNION ALL SELECT 'Bangladesh','Sylhet' UNION ALL SELECT 'Bangladesh','Barishal'
  UNION ALL SELECT 'India','Delhi' UNION ALL SELECT 'India','Mumbai' UNION ALL SELECT 'India','Kolkata' UNION ALL SELECT 'India','Chennai'
  UNION ALL SELECT 'Pakistan','Karachi' UNION ALL SELECT 'Pakistan','Lahore' UNION ALL SELECT 'Pakistan','Islamabad'
  UNION ALL SELECT 'Nepal','Kathmandu' UNION ALL SELECT 'Nepal','Pokhara'
  UNION ALL SELECT 'United States','New York' UNION ALL SELECT 'United States','Los Angeles' UNION ALL SELECT 'United States','Chicago'
  UNION ALL SELECT 'United Kingdom','London' UNION ALL SELECT 'United Kingdom','Manchester'
  UNION ALL SELECT 'Canada','Toronto' UNION ALL SELECT 'Canada','Vancouver'
  UNION ALL SELECT 'Australia','Sydney' UNION ALL SELECT 'Australia','Melbourne'
 ) x ON x.country = c.name;

-- #20 Crystal Report data sources (use these views as the report's tables)
CREATE OR REPLACE VIEW v_report_emotion_summary AS
  SELECT COALESCE(NULLIF(a.emotion,''),'unknown') AS emotion,
         COUNT(*) AS posts_count,
         ROUND(AVG(a.risk_score),1) AS avg_risk,
         MIN(a.created_at) AS first_seen, MAX(a.created_at) AS last_seen
  FROM ai_analysis a GROUP BY COALESCE(NULLIF(a.emotion,''),'unknown');

CREATE OR REPLACE VIEW v_report_post_engagement AS
  SELECT p.id AS post_id, p.title, p.category, p.mood, p.created_at,
         (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id) AS comments_count,
         (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.reaction_type='like') AS likes,
         (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.reaction_type='dislike') AS dislikes,
         (SELECT ROUND(AVG(stars),2) FROM ratings x WHERE x.target_type='post' AND x.target_id=p.id) AS avg_rating
  FROM posts p WHERE p.status <> 'deleted';

-- #9 external video/audio embeds on posts (already added by community_fix.sql on most installs)
ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS external_url VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS external_type VARCHAR(30) NULL;

-- #12 TensorFlow Lite results per post (same table as database/ml_emotions.sql; IF NOT EXISTS so it is safe)
CREATE TABLE IF NOT EXISTS `post_emotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `top_label` varchar(30) NOT NULL,
  `top_score` decimal(5,4) NOT NULL DEFAULT 0,
  `app_emotion` varchar(20) NOT NULL DEFAULT 'neutral',
  `sentiment` decimal(3,2) NOT NULL DEFAULT 0.50,
  `scores` text NOT NULL,
  `model_version` varchar(40) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'tflite-browser',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_post` (`post_id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_label` (`top_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
