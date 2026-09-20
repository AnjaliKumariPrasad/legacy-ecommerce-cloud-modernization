-- =======================================================
--  PHP Admin Panel - Database Schema
--  Run this once in phpMyAdmin or via the MySQL CLI.
-- =======================================================

CREATE DATABASE IF NOT EXISTS `php_admin_panel`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `php_admin_panel`;

-- ---------------------------------------------------------
-- Users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','editor','user') NOT NULL DEFAULT 'user',
  `status`     ENUM('active','inactive')     NOT NULL DEFAULT 'active',
  `avatar`     VARCHAR(255) DEFAULT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `bio`        TEXT         DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_role`   (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Activity log
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `description` TEXT        DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user`   (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- App settings (key/value)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name`   VARCHAR(100) NOT NULL UNIQUE,
  `value`      TEXT         DEFAULT NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Default settings
-- (The default admin user is created by running install.php
--  in the browser — that way the password is hashed by PHP.)
-- ---------------------------------------------------------
INSERT INTO `settings` (`key_name`, `value`) VALUES
  ('site_name',      'PHP Admin Panel'),
  ('site_email',     'admin@example.com'),
  ('site_about',     'A modern PHP admin panel.'),
  ('items_per_page', '10')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
