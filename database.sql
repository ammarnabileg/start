-- ============================================================
-- Discover Platform — Full Database Setup (Schema + Seed)
-- Database: admin_discover
-- Run once on a fresh MySQL server
-- All seed passwords: Password123!
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS admin_discover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE admin_discover;

-- Drop all tables (safe reset)
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS community_notification_settings;
DROP TABLE IF EXISTS notification_settings;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_streaks;
DROP TABLE IF EXISTS member_points;
DROP TABLE IF EXISTS community_points;
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

-- ============================================================
-- SCHEMA
-- ============================================================

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
  theme ENUM('light','dark') DEFAULT 'dark',
  affiliate_code VARCHAR(32) UNIQUE,
  referred_by INT,
  affiliate_balance DECIMAL(10,2) DEFAULT 0,
  wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100),
  url VARCHAR(500),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_token VARCHAR(64) UNIQUE NOT NULL,
  device_info VARCHAR(255),
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE follows (
  follower_id INT NOT NULL,
  following_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wallet_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type ENUM('deposit','community_join','community_creation','course_purchase','refund','admin_credit','withdrawal') NOT NULL,
  description VARCHAR(500),
  reference_id INT DEFAULT NULL,
  balance_after DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(100),
  url VARCHAR(500),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE topics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  user_id INT NOT NULL,
  topic_id INT DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE post_likes (
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  like_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lesson_progress (
  user_id INT NOT NULL,
  lesson_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  icon VARCHAR(10) NOT NULL DEFAULT '🏅',
  points_required INT DEFAULT 0,
  badge_type ENUM('course_complete','points','streak','manual') DEFAULT 'manual',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  community_id INT DEFAULT NULL,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_badge (user_id, badge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  points INT NOT NULL DEFAULT 0,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_points (
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  total_points INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_streaks (
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  current_streak INT DEFAULT 0,
  longest_streak INT DEFAULT 0,
  last_active DATE DEFAULT NULL,
  PRIMARY KEY (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_settings (
  user_id INT NOT NULL PRIMARY KEY,
  new_follower TINYINT DEFAULT 1,
  post_likes TINYINT DEFAULT 1,
  affiliate_referral TINYINT DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_notification_settings (
  user_id INT NOT NULL,
  community_id INT NOT NULL,
  admin_posts TINYINT DEFAULT 1,
  new_events TINYINT DEFAULT 1,
  PRIMARY KEY (user_id, community_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  community_id INT DEFAULT NULL,
  course_id INT DEFAULT NULL,
  amount DECIMAL(10,2),
  type ENUM('membership','course','community_creation','platform_fee') DEFAULT 'membership',
  status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  affiliate_user_id INT DEFAULT NULL,
  affiliate_commission DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE platform_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Platform settings
INSERT INTO platform_settings (setting_key, setting_value) VALUES
('platform_name', 'Discover'),
('platform_tagline', 'Join thousands of communities for learning, networking, and growth'),
('platform_logo', ''),
('footer_text', '© 2025 Discover. All rights reserved.'),
('seo_title', 'Discover - Find Your Community'),
('seo_description', 'Join thousands of communities for learning, networking, and growth across the Gulf region.'),
('seo_keywords', 'community, learning, courses, networking, Gulf, Saudi Arabia'),
('seo_og_image', ''),
('ga_id', ''),
('seo_index', 'yes'),
('community_creation_price', '0'),
('affiliate_commission_rate', '7');

-- Users (password: Password123!)
INSERT INTO users (username, email, password_hash, first_name, last_name, bio, location, affiliate_code, theme) VALUES
('admin',        'admin@discover.com',  '$2y$12$s/CEo3Ve0QH7RPTh2GXFb.1lMrNBjzOGVOEANLQnpetdWJTohB5iq', 'Ahmad',  'Al-Rashid',  'Founder of Discover platform. Tech entrepreneur and community builder from Riyadh.', 'Riyadh, Saudi Arabia', 'ADMIN001', 'dark'),
('sarah_tech',   'sarah@discover.com',  '$2y$12$s/CEo3Ve0QH7RPTh2GXFb.1lMrNBjzOGVOEANLQnpetdWJTohB5iq', 'Sarah',  'Al-Hassan',  'Full-stack developer and online educator. Passionate about teaching programming in Arabic.', 'Dubai, UAE', 'SARAH002', 'dark'),
('khalid_music', 'khalid@discover.com', '$2y$12$s/CEo3Ve0QH7RPTh2GXFb.1lMrNBjzOGVOEANLQnpetdWJTohB5iq', 'Khalid', 'Al-Mansouri','Professional musician, oud player, and music educator with 15 years of experience.', 'Abu Dhabi, UAE', 'KHALID003', 'dark'),
('fatima_biz',   'fatima@discover.com', '$2y$12$s/CEo3Ve0QH7RPTh2GXFb.1lMrNBjzOGVOEANLQnpetdWJTohB5iq', 'Fatima', 'Al-Zahra',   'Business consultant and entrepreneur. Helping Gulf businesses scale and grow.', 'Kuwait City, Kuwait', 'FATIMA004', 'dark');

-- Communities
INSERT INTO communities (owner_id, name, slug, description, short_bio, category, type, pricing, price, price_interval, language, member_count) VALUES
(2, 'Tech Learning Hub', 'tech-learning-hub',
 'The premier Arabic-language tech community for developers in the Gulf region. We cover web development, mobile apps, AI/ML, cloud computing, and more.',
 'Learn coding & tech in Arabic. Courses, mentorship & community for Gulf developers.',
 'tech', 'public', 'free', 0.00, 'monthly', 'ar', 4),
(3, 'Arabic Music Academy', 'arabic-music-academy',
 'Master traditional and contemporary Arabic music. Learn oud, qanun, violin, and Arabic vocal techniques from professional musicians.',
 'Professional Arabic music education - Oud, Maqam theory & more.',
 'music', 'public', 'paid', 29.99, 'monthly', 'ar', 3),
(4, 'Gulf Business Network', 'gulf-business-network',
 'Connect with entrepreneurs, investors, and business leaders across the Gulf Cooperation Council.',
 'Connect with Gulf entrepreneurs & investors. Business insights & networking.',
 'money', 'public', 'free_trial', 19.99, 'monthly', 'en', 4);

-- Community Links
INSERT INTO community_links (community_id, name, url, sort_order) VALUES
(1, 'GitHub', 'https://github.com', 1),
(1, 'YouTube Channel', 'https://youtube.com', 2),
(1, 'Discord Server', 'https://discord.gg', 3),
(2, 'SoundCloud', 'https://soundcloud.com', 1),
(2, 'Instagram', 'https://instagram.com', 2),
(3, 'LinkedIn Group', 'https://linkedin.com', 1),
(3, 'Website', 'https://gulfbiz.com', 2);

-- Memberships
INSERT INTO memberships (user_id, community_id, role, status) VALUES
(2, 1, 'owner', 'approved'),
(1, 1, 'admin', 'approved'),
(3, 1, 'member', 'approved'),
(4, 1, 'member', 'approved'),
(3, 2, 'owner', 'approved'),
(1, 2, 'member', 'approved'),
(2, 2, 'member', 'approved'),
(4, 2, 'member', 'pending'),
(4, 3, 'owner', 'approved'),
(1, 3, 'admin', 'approved'),
(2, 3, 'member', 'approved'),
(3, 3, 'member', 'approved');

-- Topics
INSERT INTO topics (community_id, name, sort_order) VALUES
(1, 'General', 1),
(1, 'Web Development', 2),
(1, 'Mobile Development', 3),
(1, 'AI & Machine Learning', 4),
(1, 'Career Advice', 5),
(2, 'General', 1),
(2, 'Oud Lessons', 2),
(2, 'Maqam Theory', 3),
(2, 'Music Production', 4),
(3, 'General', 1),
(3, 'Startup Funding', 2),
(3, 'Marketing', 3),
(3, 'Networking', 4);

-- Posts
INSERT INTO posts (community_id, user_id, topic_id, title, content, is_pinned, pin_order, like_count, comment_count) VALUES
(1, 2, 1, 'Welcome to Tech Learning Hub!', 'Ahlan wa sahlan! We are thrilled to have you here. This is the place where Arab developers come to learn, grow, and connect.\n\nPlease introduce yourself below and let us know what you are hoping to learn!', 1, 1, 234, 3),
(1, 2, 1, 'Roadmap 2025: What We Are Building', 'Exciting news! Here is our content roadmap for 2025:\n\n- React & Next.js Course\n- Python for Data Science\n- Mobile Dev with Flutter\n- Cloud Architecture on AWS\n\nWhich course are you most excited about?', 1, 2, 189, 2),
(1, 1, 2, 'How I landed my first remote job as a developer', 'After 8 months of learning through this community, I finally got my first remote job offer! Building real projects and contributing to open source made all the difference.', 0, 0, 312, 1),
(1, 3, 4, 'My First AI Project - Image Classifier', 'I just finished building my first image classification model using PyTorch! It can distinguish between 10 different types of Arabic calligraphy styles. Accuracy: 94.7%', 0, 0, 156, 0),
(2, 3, 6, 'Welcome to Arabic Music Academy', 'Welcome everyone to our beautiful musical journey! This community is dedicated to preserving and advancing Arabic musical traditions.', 1, 1, 178, 1),
(2, 3, 7, 'Maqam Rast - The Foundation of Arabic Music', 'Today we explore Maqam Rast, often called the mother of all maqamat. Starting on C, it creates that quintessential Arabic sound we all love.', 0, 0, 145, 0),
(3, 4, 10, 'Welcome to Gulf Business Network', 'Hello Gulf entrepreneurs and business leaders! This is your space to connect, collaborate, and grow.', 1, 1, 287, 1),
(3, 1, 11, 'Series A Funding in the Gulf - What VCs Look For', 'After meeting with 20+ VCs in the Gulf region, here are the key things they look for:\n\n1. Market Size\n2. Team\n3. Traction\n4. Defensibility\n5. Regional Understanding', 0, 0, 423, 1);

-- Comments
INSERT INTO comments (post_id, user_id, content, like_count) VALUES
(1, 1, 'Ahlan! I am Ahmad, software engineer from Riyadh. Looking to improve my cloud architecture skills!', 45),
(1, 3, 'Excited to be here! I am Khalid, mostly interested in AI and data science.', 32),
(1, 4, 'Marhaba! Fatima here from Kuwait. I am a business analyst trying to learn programming.', 28),
(2, 1, 'Voting for the AI/ML course! Would love to see practical business applications covered.', 67),
(2, 3, 'Flutter course please! Mobile development is huge in the Gulf market.', 54),
(3, 4, 'This is so inspiring! What resources did you use for interview preparation?', 23),
(5, 1, 'Yalla! Ready to learn Oud! I have been wanting to start for years.', 34),
(7, 2, 'Great initiative Fatima! The Gulf tech startup ecosystem needs more connection points like this.', 45);

-- Courses
INSERT INTO courses (community_id, title, description, thumbnail, pricing, price, sort_order) VALUES
(1, 'Web Development Fundamentals', 'Master HTML, CSS, JavaScript and build your first websites. Perfect for absolute beginners.', '', 'free', 0, 1),
(1, 'React & Next.js Masterclass', 'Build modern, full-stack web applications with React and Next.js.', '', 'paid', 49.99, 2),
(1, 'Python for Data Science', 'Learn Python programming and apply it to data analysis and machine learning.', '', 'free', 0, 3),
(2, 'Oud Mastery - Level 1 Beginners', 'Start your oud journey with proper technique, basic scales, and your first Arabic songs.', '', 'free', 0, 1),
(2, 'Maqam Theory Complete Course', 'Deep dive into the 7 main maqamat, their emotional qualities and applications.', '', 'paid', 39.99, 2),
(3, 'Business Plan Masterclass', 'Create a compelling, investor-ready business plan with financial modeling.', '', 'free', 0, 1),
(3, 'Gulf Market Entry Strategy', 'Everything you need to know about entering Gulf markets.', '', 'paid', 79.99, 2);

-- Course Sections
INSERT INTO course_sections (course_id, title, sort_order) VALUES
(1, 'Introduction to Web Development', 1),
(1, 'HTML Fundamentals', 2),
(1, 'CSS Styling & Layout', 3),
(2, 'React Basics', 1),
(2, 'Advanced React Patterns', 2),
(2, 'Next.js & Full Stack', 3),
(3, 'Python Basics', 1),
(3, 'Data Analysis with Pandas', 2),
(3, 'Data Visualization', 3),
(4, 'Getting Started with Oud', 1),
(4, 'Basic Scales & Positions', 2),
(4, 'Your First Songs', 3),
(5, 'Introduction to Maqamat', 1),
(5, 'The Seven Main Maqamat', 2),
(5, 'Modulation & Composition', 3),
(6, 'Business Plan Foundations', 1),
(6, 'Market Analysis', 2),
(6, 'Financial Planning', 3),
(7, 'Understanding Gulf Markets', 1),
(7, 'Legal & Regulatory', 2),
(7, 'Go-to-Market Strategy', 3);

-- Lessons
INSERT INTO lessons (section_id, title, content, video_url, lesson_type, duration_minutes, sort_order) VALUES
(1, 'What is Web Development?', 'Web development is the process of creating websites and web applications...', 'https://www.youtube.com/watch?v=ysEN5RaKOlA', 'video', 12, 1),
(1, 'Setting Up Your Development Environment', 'Install VS Code, configure extensions, and set up your workspace...', 'https://www.youtube.com/watch?v=VqCgcpAypFQ', 'video', 18, 2),
(1, 'How the Web Works - HTTP & Browsers', 'Understanding HTTP requests, responses, DNS, and how browsers render pages...', NULL, 'text', 15, 3),
(2, 'HTML Document Structure', 'Every HTML document follows a specific structure. DOCTYPE, html, head, and body...', 'https://www.youtube.com/watch?v=UB1O30fR-EE', 'video', 20, 1),
(2, 'HTML Elements & Tags', 'Headings, paragraphs, links, images, lists, tables, and forms...', 'https://www.youtube.com/watch?v=FQdaUv95mR8', 'video', 25, 2),
(2, 'Semantic HTML5', 'Using header, nav, main, article, section, aside, and footer...', NULL, 'text', 20, 3),
(3, 'CSS Selectors & Properties', 'Learn how to target HTML elements and apply styles...', 'https://www.youtube.com/watch?v=1PnVor36_40', 'video', 30, 1),
(3, 'Flexbox Layout', 'Master flexible box layout for building responsive layouts...', 'https://www.youtube.com/watch?v=JJSoEo8JSnc', 'video', 35, 2),
(3, 'CSS Grid', 'Build complex two-dimensional layouts with CSS Grid...', 'https://www.youtube.com/watch?v=jV8B24rSN5o', 'video', 28, 3),
(4, 'Introduction to React', 'Component-based architecture, virtual DOM, JSX syntax...', 'https://www.youtube.com/watch?v=Tn6-PIqc4UM', 'video', 22, 1),
(4, 'Components & Props', 'Building reusable components and passing data with props...', 'https://www.youtube.com/watch?v=9D1x7-2FmTA', 'video', 28, 2),
(4, 'State & useState Hook', 'Managing component state with the useState hook...', 'https://www.youtube.com/watch?v=O6P86uwfdR0', 'video', 32, 3),
(5, 'useEffect & Side Effects', 'Fetching data and managing side effects with useEffect...', 'https://www.youtube.com/watch?v=0ZJgIjIuY7U', 'video', 35, 1),
(5, 'Context API & State Management', 'Global state management with Context API and useReducer...', NULL, 'text', 40, 2),
(5, 'Custom Hooks', 'Building and reusing custom hooks to share logic...', 'https://www.youtube.com/watch?v=6ThXsUwLWvc', 'video', 30, 3),
(6, 'Next.js Setup & File-based Routing', 'Installing Next.js, understanding pages directory, dynamic routes...', 'https://www.youtube.com/watch?v=mTz0GXj8NN0', 'video', 25, 1),
(6, 'API Routes & Server Components', 'Building backend APIs within Next.js...', 'https://www.youtube.com/watch?v=W4UhNo3HAMw', 'video', 45, 2),
(6, 'Deployment to Vercel', 'Deploying your Next.js application to production...', 'https://www.youtube.com/watch?v=2HBIzEx6IZA', 'video', 20, 3),
(7, 'Python Installation & Setup', 'Installing Python, pip, virtual environments, and Jupyter notebooks...', 'https://www.youtube.com/watch?v=YYXdXT2l-Gg', 'video', 15, 1),
(7, 'Variables, Data Types & Operators', 'Python basics: variables, strings, numbers, booleans, lists, dicts...', 'https://www.youtube.com/watch?v=khKv-8q7YmY', 'video', 35, 2),
(7, 'Functions & Control Flow', 'Defining functions, if/else, loops, list comprehensions...', NULL, 'text', 40, 3),
(8, 'Introduction to Pandas', 'Loading, exploring, and manipulating data with Pandas DataFrames...', 'https://www.youtube.com/watch?v=vmEHCJofslg', 'video', 45, 1),
(8, 'Data Cleaning & Transformation', 'Handling missing values, merging datasets, applying transformations...', NULL, 'text', 50, 2),
(8, 'Statistical Analysis', 'Descriptive statistics, groupby operations, pivot tables...', 'https://www.youtube.com/watch?v=r-uOLxNrNk8', 'video', 40, 3),
(9, 'Matplotlib Basics', 'Creating line charts, bar charts, histograms, and scatter plots...', 'https://www.youtube.com/watch?v=3Xc3CA655Y4', 'video', 30, 1),
(9, 'Seaborn for Statistical Visualization', 'Beautiful statistical visualizations with Seaborn...', NULL, 'text', 35, 2),
(9, 'Dashboard with Plotly', 'Interactive dashboards using Plotly Express...', 'https://www.youtube.com/watch?v=GGL6U0k8WYA', 'video', 40, 3),
(10, 'Parts of the Oud', 'Understanding the anatomy of the oud: body, neck, pegs, strings, and their tunings...', 'https://www.youtube.com/watch?v=Rh1bQkUCIAo', 'video', 10, 1),
(10, 'Holding the Oud & Pick Technique', 'Proper posture, how to hold the oud, and basic plectrum techniques...', 'https://www.youtube.com/watch?v=7DEFHmGSR0s', 'video', 15, 2),
(10, 'Your First Notes', 'Playing your first notes on the oud, simple exercises for finger placement...', NULL, 'text', 20, 3),
(11, 'The Arabic Scale (Ajam)', 'Learning the Ajam scale, similar to the Western major scale...', 'https://www.youtube.com/watch?v=YOlT_EMv8jY', 'video', 22, 1),
(11, 'Rast Scale - The Heart of Arabic Music', 'Mastering Maqam Rast, the most fundamental Arabic scale...', 'https://www.youtube.com/watch?v=pCBikTDNmCM', 'video', 28, 2),
(11, 'Right Hand Patterns', 'Essential right hand patterns: single stroke, tremolo, and basic rhythmic patterns...', NULL, 'text', 25, 3),
(12, 'Ya Msafer Wahdak', 'Learning the classic Egyptian song by Abdel Halim Hafez...', 'https://www.youtube.com/watch?v=IjC0lAFz_cA', 'video', 35, 1),
(12, 'Lamma Bada Yatathanna', 'This Andalusian classic piece step by step...', 'https://www.youtube.com/watch?v=V9Q_ER_8pAE', 'video', 40, 2),
(12, 'Improvisation Basics', 'Introduction to taqsim - free improvisation in Arabic music...', NULL, 'text', 30, 3),
(16, 'What is a Business Plan?', 'Understanding the purpose, audience, and structure of a business plan...', 'https://www.youtube.com/watch?v=Fqch5OrUPvA', 'video', 12, 1),
(16, 'Executive Summary', 'Writing a compelling executive summary that captures attention...', NULL, 'text', 20, 2),
(16, 'Business Model Canvas', 'Using the Business Model Canvas to map out your business on one page...', 'https://www.youtube.com/watch?v=IP0cUBWTgpY', 'video', 25, 3),
(17, 'Market Size & TAM/SAM/SOM', 'Calculating total addressable, serviceable, and obtainable market sizes...', 'https://www.youtube.com/watch?v=v1aD1JU1L1w', 'video', 30, 1),
(17, 'Competitive Analysis', 'Mapping competitors, identifying your unique value proposition...', NULL, 'text', 25, 2),
(17, 'Customer Personas in the Gulf', 'Creating data-driven customer personas for Gulf market segments...', 'https://www.youtube.com/watch?v=Z5RxGHB2_E4', 'video', 28, 3),
(18, 'Revenue Projections', 'Building bottom-up financial projections for 3-5 years...', 'https://www.youtube.com/watch?v=vl-7KJnzGsw', 'video', 35, 1),
(18, 'Funding Requirements', 'Calculating how much to raise and what to use it for...', NULL, 'text', 20, 2),
(18, 'Break-Even Analysis', 'Understanding when your business becomes profitable...', 'https://www.youtube.com/watch?v=IuQ0DkJqrpg', 'video', 22, 3);

-- Badges
INSERT INTO badges (community_id, name, description, icon, points_required, badge_type) VALUES
(NULL, 'First Steps',    'Joined your first community',       '🌟', 0,    'manual'),
(NULL, 'Scholar',        'Completed your first course',       '📚', 0,    'course_complete'),
(NULL, 'Master',         'Completed 5 courses',               '🎓', 0,    'course_complete'),
(NULL, 'Contributor',    'Created 10 posts',                  '✍️', 0,    'manual'),
(NULL, 'Popular',        'Received 100 likes',                '❤️', 0,    'points'),
(NULL, 'Streak Master',  'Maintained a 30-day streak',        '🔥', 0,    'streak'),
(NULL, 'Top Learner',    'Reached leaderboard #1',            '🏆', 1000, 'points'),
(1,   'Code Warrior',   'Completed all web dev courses',     '⚔️', 500,  'course_complete'),
(1,   'Algorithm Master','Solved 50 coding challenges',      '🧠', 750,  'points'),
(2,   'Maqam Master',   'Completed all maqam courses',       '🎵', 500,  'course_complete'),
(3,   'Gulf Shark',     'Top business contributor',          '🦈', 1000, 'points');

-- User badges
INSERT INTO user_badges (user_id, badge_id, community_id) VALUES
(2, 1, 1), (2, 2, 1), (2, 7, 1), (2, 8, 1),
(1, 1, 1), (1, 3, 1),
(3, 1, 2), (3, 2, 2), (3, 10, 2),
(4, 1, 3), (4, 7, 3);

-- Community points
INSERT INTO community_points (user_id, community_id, points, reason) VALUES
(2, 1, 500, 'Course completion bonus'),
(2, 1, 200, 'Community contributions'),
(2, 1, 150, 'Post engagement'),
(1, 1, 300, 'Admin activities'),
(1, 1, 100, 'Helpful answers'),
(3, 1, 150, 'Course progress'),
(3, 2, 600, 'Teaching and contributions'),
(3, 2, 200, 'Course creation'),
(4, 3, 450, 'Business insights'),
(4, 3, 300, 'Network building'),
(2, 3, 150, 'Active participation');

-- Member points totals (leaderboard)
INSERT INTO member_points (user_id, community_id, total_points) VALUES
(2, 1, 850),
(1, 1, 400),
(3, 1, 150),
(3, 2, 800),
(4, 3, 750),
(2, 3, 150);

-- Notification settings
INSERT INTO notification_settings (user_id, new_follower, post_likes, affiliate_referral) VALUES
(1, 1, 1, 1),
(2, 1, 1, 1),
(3, 1, 0, 1),
(4, 1, 1, 0);

-- Notifications
INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(1, 'new_follower',        'New Follower',    'Sarah Al-Hassan started following you',              '/profile.php?username=sarah_tech', 0),
(1, 'post_like',           'Post Liked',      'Khalid Al-Mansouri liked your post',                 '/community.php?slug=gulf-business-network', 1),
(2, 'membership_approved', 'Welcome!',        'Your membership to Gulf Business Network was approved', '/community.php?slug=gulf-business-network', 0),
(3, 'new_follower',        'New Follower',    'Ahmad Al-Rashid started following you',              '/profile.php?username=admin', 1),
(4, 'points_awarded',      'Points Earned',   'You earned 50 XP in Gulf Business Network',          '/community.php?slug=gulf-business-network', 0);

-- Follows
INSERT INTO follows (follower_id, following_id) VALUES
(1, 2), (1, 3), (2, 1), (2, 4), (3, 1), (3, 2), (4, 1), (4, 2), (4, 3);

-- Recalculate member counts
UPDATE communities c SET member_count = (
  SELECT COUNT(*) FROM memberships m WHERE m.community_id = c.id AND m.status = 'approved'
);
