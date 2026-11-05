<?php
// ===== ตั้งค่าหลักของเว็บ =====
date_default_timezone_set('Asia/Bangkok');

define('DB_HOST', 'localhost');
define('DB_NAME', 'property_test');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'KASA Property');
define('SITE_VERSION_FILE', dirname(__DIR__) . '/version.json');   // จัดการเวอร์ชันจากหลังบ้าน

// ── อ่านเวอร์ชันปัจจุบัน (เก็บใน version.json เพื่อให้หลังบ้านแก้ได้แบบไม่ต้องแก้โค้ด) ──
function current_site_version(bool $forceRefresh = false): string
{
    static $cached = null;
    if ($cached !== null && !$forceRefresh) { return $cached; }
    $fallback = 'v.3.10.10';
    if (is_file(SITE_VERSION_FILE)) {
        $data = json_decode((string)file_get_contents(SITE_VERSION_FILE), true);
        $version = is_array($data) ? trim((string)($data['version'] ?? '')) : '';
        if ($version !== '' && preg_match('/^v\.\d+\.\d+\.\d+$/', $version)) {
            return $cached = $version;
        }
    }
    return $cached = $fallback;
}
define('SITE_VERSION', current_site_version());
define('SITE_URL', 'https://standardproperty.cloud');

// เปลี่ยนช่องทางติดต่อเป็น Email
define('CONTACT_EMAIL', 'Stadardproperty@gmail.com');

// ===== ตั้งค่ารูปภาพ =====
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_IMAGES', 10);
define('MAX_IMAGE_BYTES', 5 * 1024 * 1024);

const PROPERTY_CATEGORIES = [
    'condo'      => 'คอนโด',
    'house'      => 'บ้านเดี่ยว',
    'townhouse'  => 'ทาวน์เฮ้าส์ / ทาวน์โฮม',
    'land'       => 'ที่ดิน',
    'commercial' => 'อาคารพาณิชย์',
    'warehouse'  => 'โกดัง / โรงงาน',
    'other'      => 'อื่น ๆ',
];

const OWNER_USERNAME = 'jesada';
const OWNER_EMAIL = 'jesada@standardproperty.local';
