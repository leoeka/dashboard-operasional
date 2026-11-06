-- Upgrade database lama exib8656_finance ke skema aplikasi Dashboard Operasional.
-- Dibuat untuk dump MariaDB 11.4.13 yang diberikan pemilik aplikasi.
--
-- PENTING:
-- 1. Buat dan unduh backup database terbaru sebelum mengimpor file ini.
-- 2. Pilih database LAMA yang benar di phpMyAdmin, lalu impor file ini SATU KALI.
-- 3. Jangan impor database-cpanel.sql setelah file upgrade ini.
-- 4. File ini sengaja tidak menghapus tabel lama. Tabel lama tetap ada sebagai
--    arsip; migrasi 2026_10_21_140000 ditandai selesai agar Laravel tidak
--    menghapus data lama tersebut pada deploy berikutnya.
-- 5. DDL MariaDB melakukan implicit commit. Jika proses terhenti di tengah,
--    pulihkan backup sebelum mencoba lagi.

SET NAMES utf8mb4;

-- Perubahan kolom yang tidak menghapus atau mengganti nilai data.
ALTER TABLE `projects`
    MODIFY `design_reference_url` TEXT NULL DEFAULT NULL;

ALTER TABLE `billing_reminder_logs`
    ADD COLUMN `whatsapp_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `sent_at`;

ALTER TABLE `billing_subscriptions`
    ADD COLUMN `ends_on` DATE NULL DEFAULT NULL AFTER `next_renewal_date`;

-- Tabel baru untuk alur kandidat mockup dan build WordPress.
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

-- Pisahkan data SEO/backlink lama ke tabel baru sambil tetap menyimpan kolom
-- sumber di projects. JSON_TABLE mengubah daftar lama menjadi teks berurutan.
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

INSERT INTO `project_seos`
    (`project_id`, `target_url`, `location`, `keywords`, `competitors`,
     `cms_platform`, `data`, `created_at`, `updated_at`)
SELECT
    p.`id`,
    JSON_UNQUOTE(JSON_EXTRACT(p.`seo_requirements`, '$.target_url')),
    JSON_UNQUOTE(JSON_EXTRACT(p.`seo_requirements`, '$.location')),
    CASE
        WHEN JSON_TYPE(JSON_EXTRACT(p.`seo_requirements`, '$.keywords')) = 'ARRAY'
        THEN (
            SELECT GROUP_CONCAT(j.`item` ORDER BY j.`position` SEPARATOR ', ')
            FROM JSON_TABLE(
                p.`seo_requirements`,
                '$.keywords[*]' COLUMNS (
                    `position` FOR ORDINALITY,
                    `item` VARCHAR(1000) PATH '$'
                )
            ) AS j
        )
        ELSE JSON_UNQUOTE(JSON_EXTRACT(p.`seo_requirements`, '$.keywords'))
    END,
    CASE
        WHEN JSON_TYPE(JSON_EXTRACT(p.`seo_requirements`, '$.competitors')) = 'ARRAY'
        THEN (
            SELECT GROUP_CONCAT(j.`item` ORDER BY j.`position` SEPARATOR '\n')
            FROM JSON_TABLE(
                p.`seo_requirements`,
                '$.competitors[*]' COLUMNS (
                    `position` FOR ORDINALITY,
                    `item` VARCHAR(1000) PATH '$'
                )
            ) AS j
        )
        ELSE JSON_UNQUOTE(JSON_EXTRACT(p.`seo_requirements`, '$.competitors'))
    END,
    JSON_UNQUOTE(JSON_EXTRACT(p.`seo_requirements`, '$.cms_platform')),
    p.`seo_requirements`,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM `projects` AS p
WHERE p.`seo_requirements` IS NOT NULL
  AND JSON_VALID(p.`seo_requirements`)
  AND JSON_TYPE(p.`seo_requirements`) = 'OBJECT'
  AND JSON_LENGTH(p.`seo_requirements`) > 0;

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

INSERT INTO `project_backlinks`
    (`project_id`, `target_url`, `quantity`, `anchor_text`, `niche`,
     `anchor_type`, `priority`, `data`, `created_at`, `updated_at`)
SELECT
    p.`id`,
    JSON_UNQUOTE(JSON_EXTRACT(p.`backlink_requirements`, '$.target_url')),
    JSON_UNQUOTE(JSON_EXTRACT(p.`backlink_requirements`, '$.quantity')),
    CASE
        WHEN JSON_TYPE(JSON_EXTRACT(p.`backlink_requirements`, '$.anchor_text')) = 'ARRAY'
        THEN (
            SELECT GROUP_CONCAT(j.`item` ORDER BY j.`position` SEPARATOR ', ')
            FROM JSON_TABLE(
                p.`backlink_requirements`,
                '$.anchor_text[*]' COLUMNS (
                    `position` FOR ORDINALITY,
                    `item` VARCHAR(1000) PATH '$'
                )
            ) AS j
        )
        ELSE JSON_UNQUOTE(JSON_EXTRACT(p.`backlink_requirements`, '$.anchor_text'))
    END,
    JSON_UNQUOTE(JSON_EXTRACT(p.`backlink_requirements`, '$.niche')),
    JSON_EXTRACT(p.`backlink_requirements`, '$.anchor_type'),
    JSON_UNQUOTE(JSON_EXTRACT(p.`backlink_requirements`, '$.priority')),
    p.`backlink_requirements`,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM `projects` AS p
WHERE p.`backlink_requirements` IS NOT NULL
  AND JSON_VALID(p.`backlink_requirements`)
  AND JSON_TYPE(p.`backlink_requirements`) = 'OBJECT'
  AND JSON_LENGTH(p.`backlink_requirements`) > 0;

-- Ganti nama tabel tanpa menghapus isi atau foreign key; tambahkan metadata file.
RENAME TABLE `project_files` TO `mockup_assets`;

ALTER TABLE `mockup_assets`
    ADD COLUMN `file_size` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `category`,
    ADD COLUMN `mime_type` VARCHAR(255) NULL DEFAULT NULL AFTER `file_size`;

-- Catat migrasi yang dilakukan manual. Baris 2026_10_21_140000 sengaja
-- ditandai selesai tanpa menjalankan DROP agar tabel arsip/data lama tetap utuh.
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_10_06_000001_expand_design_reference_url_column', 2),
('2026_10_12_063107_add_whatsapp_sent_at_to_billing_reminder_logs_table', 2),
('2026_10_14_000001_add_ends_on_to_billing_subscriptions_table', 2),
('2026_10_21_140000_drop_web_builder_artifacts', 2),
('2026_10_21_200000_create_mockup_candidates_table', 2),
('2026_10_21_200001_create_client_proposals_table', 2),
('2026_10_21_200002_create_wp_builds_table', 2),
('2026_10_23_000001_create_project_seos_and_backlinks_tables', 2),
('2026_10_23_000002_create_mockup_assets_table', 2);
