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
    ];
    foreach ($columns as $column => $definition) {
        if (!$pdo->query('SHOW COLUMNS FROM users LIKE ' . $pdo->quote($column))->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }
}
