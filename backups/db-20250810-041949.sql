-- STH Portal PHP dump for `sthportal`
-- 2025-08-10T04:19:49-04:00

SET FOREIGN_KEY_CHECKS=0;

--
-- Table structure for `audit_log`
--
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `admin_user` varchar(128) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `event` varchar(128) NOT NULL DEFAULT '',
  `actor` varchar(128) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_log` (`id`,`occurred_at`,`ip`,`admin_user`,`action`,`details`,`event`,`actor`,`created_at`) VALUES ('1','2025-08-09 19:26:54','::1','admin','admin_login_success','{\"ip\":\"::1\",\"user\":\"admin\"}','',NULL,'2025-08-09 21:46:01');

--
-- Table structure for `devlog`
--
DROP TABLE IF EXISTS `devlog`;
CREATE TABLE `devlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `body` mediumtext NOT NULL,
  `tags` varchar(200) DEFAULT NULL,
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for `licenses`
--
DROP TABLE IF EXISTS `licenses`;
CREATE TABLE `licenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `portal_id` varchar(32) NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `licensed_to` varchar(128) NOT NULL,
  `email` varchar(128) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'issued',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_check` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `registered_domain` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'full',
  `issued_to` varchar(190) DEFAULT NULL,
  `site_domain` varchar(190) DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_by` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `licenses` (`id`,`portal_id`,`license_key`,`licensed_to`,`email`,`status`,`created_at`,`last_check`,`expires_at`,`registered_domain`,`notes`,`updated_at`,`type`,`issued_to`,`site_domain`,`activated_at`,`created_by`) VALUES ('1','PORTAL-001','GRIFFINK2T0RSAP0-001-AUG2025-2Y','UNITED HOCKEY ASSOCIATION','griffin.k.r.1988@gmail.com','active','2025-08-09 01:49:39',NULL,'2027-08-09 07:49:39',NULL,NULL,NULL,'full',NULL,NULL,NULL,NULL);
INSERT INTO `licenses` (`id`,`portal_id`,`license_key`,`licensed_to`,`email`,`status`,`created_at`,`last_check`,`expires_at`,`registered_domain`,`notes`,`updated_at`,`type`,`issued_to`,`site_domain`,`activated_at`,`created_by`) VALUES ('7','','U5VJR-VHAUK-4XSMF-ZRUCM','',NULL,'active','2025-08-10 00:15:30',NULL,'2027-08-10 23:59:59',NULL,'Testing','2025-08-10 00:19:27','full','testing@email.com','aleague.com','2025-08-10 00:19:27','admin');

--
-- Table structure for `login_attempts`
--
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) DEFAULT NULL,
  `username` varchar(128) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `actor` varchar(128) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempt_time` (`attempted_at`),
  KEY `idx_ip` (`ip`),
  KEY `idx_user` (`username`),
  KEY `idx_login_ip_created` (`ip`,`created_at`),
  KEY `idx_login_actor_created` (`actor`,`created_at`),
  KEY `idx_login_ip_actor_created` (`ip`,`actor`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('1','::1','admin','2025-08-09 19:26:54','1',NULL,NULL,'2025-08-09 21:46:02');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('2','::1',NULL,'2025-08-09 21:46:05','1','admin','admin login ok','2025-08-09 21:46:05');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('3','::1',NULL,'2025-08-09 21:48:15','0','guest','invalid pin','2025-08-09 21:48:15');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('4','127.0.0.1',NULL,'2025-08-09 21:52:27','0','guest','invalid pin','2025-08-09 21:52:27');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('5','127.0.0.1',NULL,'2025-08-09 21:52:47','1','admin','admin login ok','2025-08-09 21:52:47');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('6','127.0.0.1',NULL,'2025-08-09 22:36:16','1','test@email.com','user login ok','2025-08-09 22:36:16');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('7','127.0.0.1',NULL,'2025-08-09 22:36:24','0','test@email.com','bad password','2025-08-09 22:36:24');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('8','127.0.0.1',NULL,'2025-08-09 22:36:35','0','test@email.com','bad password','2025-08-09 22:36:35');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('9','127.0.0.1',NULL,'2025-08-09 22:36:41','0','test@email.com','bad password','2025-08-09 22:36:41');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('10','127.0.0.1',NULL,'2025-08-09 22:36:48','0','test@email.com','bad password','2025-08-09 22:36:48');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('11','127.0.0.1',NULL,'2025-08-09 22:36:55','0','test@email.com','bad password','2025-08-09 22:36:55');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('12','127.0.0.1',NULL,'2025-08-09 22:37:11','0','testing@testing.com','unknown email','2025-08-09 22:37:11');
INSERT INTO `login_attempts` (`id`,`ip`,`username`,`attempted_at`,`success`,`actor`,`note`,`created_at`) VALUES ('13','127.0.0.1',NULL,'2025-08-09 22:38:08','1','admin','admin login ok','2025-08-09 22:38:08');

--
-- Table structure for `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(190) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'member',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `failed_logins` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`,`name`,`email`,`role`,`active`,`created_at`,`updated_at`,`password_hash`,`last_login_at`,`failed_logins`,`locked_until`) VALUES ('2','Test','test@email.com','member','1','2025-08-10 00:40:40','2025-08-10 00:40:40','$2y$10$H7mLBc3XLYwfDfpWrDzoMOORrgqvaToYYAtJ.2R0N99K2q.yLse2u',NULL,'0',NULL);

SET FOREIGN_KEY_CHECKS=1;
