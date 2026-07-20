-- ===============================================================
--  ERP Foundation — Database Schema
--  (install.php নিজে থেকেই এই টেবিলগুলো বানায় ও ডিফল্ট ডেটা দেয়।
--   চাইলে phpMyAdmin-এ ম্যানুয়ালি এই ফাইলও import করতে পারেন।)
-- ===============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Roles (ভূমিকা) ----------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(60) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_locked`   TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = ডিফল্ট role, ডিলিট করা যাবে না
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Permissions (অনুমতি) ----------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id`     INT AUTO_INCREMENT PRIMARY KEY,
  `name`   VARCHAR(100) NOT NULL,      -- মানুষের পড়ার মতো নাম
  `slug`   VARCHAR(100) NOT NULL UNIQUE, -- কোডে ব্যবহৃত (যেমন users.create)
  `module` VARCHAR(60)  NOT NULL DEFAULT 'system'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Role ↔ Permission ----------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Users (ব্যবহারকারী) ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role_id`    INT DEFAULT NULL,
  `status`     TINYINT(1) NOT NULL DEFAULT 1,   -- 1 = active, 0 = inactive
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Menus (ডাইনামিক মেনু) ----------
CREATE TABLE IF NOT EXISTS `menus` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id`  INT DEFAULT NULL,                 -- সাব-মেনুর জন্য
  `title`      VARCHAR(80) NOT NULL,
  `url`        VARCHAR(255) NOT NULL DEFAULT '#',
  `icon`       VARCHAR(60)  NOT NULL DEFAULT 'bi-dot',  -- Bootstrap Icons ক্লাস
  `permission` VARCHAR(100) DEFAULT NULL,         -- এই permission থাকলেই মেনু দেখাবে
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`parent_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
