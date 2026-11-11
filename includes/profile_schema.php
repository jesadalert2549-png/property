<?php
function ensure_profile_schema(PDO $pdo): void
{
    $columns = [
        'avatar_path' => 'VARCHAR(255) NULL',
        'bio' => 'TEXT NULL',
        'agent_status' => "ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none'",
        'agent_license' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'agent_agency' => "VARCHAR(160) NOT NULL DEFAULT ''",
        'agent_document' => 'VARCHAR(100) NULL',
        'agent_reviewed_at' => 'DATETIME NULL',
        'tier' => "ENUM('free', 'pro', 'vip', 'unlimited') NOT NULL DEFAULT 'free'",
        'max_listings' => "INT NOT NULL DEFAULT 5",
        'can_post_featured' => "TINYINT(1) NOT NULL DEFAULT 0",
        'can_post_project' => "TINYINT(1) NOT NULL DEFAULT 0",
        'is_banned' => "TINYINT(1) NOT NULL DEFAULT 0",
        'ban_reason' => "VARCHAR(255) NULL",
        'admin_notes' => "TEXT NULL",
    ];
    foreach ($columns as $column => $definition) {
        if (!$pdo->query('SHOW COLUMNS FROM users LIKE ' . $pdo->quote($column))->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }

    // ตรวจสอบและสร้างคอลัมน์ original_price, views_count, clicks_count, video_file ใน properties อัตโนมัติ (กรณี deploy ขึ้นโฮสต์จริง)
    try {
        if (!$pdo->query("SHOW COLUMNS FROM properties LIKE 'original_price'")->fetch()) {
            $pdo->exec("ALTER TABLE properties ADD COLUMN original_price INT UNSIGNED NULL DEFAULT NULL AFTER price");
        }
        if (!$pdo->query("SHOW COLUMNS FROM properties LIKE 'views_count'")->fetch()) {
            $pdo->exec("ALTER TABLE properties ADD COLUMN views_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER original_price");
        }
        if (!$pdo->query("SHOW COLUMNS FROM properties LIKE 'clicks_count'")->fetch()) {
            $pdo->exec("ALTER TABLE properties ADD COLUMN clicks_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER views_count");
        }
        if (!$pdo->query("SHOW COLUMNS FROM properties LIKE 'video_file'")->fetch()) {
            $pdo->exec("ALTER TABLE properties ADD COLUMN video_file VARCHAR(255) NULL DEFAULT NULL AFTER clicks_count");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS property_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            user_id INT UNSIGNED NULL,
            author_name VARCHAR(100) NOT NULL,
            comment TEXT NOT NULL,
            image_path VARCHAR(255) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_prop_comments (property_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$pdo->query("SHOW COLUMNS FROM property_comments LIKE 'image_path'")->fetch()) {
            $pdo->exec("ALTER TABLE property_comments ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER comment");
        }

        // Automatic High-Performance Indexes for High Speed Queries
        $perfIndexes = [
            'properties' => [
                'idx_prop_appr_stat_cat' => '`approval`, `status`, `property_category`, `created_at` DESC',
                'idx_prop_appr_created' => '`approval`, `created_at` DESC',
                'idx_prop_type_appr' => '`type`, `approval`',
                'idx_properties_user_created' => '`user_id`, `created_at` DESC',
                'idx_properties_user_video' => '`user_id`, `video_file`',
            ],
            'development_projects' => [
                'idx_dp_pub_updated' => '`is_published`, `updated_at` DESC, `id` DESC',
                'idx_dev_proj_owner_created' => '`owner_user_id`, `created_at` DESC',
            ],
            'property_favorites' => [
                'idx_fav_user_id' => '`user_id`, `id` DESC',
                'idx_fav_prop_user' => '`property_id`, `user_id`',
            ],
            'property_images' => [
                'idx_prop_images_prop_sort' => '`property_id`, `sort_order`, `id`',
            ],
            'project_images' => [
                'idx_proj_images_proj_sort' => '`project_id`, `sort_order`, `id`',
            ],
        ];

        foreach ($perfIndexes as $tbl => $indexes) {
            foreach ($indexes as $idxName => $idxCols) {
                try {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS `$idxName` ON `$tbl` ($idxCols)");
                } catch (Throwable $ie) {}
            }
        }
    } catch (Throwable $e) {}
}
