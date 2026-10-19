CREATE TABLE IF NOT EXISTS `jvb_layouts` (
    `post_id` int(10) unsigned NOT NULL,
    `status` varchar(10) NOT NULL DEFAULT 'draft',
    `draft_json` longtext DEFAULT NULL,
    `published_json` longtext DEFAULT NULL,
    `published_at` datetime DEFAULT NULL,
    `updated_by` int(10) unsigned DEFAULT NULL,
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jvb_revisions` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `post_id` int(10) unsigned NOT NULL,
    `layout_json` longtext DEFAULT NULL,
    `note` varchar(120) NOT NULL DEFAULT '',
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jvb_templates` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(191) NOT NULL,
    `type` varchar(20) NOT NULL DEFAULT 'section',
    `layout_json` longtext DEFAULT NULL,
    `is_starter` tinyint(1) NOT NULL DEFAULT 0,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
