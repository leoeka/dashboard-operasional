-- Dashboard Operasional - skema database untuk instalasi baru.
-- Pilih database kosong di phpMyAdmin sebelum mengimpor file ini.
-- Jangan jalankan pada database aplikasi yang sudah berisi data.
-- Setelah berhasil, tabel migrations mencatat seluruh migrasi sebagai batch 1.

SET NAMES utf8mb4;

CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `remember_token` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clients` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL,
    `contact_name` VARCHAR(255) NULL DEFAULT NULL,
    `billing_name` VARCHAR(255) NULL DEFAULT NULL,
    `email` VARCHAR(255) NULL DEFAULT NULL,
    `billing_email` VARCHAR(255) NULL DEFAULT NULL,
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `billing_phone` VARCHAR(50) NULL DEFAULT NULL,
    `whatsapp` VARCHAR(50) NULL DEFAULT NULL,
    `address` TEXT NULL,
    `logo_path` VARCHAR(255) NULL DEFAULT NULL,
    `website` VARCHAR(255) NULL DEFAULT NULL,
    `instagram` VARCHAR(100) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `clients_email_unique` (`email`),
    KEY `clients_created_by_foreign` (`created_by`),
    CONSTRAINT `clients_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `code` VARCHAR(255) NOT NULL,
    `client_name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(255) NULL DEFAULT NULL,
    `website_name` VARCHAR(255) NULL DEFAULT NULL,
    `website_features` TEXT NULL,
    `description` TEXT NULL,
    `target_market` TEXT NULL,
    `design_reference_type` VARCHAR(20) NULL DEFAULT NULL,
    `design_reference_url` TEXT NULL,
    `design_reference_path` VARCHAR(255) NULL DEFAULT NULL,
    `has_client_assets` TINYINT(1) NULL DEFAULT NULL,
    `status` ENUM('request', 'in_progress', 'completed') NOT NULL DEFAULT 'request',
    `value` DECIMAL(15, 2) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `projects_code_unique` (`code`),
    KEY `projects_client_id_foreign` (`client_id`),
    CONSTRAINT `projects_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `client_name` VARCHAR(255) NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `status` VARCHAR(255) NOT NULL DEFAULT 'info',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `activity_logs_project_id_foreign` (`project_id`),
    CONSTRAINT `activity_logs_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_seos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `target_url` VARCHAR(255) NULL DEFAULT NULL,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `keywords` TEXT NULL,
    `competitors` TEXT NULL,
    `cms_platform` VARCHAR(255) NULL DEFAULT NULL,
    `data` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `project_seos_project_id_unique` (`project_id`),
    CONSTRAINT `project_seos_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_backlinks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `target_url` VARCHAR(255) NULL DEFAULT NULL,
    `quantity` VARCHAR(50) NULL DEFAULT NULL,
    `anchor_text` TEXT NULL,
    `niche` VARCHAR(255) NULL DEFAULT NULL,
    `anchor_type` JSON NULL,
    `priority` VARCHAR(50) NULL DEFAULT NULL,
    `data` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `project_backlinks_project_id_unique` (`project_id`),
    CONSTRAINT `project_backlinks_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mockup_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `category` VARCHAR(255) NULL DEFAULT NULL,
    `file_size` BIGINT UNSIGNED NULL DEFAULT NULL,
    `mime_type` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `mockup_assets_project_id_foreign` (`project_id`),
    CONSTRAINT `mockup_assets_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mockup_candidates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `candidate_number` TINYINT UNSIGNED NOT NULL,
    `idea_sentence` VARCHAR(255) NULL DEFAULT NULL,
    `folder` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `png_path` VARCHAR(255) NULL DEFAULT NULL,
    `critique_rounds` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `failure_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `mockup_candidates_project_id_status_index` (`project_id`, `status`),
    CONSTRAINT `mockup_candidates_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_proposals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `chosen_candidate_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `pdf_path` VARCHAR(255) NULL DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `chosen_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `client_proposals_project_id_index` (`project_id`),
    KEY `client_proposals_chosen_candidate_id_foreign` (`chosen_candidate_id`),
    CONSTRAINT `client_proposals_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `client_proposals_chosen_candidate_id_foreign`
        FOREIGN KEY (`chosen_candidate_id`) REFERENCES `mockup_candidates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wp_builds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `mockup_candidate_id` BIGINT UNSIGNED NOT NULL,
    `folder` VARCHAR(255) NULL DEFAULT NULL,
    `zip_path` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `inspection_results` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `wp_builds_project_id_status_index` (`project_id`, `status`),
    KEY `wp_builds_mockup_candidate_id_foreign` (`mockup_candidate_id`),
    CONSTRAINT `wp_builds_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `wp_builds_mockup_candidate_id_foreign`
        FOREIGN KEY (`mockup_candidate_id`) REFERENCES `mockup_candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `service_package_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `service_type` VARCHAR(30) NOT NULL DEFAULT 'other',
    `description` TEXT NULL,
    `billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'yearly',
    `amount` DECIMAL(15, 2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR',
    `start_date` DATE NOT NULL,
    `next_renewal_date` DATE NOT NULL,
    `ends_on` DATE NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `auto_invoice` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_reminder` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `billing_subscriptions_status_next_renewal_date_index` (`status`, `next_renewal_date`),
    KEY `billing_subscriptions_service_type_index` (`service_type`),
    KEY `billing_subscriptions_project_id_foreign` (`project_id`),
    CONSTRAINT `billing_subscriptions_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `billing_subscriptions_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `client_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `billing_subscription_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `invoice_number` VARCHAR(255) NOT NULL,
    `type` ENUM('dp', 'pelunasan', 'full') NOT NULL DEFAULT 'full',
    `purpose` VARCHAR(30) NOT NULL DEFAULT 'project_payment',
    `issue_date` DATE NULL DEFAULT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `subtotal` DECIMAL(15, 2) NULL DEFAULT NULL,
    `discount` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `tax` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR',
    `items` JSON NULL,
    `due_date` DATE NOT NULL,
    `billing_period_start` DATE NULL DEFAULT NULL,
    `billing_period_end` DATE NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    `paid_at` DATE NULL DEFAULT NULL,
    `last_reminder_sent_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
    KEY `invoices_purpose_status_index` (`purpose`, `status`),
    KEY `invoices_due_date_index` (`due_date`),
    KEY `invoices_project_id_foreign` (`project_id`),
    KEY `invoices_client_id_foreign` (`client_id`),
    KEY `invoices_billing_subscription_id_foreign` (`billing_subscription_id`),
    CONSTRAINT `invoices_project_id_foreign`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `invoices_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
    CONSTRAINT `invoices_billing_subscription_id_foreign`
        FOREIGN KEY (`billing_subscription_id`) REFERENCES `billing_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_reminder_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `billing_subscription_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `days_before` SMALLINT UNSIGNED NOT NULL,
    `scheduled_for` DATE NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `whatsapp_sent_at` TIMESTAMP NULL DEFAULT NULL,
    `recipient` VARCHAR(255) NULL DEFAULT NULL,
    `error_message` TEXT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `billing_reminder_once_per_threshold` (`invoice_id`, `days_before`),
    KEY `billing_reminder_logs_status_scheduled_for_index` (`status`, `scheduled_for`),
    KEY `billing_reminder_logs_billing_subscription_id_foreign` (`billing_subscription_id`),
    CONSTRAINT `billing_reminder_logs_invoice_id_foreign`
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `billing_reminder_logs_billing_subscription_id_foreign`
        FOREIGN KEY (`billing_subscription_id`) REFERENCES `billing_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_07_31_040417_create_projects_table', 1),
('2026_07_31_040546_create_activity_logs_table', 1),
('2026_07_31_054856_add_username_to_users_table', 1),
('2026_08_01_011352_create_clients_table', 1),
('2026_08_01_022753_add_name_to_projects_table', 1),
('2026_08_01_033042_add_client_id_to_projects_table', 1),
('2026_08_01_041342_create_project_files_table', 1),
('2026_08_03_060751_create_invoices_table', 1),
('2026_08_05_083928_add_logo_path_to_clients_table', 1),
('2026_08_05_084104_add_zipwp_template_to_projects_table', 1),
('2026_08_11_061758_add_seo_backlink_to_projects_table', 1),
('2026_08_20_000000_add_zipwp_site_to_projects_table', 1),
('2026_08_22_000000_remove_progress_and_deadline_from_projects_table', 1),
('2026_08_22_000001_normalize_project_statuses', 1),
('2026_08_24_031812_add_business_type_and_business_goal_to_projects_table', 1),
('2026_08_24_040058_rename_business_type_and_business_goal_columns_on_projects_table', 1),
('2026_08_31_000000_add_design_reference_to_projects_table', 1),
('2026_09_01_000003_create_project_brands_table', 1),
('2026_09_01_000004_create_project_contents_table', 1),
('2026_09_01_000005_remove_unused_bundle_tables', 1),
('2026_09_09_000000_purge_zipwp_remnants', 1),
('2026_09_18_000002_create_billing_subscriptions_table', 1),
('2026_09_18_000005_create_billing_reminder_logs_table', 1),
('2026_09_18_000006_extend_invoices_for_recurring_billing', 1),
('2026_09_18_000007_add_billing_contact_to_clients_table', 1),
('2026_10_06_000001_expand_design_reference_url_column', 1),
('2026_10_12_063107_add_whatsapp_sent_at_to_billing_reminder_logs_table', 1),
('2026_10_14_000001_add_ends_on_to_billing_subscriptions_table', 1),
('2026_10_21_140000_drop_web_builder_artifacts', 1),
('2026_10_21_200000_create_mockup_candidates_table', 1),
('2026_10_21_200001_create_client_proposals_table', 1),
('2026_10_21_200002_create_wp_builds_table', 1),
('2026_10_23_000001_create_project_seos_and_backlinks_tables', 1),
('2026_10_23_000002_create_mockup_assets_table', 1);
