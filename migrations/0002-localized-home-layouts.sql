CREATE TABLE IF NOT EXISTS `jvb_home_locales` (
    `source_post_id` int(10) unsigned NOT NULL,
    `locale` varchar(16) NOT NULL,
    `localized_post_id` int(10) unsigned NOT NULL,
    `updated_by` int(10) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`source_post_id`, `locale`),
    UNIQUE KEY `uniq_jvb_home_locale_post` (`localized_post_id`),
    KEY `idx_jvb_home_locale` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
