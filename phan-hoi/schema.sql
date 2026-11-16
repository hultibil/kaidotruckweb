USE `kaido`;

CREATE TABLE IF NOT EXISTS `system_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `category` enum('website_error','product_service','feature_idea','other') NOT NULL DEFAULT 'other',
  `message` text NOT NULL,
  `status` enum('new','reviewing','resolved') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feedback_status_created` (`status`,`created_at`),
  KEY `idx_feedback_user` (`user_id`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
