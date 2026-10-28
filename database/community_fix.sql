-- HAVEN COMMUNITY FIX MIGRATION
-- Run this AFTER your current rfnhscco_mind database import.
-- It only adds missing fields/tables required by the functional community UI.

SET NAMES utf8mb4;

ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS media_url varchar(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS media_type varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS external_url varchar(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS external_type varchar(30) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS share_count int(11) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS hidden_posts (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  post_id int(11) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY(id),
  UNIQUE KEY uq_hidden_user_post(user_id,post_id),
  KEY idx_hidden_post(post_id),
  CONSTRAINT fk_hidden_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hidden_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Useful uniqueness for one reaction per user/post.
ALTER TABLE reactions
  ADD UNIQUE KEY uq_reaction_user_post(user_id,post_id);

-- Helpful indexes for infinite feed.
CREATE INDEX idx_posts_feed ON posts(status,created_at);
CREATE INDEX idx_posts_category ON posts(category,status,created_at);
CREATE INDEX idx_comments_post_created ON comments(post_id,created_at);
CREATE INDEX idx_bookmarks_user_post ON bookmarks(user_id,post_id);
CREATE INDEX idx_followers_follower ON followers(follower_id,user_id);
