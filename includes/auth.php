<?php
require_once __DIR__ . '/helpers.php';

// ใส่ไว้บนสุดของหน้าหลังบ้านทุกหน้า ถ้ายังไม่ล็อกอินจะถูกส่งไปหน้า login
function require_admin(): void
{
    start_session();
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $adminPages = [
            'index.php', 'analytics.php', 'pdf-report.php', 'category-images.php',
            'featured.php', 'projects.php', 'add.php', 'edit.php', 'view.php', 'guestbook.php',
            'cookie_stats.php', 'dashboard.php', 'profile.php', 'agents.php', 'agent-document.php',
        ];
        $returnTo = in_array($page, $adminPages, true) ? 'admin/' . $page : 'admin/index.php';
        if (in_array($page, ['edit.php', 'view.php', 'agent-document.php'], true)
            && isset($_GET['id']) && ctype_digit((string)$_GET['id']) && (int)$_GET['id'] > 0) {
            $returnTo .= '?id=' . (int)$_GET['id'];
        }
        redirect('../login.php?return_to=' . rawurlencode($returnTo));
    }
    require_once __DIR__ . '/profile_schema.php';
    ensure_profile_schema(db());
}

function pending_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM properties WHERE approval = 'pending'")->fetchColumn();
}
