-- ============================================================
--  PioneerIcons — Full Schema + Seed Data
--  Run once on a FRESH / empty database
--  Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
--  DROP (safe clean install)
-- ─────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `pi_list_blocks`;
DROP TABLE IF EXISTS `pi_list_items`;
DROP TABLE IF EXISTS `pi_lists`;
DROP TABLE IF EXISTS `pi_edit_requests`;
DROP TABLE IF EXISTS `pi_complaints`;
DROP TABLE IF EXISTS `pi_users`;
DROP TABLE IF EXISTS `pi_labels`;
DROP TABLE IF EXISTS `pi_advertise`;
DROP TABLE IF EXISTS `pi_memberships`;
DROP TABLE IF EXISTS `pi_submissions`;
DROP TABLE IF EXISTS `pi_admin_users`;
DROP TABLE IF EXISTS `pi_roles`;
DROP TABLE IF EXISTS `pi_sponsors`;
DROP TABLE IF EXISTS `pi_daily_personality`;
DROP TABLE IF EXISTS `pi_related_personalities`;
DROP TABLE IF EXISTS `pi_articles`;
DROP TABLE IF EXISTS `pi_timeline`;
DROP TABLE IF EXISTS `pi_social_links`;
DROP TABLE IF EXISTS `pi_institution_categories`;
DROP TABLE IF EXISTS `pi_personality_categories`;
DROP TABLE IF EXISTS `pi_institutions`;
DROP TABLE IF EXISTS `pi_personalities`;
DROP TABLE IF EXISTS `pi_categories`;
DROP TABLE IF EXISTS `pi_countries`;
DROP TABLE IF EXISTS `pi_visits`;
DROP TABLE IF EXISTS `pi_settings`;

-- ─────────────────────────────────────────────────────────────
--  TABLES
-- ─────────────────────────────────────────────────────────────

CREATE TABLE `pi_settings` (
  `s_id`    INT          NOT NULL AUTO_INCREMENT,
  `s_key`   VARCHAR(100) NOT NULL,
  `s_value` TEXT         DEFAULT NULL,
  PRIMARY KEY (`s_id`),
  UNIQUE KEY `s_key` (`s_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_countries` (
  `c_id`     INT          NOT NULL AUTO_INCREMENT,
  `c_name`   VARCHAR(100) NOT NULL,
  `c_flag`   VARCHAR(20)  DEFAULT '🌍',
  `c_code`   VARCHAR(10)  DEFAULT NULL,
  `c_active` TINYINT(1)   DEFAULT 1,
  `c_order`  INT          DEFAULT 0,
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_categories` (
  `cat_id`          INT          NOT NULL AUTO_INCREMENT,
  `cat_name`        VARCHAR(200) NOT NULL,
  `cat_name_en`     VARCHAR(200) DEFAULT NULL,
  `cat_icon`        VARCHAR(100) DEFAULT 'fa-star',
  `cat_badge_color` VARCHAR(50)  DEFAULT 'blue',
  `cat_label_id`    INT          DEFAULT NULL,
  `cat_order`       INT          DEFAULT 0,
  `cat_active`      TINYINT(1)   DEFAULT 1,
  `cat_created`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_personalities` (
  `p_id`              INT          NOT NULL AUTO_INCREMENT,
  `p_name_ar`         VARCHAR(300) NOT NULL,
  `p_name_en`         VARCHAR(300) DEFAULT NULL,
  `p_title`           VARCHAR(300) DEFAULT NULL,
  `p_nationality`     VARCHAR(100) DEFAULT NULL,
  `p_residence`       VARCHAR(100) DEFAULT NULL,
  `p_bio`             TEXT         DEFAULT NULL,
  `p_bio_platform`    TEXT         DEFAULT NULL,
  `p_photo`           VARCHAR(500) DEFAULT NULL,
  `p_verified`        TINYINT(1)   DEFAULT 0,
  `p_membership_type` ENUM('standard','verified','executive') DEFAULT 'standard',
  `p_country_id`      INT          DEFAULT 0,
  `p_views`           INT          DEFAULT 0,
  `p_active`          TINYINT(1)   DEFAULT 1,
  `p_added_by_user`   INT          DEFAULT NULL,
  `p_position`        VARCHAR(300) DEFAULT NULL,
  `p_created`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`p_id`),
  KEY `idx_p_country` (`p_country_id`),
  KEY `idx_p_views`   (`p_views`),
  KEY `idx_p_active`  (`p_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_institutions` (
  `inst_id`             INT          NOT NULL AUTO_INCREMENT,
  `inst_name_ar`        VARCHAR(300) NOT NULL,
  `inst_name_en`        VARCHAR(300) DEFAULT NULL,
  `inst_logo`           VARCHAR(500) DEFAULT NULL,
  `inst_description`    TEXT         DEFAULT NULL,
  `inst_country_id`     INT          DEFAULT 0,
  `inst_verified`       TINYINT(1)   DEFAULT 0,
  `inst_membership_type` ENUM('standard','verified','executive') DEFAULT 'standard',
  `inst_views`          INT          DEFAULT 0,
  `inst_active`         TINYINT(1)   DEFAULT 1,
  `inst_added_by_user`  INT          DEFAULT NULL,
  `inst_country`        VARCHAR(100) DEFAULT NULL,
  `inst_created`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inst_id`),
  KEY `idx_inst_country` (`inst_country_id`),
  KEY `idx_inst_active`  (`inst_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_personality_categories` (
  `id`     INT NOT NULL AUTO_INCREMENT,
  `p_id`   INT NOT NULL,
  `cat_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pc_p_id`   (`p_id`),
  KEY `pc_cat_id` (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_institution_categories` (
  `id`      INT NOT NULL AUTO_INCREMENT,
  `inst_id` INT NOT NULL,
  `cat_id`  INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ic_inst_id` (`inst_id`),
  KEY `ic_cat_id`  (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_social_links` (
  `sl_id`          INT          NOT NULL AUTO_INCREMENT,
  `sl_entity_type` ENUM('personality','institution') DEFAULT 'personality',
  `sl_entity_id`   INT          NOT NULL,
  `sl_platform`    VARCHAR(50)  NOT NULL,
  `sl_url`         VARCHAR(500) NOT NULL,
  PRIMARY KEY (`sl_id`),
  KEY `idx_sl_entity` (`sl_entity_type`, `sl_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_timeline` (
  `tl_id`             INT          NOT NULL AUTO_INCREMENT,
  `tl_p_id`           INT          NOT NULL,
  `tl_type`           ENUM('education','work') DEFAULT 'work',
  `tl_title`          VARCHAR(300) NOT NULL,
  `tl_institution`    VARCHAR(300) DEFAULT NULL,
  `tl_institution_id` INT          DEFAULT NULL,
  `tl_year_start`     VARCHAR(10)  DEFAULT NULL,
  `tl_year_end`       VARCHAR(10)  DEFAULT NULL,
  `tl_order`          INT          DEFAULT 0,
  PRIMARY KEY (`tl_id`),
  KEY `idx_tl_p_id` (`tl_p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_articles` (
  `art_id`      INT          NOT NULL AUTO_INCREMENT,
  `art_p_id`    INT          NOT NULL,
  `art_title`   VARCHAR(500) NOT NULL,
  `art_source`  VARCHAR(200) DEFAULT NULL,
  `art_url`     VARCHAR(500) DEFAULT NULL,
  `art_image`   VARCHAR(500) DEFAULT NULL,
  `art_active`  TINYINT(1)   DEFAULT 1,
  `art_created` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`art_id`),
  KEY `idx_art_p_id` (`art_p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_related_personalities` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `p_id`         INT NOT NULL,
  `related_p_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rp_p_id` (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_daily_personality` (
  `dp_id`   INT  NOT NULL AUTO_INCREMENT,
  `dp_p_id` INT  NOT NULL,
  `dp_date` DATE NOT NULL,
  PRIMARY KEY (`dp_id`),
  UNIQUE KEY `uq_dp_date` (`dp_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_sponsors` (
  `sp_id`     INT          NOT NULL AUTO_INCREMENT,
  `sp_name`   VARCHAR(200) NOT NULL,
  `sp_logo`   VARCHAR(500) DEFAULT NULL,
  `sp_url`    VARCHAR(500) DEFAULT NULL,
  `sp_active` TINYINT(1)   DEFAULT 1,
  `sp_order`  INT          DEFAULT 0,
  PRIMARY KEY (`sp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_labels` (
  `label_id`      INT          NOT NULL AUTO_INCREMENT,
  `label_name`    VARCHAR(100) NOT NULL,
  `label_color`   VARCHAR(20)  DEFAULT '#8829C8',
  `label_order`   INT          DEFAULT 0,
  `label_active`  TINYINT(1)   DEFAULT 1,
  `label_created` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_roles` (
  `role_id`          INT          NOT NULL AUTO_INCREMENT,
  `role_name`        VARCHAR(200) NOT NULL,
  `role_permissions` TEXT         DEFAULT NULL,
  `role_created`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_admin_users` (
  `au_id`       INT          NOT NULL AUTO_INCREMENT,
  `au_name`     VARCHAR(200) NOT NULL,
  `au_email`    VARCHAR(200) NOT NULL,
  `au_password` VARCHAR(255) NOT NULL,
  `au_role_id`  INT          DEFAULT NULL,
  `au_active`   TINYINT(1)   DEFAULT 1,
  `au_created`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`au_id`),
  UNIQUE KEY `uq_au_email` (`au_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_visits` (
  `v_id`      INT          NOT NULL AUTO_INCREMENT,
  `v_page`    VARCHAR(255) DEFAULT NULL,
  `v_ip`      VARCHAR(45)  DEFAULT NULL,
  `v_user_id` INT          DEFAULT NULL,
  `v_ref`     VARCHAR(500) DEFAULT NULL,
  `v_created` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`v_id`),
  KEY `idx_v_created` (`v_created`),
  KEY `idx_v_page`    (`v_page`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_submissions` (
  `sub_id`              INT          NOT NULL AUTO_INCREMENT,
  `sub_type`            ENUM('personality','institution') DEFAULT 'personality',
  `sub_data`            TEXT         DEFAULT NULL,
  `sub_status`          ENUM('pending','approved','rejected') DEFAULT 'pending',
  `sub_submitter_name`  VARCHAR(200) DEFAULT NULL,
  `sub_submitter_email` VARCHAR(200) DEFAULT NULL,
  `sub_user_id`         INT          DEFAULT NULL,
  `sub_note`            TEXT         DEFAULT NULL,
  `sub_created`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sub_id`),
  KEY `idx_sub_status` (`sub_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_memberships` (
  `mem_id`          INT          NOT NULL AUTO_INCREMENT,
  `mem_plan`        ENUM('monthly','lifetime') NOT NULL,
  `mem_name`        VARCHAR(200) NOT NULL,
  `mem_phone`       VARCHAR(50)  NOT NULL,
  `mem_email`       VARCHAR(200) NOT NULL,
  `mem_profile_url` VARCHAR(500) DEFAULT NULL,
  `mem_status`      ENUM('pending','active','cancelled') DEFAULT 'pending',
  `mem_created`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mem_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_advertise` (
  `adv_id`      INT          NOT NULL AUTO_INCREMENT,
  `adv_company` VARCHAR(200) NOT NULL,
  `adv_contact` VARCHAR(200) NOT NULL,
  `adv_phone`   VARCHAR(50)  NOT NULL,
  `adv_email`   VARCHAR(200) NOT NULL,
  `adv_note`    TEXT         DEFAULT NULL,
  `adv_status`  ENUM('new','contacted','done') DEFAULT 'new',
  `adv_created` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_users` (
  `u_id`          INT          NOT NULL AUTO_INCREMENT,
  `u_name`        VARCHAR(200) NOT NULL,
  `u_email`       VARCHAR(200) NOT NULL,
  `u_password`    VARCHAR(255) NOT NULL,
  `u_phone`       VARCHAR(50)  DEFAULT NULL,
  `u_photo`       VARCHAR(500) DEFAULT NULL,
  `u_nationality` VARCHAR(100) DEFAULT NULL,
  `u_company`     VARCHAR(200) DEFAULT NULL,
  `u_job`         VARCHAR(200) DEFAULT NULL,
  `u_birthdate`   DATE         DEFAULT NULL,
  `u_gender`      ENUM('male','female','') DEFAULT '',
  `u_plan`        ENUM('free','verified','executive') DEFAULT 'free',
  `u_active`      TINYINT(1)   DEFAULT 1,
  `u_verified`    TINYINT(1)   DEFAULT 0,
  `u_created`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`u_id`),
  UNIQUE KEY `uq_u_email` (`u_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_complaints` (
  `cmp_id`      INT          NOT NULL AUTO_INCREMENT,
  `cmp_user_id` INT          DEFAULT NULL,
  `cmp_type`    ENUM('complaint','suggestion','feedback','request') DEFAULT 'complaint',
  `cmp_subject` VARCHAR(300) NOT NULL,
  `cmp_message` TEXT         NOT NULL,
  `cmp_name`    VARCHAR(200) DEFAULT NULL,
  `cmp_email`   VARCHAR(200) DEFAULT NULL,
  `cmp_status`  ENUM('new','read','resolved') DEFAULT 'new',
  `cmp_created` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cmp_id`),
  KEY `idx_cmp_status` (`cmp_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_edit_requests` (
  `er_id`          INT          NOT NULL AUTO_INCREMENT,
  `er_user_id`     INT          NOT NULL,
  `er_entity_type` ENUM('personality','institution') DEFAULT 'personality',
  `er_entity_id`   INT          NOT NULL,
  `er_req_type`    ENUM('edit','upgrade') DEFAULT 'edit',
  `er_edit_data`   TEXT         DEFAULT NULL,
  `er_notes`       TEXT         DEFAULT NULL,
  `er_upgrade_to`  VARCHAR(50)  DEFAULT NULL,
  `er_status`      ENUM('pending','approved','rejected') DEFAULT 'pending',
  `er_admin_note`  TEXT         DEFAULT NULL,
  `er_created`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`er_id`),
  KEY `idx_er_user`   (`er_user_id`),
  KEY `idx_er_entity` (`er_entity_type`, `er_entity_id`),
  KEY `idx_er_status` (`er_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_lists` (
  `list_id`           INT          NOT NULL AUTO_INCREMENT,
  `list_title`        VARCHAR(500) NOT NULL,
  `list_title_en`     VARCHAR(500) DEFAULT NULL,
  `list_slug`         VARCHAR(300) DEFAULT NULL,
  `list_description`  TEXT         DEFAULT NULL,
  `list_criteria`     TEXT         DEFAULT NULL,
  `list_cover`        VARCHAR(500) DEFAULT NULL,
  `list_logo`         VARCHAR(500) DEFAULT NULL,
  `list_year`         VARCHAR(10)  DEFAULT NULL,
  `list_columns`      TEXT         DEFAULT NULL,
  `list_blocks`       TEXT         DEFAULT NULL,
  `list_active`       TINYINT(1)   DEFAULT 1,
  `list_order`        INT          DEFAULT 0,
  `list_views`        INT          DEFAULT 0,
  `list_sponsor_id`   INT          DEFAULT NULL,
  `list_sponsor_img`  VARCHAR(500) DEFAULT NULL,
  `list_sponsor_url`  VARCHAR(500) DEFAULT NULL,
  `list_sponsor_name` VARCHAR(300) DEFAULT NULL,
  `list_spotlight`    TEXT         DEFAULT NULL,
  `list_created`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`list_id`),
  KEY `idx_list_active` (`list_active`),
  KEY `idx_list_slug`   (`list_slug`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_list_items` (
  `li_id`          INT  NOT NULL AUTO_INCREMENT,
  `li_list_id`     INT  NOT NULL,
  `li_entity_type` ENUM('personality','institution') DEFAULT 'personality',
  `li_entity_id`   INT  NOT NULL,
  `li_rank`        INT  DEFAULT 0,
  `li_order`       INT  DEFAULT 0,
  `li_data`        TEXT DEFAULT NULL,
  `li_custom_data` TEXT DEFAULT NULL,
  PRIMARY KEY (`li_id`),
  KEY `idx_li_list`   (`li_list_id`),
  KEY `idx_li_entity` (`li_entity_type`, `li_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
CREATE TABLE `pi_list_blocks` (
  `lb_id`      INT  NOT NULL AUTO_INCREMENT,
  `lb_list_id` INT  NOT NULL,
  `lb_type`    ENUM('text','image','video') DEFAULT 'text',
  `lb_content` TEXT DEFAULT NULL,
  `lb_order`   INT  DEFAULT 0,
  PRIMARY KEY (`lb_id`),
  KEY `idx_lb_list` (`lb_list_id`)
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
  (1,  'ريادة الأعمال',    'Entrepreneurship', 'fa-rocket',         'orange',   1),
  (2,  'التعليم',           'Education',        'fa-graduation-cap', 'blue',     2),
  (3,  'الفن والثقافة',     'Arts & Culture',   'fa-palette',        'purple',   3),
  (4,  'التكنولوجيا',       'Technology',       'fa-microchip',      'cyan',     4),
  (5,  'الصحافة والإعلام',  'Media',            'fa-newspaper',      'red',      5),
  (6,  'الصحة والطب',       'Health',           'fa-heart-pulse',    'green',    6),
  (7,  'الرياضة',           'Sports',           'fa-trophy',         'gold',     7),
  (8,  'السياسة والقيادة',  'Politics',         'fa-landmark',       'navy',     8),
  (9,  'العلوم والبحث',     'Science',          'fa-flask',          'teal',     9),
  (10, 'الأدب والكتابة',    'Literature',       'fa-book-open',      'brown',    10),
  (11, 'الهندسة والعمارة',  'Engineering',      'fa-building',       'gray',     11),
  (12, 'الاقتصاد والمال',   'Finance',          'fa-chart-line',     'darkblue', 12)
ON DUPLICATE KEY UPDATE cat_name=VALUES(cat_name);

-- ── Roles ─────────────────────────────────────────────────────
--  Permissions:
--   1  view_personalities     2  add_personality      3  edit_personality    4  delete_personality
--   5  view_institutions      6  add_institution      7  edit_institution    8  delete_institution
--   9  view_categories       10  add_category        11  edit_category      12  delete_category
--  13  view_articles         14  add_article         15  edit_article       16  delete_article
--  17  view_roles            18  add_role            19  edit_role          20  delete_role
--  21  view_admin_users      22  add_admin_user      23  edit_admin_user    24  delete_admin_user
--  25  view_sponsors         26  manage_sponsors     27  view_timeline      28  manage_timeline
--  29  manage_countries      30  manage_settings     31  manage_users       32  manage_advertise
--  33  manage_memberships    34  manage_complaints   35  manage_submissions 36  manage_edit_requests
--  37  manage_lists
INSERT INTO `pi_roles` (`role_id`, `role_name`, `role_permissions`) VALUES
  (1, 'مدير النظام',   '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37'),
  (2, 'محرر محتوى',    '1,2,3,5,6,7,9,13,14,15,27,28'),
  (3, 'مشرف عام',      '1,2,3,5,6,7,9,10,11,13,14,15,25,27,28'),
  (4, 'مراجع مقترحات', '1,5,9,13,35')
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), role_permissions=VALUES(role_permissions);

-- ── Admin Users ───────────────────────────────────────────────
--  Default password: admin123
--  ⚠️  Change immediately after first login!
INSERT INTO `pi_admin_users` (`au_id`, `au_name`, `au_email`, `au_password`, `au_role_id`, `au_active`) VALUES
  (1, 'مدير النظام', 'admin@pioneericons.com', '$2y$12$JdbNysbQnXW3eIL6nz4ElupiPsv5HT83bd04FVBv2BLrKUUV/4mhu', 1, 1)
ON DUPLICATE KEY UPDATE au_name=VALUES(au_name);

-- ─────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

/*
  ✅ Installation complete!

  Admin login : /admin.php?p=login
  Email       : admin@pioneericons.com
  Password    : admin123

  ⚠️  Change the admin password immediately after login!
*/
