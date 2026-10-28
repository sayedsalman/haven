-- Haven v2 upgrade (safe to run more than once; MariaDB). It also runs automatically on first page load.
SET NAMES utf8mb4;

ALTER TABLE reactions MODIFY reaction_type ENUM('like','dislike','support','empathy','helpful') DEFAULT 'like';

ALTER TABLE reports MODIFY post_id INT NULL DEFAULT NULL;

ALTER TABLE reports MODIFY target_type ENUM('post','comment','user','profile','message','story') DEFAULT 'post';

ALTER TABLE reports MODIFY reason VARCHAR(100) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS bio TEXT NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS occupation VARCHAR(100) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS education VARCHAR(100) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS country VARCHAR(80) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS city VARCHAR(80) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS language VARCHAR(40) NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS birth_date DATE NULL;

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS gender VARCHAR(30) NULL;

ALTER TABLE users MODIFY role ENUM('user','volunteer','moderator','admin','ai','doctor') DEFAULT 'user';

ALTER TABLE posts ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) NULL;

ALTER TABLE posts ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) NULL;

ALTER TABLE posts ADD COLUMN IF NOT EXISTS place_name VARCHAR(200) NULL;

ALTER TABLE posts MODIFY location VARCHAR(200) NULL;

ALTER TABLE stories MODIFY content VARCHAR(280) NOT NULL DEFAULT '';

ALTER TABLE stories ADD COLUMN IF NOT EXISTS media_url VARCHAR(255) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS media_type VARCHAR(10) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS bg VARCHAR(20) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS place_name VARCHAR(200) NULL;

ALTER TABLE stories ADD COLUMN IF NOT EXISTS audience ENUM('everyone','friends') NOT NULL DEFAULT 'everyone';

ALTER TABLE stories ADD COLUMN IF NOT EXISTS is_hidden TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE stories ADD INDEX IF NOT EXISTS idx_story_user (user_id, expires_at);

ALTER TABLE story_views ADD UNIQUE INDEX IF NOT EXISTS uniq_view (story_id, user_id);

ALTER TABLE followers ADD UNIQUE INDEX IF NOT EXISTS uq_follow (user_id, follower_id);

ALTER TABLE friends ADD INDEX IF NOT EXISTS idx_friend_pair (user_id, friend_id);

ALTER TABLE blocked_users ADD UNIQUE INDEX IF NOT EXISTS uq_block (user_id, blocked_id);

CREATE TABLE IF NOT EXISTS dm_threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_a INT NOT NULL,
  user_b INT NOT NULL,
  status ENUM('request','active','declined') NOT NULL DEFAULT 'active',
  requested_by INT NULL,
  declined_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  last_message_at DATETIME NULL,
  UNIQUE KEY uq_pair (user_a, user_b),
  KEY idx_a (user_a, last_message_at),
  KEY idx_b (user_b, last_message_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dm_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  sender_id INT NOT NULL,
  kind ENUM('text','image','location') NOT NULL DEFAULT 'text',
  body MEDIUMTEXT NULL,
  media_path VARCHAR(255) NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  created_at DATETIME NOT NULL,
  read_at DATETIME NULL,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  KEY idx_thread (thread_id, id),
  KEY idx_unread (thread_id, sender_id, read_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dm_user_state (
  user_id INT PRIMARY KEY,
  strikes INT NOT NULL DEFAULT 0,
  muted_until DATETIME NULL,
  updated_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_rate (user_id, action, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctor_profiles (
  user_id INT PRIMARY KEY,
  status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  display_name VARCHAR(120) NOT NULL,
  specialty VARCHAR(120) NOT NULL,
  qualifications VARCHAR(255) NULL,
  reg_no VARCHAR(60) NULL,
  experience_years SMALLINT NULL,
  bio TEXT NULL,
  languages VARCHAR(160) NULL,
  fee INT NULL,
  online_consult TINYINT(1) NOT NULL DEFAULT 1,
  chamber_name VARCHAR(160) NULL,
  chamber_address VARCHAR(255) NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  license_doc VARCHAR(255) NULL,
  review_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  KEY idx_status (status, specialty)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctor_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  weekday TINYINT NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  slot_minutes SMALLINT NOT NULL DEFAULT 30,
  mode ENUM('chamber','online') NOT NULL DEFAULT 'chamber',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_doc (doctor_id, weekday)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  patient_id INT NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  mode ENUM('chamber','online') NOT NULL DEFAULT 'chamber',
  status ENUM('requested','confirmed','declined','cancelled','completed') NOT NULL DEFAULT 'requested',
  reason TEXT NULL,
  doctor_note TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_doc_time (doctor_id, starts_at),
  KEY idx_patient (patient_id, starts_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctor_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  patient_id INT NOT NULL,
  stars TINYINT NOT NULL,
  comment VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_review (doctor_id, patient_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NULL,
  appointment_id INT NULL,
  source ENUM('uploaded','issued') NOT NULL DEFAULT 'uploaded',
  title VARCHAR(160) NOT NULL,
  issued_on DATE NULL,
  doctor_name_text VARCHAR(160) NULL,
  file_path VARCHAR(255) NULL,
  ocr_text MEDIUMTEXT NULL,
  ocr_engine VARCHAR(30) NULL,
  medicines MEDIUMTEXT NULL,
  advice MEDIUMTEXT NULL,
  follow_up DATE NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  KEY idx_patient (patient_id, created_at),
  KEY idx_doctor (doctor_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescription_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  doctor_id INT NOT NULL,
  shared_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_share (prescription_id, doctor_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

