<?php
// Run this ONCE to install the database schema
// Access: /pioneer/install.php
// Delete or rename after installation

require_once 'includes/config.php';

$errors = [];
$success = [];

$sql_statements = [
"CREATE TABLE IF NOT EXISTS `pi_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(200) NOT NULL,
  `cat_name_en` varchar(200) DEFAULT NULL,
  `cat_icon` varchar(100) DEFAULT 'fa-star',
  `cat_badge_color` varchar(50) DEFAULT 'blue',
  `cat_order` int(11) DEFAULT 0,
  `cat_active` tinyint(1) DEFAULT 1,
  `cat_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_personalities` (
  `p_id` int(11) NOT NULL AUTO_INCREMENT,
  `p_name_ar` varchar(300) NOT NULL,
  `p_name_en` varchar(300) DEFAULT NULL,
  `p_title` varchar(300) DEFAULT NULL,
  `p_nationality` varchar(100) DEFAULT NULL,
  `p_residence` varchar(100) DEFAULT NULL,
  `p_bio` text DEFAULT NULL,
  `p_bio_platform` text DEFAULT NULL,
  `p_photo` varchar(500) DEFAULT NULL,
  `p_verified` tinyint(1) DEFAULT 0,
  `p_membership_type` enum('standard','verified','executive') DEFAULT 'standard',
  `p_views` int(11) DEFAULT 0,
  `p_country_id` int(11) DEFAULT 0,
  `p_active` tinyint(1) DEFAULT 1,
  `p_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_countries` (
  `c_id` int(11) NOT NULL AUTO_INCREMENT,
  `c_name` varchar(100) NOT NULL,
  `c_flag` varchar(20) DEFAULT '🌍',
  `c_code` varchar(10) DEFAULT NULL,
  `c_active` tinyint(1) DEFAULT 1,
  `c_order` int(11) DEFAULT 0,
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_settings` (
  `s_id` int(11) NOT NULL AUTO_INCREMENT,
  `s_key` varchar(100) NOT NULL,
  `s_value` text DEFAULT NULL,
  PRIMARY KEY (`s_id`),
  UNIQUE KEY `s_key` (`s_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_institutions` (
  `inst_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_name_ar` varchar(300) NOT NULL,
  `inst_name_en` varchar(300) DEFAULT NULL,
  `inst_logo` varchar(500) DEFAULT NULL,
  `inst_description` text DEFAULT NULL,
  `inst_country_id` int(11) DEFAULT 0,
  `inst_verified` tinyint(1) DEFAULT 0,
  `inst_views` int(11) DEFAULT 0,
  `inst_active` tinyint(1) DEFAULT 1,
  `inst_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_personality_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_institution_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_social_links` (
  `sl_id` int(11) NOT NULL AUTO_INCREMENT,
  `sl_entity_type` enum('personality','institution') DEFAULT 'personality',
  `sl_entity_id` int(11) NOT NULL,
  `sl_platform` varchar(50) NOT NULL,
  `sl_url` varchar(500) NOT NULL,
  PRIMARY KEY (`sl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_timeline` (
  `tl_id` int(11) NOT NULL AUTO_INCREMENT,
  `tl_p_id` int(11) NOT NULL,
  `tl_type` enum('education','work') DEFAULT 'work',
  `tl_title` varchar(300) NOT NULL,
  `tl_institution` varchar(300) DEFAULT NULL,
  `tl_institution_id` int(11) DEFAULT NULL,
  `tl_year_start` varchar(10) DEFAULT NULL,
  `tl_year_end` varchar(10) DEFAULT NULL,
  `tl_order` int(11) DEFAULT 0,
  PRIMARY KEY (`tl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_articles` (
  `art_id` int(11) NOT NULL AUTO_INCREMENT,
  `art_p_id` int(11) NOT NULL,
  `art_title` varchar(500) NOT NULL,
  `art_source` varchar(200) DEFAULT NULL,
  `art_url` varchar(500) DEFAULT NULL,
  `art_image` varchar(500) DEFAULT NULL,
  `art_active` tinyint(1) DEFAULT 1,
  `art_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`art_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_related_personalities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p_id` int(11) NOT NULL,
  `related_p_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_daily_personality` (
  `dp_id` int(11) NOT NULL AUTO_INCREMENT,
  `dp_p_id` int(11) NOT NULL,
  `dp_date` date NOT NULL,
  PRIMARY KEY (`dp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_sponsors` (
  `sp_id` int(11) NOT NULL AUTO_INCREMENT,
  `sp_name` varchar(200) NOT NULL,
  `sp_logo` varchar(500) DEFAULT NULL,
  `sp_url` varchar(500) DEFAULT NULL,
  `sp_active` tinyint(1) DEFAULT 1,
  `sp_order` int(11) DEFAULT 0,
  PRIMARY KEY (`sp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(200) NOT NULL,
  `role_permissions` text DEFAULT NULL,
  `role_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_admin_users` (
  `au_id` int(11) NOT NULL AUTO_INCREMENT,
  `au_name` varchar(200) NOT NULL,
  `au_email` varchar(200) NOT NULL,
  `au_password` varchar(255) NOT NULL,
  `au_role_id` int(11) DEFAULT NULL,
  `au_active` tinyint(1) DEFAULT 1,
  `au_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`au_id`),
  UNIQUE KEY `au_email` (`au_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `pi_submissions` (
  `sub_id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_type` enum('personality','institution') DEFAULT 'personality',
  `sub_data` text,
  `sub_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `sub_submitter_name` varchar(200) DEFAULT NULL,
  `sub_submitter_email` varchar(200) DEFAULT NULL,
  `sub_note` text DEFAULT NULL,
  `sub_created` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

// Default roles
"INSERT IGNORE INTO `pi_roles` (`role_id`, `role_name`, `role_permissions`) VALUES
(1, 'مدير النظام', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30'),
(2, 'محرر محتوى', '1,2,3,5,6,7,9,13,14,15,27,28'),
(3, 'مشرف', '1,2,3,5,6,7,9,10,13,14,15,27,28')",

// Default admin (password: admin123)
"INSERT IGNORE INTO `pi_admin_users` (`au_id`, `au_name`, `au_email`, `au_password`, `au_role_id`) VALUES
(1, 'مدير النظام', 'admin@pioneericons.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)",

// Sample categories
"INSERT IGNORE INTO `pi_categories` (`cat_id`, `cat_name`, `cat_name_en`, `cat_icon`, `cat_badge_color`) VALUES
(1,'ريادة الأعمال','Entrepreneurship','fa-rocket','orange'),
(2,'التعليم','Education','fa-graduation-cap','blue'),
(3,'الفن والثقافة','Arts & Culture','fa-palette','purple'),
(4,'التكنولوجيا','Technology','fa-microchip','cyan'),
(5,'الصحافة والإعلام','Media','fa-newspaper','red'),
(6,'الصحة والطب','Health','fa-heart-pulse','green'),
(7,'الرياضة','Sports','fa-trophy','gold'),
(8,'السياسة والقيادة','Politics','fa-landmark','navy'),
(9,'العلوم والبحث','Science','fa-flask','teal'),
(10,'الأدب والكتابة','Literature','fa-book-open','brown'),
(11,'الهندسة والعمارة','Engineering','fa-building','gray'),
(12,'الاقتصاد والمال','Finance','fa-chart-line','darkblue')",
];

foreach ($sql_statements as $i => $sql) {
    if ($mysqli->query($sql)) {
        $success[] = "✓ SQL " . ($i+1) . " executed OK";
    } else {
        $errors[] = "✗ SQL " . ($i+1) . " failed: " . $mysqli->error;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>PioneerIcons Installer</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>*{font-family:'Cairo',sans-serif}</style>
</head>
<body class="bg-gray-100 p-8">
<div class="max-w-2xl mx-auto">
  <div class="bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-black text-gray-800 mb-6">🛠️ PioneerIcons Installer</h1>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
      <?php foreach ($errors as $e): ?>
      <p class="text-red-700 text-sm font-mono py-0.5"><?= htmlspecialchars($e) ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
      <?php foreach ($success as $s): ?>
      <p class="text-green-700 text-sm py-0.5"><?= htmlspecialchars($s) ?></p>
      <?php endforeach; ?>
    </div>

    <?php if (empty($errors)): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-5">
      <h3 class="font-bold text-blue-800 mb-2">✅ تم التثبيت بنجاح!</h3>
      <p class="text-blue-700 text-sm mb-3">بيانات الدخول الافتراضية:</p>
      <p class="text-blue-700 text-sm"><strong>البريد:</strong> admin@pioneericons.com</p>
      <p class="text-blue-700 text-sm"><strong>كلمة المرور:</strong> admin123</p>
      <p class="text-red-600 text-sm font-bold mt-3">⚠️ احذف هذا الملف (install.php) فوراً بعد التثبيت!</p>
    </div>
    <div class="flex gap-3">
      <a href="index.php" class="flex-1 text-center py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">الذهاب للموقع</a>
      <a href="admin.php?p=login" class="flex-1 text-center py-3 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 transition">لوحة التحكم</a>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
