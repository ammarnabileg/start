-- Discover Platform - Database Schema
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS discover_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE discover_platform;

DROP TABLE IF EXISTS community_notification_settings;
DROP TABLE IF EXISTS notification_settings;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_streaks;
DROP TABLE IF EXISTS user_points;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS lesson_progress;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS course_sections;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS topics;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS community_links;
DROP TABLE IF EXISTS communities;
DROP TABLE IF EXISTS follows;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS user_links;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS platform_settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  bio TEXT,
  avatar VARCHAR(500),
  location VARCHAR(255),
  timezone VARCHAR(100) DEFAULT 'UTC',
  theme ENUM('light','dark') DEFAULT 'light',
  affiliate_code VARCHAR(32) UNIQUE,
  referred_by INT,
  affiliate_balance DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

-- User Social Links
CREATE TABLE user_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100),
  url VARCHAR(500),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Sessions (for multi-device logout)
CREATE TABLE user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_token VARCHAR(64) UNIQUE NOT NULL,
  device_info VARCHAR(255),
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Follows
CREATE TABLE follows (
  follower_id INT NOT NULL,
  following_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Communities
CREATE TABLE communities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  description TEXT,
  short_bio VARCHAR(500),
  logo VARCHAR(500),
  banner VARCHAR(500),
  category ENUM('trending','hobbies','music','money','celebrity','tech','health','sports','self_improvement','relationships') DEFAULT 'trending',
  type ENUM('public','private') DEFAULT 'public',
  pricing ENUM('free','paid','free_trial') DEFAULT 'free',
  price DECIMAL(10,2) DEFAULT 0,
  price_interval ENUM('monthly','yearly','one_time') DEFAULT 'monthly',
  language VARCHAR(10) DEFAULT 'en',
  is_active TINYINT DEFAULT 1,
  member_count INT DEFAULT 0,
  creation_price DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Community Links
CREATE TABLE community_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(100),
  url VARCHAR(500),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Memberships
CREATE TABLE memberships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  role ENUM('member','admin','owner') DEFAULT 'member',
  status ENUM('pending','approved','rejected','banned') DEFAULT 'pending',
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_membership (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Topics (per community)
CREATE TABLE topics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Posts
CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  user_id INT NOT NULL,
  topic_id INT,
  title VARCHAR(500),
  content TEXT,
  is_pinned TINYINT DEFAULT 0,
  pin_order INT DEFAULT 0,
  like_count INT DEFAULT 0,
  comment_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
);

-- Post Likes
CREATE TABLE post_likes (
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- Comments
CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  like_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Courses
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  thumbnail VARCHAR(500),
  pricing ENUM('free','paid') DEFAULT 'free',
  price DECIMAL(10,2) DEFAULT 0,
  is_published TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Course Sections
CREATE TABLE course_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Lessons
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  content TEXT,
  video_url VARCHAR(1000),
  video_embed TEXT,
  lesson_type ENUM('video','text','mixed') DEFAULT 'text',
  duration_minutes INT DEFAULT 0,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
);

-- Lesson Progress
CREATE TABLE lesson_progress (
  user_id INT NOT NULL,
  lesson_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Badges
CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  icon VARCHAR(500),
  points_required INT DEFAULT 0,
  badge_type ENUM('course_complete','points','streak','manual') DEFAULT 'manual',
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- User Badges
CREATE TABLE user_badges (
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  community_id INT,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, badge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

-- Points / XP
CREATE TABLE user_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  points INT DEFAULT 0,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Streaks
CREATE TABLE user_streaks (
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  current_streak INT DEFAULT 0,
  longest_streak INT DEFAULT 0,
  last_active DATE,
  PRIMARY KEY (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50),
  title VARCHAR(255),
  message TEXT,
  link VARCHAR(500),
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notification Settings
CREATE TABLE notification_settings (
  user_id INT NOT NULL PRIMARY KEY,
  new_follower TINYINT DEFAULT 1,
  post_likes TINYINT DEFAULT 1,
  affiliate_referral TINYINT DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Community Notification Settings
CREATE TABLE community_notification_settings (
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  admin_posts TINYINT DEFAULT 1,
  new_events TINYINT DEFAULT 1,
  PRIMARY KEY (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
);

-- Payment Methods
CREATE TABLE payment_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  card_last4 VARCHAR(4),
  card_brand VARCHAR(20),
  exp_month INT,
  exp_year INT,
  is_default TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments / Transactions
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  community_id INT,
  course_id INT,
  amount DECIMAL(10,2),
  type ENUM('membership','course','community_creation','platform_fee') DEFAULT 'membership',
  status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  affiliate_user_id INT,
  affiliate_commission DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Platform Settings
CREATE TABLE platform_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT
);

INSERT INTO platform_settings VALUES ('community_creation_price', '0');
INSERT INTO platform_settings VALUES ('affiliate_commission_rate', '7');
INSERT INTO platform_settings VALUES ('platform_name', 'Discover');
