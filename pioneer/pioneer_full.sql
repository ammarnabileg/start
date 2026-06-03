-- ============================================================
--  PioneerIcons — Full Schema + Seed Data
--  Run once on your MySQL database
--  Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
--  TABLES
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pi_settings` (
  `s_id`    int(11)      NOT NULL AUTO_INCREMENT,
  `s_key`   varchar(100) NOT NULL,
  `s_value` text         DEFAULT NULL,
  PRIMARY KEY (`s_id`),
  UNIQUE KEY `s_key` (`s_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_countries` (
  `c_id`     int(11)     NOT NULL AUTO_INCREMENT,
  `c_name`   varchar(100) NOT NULL,
  `c_flag`   varchar(20)  DEFAULT '🌍',
  `c_code`   varchar(10)  DEFAULT NULL,
  `c_active` tinyint(1)   DEFAULT 1,
  `c_order`  int(11)      DEFAULT 0,
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_categories` (
  `cat_id`          int(11)      NOT NULL AUTO_INCREMENT,
  `cat_name`        varchar(200) NOT NULL,
  `cat_name_en`     varchar(200) DEFAULT NULL,
  `cat_icon`        varchar(100) DEFAULT 'fa-star',
  `cat_badge_color` varchar(50)  DEFAULT 'blue',
  `cat_order`       int(11)      DEFAULT 0,
  `cat_active`      tinyint(1)   DEFAULT 1,
  `cat_created`     timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_personalities` (
  `p_id`              int(11)      NOT NULL AUTO_INCREMENT,
  `p_name_ar`         varchar(300) NOT NULL,
  `p_name_en`         varchar(300) DEFAULT NULL,
  `p_title`           varchar(300) DEFAULT NULL,
  `p_nationality`     varchar(100) DEFAULT NULL,
  `p_residence`       varchar(100) DEFAULT NULL,
  `p_bio`             text         DEFAULT NULL,
  `p_bio_platform`    text         DEFAULT NULL,
  `p_photo`           varchar(500) DEFAULT NULL,
  `p_verified`        tinyint(1)   DEFAULT 0,
  `p_membership_type` enum('standard','verified','executive') DEFAULT 'standard',
  `p_country_id`      int(11)      DEFAULT 0,
  `p_views`           int(11)      DEFAULT 0,
  `p_active`          tinyint(1)   DEFAULT 1,
  `p_created`         timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`p_id`),
  KEY `idx_p_country` (`p_country_id`),
  KEY `idx_p_views`   (`p_views`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_institutions` (
  `inst_id`          int(11)      NOT NULL AUTO_INCREMENT,
  `inst_name_ar`     varchar(300) NOT NULL,
  `inst_name_en`     varchar(300) DEFAULT NULL,
  `inst_logo`        varchar(500) DEFAULT NULL,
  `inst_description` text         DEFAULT NULL,
  `inst_country_id`  int(11)      DEFAULT 0,
  `inst_verified`    tinyint(1)   DEFAULT 0,
  `inst_views`       int(11)      DEFAULT 0,
  `inst_active`      tinyint(1)   DEFAULT 1,
  `inst_created`     timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inst_id`),
  KEY `idx_inst_country` (`inst_country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_personality_categories` (
  `id`     int(11) NOT NULL AUTO_INCREMENT,
  `p_id`   int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pc_p_id`   (`p_id`),
  KEY `pc_cat_id` (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_institution_categories` (
  `id`      int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL,
  `cat_id`  int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_social_links` (
  `sl_id`          int(11)  NOT NULL AUTO_INCREMENT,
  `sl_entity_type` enum('personality','institution') DEFAULT 'personality',
  `sl_entity_id`   int(11)  NOT NULL,
  `sl_platform`    varchar(50)  NOT NULL,
  `sl_url`         varchar(500) NOT NULL,
  PRIMARY KEY (`sl_id`),
  KEY `idx_sl_entity` (`sl_entity_type`, `sl_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_timeline` (
  `tl_id`             int(11)  NOT NULL AUTO_INCREMENT,
  `tl_p_id`           int(11)  NOT NULL,
  `tl_type`           enum('education','work') DEFAULT 'work',
  `tl_title`          varchar(300) NOT NULL,
  `tl_institution`    varchar(300) DEFAULT NULL,
  `tl_institution_id` int(11)      DEFAULT NULL,
  `tl_year_start`     varchar(10)  DEFAULT NULL,
  `tl_year_end`       varchar(10)  DEFAULT NULL,
  `tl_order`          int(11)      DEFAULT 0,
  PRIMARY KEY (`tl_id`),
  KEY `idx_tl_p_id` (`tl_p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_articles` (
  `art_id`      int(11)      NOT NULL AUTO_INCREMENT,
  `art_p_id`    int(11)      NOT NULL,
  `art_title`   varchar(500) NOT NULL,
  `art_source`  varchar(200) DEFAULT NULL,
  `art_url`     varchar(500) DEFAULT NULL,
  `art_image`   varchar(500) DEFAULT NULL,
  `art_active`  tinyint(1)   DEFAULT 1,
  `art_created` timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`art_id`),
  KEY `idx_art_p_id` (`art_p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_related_personalities` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `p_id`          int(11) NOT NULL,
  `related_p_id`  int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rp_p_id` (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_daily_personality` (
  `dp_id`   int(11) NOT NULL AUTO_INCREMENT,
  `dp_p_id` int(11) NOT NULL,
  `dp_date` date    NOT NULL,
  PRIMARY KEY (`dp_id`),
  UNIQUE KEY `uq_dp_date` (`dp_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_sponsors` (
  `sp_id`     int(11)      NOT NULL AUTO_INCREMENT,
  `sp_name`   varchar(200) NOT NULL,
  `sp_logo`   varchar(500) DEFAULT NULL,
  `sp_url`    varchar(500) DEFAULT NULL,
  `sp_active` tinyint(1)   DEFAULT 1,
  `sp_order`  int(11)      DEFAULT 0,
  PRIMARY KEY (`sp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_roles` (
  `role_id`          int(11)      NOT NULL AUTO_INCREMENT,
  `role_name`        varchar(200) NOT NULL,
  `role_permissions` text         DEFAULT NULL,
  `role_created`     timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_admin_users` (
  `au_id`       int(11)      NOT NULL AUTO_INCREMENT,
  `au_name`     varchar(200) NOT NULL,
  `au_email`    varchar(200) NOT NULL,
  `au_password` varchar(255) NOT NULL,
  `au_role_id`  int(11)      DEFAULT NULL,
  `au_active`   tinyint(1)   DEFAULT 1,
  `au_created`  timestamp    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`au_id`),
  UNIQUE KEY `uq_au_email` (`au_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pi_submissions` (
  `sub_id`              int(11)   NOT NULL AUTO_INCREMENT,
  `sub_type`            enum('personality','institution') DEFAULT 'personality',
  `sub_data`            text      DEFAULT NULL,
  `sub_status`          enum('pending','approved','rejected') DEFAULT 'pending',
  `sub_submitter_name`  varchar(200) DEFAULT NULL,
  `sub_submitter_email` varchar(200) DEFAULT NULL,
  `sub_note`            text      DEFAULT NULL,
  `sub_created`         timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sub_id`),
  KEY `idx_sub_status` (`sub_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────────
--  SEED DATA
-- ─────────────────────────────────────────────────────────────

-- ── Site Settings ─────────────────────────────────────────────
INSERT INTO `pi_settings` (`s_key`, `s_value`) VALUES
  ('site_name',        'PioneerIcons'),
  ('site_name_ar',     'من هم'),
  ('site_tagline',     'منصة الحضور العربي الموثق'),
  ('site_description', 'تحكم بما يعرفه الناس عنك — دليل الشخصيات والمؤسسات العربية الرائدة'),
  ('site_keywords',    'شخصيات عربية, مؤسسات, من هم, PioneerIcons, دليل عربي'),
  ('site_logo',        ''),
  ('footer_about',     'منصة الحضور العربي الموثق — تحكم بما يعرفه الناس عنك. نوثق الشخصيات والمؤسسات العربية الرائدة في مختلف المجالات.'),
  ('social_whatsapp',  ''),
  ('social_linkedin',  ''),
  ('social_twitter',   ''),
  ('primary_color',    '#8829C8'),
  ('admin_email',      'admin@pioneericons.com'),
  ('copyright_text',   'جميع الحقوق محفوظة لـ PioneerIcons'),
  ('google_analytics', ''),
  ('default_country',  '0')
ON DUPLICATE KEY UPDATE s_value = VALUES(s_value);

-- ── Countries ─────────────────────────────────────────────────
INSERT INTO `pi_countries` (`c_id`, `c_name`, `c_flag`, `c_code`, `c_active`, `c_order`) VALUES
  (1,  'السعودية', '🇸🇦', 'sa', 1, 1),
  (2,  'مصر',      '🇪🇬', 'eg', 1, 2),
  (3,  'الإمارات', '🇦🇪', 'ae', 1, 3),
  (4,  'الكويت',   '🇰🇼', 'kw', 1, 4),
  (5,  'البحرين',  '🇧🇭', 'bh', 1, 5),
  (6,  'عمان',     '🇴🇲', 'om', 1, 6),
  (7,  'قطر',      '🇶🇦', 'qa', 1, 7),
  (8,  'سوريا',    '🇸🇾', 'sy', 1, 8),
  (9,  'العراق',   '🇮🇶', 'iq', 1, 9),
  (10, 'الأردن',   '🇯🇴', 'jo', 1, 10),
  (11, 'لبنان',    '🇱🇧', 'lb', 1, 11),
  (12, 'المغرب',   '🇲🇦', 'ma', 1, 12),
  (13, 'تونس',     '🇹🇳', 'tn', 1, 13),
  (14, 'الجزائر',  '🇩🇿', 'dz', 1, 14),
  (15, 'ليبيا',    '🇱🇾', 'ly', 1, 15),
  (16, 'اليمن',    '🇾🇪', 'ye', 1, 16),
  (17, 'السودان',  '🇸🇩', 'sd', 1, 17)
ON DUPLICATE KEY UPDATE c_name=VALUES(c_name);

-- ── Categories ────────────────────────────────────────────────
INSERT INTO `pi_categories` (`cat_id`, `cat_name`, `cat_name_en`, `cat_icon`, `cat_badge_color`, `cat_order`) VALUES
  (1,  'ريادة الأعمال',    'Entrepreneurship', 'fa-rocket',        'orange',   1),
  (2,  'التعليم',           'Education',        'fa-graduation-cap','blue',     2),
  (3,  'الفن والثقافة',     'Arts & Culture',   'fa-palette',       'purple',   3),
  (4,  'التكنولوجيا',       'Technology',       'fa-microchip',     'cyan',     4),
  (5,  'الصحافة والإعلام',  'Media',            'fa-newspaper',     'red',      5),
  (6,  'الصحة والطب',       'Health',           'fa-heart-pulse',   'green',    6),
  (7,  'الرياضة',           'Sports',           'fa-trophy',        'gold',     7),
  (8,  'السياسة والقيادة',  'Politics',         'fa-landmark',      'navy',     8),
  (9,  'العلوم والبحث',     'Science',          'fa-flask',         'teal',     9),
  (10, 'الأدب والكتابة',    'Literature',       'fa-book-open',     'brown',    10),
  (11, 'الهندسة والعمارة',  'Engineering',      'fa-building',      'gray',     11),
  (12, 'الاقتصاد والمال',   'Finance',          'fa-chart-line',    'darkblue', 12)
ON DUPLICATE KEY UPDATE cat_name=VALUES(cat_name);

-- ── Roles ─────────────────────────────────────────────────────
--
--  Permissions reference:
--   1  view_personalities     2  add_personality      3  edit_personality    4  delete_personality
--   5  view_institutions      6  add_institution      7  edit_institution    8  delete_institution
--   9  view_categories       10  add_category        11  edit_category      12  delete_category
--  13  view_articles         14  add_article         15  edit_article       16  delete_article
--  17  view_roles            18  add_role            19  edit_role          20  delete_role
--  21  view_admin_users      22  add_admin_user      23  edit_admin_user    24  delete_admin_user
--  25  view_sponsors         26  manage_sponsors     27  view_timeline      28  manage_timeline
--  29  manage_countries      30  manage_settings
--
INSERT INTO `pi_roles` (`role_id`, `role_name`, `role_permissions`) VALUES
  (1, 'مدير النظام',   '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30'),
  (2, 'محرر محتوى',    '1,2,3,5,6,7,9,13,14,15,27,28'),
  (3, 'مشرف عام',      '1,2,3,5,6,7,9,10,11,13,14,15,25,27,28'),
  (4, 'مراجع مقترحات', '1,5,9,13')
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), role_permissions=VALUES(role_permissions);

-- ── Admin Users ───────────────────────────────────────────────
--  Default password for ALL users below: admin123
--  Hash generated with: password_hash('admin123', PASSWORD_DEFAULT)
--  ⚠️  Change passwords immediately after first login!
--
INSERT INTO `pi_admin_users` (`au_id`, `au_name`, `au_email`, `au_password`, `au_role_id`, `au_active`) VALUES
  (1, 'مدير النظام',   'admin@pioneericons.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)
ON DUPLICATE KEY UPDATE au_name=VALUES(au_name);

-- ─────────────────────────────────────────────────────────────
--  DONE
-- ─────────────────────────────────────────────────────────────

SET FOREIGN_KEY_CHECKS = 1;

/*
  ✅ Installation complete!

  Login at: /pioneer/admin.php?p=login
  Email   : admin@pioneericons.com
  Password: admin123

  ⚠️  Change the admin password immediately after login!
*/
