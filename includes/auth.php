<?php
require_once __DIR__ . '/helpers.php';

// ตรวจสอบสิทธิ์สำหรับ Founder & CEO และ หุ้นส่วน/ผู้จัดการ (Partners & Management)
function require_admin(?string $module = null, string $permission = 'view'): void
{
    start_session();
    $role = $_SESSION['role'] ?? '';
    if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'partner'], true)) {
        $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $adminPages = [
            'index.php', 'analytics.php', 'pdf-report.php', 'category-images.php',
            'featured.php', 'projects.php', 'add.php', 'edit.php', 'view.php', 'guestbook.php',
            'cookie_stats.php', 'dashboard.php', 'profile.php', 'agents.php', 'agent-document.php',
            'banners.php', 'version.php', 'oauth.php', 'partners.php', 'staff.php'
        ];
        $returnTo = in_array($page, $adminPages, true) ? 'admin/' . $page : 'admin/index.php';
        if (in_array($page, ['edit.php', 'view.php', 'agent-document.php'], true)
            && isset($_GET['id']) && ctype_digit((string)$_GET['id']) && (int)$_GET['id'] > 0) {
            $returnTo .= '?id=' . (int)$_GET['id'];
        }
        redirect('../login.php?return_to=' . rawurlencode($returnTo));
    }

    // Founder & CEO (role === 'admin') มีสิทธิ์สูงสุดทุกส่วนของระบบ
    if ($role === 'admin') {
        require_once __DIR__ . '/profile_schema.php';
        ensure_profile_schema(db());
        return;
    }

    // หุ้นส่วน / ผู้จัดการ (role === 'partner'):
    // 1) ห้ามเข้าหน้าจัดการหุ้นส่วน (partners.php / staff.php) — เฉพาะ Founder & CEO เท่านั้น
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($page, ['partners.php', 'staff.php'], true)) {
        redirect('index.php');
    }

    // 2) ตรวจสอบสิทธิ์เฉพาะโมดูลถ้ามีการกำหนด
    if ($module !== null) {
        require_once __DIR__ . '/staff.php';
        $staffId = (int)($_SESSION['staff_id'] ?? 0);
        if (!staff_can(db(), $staffId, $module, $permission)) {
            http_response_code(403);
            render_access_denied_screen($module, $permission);
            exit;
        }
    }
}

function render_access_denied_screen(string $module, string $permission): void
{
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>สิทธิ์การเข้าถึงถูกจำกัด | ' . htmlspecialchars(SITE_NAME) . '</title>'
        . '<link rel="stylesheet" href="../css/style.css"></head><body style="display:grid;place-items:center;min-height:100vh;background:#F9F9FB;font-family:Prompt,sans-serif;margin:0;padding:20px;">'
        . '<div style="text-align:center;padding:42px 32px;background:#fff;border-radius:22px;border:1px solid #E2E8F0;max-width:480px;box-shadow:0 14px 40px rgba(11,28,51,.08);">'
        . '<div style="font-size:48px;margin-bottom:12px;">🔒</div>'
        . '<h1 style="color:#0B1C33;font-size:22px;margin:0 0 8px;">สิทธิ์การเข้าถึงส่วนนี้ถูกจำกัด</h1>'
        . '<p style="color:#64748B;font-size:14px;line-height:1.6;margin:0 0 24px;">บัญชีของคุณยังไม่ได้รับสิทธิ์ <strong>' . htmlspecialchars($permission) . '</strong> สำหรับส่วน <strong>' . htmlspecialchars($module) . '</strong><br>กรุณาติดต่อ <strong>Founder &amp; CEO</strong> เพื่อเปิดสิทธิ์การใช้งาน</p>'
        . '<a href="index.php" style="display:inline-block;padding:12px 28px;border-radius:999px;background:#0B1C33;color:#fff;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 4px 14px rgba(11,28,51,.25);">← กลับหน้าหลัก</a>'
        . '</div></body></html>';
}

function pending_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM properties WHERE approval = 'pending'")->fetchColumn();
}
