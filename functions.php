<?php
// ===== ไฟล์รวมฟังก์ชันหลักของระบบ Standard Property v.2 =====

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// โหลดไฟล์ตั้งค่า (config.php)
if (is_file(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
} elseif (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// ฟังก์ชันเชื่อมต่อฐานข้อมูล PDO
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }
    return $pdo;
}

// ฟังก์ชันป้องกัน XSS
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// ฟังก์ชันดึง URL หลักของเว็บไซต์
function site_url() {
    return rtrim(SITE_URL, '/');
}

// แปลงประเภทประกาศ (sale / rent) เป็นภาษาไทย
function type_label($type) {
    return $type === 'rent' ? 'ให้เช่า' : 'ขาย';
}

// จัดรูปแบบราคา
function price_label($type, $price) {
    $formatted = number_format((int)$price);
    return $type === 'rent' ? $formatted . ' บาท/เดือน' : $formatted . ' บาท';
}