<?php
// ===== ติดตั้งระบบครั้งแรก =====
// เปิดไฟล์นี้ครั้งเดียวเพื่อสร้างตารางและบัญชีเจ้าของ แล้วลบไฟล์นี้ทันที
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();

// สร้างตารางทั้งหมด (รันซ้ำได้ ไม่ทำให้ข้อมูลเดิมหาย)
$tables = [
    "CREATE TABLE IF NOT EXISTS properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type ENUM('sale','rent') NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'other',
        property_category VARCHAR(30) NOT NULL DEFAULT 'other',
        status ENUM('available','done') NOT NULL DEFAULT 'available',
        approval ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        verification_status ENUM('unchecked','passed','flagged') NOT NULL DEFAULT 'unchecked',
        verification_notes TEXT NULL,
        price INT UNSIGNED NOT NULL DEFAULT 0,
        location VARCHAR(255) NOT NULL DEFAULT '',
        lat DECIMAL(10,7) NULL,
        lng DECIMAL(10,7) NULL,
        description TEXT,
        line_id VARCHAR(40) NOT NULL DEFAULT '',
        contact_name VARCHAR(100) NOT NULL DEFAULT '',
        phone VARCHAR(20) NOT NULL DEFAULT '',
        user_id INT UNSIGNED NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_approval (approval)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS featured_properties (
        property_id INT NOT NULL PRIMARY KEY,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_featured_properties_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        KEY idx_featured_properties_order (sort_order, property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        full_name VARCHAR(120) NOT NULL DEFAULT '',
        avatar_path VARCHAR(255) NULL,
        agent_status ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none',
        agent_license VARCHAR(100) NOT NULL DEFAULT '',
        agent_agency VARCHAR(160) NOT NULL DEFAULT '',
        agent_document VARCHAR(100) NULL,
        agent_reviewed_at DATETIME NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        line_id VARCHAR(40) NOT NULL DEFAULT '',
        privacy_accepted_at DATETIME NULL,
        last_login_at DATETIME NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('user','admin') NOT NULL DEFAULT 'user',
        tier ENUM('free', 'pro', 'vip', 'unlimited') NOT NULL DEFAULT 'free',
        max_listings INT NOT NULL DEFAULT 5,
        can_post_featured TINYINT(1) NOT NULL DEFAULT 0,
        can_post_project TINYINT(1) NOT NULL DEFAULT 0,
        is_banned TINYINT(1) NOT NULL DEFAULT 0,
        ban_reason VARCHAR(255) NULL,
        admin_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_users_tier (tier),
        INDEX idx_users_banned (is_banned)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_hash CHAR(64) NOT NULL,
        user_id INT UNSIGNED NULL,
        event_type ENUM('page','property') NOT NULL,
        property_id INT NULL,
        page_path VARCHAR(120) NOT NULL DEFAULT '',
        referrer VARCHAR(255) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        device ENUM('mobile','desktop') NOT NULL DEFAULT 'desktop',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_event_property (event_type, property_id),
        INDEX idx_visitor_hash (visitor_hash),
        INDEX idx_visit_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS development_projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_user_id INT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        developer VARCHAR(140) NOT NULL DEFAULT '',
        category VARCHAR(80) NOT NULL DEFAULT '',
        project_status VARCHAR(80) NOT NULL DEFAULT '',
        completion_year SMALLINT UNSIGNED NULL,
        unit_count INT UNSIGNED NULL,
        price_from BIGINT UNSIGNED NULL,
        price_to BIGINT UNSIGNED NULL,
        location VARCHAR(180) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        amenities TEXT NOT NULL,
        nearby_places TEXT NOT NULL,
        map_url VARCHAR(1000) NOT NULL DEFAULT '',
        contact_name VARCHAR(120) NOT NULL DEFAULT '',
        phone VARCHAR(20) NOT NULL DEFAULT '',
        line_id VARCHAR(40) NOT NULL DEFAULT '',
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_development_projects_published (is_published, updated_at),
        KEY idx_development_projects_owner (owner_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS project_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        filename VARCHAR(255) NOT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_project_images_order (project_id, sort_order),
        CONSTRAINT fk_project_images_project FOREIGN KEY (project_id) REFERENCES development_projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS project_units (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        unit_type VARCHAR(80) NOT NULL DEFAULT '',
        bedrooms SMALLINT UNSIGNED NULL,
        bathrooms SMALLINT UNSIGNED NULL,
        area_sqm DECIMAL(10,2) NULL,
        land_area DECIMAL(10,2) NULL,
        price_from BIGINT UNSIGNED NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        KEY idx_project_units_order (project_id, sort_order),
        CONSTRAINT fk_project_units_project FOREIGN KEY (project_id) REFERENCES development_projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS category_images (
        category VARCHAR(30) PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS visitor_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(190) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($tables as $sql) {
    $pdo->exec($sql);
}

$projectOwnerColumn = $pdo->query("SHOW COLUMNS FROM development_projects LIKE 'owner_user_id'")->fetch();
if (!$projectOwnerColumn) {
    $pdo->exec('ALTER TABLE development_projects ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id');
}
$projectOwnerIndex = $pdo->query("SHOW INDEX FROM development_projects WHERE Key_name = 'idx_development_projects_owner'")->fetch();
if (!$projectOwnerIndex) {
    $pdo->exec('ALTER TABLE development_projects ADD INDEX idx_development_projects_owner (owner_user_id)');
}

$statusColumn = $pdo->query("SHOW COLUMNS FROM properties LIKE 'status'")->fetch();
if (!$statusColumn) {
    $pdo->exec("ALTER TABLE properties ADD COLUMN status ENUM('available','done') NOT NULL DEFAULT 'available'");
}

$approvalColumn = $pdo->query("SHOW COLUMNS FROM properties LIKE 'approval'")->fetch();
if (!$approvalColumn) {
    $pdo->exec("ALTER TABLE properties ADD COLUMN approval ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
}

foreach ([
    'verification_status' => "ENUM('unchecked','passed','flagged') NOT NULL DEFAULT 'unchecked'",
    'verification_notes' => 'TEXT NULL',
] as $column => $definition) {
    if (!$pdo->query("SHOW COLUMNS FROM properties LIKE " . $pdo->quote($column))->fetch()) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN `$column` $definition");
    }
}
$verificationStatusColumn = $pdo->query("SHOW COLUMNS FROM properties LIKE 'verification_status'")->fetch();
if ($verificationStatusColumn && !str_contains((string)$verificationStatusColumn['Type'], "'unchecked'")) {
    $pdo->exec("ALTER TABLE properties MODIFY verification_status ENUM('unchecked','passed','flagged') NOT NULL DEFAULT 'unchecked'");
    $pdo->exec("UPDATE properties SET verification_status = 'unchecked', verification_notes = NULL WHERE verification_status = 'passed'");
}
$verificationIndex = $pdo->query("SHOW INDEX FROM properties WHERE Key_name = 'idx_verification_review'")->fetch();
if (!$verificationIndex) {
    $pdo->exec('ALTER TABLE properties ADD INDEX idx_verification_review (approval, verification_status, created_at)');
}

foreach ([
    'category' => "VARCHAR(50) NOT NULL DEFAULT 'other'",
    'property_category' => "VARCHAR(30) NOT NULL DEFAULT 'other'",
    'lat' => 'DECIMAL(10,7) NULL',
    'lng' => 'DECIMAL(10,7) NULL',
    'bedrooms' => 'SMALLINT UNSIGNED NULL',
    'bathrooms' => 'SMALLINT UNSIGNED NULL',
    'area_sqm' => 'DECIMAL(10,2) NULL',
    'land_area' => 'DECIMAL(10,2) NULL',
    'parking_spaces' => 'SMALLINT UNSIGNED NULL',
    'floor_number' => 'VARCHAR(20) NULL',
    'furnished' => 'VARCHAR(50) NULL',
    'amenities' => 'TEXT NULL',
    'nearby_places' => 'TEXT NULL',
    'line_id' => "VARCHAR(40) NOT NULL DEFAULT ''",
] as $column => $definition) {
    if (!$pdo->query("SHOW COLUMNS FROM properties LIKE " . $pdo->quote($column))->fetch()) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN `$column` $definition");
    }
}

foreach ([
    'full_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'phone' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'line_id' => "VARCHAR(40) NOT NULL DEFAULT ''",
    'privacy_accepted_at' => 'DATETIME NULL',
    'last_login_at' => 'DATETIME NULL',
    'avatar_path' => 'VARCHAR(255) NULL',
    'agent_status' => "ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none'",
    'agent_license' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'agent_agency' => "VARCHAR(160) NOT NULL DEFAULT ''",
    'agent_document' => 'VARCHAR(100) NULL',
    'agent_reviewed_at' => 'DATETIME NULL',
] as $column => $definition) {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column))->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
}

// Keep the legacy category field and the current property_category field aligned.
$pdo->exec("UPDATE properties SET property_category = category WHERE property_category = 'other' AND category <> 'other'");
$pdo->exec("UPDATE properties SET category = property_category WHERE property_category <> 'other'");

$propertyUserColumn = $pdo->query("SHOW COLUMNS FROM properties LIKE 'user_id'")->fetch();
if (!$propertyUserColumn) {
    $pdo->exec('ALTER TABLE properties ADD COLUMN user_id INT UNSIGNED NULL');
    $pdo->exec('ALTER TABLE properties ADD INDEX idx_properties_user (user_id)');
}

$visitColumns = $pdo->query("SHOW COLUMNS FROM visits LIKE 'page_path'")->fetch();
if (!$visitColumns) {
    $pdo->exec("ALTER TABLE visits ADD COLUMN page_path VARCHAR(120) NOT NULL DEFAULT '' AFTER property_id");
}
$visitorIndex = $pdo->query("SHOW INDEX FROM visits WHERE Key_name = 'idx_visitor_hash'")->fetch();
if (!$visitorIndex) {
    $pdo->exec('ALTER TABLE visits ADD INDEX idx_visitor_hash (visitor_hash)');
}
$visitUserColumn = $pdo->query("SHOW COLUMNS FROM visits LIKE 'user_id'")->fetch();
if (!$visitUserColumn) {
    $pdo->exec('ALTER TABLE visits ADD COLUMN user_id INT UNSIGNED NULL AFTER visitor_hash');
}
$visitUserIndex = $pdo->query("SHOW INDEX FROM visits WHERE Key_name = 'idx_visit_user'")->fetch();
if (!$visitUserIndex) {
    $pdo->exec('ALTER TABLE visits ADD INDEX idx_visit_user (user_id)');
}

$hasOwner = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;

$message = '';
$installed = false;

if (!$hasOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'กรุณากรอกชื่อผู้ใช้ อีเมล และรหัสผ่านอย่างน้อย 8 ตัวอักษรให้ถูกต้อง';
    } else {
        $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $existing->execute([$username, $email]);
        if ($existing->fetch()) {
            $message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว';
        } else {
            $pdo->prepare('INSERT INTO users (email, username, password_hash, role) VALUES (?, ?, ?, "admin")')
                ->execute([$email, $username, password_hash($password, PASSWORD_DEFAULT)]);
            $installed = true;
            $hasOwner = true;
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ติดตั้งระบบ</title>
  <link rel="stylesheet" href="admin/admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin/admin.css')) ?>">
  <?php site_controls_head(); ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <h2>ติดตั้งระบบ <?= e(SITE_NAME) ?></h2>

    <?php if ($installed): ?>
      <div class="alert success">
        ✅ สร้างบัญชีเจ้าของเว็บไซต์เรียบร้อยแล้ว<br><br>
        <strong>ขั้นต่อไป:</strong> ลบไฟล์ <code>install.php</code> ออกจากโฟลเดอร์ทันที<br>
        แล้วเข้าสู่ระบบที่ <a href="login.php">login.php</a>
      </div>
    <?php elseif ($hasOwner): ?>
      <div class="alert success">
        ติดตั้งเรียบร้อยแล้ว กรุณา <strong>ลบไฟล์ install.php</strong> ออกจากเซิร์ฟเวอร์<br><br>
        <a href="login.php">ไปหน้าเข้าสู่ระบบ</a>
      </div>
    <?php else: ?>
      <?php if ($message): ?>
        <div class="alert error"><?= e($message) ?></div>
      <?php endif; ?>
      <p class="muted">สร้างบัญชีเจ้าของเว็บไซต์สำหรับเข้าสู่ระบบและจัดการหลังบ้าน (รหัสควรยาวอย่างน้อย 8 ตัว)</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>ชื่อผู้ใช้</label>
        <input type="text" name="username" minlength="3" required autofocus>
        <label>อีเมล</label>
        <input type="email" name="email" required>
        <label>รหัสผ่าน</label>
        <input type="password" name="password" minlength="8" required>
        <button type="submit" class="btn-block">สร้างบัญชีเจ้าของเว็บไซต์</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<footer class="site-version" style="padding:18px;text-align:center;"><?= e(SITE_NAME) ?> · <?= e(SITE_VERSION) ?></footer>
</body>
</html>
