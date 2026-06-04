-- ============================================================
-- Migration: Rename pi_users columns from user_* to u_*
-- Run this ONCE in phpMyAdmin on your server
-- ============================================================

ALTER TABLE pi_users
  CHANGE COLUMN user_id       u_id        INT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN user_name     u_name      VARCHAR(200) NOT NULL DEFAULT '',
  CHANGE COLUMN user_email    u_email     VARCHAR(200) NOT NULL DEFAULT '',
  CHANGE COLUMN user_password u_password  VARCHAR(255) NOT NULL DEFAULT '',
  CHANGE COLUMN user_active   u_active    TINYINT(1) NOT NULL DEFAULT 1,
  CHANGE COLUMN user_created  u_created   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add missing columns if they don't exist yet
-- (safe to run multiple times)

SET @col_phone = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_phone');
SET @sql_phone = IF(@col_phone=0, 'ALTER TABLE pi_users ADD COLUMN u_phone VARCHAR(50) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_phone; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_photo = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_photo');
SET @sql_photo = IF(@col_photo=0, 'ALTER TABLE pi_users ADD COLUMN u_photo VARCHAR(500) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_photo; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_verified = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_verified');
SET @sql_verified = IF(@col_verified=0, 'ALTER TABLE pi_users ADD COLUMN u_verified TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql_verified; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_plan = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_plan');
SET @sql_plan = IF(@col_plan=0, "ALTER TABLE pi_users ADD COLUMN u_plan ENUM('free','verified','executive') DEFAULT 'free'", 'SELECT 1');
PREPARE stmt FROM @sql_plan; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_nat = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_nationality');
SET @sql_nat = IF(@col_nat=0, 'ALTER TABLE pi_users ADD COLUMN u_nationality VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_nat; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_company = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_company');
SET @sql_company = IF(@col_company=0, 'ALTER TABLE pi_users ADD COLUMN u_company VARCHAR(200) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_company; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_birth = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_birthdate');
SET @sql_birth = IF(@col_birth=0, 'ALTER TABLE pi_users ADD COLUMN u_birthdate DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_birth; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_job = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_job');
SET @sql_job = IF(@col_job=0, 'ALTER TABLE pi_users ADD COLUMN u_job VARCHAR(200) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_job; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_gender = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pi_users' AND COLUMN_NAME='u_gender');
SET @sql_gender = IF(@col_gender=0, "ALTER TABLE pi_users ADD COLUMN u_gender ENUM('male','female','') DEFAULT ''", 'SELECT 1');
PREPARE stmt FROM @sql_gender; EXECUTE stmt; DEALLOCATE PREPARE stmt;
