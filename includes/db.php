<?php
require_once __DIR__ . '/config.php';

// เรียกใช้ได้ทุกที่ด้วย: $pdo = db();
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // ถ้าระบบ Local Windows / XAMPP แล้ว MySQL ยังไม่ได้เปิด ให้ Auto-start mysqld ให้อัตโนมัติทันที
            $isLocal = in_array(DB_HOST, ['localhost', '127.0.0.1'], true);
            $mysqldPath = 'C:\\xampp\\mysql\\bin\\mysqld.exe';
            $myIniPath  = 'C:\\xampp\\mysql\\bin\\my.ini';

            if ($isLocal && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists($mysqldPath)) {
                // เรียก start mysqld background แบบ non-blocking Process ทันที พร้อมกำหนด Working Directory
                $psCmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process \'' . $mysqldPath . '\' -ArgumentList \'--defaults-file=' . $myIniPath . ' --standalone\' -WorkingDirectory \'C:\\xampp\\mysql\' -WindowStyle Hidden"';
                @exec($psCmd);

                // รอให้ MySQL เปิดพอร์ตสูงสุด 5 วินาที
                for ($retry = 0; $retry < 5; $retry++) {
                    sleep(1);
                    try {
                        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                        break;
                    } catch (PDOException $retryEx) {
                        // ยังเชื่อมไม่ได้ รอต่ออีกรอบ
                    }
                }
            }

            // ถ้าพยายาม Auto-start แล้วยังไม่ได้ ให้โยน Error ตามเดิม
            if ($pdo === null) {
                throw $e;
            }
        }

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
