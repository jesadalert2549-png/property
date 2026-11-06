<?php
/**
 * PropertyGO — Staff Module (Add-on)
 * ---------------------------------------------------------------
 * ระบบทีมงาน (Staff) แยกจากสมาชิก/ลูกค้าทั่วไปโดยสิ้นเชิง
 * - ใช้ตารางใหม่ staff_accounts / staff_roles / staff_permissions
 * - ไม่แก้ไขตาราง users เดิม และไม่แตะระบบ login ของลูกค้า
 * - Founder & CEO = บัญชี admin เดิม (users.role = 'admin')
 */

require_once __DIR__ . '/helpers.php';

const STAFF_PERMISSIONS = [
    'view'   => ['label' => 'View',   'th' => 'ดูข้อมูล',        'icon' => '👁'],
    'create' => ['label' => 'Create', 'th' => 'เพิ่มข้อมูล',     'icon' => '＋'],
    'edit'   => ['label' => 'Edit',   'th' => 'แก้ไขข้อมูล',     'icon' => '✎'],
    'delete' => ['label' => 'Delete', 'th' => 'ลบข้อมูล',        'icon' => '🗑'],
    'manage' => ['label' => 'Manage', 'th' => 'จัดการระบบ',      'icon' => '⚙'],
];

const STAFF_MODULES = [
    'listings'  => ['th' => 'ประกาศอสังหาฯ', 'en' => 'Listings',  'icon' => '▤'],
    'projects'  => ['th' => 'โครงการ',        'en' => 'Projects',  'icon' => '⌂'],
    'agents'    => ['th' => 'รับรอง Agent',   'en' => 'Agents',    'icon' => '✓'],
    'content'   => ['th' => 'เนื้อหา/แบนเนอร์', 'en' => 'Content', 'icon' => '🎬'],
    'marketing' => ['th' => 'การตลาด',        'en' => 'Marketing', 'icon' => '📣'],
    'sales'     => ['th' => 'การขาย',         'en' => 'Sales',     'icon' => '💼'],
    'analytics' => ['th' => 'สถิติ',          'en' => 'Analytics', 'icon' => '◷'],
    'settings'  => ['th' => 'ตั้งค่าระบบ',    'en' => 'Settings',  'icon' => '⚙'],
];

const STAFF_DEFAULT_ROLES = [
    'marketing' => ['th' => 'Marketing', 'en' => 'Marketing', 'icon' => '📣', 'desc' => 'เครื่องมือและข้อมูลด้านการตลาด'],
    'sales'     => ['th' => 'Sales',     'en' => 'Sales',     'icon' => '💼', 'desc' => 'เครื่องมือและข้อมูลด้านการขาย'],
    'content'   => ['th' => 'Content',   'en' => 'Content',   'icon' => '✎', 'desc' => 'เครื่องมือจัดการเนื้อหา'],
    'webadmin'  => ['th' => 'Web Admin', 'en' => 'Web Admin', 'icon' => '⚙', 'desc' => 'เครื่องมือจัดการเว็บไซต์'],
];

function ensure_staff_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(40) NOT NULL,
            name_th VARCHAR(80) NOT NULL,
            name_en VARCHAR(80) NOT NULL DEFAULT '',
            icon VARCHAR(8) NOT NULL DEFAULT '👤',
            description VARCHAR(255) NOT NULL DEFAULT '',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_staff_role_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(190) NOT NULL,
            full_name VARCHAR(120) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            role_slug VARCHAR(40) NOT NULL DEFAULT 'content',
            status ENUM('active','suspended') NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED NULL,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_staff_username (username),
            UNIQUE KEY uq_staff_email (email),
            KEY idx_staff_role (role_slug),
            KEY idx_staff_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id INT UNSIGNED NOT NULL,
            module VARCHAR(40) NOT NULL,
            permission ENUM('view','create','edit','delete','manage') NOT NULL,
            granted TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_staff_perm (staff_id, module, permission),
            KEY idx_staff_perm_staff (staff_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_activity_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id INT UNSIGNED NULL,
            actor_label VARCHAR(120) NOT NULL DEFAULT '',
            action VARCHAR(60) NOT NULL,
            detail VARCHAR(255) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_staff_log_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // สร้างตำแหน่งเริ่มต้น (ตัวอย่าง) ถ้ายังไม่มี
    $count = (int)$pdo->query('SELECT COUNT(*) FROM staff_roles')->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare('INSERT INTO staff_roles (slug, name_th, name_en, icon, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $order = 0;
        foreach (STAFF_DEFAULT_ROLES as $slug => $role) {
            $insert->execute([$slug, $role['th'], $role['en'], $role['icon'], $role['desc'], $order++]);
        }
    }
}

function staff_roles(PDO $pdo, bool $onlyActive = true): array
{
    ensure_staff_schema($pdo);
    $sql = 'SELECT * FROM staff_roles' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function staff_role_by_slug(PDO $pdo, string $slug): ?array
{
    ensure_staff_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM staff_roles WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** สิทธิ์ของทีมงาน 1 คน: [module => [perm => bool]] */
function staff_permissions(PDO $pdo, int $staffId): array
{
    ensure_staff_schema($pdo);
    $stmt = $pdo->prepare('SELECT module, permission, granted FROM staff_permissions WHERE staff_id = ?');
    $stmt->execute([$staffId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['module']][$row['permission']] = (bool)$row['granted'];
    }
    return $map;
}

function staff_can(PDO $pdo, int $staffId, string $module, string $permission): bool
{
    $perms = staff_permissions($pdo, $staffId);
    return !empty($perms[$module][$permission]);
}

function staff_set_permissions(PDO $pdo, int $staffId, array $posted): void
{
    ensure_staff_schema($pdo);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM staff_permissions WHERE staff_id = ?')->execute([$staffId]);
        $insert = $pdo->prepare('INSERT INTO staff_permissions (staff_id, module, permission, granted) VALUES (?, ?, ?, 1)');
        foreach (STAFF_MODULES as $module => $meta) {
            foreach (STAFF_PERMISSIONS as $perm => $permMeta) {
                if (!empty($posted[$module][$perm])) {
                    $insert->execute([$staffId, $module, $perm]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function staff_log(PDO $pdo, ?int $staffId, string $actor, string $action, string $detail = ''): void
{
    try {
        ensure_staff_schema($pdo);
        $pdo->prepare('INSERT INTO staff_activity_log (staff_id, actor_label, action, detail, ip) VALUES (?, ?, ?, ?, ?)')
            ->execute([$staffId, mb_substr($actor, 0, 120), mb_substr($action, 0, 60), mb_substr($detail, 0, 255), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    } catch (Throwable $e) {
        // logging ต้องไม่ทำให้ระบบหลักล้ม
    }
}

/** ตรวจว่าผู้ใช้ปัจจุบันเป็น Founder & CEO (admin เดิม) */
function is_founder(): bool
{
    start_session();
    return !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
}

/** ตรวจว่าผู้ใช้ปัจจุบันเป็นทีมงานที่ล็อกอินอยู่ */
function is_staff(): bool
{
    start_session();
    return !empty($_SESSION['staff_id']) && ($_SESSION['staff_role'] ?? '') === 'staff';
}

function current_staff(PDO $pdo): ?array
{
    if (!is_staff()) return null;
    ensure_staff_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([(int)$_SESSION['staff_id']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff) {
        // บัญชีถูกระงับ/ลบระหว่างใช้งาน → ออกจากระบบทันที
        unset($_SESSION['staff_id'], $_SESSION['staff_username'], $_SESSION['staff_role'], $_SESSION['staff_name'], $_SESSION['staff_role_slug']);
        return null;
    }
    return $staff;
}

/** บังคับให้ต้องเป็นทีมงานที่ล็อกอินแล้ว */
function require_staff(PDO $pdo): array
{
    $staff = current_staff($pdo);
    if (!$staff) {
        redirect('login.php');
    }
    return $staff;
}

/** บังคับสิทธิ์เฉพาะ module/permission */
function require_staff_permission(PDO $pdo, string $module, string $permission = 'view'): array
{
    $staff = require_staff($pdo);
    if (!staff_can($pdo, (int)$staff['id'], $module, $permission)) {
        http_response_code(403);
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>ไม่มีสิทธิ์เข้าถึง</title>'
            . '<link rel="stylesheet" href="../css/style.css"></head><body style="display:grid;place-items:center;min-height:100vh;background:#F9F9FB;font-family:Prompt,sans-serif;">'
            . '<div style="text-align:center;padding:40px;background:#fff;border-radius:20px;border:1px solid #E2E8F0;max-width:460px;">'
            . '<div style="font-size:44px;">🔒</div><h1 style="color:#0B1C33;font-size:22px;margin:12px 0 8px;">ไม่มีสิทธิ์เข้าถึงส่วนนี้</h1>'
            . '<p style="color:#64748B;font-size:14px;">บัญชีทีมงานของคุณไม่ได้รับสิทธิ์ <strong>' . htmlspecialchars($permission) . '</strong> สำหรับโมดูล <strong>' . htmlspecialchars($module) . '</strong><br>กรุณาติดต่อ Founder &amp; CEO เพื่อขอสิทธิ์เพิ่มเติม</p>'
            . '<a href="index.php" style="display:inline-block;margin-top:16px;padding:10px 22px;border-radius:999px;background:#0B1C33;color:#fff;text-decoration:none;font-weight:700;">← กลับแดชบอร์ด</a>'
            . '</div></body></html>';
        exit;
    }
    return $staff;
}

/** สร้างบัญชีทีมงาน (เฉพาะ Founder) */
function staff_create(PDO $pdo, array $data, array $permissions, int $createdBy): int
{
    ensure_staff_schema($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO staff_accounts (username, email, full_name, password_hash, role_slug, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['username'],
        $data['email'],
        $data['full_name'],
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['role_slug'],
        $data['status'] ?? 'active',
        $createdBy,
    ]);
    $id = (int)$pdo->lastInsertId();
    staff_set_permissions($pdo, $id, $permissions);
    return $id;
}

function staff_validate_username(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username);
}
