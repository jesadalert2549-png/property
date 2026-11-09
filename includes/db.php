<?php
require_once __DIR__ . '/config.php';

// เรียกใช้ได้ทุกที่ด้วย: $pdo = db();
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+07:00'");

        // Auto-migrate schema on production server if newly deployed columns are missing
        static $schemaChecked = false;
        if (!$schemaChecked) {
            $schemaChecked = true;
            try {
                require_once __DIR__ . '/profile_schema.php';
                ensure_profile_schema($pdo);
            } catch (Throwable $e) {}
        }
    }

    return $pdo;
}
