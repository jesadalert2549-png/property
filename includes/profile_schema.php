<?php
function ensure_profile_schema(PDO $pdo): void
{
    $columns = [
        'avatar_path' => 'VARCHAR(255) NULL',
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

    // ตรวจสอบและสร้างคอลัมน์ original_price, views_count, clicks_count ใน properties อัตโนมัติ (กรณี deploy ขึ้นโฮสต์จริง)
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
    } catch (Throwable $e) {}
}
