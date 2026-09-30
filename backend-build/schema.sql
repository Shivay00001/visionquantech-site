-- ============================================================================
-- VisionQuantech website backend — MySQL schema
-- Target: shared cPanel hosting (MySQL 5.7+ / 8.x). Import via phpMyAdmin.
-- No secrets in this file. Create the admin user with tools/make_admin.php
-- AFTER importing (see README.md).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `visionquantech`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `visionquantech`;

-- ----------------------------------------------------------------------------
-- leads: contact / pilot-request form submissions from the website
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `phone`         VARCHAR(30)  NULL,
  `business_type` VARCHAR(30)  NULL COMMENT 'real-estate | hotel | clinic | hospital | other',
  `message`       TEXT NOT NULL,
  `source_page`   VARCHAR(255) NULL COMMENT 'page/section the form was submitted from',
  `status`        ENUM('new','contacted','qualified','won','lost') NOT NULL DEFAULT 'new',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leads_status`  (`status`),
  KEY `idx_leads_created` (`created_at`),
  KEY `idx_leads_email`   (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- admins: admin-panel users (passwords stored as password_hash() hashes)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- posts: research papers / updates managed from the admin panel
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(190) NOT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `body`         MEDIUMTEXT NOT NULL COMMENT 'HTML or Markdown, rendered by the frontend',
  `category`     ENUM('research','updates') NOT NULL DEFAULT 'updates',
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_status_published` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- page_views: lightweight analytics (written by api/track.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `page_views` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path`      VARCHAR(255) NOT NULL,
  `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_views_path` (`path`),
  KEY `idx_views_at`   (`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings: simple key/value site settings editable from the admin panel
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `skey`   VARCHAR(100) NOT NULL,
  `svalue` TEXT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`skey`, `svalue`) VALUES
  ('site_name',      'VisionQuantech'),
  ('contact_email',  'Contact@visionquantech.com'),
  ('whatsapp_number',''),
  ('announcement',   '')
ON DUPLICATE KEY UPDATE `svalue` = VALUES(`svalue`);
