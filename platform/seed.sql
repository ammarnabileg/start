USE admin_discover;

-- Clear existing data (in reverse FK order)
DELETE FROM notifications;
DELETE FROM notification_settings;
DELETE FROM community_notification_settings;
DELETE FROM user_points;
DELETE FROM user_streaks;
DELETE FROM user_badges;
DELETE FROM badges;
DELETE FROM lesson_progress;
DELETE FROM lessons;
DELETE FROM course_sections;
DELETE FROM courses;
DELETE FROM comments;
DELETE FROM post_likes;
DELETE FROM posts;
DELETE FROM topics;
DELETE FROM memberships;
DELETE FROM community_links;
DELETE FROM communities;
DELETE FROM follows;
DELETE FROM user_links;
DELETE FROM user_sessions;
DELETE FROM payment_methods;
DELETE FROM payments;
DELETE FROM users;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE communities AUTO_INCREMENT = 1;
ALTER TABLE courses AUTO_INCREMENT = 1;
ALTER TABLE course_sections AUTO_INCREMENT = 1;
ALTER TABLE lessons AUTO_INCREMENT = 1;
ALTER TABLE posts AUTO_INCREMENT = 1;
ALTER TABLE badges AUTO_INCREMENT = 1;
ALTER TABLE topics AUTO_INCREMENT = 1;
ALTER TABLE comments AUTO_INCREMENT = 1;
ALTER TABLE memberships AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE user_points AUTO_INCREMENT = 1;

-- Sample Users (password: Password123!)
INSERT INTO users (username, email, password_hash, first_name, last_name, bio, location, affiliate_code, theme) VALUES
('admin', 'admin@discover.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmad', 'Al-Rashid', 'Founder of Discover platform. Tech entrepreneur and community builder from Riyadh.', 'Riyadh, Saudi Arabia', 'ADMIN001', 'light'),
('sarah_tech', 'sarah@discover.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Al-Hassan', 'Full-stack developer and online educator. Passionate about teaching programming in Arabic.', 'Dubai, UAE', 'SARAH002', 'dark'),
('khalid_music', 'khalid@discover.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Khalid', 'Al-Mansouri', 'Professional musician, oud player, and music educator with 15 years of experience.', 'Abu Dhabi, UAE', 'KHALID003', 'light'),
('fatima_biz', 'fatima@discover.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatima', 'Al-Zahra', 'Business consultant and entrepreneur. Helping Gulf businesses scale and grow.', 'Kuwait City, Kuwait', 'FATIMA004', 'light');

-- Communities
INSERT INTO communities (owner_id, name, slug, description, short_bio, category, type, pricing, price, price_interval, language, member_count) VALUES
(2, 'Tech Learning Hub', 'tech-learning-hub',
 'The premier Arabic-language tech community for developers in the Gulf region. We cover web development, mobile apps, AI/ML, cloud computing, and more. Join thousands of developers on their learning journey.\n\nOur community offers structured courses, weekly live sessions, and a supportive peer learning environment. Whether you are a complete beginner or an experienced developer, there is something for everyone.',
 'Learn coding & tech in Arabic. Courses, mentorship & community for Gulf developers.',
 'tech', 'public', 'free', 0.00, 'monthly', 'ar', 1250),
(3, 'Arabic Music Academy', 'arabic-music-academy',
 'Master traditional and contemporary Arabic music. Learn oud, qanun, violin, and Arabic vocal techniques from professional musicians.\n\nOur academy combines traditional maqam theory with modern music production. Students from across the Arab world and beyond have transformed their musical abilities through our structured curriculum.',
 'Professional Arabic music education - Oud, Maqam theory & more.',
 'music', 'public', 'paid', 29.99, 'monthly', 'ar', 487),
(4, 'Gulf Business Network', 'gulf-business-network',
 'Connect with entrepreneurs, investors, and business leaders across the Gulf Cooperation Council. Share insights, opportunities, and build valuable relationships.\n\nFrom startups to enterprise, we welcome all business professionals looking to grow their network and knowledge in the Gulf market.',
 'Connect with Gulf entrepreneurs & investors. Business insights & networking.',
 'money', 'public', 'free_trial', 19.99, 'monthly', 'en', 3420);

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
(1, 2, 1, 'Welcome to Tech Learning Hub!', 'Ahlan wa sahlan to our community!  We are thrilled to have you here. This is the place where Arab developers come to learn, grow, and connect.\n\nPlease introduce yourself below and let us know what you are hoping to learn. We have structured courses, weekly Q&A sessions, and a very supportive community.\n\nRemember our community rules:\n1. Be respectful and supportive\n2. Share knowledge freely\n3. Help others when you can\n4. No spam or self-promotion without permission', 1, 1, 234, 45),
(1, 2, 1, 'Roadmap 2025: What We Are Building', 'Exciting news! Here is our content roadmap for 2025:\n\n- **React & Next.js Course** (January)\n- **Python for Data Science** (February)\n- **Mobile Dev with Flutter** (March)\n- **Cloud Architecture on AWS** (April)\n- **AI/ML Fundamentals** (May)\n\nWhich course are you most excited about? Vote in the comments!', 1, 2, 189, 67),
(1, 1, 2, 'How I landed my first remote job as a developer', 'After 8 months of learning through this community, I finally got my first remote job offer! Here is what helped me:\n\n1. Building real projects (not just tutorials)\n2. Contributing to open source\n3. The mock interview sessions in this community\n4. My portfolio on GitHub\n\nDo not give up! The path is long but worth it. Happy to answer questions.', 0, 0, 312, 89),
(1, 3, 4, 'My First AI Project - Image Classifier', 'I just finished building my first image classification model using PyTorch!  It can distinguish between 10 different types of Arabic calligraphy styles.\n\nAccuracy: 94.7% on test set\nTraining time: 3 hours on Google Colab\n\nFull code on GitHub. Would love feedback from the AI/ML folks here!', 0, 0, 156, 34),
(2, 3, 6, 'Welcome to Arabic Music Academy', 'Welcome everyone to our beautiful musical journey! \n\nThis community is dedicated to preserving and advancing Arabic musical traditions while embracing modern techniques.\n\nWhether you play oud, qanun, violin, or sing - you belong here. Let us learn from each other and keep our musical heritage alive.', 1, 1, 178, 23),
(2, 3, 7, 'Maqam Rast - The Foundation of Arabic Music', 'Today we explore Maqam Rast, often called the "mother of all maqamat." Starting on C, it creates that quintessential Arabic sound we all love.\n\nKey characteristics:\n- Neutral third (between major and minor)\n- Creates a feeling of joy and openness\n- Used extensively in Egyptian classical music\n\nListen to Om Kalthoum singing in Rast to truly understand its beauty.', 0, 0, 145, 28),
(3, 4, 10, 'Welcome to Gulf Business Network', 'Hello Gulf entrepreneurs and business leaders! \n\nThis is your space to connect, collaborate, and grow. We have members from Saudi Arabia, UAE, Kuwait, Qatar, Bahrain, and Oman.\n\nShare your biggest business challenge below and let the community help!', 1, 1, 287, 56),
(3, 1, 11, 'Series A Funding in the Gulf - What VCs Look For', 'After meeting with 20+ VCs in the Gulf region over the past year, here are the key things they look for:\n\n1. **Market Size**: Is the Gulf market big enough? Usually looking for $500M+ TAM\n2. **Team**: Domain expertise and execution track record\n3. **Traction**: Revenue or strong user growth metrics\n4. **Defensibility**: What is your moat?\n5. **Regional Understanding**: You need to know the cultural nuances\n\nHappy to do a live Q&A session if there is interest!', 0, 0, 423, 112);

-- Comments
INSERT INTO comments (post_id, user_id, content, like_count) VALUES
(1, 1, 'Ahlan! I am Ahmad, software engineer from Riyadh. Looking to improve my cloud architecture skills and contribute to the community!', 45),
(1, 3, 'Excited to be here! I am Khalid, mostly interested in AI and data science. This community looks amazing!', 32),
(1, 4, 'Marhaba! Fatima here from Kuwait. I am a business analyst trying to learn programming to build my own tools. Looking forward to learning!', 28),
(2, 1, 'Voting for the AI/ML course! Would love to see practical business applications covered.', 67),
(2, 3, 'Flutter course please! Mobile development is huge here in the Gulf market.', 54),
(3, 4, 'This is so inspiring! What resources did you use for interview preparation?', 23),
(5, 1, 'Yalla! Ready to learn Oud! I have been wanting to start for years.', 34),
(7, 2, 'Great initiative Fatima! The Gulf tech startup ecosystem needs more connection points like this.', 45);

-- Courses
INSERT INTO courses (community_id, title, description, thumbnail, pricing, price, sort_order) VALUES
(1, 'Web Development Fundamentals', 'Master HTML, CSS, JavaScript and build your first websites. Perfect for absolute beginners wanting to start their web development journey.', 'https://images.unsplash.com/photo-1593720219276-0b1eacd0aef4?w=400', 'free', 0, 1),
(1, 'React & Next.js Masterclass', 'Build modern, full-stack web applications with React and Next.js. Learn hooks, state management, API routes, and deployment.', 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=400', 'paid', 49.99, 2),
(1, 'Python for Data Science', 'Learn Python programming from scratch and apply it to data analysis, visualization, and machine learning fundamentals.', 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=400', 'free', 0, 3),
(2, 'Oud Mastery - Level 1 Beginners', 'Start your oud journey with proper technique, basic scales, and your first Arabic songs. No prior musical experience needed.', 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=400', 'free', 0, 1),
(2, 'Maqam Theory Complete Course', 'Deep dive into the 7 main maqamat, understand their emotional qualities, modulations, and applications in composition.', 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?w=400', 'paid', 39.99, 2),
(3, 'Business Plan Masterclass', 'Create a compelling, investor-ready business plan. Includes financial modeling, market analysis, and pitch deck creation.', 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=400', 'free', 0, 1),
(3, 'Gulf Market Entry Strategy', 'Everything you need to know about entering Gulf markets: regulations, cultural considerations, partnerships, and go-to-market strategy.', 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=400', 'paid', 79.99, 2);

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
-- Web Dev Fundamentals - Section 1
(1, 'What is Web Development?', 'Web development is the process of creating websites and web applications...', 'https://www.youtube.com/watch?v=ysEN5RaKOlA', 'video', 12, 1),
(1, 'Setting Up Your Development Environment', 'In this lesson we will install VS Code, configure extensions, and set up our workspace...', 'https://www.youtube.com/watch?v=VqCgcpAypFQ', 'video', 18, 2),
(1, 'How the Web Works - HTTP & Browsers', 'Understanding HTTP requests, responses, DNS, and how browsers render pages...', NULL, 'text', 15, 3),
-- Web Dev - Section 2
(2, 'HTML Document Structure', 'Every HTML document follows a specific structure. Let us explore DOCTYPE, html, head, and body...', 'https://www.youtube.com/watch?v=UB1O30fR-EE', 'video', 20, 1),
(2, 'HTML Elements & Tags', 'Headings, paragraphs, links, images, lists, tables, and forms...', 'https://www.youtube.com/watch?v=FQdaUv95mR8', 'video', 25, 2),
(2, 'Semantic HTML5', 'Using header, nav, main, article, section, aside, and footer for better accessibility...', NULL, 'text', 20, 3),
-- Web Dev - Section 3
(3, 'CSS Selectors & Properties', 'Learn how to target HTML elements and apply styles using selectors, classes, and IDs...', 'https://www.youtube.com/watch?v=1PnVor36_40', 'video', 30, 1),
(3, 'Flexbox Layout', 'Master flexible box layout for building responsive, dynamic layouts...', 'https://www.youtube.com/watch?v=JJSoEo8JSnc', 'video', 35, 2),
(3, 'CSS Grid', 'Build complex, two-dimensional layouts with CSS Grid...', 'https://www.youtube.com/watch?v=jV8B24rSN5o', 'video', 28, 3),
-- React - Section 4
(4, 'Introduction to React', 'What is React? Component-based architecture, virtual DOM, JSX syntax...', 'https://www.youtube.com/watch?v=Tn6-PIqc4UM', 'video', 22, 1),
(4, 'Components & Props', 'Building reusable components and passing data with props...', 'https://www.youtube.com/watch?v=9D1x7-2FmTA', 'video', 28, 2),
(4, 'State & useState Hook', 'Managing component state with the useState hook, controlled components...', 'https://www.youtube.com/watch?v=O6P86uwfdR0', 'video', 32, 3),
-- React - Section 5
(5, 'useEffect & Side Effects', 'Fetching data, subscriptions, and managing side effects with useEffect...', 'https://www.youtube.com/watch?v=0ZJgIjIuY7U', 'video', 35, 1),
(5, 'Context API & State Management', 'Global state management with Context API and useReducer...', NULL, 'text', 40, 2),
(5, 'Custom Hooks', 'Building and reusing custom hooks to share logic across components...', 'https://www.youtube.com/watch?v=6ThXsUwLWvc', 'video', 30, 3),
-- React - Section 6
(6, 'Next.js Setup & File-based Routing', 'Installing Next.js, understanding pages directory, dynamic routes...', 'https://www.youtube.com/watch?v=mTz0GXj8NN0', 'video', 25, 1),
(6, 'API Routes & Server Components', 'Building backend APIs within Next.js, server vs client components...', 'https://www.youtube.com/watch?v=W4UhNo3HAMw', 'video', 45, 2),
(6, 'Deployment to Vercel', 'Deploying your Next.js application to production on Vercel...', 'https://www.youtube.com/watch?v=2HBIzEx6IZA', 'video', 20, 3),
-- Python - Section 7
(7, 'Python Installation & Setup', 'Installing Python, pip, virtual environments, and Jupyter notebooks...', 'https://www.youtube.com/watch?v=YYXdXT2l-Gg', 'video', 15, 1),
(7, 'Variables, Data Types & Operators', 'Python basics: variables, strings, numbers, booleans, lists, dicts...', 'https://www.youtube.com/watch?v=khKv-8q7YmY', 'video', 35, 2),
(7, 'Functions & Control Flow', 'Defining functions, if/else, loops, list comprehensions...', NULL, 'text', 40, 3),
-- Python - Section 8
(8, 'Introduction to Pandas', 'Loading, exploring, and manipulating data with Pandas DataFrames...', 'https://www.youtube.com/watch?v=vmEHCJofslg', 'video', 45, 1),
(8, 'Data Cleaning & Transformation', 'Handling missing values, merging datasets, applying transformations...', NULL, 'text', 50, 2),
(8, 'Statistical Analysis', 'Descriptive statistics, groupby operations, pivot tables...', 'https://www.youtube.com/watch?v=r-uOLxNrNk8', 'video', 40, 3),
-- Python - Section 9
(9, 'Matplotlib Basics', 'Creating line charts, bar charts, histograms, and scatter plots...', 'https://www.youtube.com/watch?v=3Xc3CA655Y4', 'video', 30, 1),
(9, 'Seaborn for Statistical Visualization', 'Beautiful statistical visualizations with Seaborn...', NULL, 'text', 35, 2),
(9, 'Dashboard with Plotly', 'Interactive dashboards using Plotly Express...', 'https://www.youtube.com/watch?v=GGL6U0k8WYA', 'video', 40, 3),
-- Oud - Section 10
(10, 'Parts of the Oud', 'Understanding the anatomy of the oud: body, neck, pegs, strings, and their tunings...', 'https://www.youtube.com/watch?v=Rh1bQkUCIAo', 'video', 10, 1),
(10, 'Holding the Oud & Pick Technique', 'Proper posture, how to hold the oud, and basic plectrum techniques...', 'https://www.youtube.com/watch?v=7DEFHmGSR0s', 'video', 15, 2),
(10, 'Your First Notes', 'Playing your first notes on the oud, simple exercises for finger placement...', NULL, 'text', 20, 3),
-- Oud - Section 11
(11, 'The Arabic Scale (Ajam)', 'Learning the Ajam scale, similar to the Western major scale...', 'https://www.youtube.com/watch?v=YOlT_EMv8jY', 'video', 22, 1),
(11, 'Rast Scale - The Heart of Arabic Music', 'Mastering Maqam Rast, the most fundamental Arabic scale...', 'https://www.youtube.com/watch?v=pCBikTDNmCM', 'video', 28, 2),
(11, 'Right Hand Patterns', 'Essential right hand patterns: single stroke, tremolo, and basic rhythmic patterns...', NULL, 'text', 25, 3),
-- Oud - Section 12
(12, 'Ya Msafer Wahdak', 'Learning the classic Egyptian song Ya Msafer Wahdak by Abdel Halim Hafez...', 'https://www.youtube.com/watch?v=IjC0lAFz_cA', 'video', 35, 1),
(12, 'Lamma Bada Yatathanna', 'This Andalusian classic piece step by step...', 'https://www.youtube.com/watch?v=V9Q_ER_8pAE', 'video', 40, 2),
(12, 'Improvisation Basics', 'Introduction to taqsim - free improvisation in Arabic music...', NULL, 'text', 30, 3),
-- Business Plan - Section 16
(16, 'What is a Business Plan?', 'Understanding the purpose, audience, and structure of a business plan...', 'https://www.youtube.com/watch?v=Fqch5OrUPvA', 'video', 12, 1),
(16, 'Executive Summary', 'Writing a compelling executive summary that captures attention in 2 minutes...', NULL, 'text', 20, 2),
(16, 'Business Model Canvas', 'Using the Business Model Canvas to map out your business on one page...', 'https://www.youtube.com/watch?v=IP0cUBWTgpY', 'video', 25, 3),
-- Business Plan - Section 17
(17, 'Market Size & TAM/SAM/SOM', 'Calculating total addressable, serviceable, and obtainable market sizes...', 'https://www.youtube.com/watch?v=v1aD1JU1L1w', 'video', 30, 1),
(17, 'Competitive Analysis', 'Mapping competitors, identifying your unique value proposition...', NULL, 'text', 25, 2),
(17, 'Customer Personas in the Gulf', 'Creating data-driven customer personas for Gulf market segments...', 'https://www.youtube.com/watch?v=Z5RxGHB2_E4', 'video', 28, 3),
-- Business Plan - Section 18
(18, 'Revenue Projections', 'Building bottom-up financial projections for 3-5 years...', 'https://www.youtube.com/watch?v=vl-7KJnzGsw', 'video', 35, 1),
(18, 'Funding Requirements', 'Calculating how much to raise and what to use it for...', NULL, 'text', 20, 2),
(18, 'Break-Even Analysis', 'Understanding when your business becomes profitable...', 'https://www.youtube.com/watch?v=IuQ0DkJqrpg', 'video', 22, 3);

-- Badges
INSERT INTO badges (community_id, name, description, icon, points_required, badge_type) VALUES
(NULL, 'First Steps', 'Joined your first community', '', 0, 'manual'),
(NULL, 'Scholar', 'Completed your first course', '', 0, 'course_complete'),
(NULL, 'Master', 'Completed 5 courses', '', 0, 'course_complete'),
(NULL, 'Contributor', 'Created 10 posts', '', 0, 'manual'),
(NULL, 'Popular', 'Received 100 likes', '', 0, 'points'),
(NULL, 'Streak Master', 'Maintained a 30-day streak', '', 0, 'streak'),
(NULL, 'Top Learner', 'Reached leaderboard #1', '', 1000, 'points'),
(1, 'Code Warrior', 'Completed all web development courses', '', 500, 'course_complete'),
(1, 'Algorithm Master', 'Solved 50 coding challenges', '', 750, 'points'),
(2, 'Maqam Master', 'Completed all maqam courses', '', 500, 'course_complete'),
(3, 'Gulf Shark', 'Top business contributor', '', 1000, 'points');

-- Award some badges to users
INSERT INTO user_badges (user_id, badge_id, community_id) VALUES
(2, 1, 1), (2, 2, 1), (2, 7, 1), (2, 8, 1),
(1, 1, 1), (1, 3, 1),
(3, 1, 2), (3, 2, 2), (3, 10, 2),
(4, 1, 3), (4, 7, 3);

-- Points
INSERT INTO user_points (user_id, community_id, points, reason) VALUES
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

-- Notification settings for users
INSERT INTO notification_settings (user_id, new_follower, post_likes, affiliate_referral) VALUES
(1, 1, 1, 1),
(2, 1, 1, 1),
(3, 1, 0, 1),
(4, 1, 1, 0);

-- Sample notifications
INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(1, 'new_follower', 'New Follower', 'Sarah Al-Hassan started following you', '/platform/profile.php?username=sarah_tech', 0),
(1, 'post_like', 'Post Liked', 'Khalid Al-Mansouri liked your post', '/platform/community.php?slug=gulf-business-network', 1),
(2, 'membership_approved', 'Welcome!', 'Your membership to Gulf Business Network was approved', '/platform/community.php?slug=gulf-business-network', 0),
(3, 'new_follower', 'New Follower', 'Ahmad Al-Rashid started following you', '/platform/profile.php?username=admin', 1),
(4, 'points_awarded', 'Points Earned', 'You earned 50 XP in Gulf Business Network', '/platform/community.php?slug=gulf-business-network', 0);

-- Follows
INSERT INTO follows (follower_id, following_id) VALUES
(1, 2), (1, 3), (2, 1), (2, 4), (3, 1), (3, 2), (4, 1), (4, 2), (4, 3);
