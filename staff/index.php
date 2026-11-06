<?php
require_once __DIR__ . '/../includes/staff.php';
start_session();

$pdo = db();
ensure_staff_schema($pdo);

$staff = current_staff($pdo);
if (!$staff) {
    redirect('login.php');
}

$role = staff_role_by_slug($pdo, (string)$staff['role_slug']) ?: ['name_th' => $staff['role_slug'], 'icon' => '👤', 'description' => ''];
$perms = staff_permissions($pdo, (int)$staff['id']);

// ── ข้อมูลสรุปตามสิทธิ์ที่ได้รับ ──
$summary = [];
if (!empty($perms['listings']['view'])) {
    $summary['listings_total'] = (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
    $summary['listings_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'pending'")->fetchColumn();
    $summary['listings_approved'] = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'approved'")->fetchColumn();
}
if (!empty($perms['projects']['view'])) {
    $summary['projects_total'] = (int)$pdo->query('SELECT COUNT(*) FROM development_projects')->fetchColumn();
}
if (!empty($perms['agents']['view'])) {
    $summary['agents_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND agent_status = 'pending'")->fetchColumn();
}
if (!empty($perms['analytics']['view'])) {
    $summary['visitors_30d'] = (int)$pdo->query('SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
    $summary['pageviews_30d'] = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE event_type = 'page' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
}
if (!empty($perms['marketing']['view'])) {
    $summary['banners_active'] = (int)$pdo->query('SELECT COUNT(*) FROM promo_banners WHERE enabled = 1')->fetchColumn();
}
if (!empty($perms['sales']['view'])) {
    $summary['members_total'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $summary['members_new_30d'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
}

// ── รายการเครื่องมือตามสิทธิ์ ──
$tools = [];
$addTool = static function (string $module, string $perm, string $icon, string $title, string $desc, string $url) use (&$tools, $perms) {
    if (!empty($perms[$module][$perm])) {
        $tools[] = ['icon' => $icon, 'title' => $title, 'desc' => $desc, 'url' => $url, 'module' => $module];
    }
};

$addTool('listings', 'view', '▤', 'จัดการประกาศอสังหาฯ', 'ตรวจสอบ อนุมัติ และแก้ไขประกาศทั้งหมด', '../admin/index.php');
$addTool('listings', 'create', '＋', 'เพิ่มประกาศใหม่', 'สร้างประกาศอสังหาริมทรัพย์เข้าระบบ', '../admin/add.php');
$addTool('projects', 'view', '⌂', 'จัดการโครงการ', 'ดูแลข้อมูลโครงการพัฒนาใหม่', '../admin/projects.php');
$addTool('agents', 'view', '✓', 'รับรอง Agent', 'ตรวจหลักฐานและอนุมัติเครื่องหมาย Agent', '../admin/agents.php');
$addTool('content', 'view', '🎬', 'แบนเนอร์ & สื่อเว็บ', 'จัดการภาพ/วิดีโอโปรโมชันทุกตำแหน่ง', '../admin/banners.php');
$addTool('content', 'edit', '▧', 'ภาพหมวดหมู่', 'อัปเดตภาพประจำหมวดหมู่บนหน้าแรก', '../admin/category-images.php');
$addTool('marketing', 'view', '✦', 'หมวดแนะนำหน้าแรก', 'เลือกหมวดหมู่ที่ต้องการโปรโมต', '../admin/featured.php');
$addTool('analytics', 'view', '◷', 'สถิติผู้เข้าชม', 'รายงานผู้เข้าชมและพฤติกรรมบนเว็บ', '../admin/analytics.php');
$addTool('analytics', 'view', '▤', 'รายงาน PDF', 'ส่งอกรายงานสรุปสถิติเป็น PDF', '../admin/pdf-report.php');
$addTool('settings', 'manage', '⬇', 'เวอร์ชันระบบ', 'ปรับเวอร์ชันแพลตฟอร์มที่แสดงบนเว็บ', '../admin/version.php');
$addTool('settings', 'manage', '🔒', 'Social Login', 'ตั้งค่าช่องทางเข้าสู่ระบบ LINE/Google', '../admin/oauth.php');

$permCount = 0;
foreach ($perms as $mod) { $permCount += count(array_filter($mod)); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แดชบอร์ดทีมงาน | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/staff.css">
</head>
<body class="staff-body">
<?php require __DIR__ . '/_nav.php'; ?>

<main class="staff-main">
  <section class="staff-hero">
    <div>
      <span class="staff-eyebrow">STAFF WORKSPACE</span>
      <h1><?= e($role['icon'] ?? '👤') ?> <?= e($role['name_th'] ?? 'ทีมงาน') ?> Dashboard</h1>
      <p>ยินดีต้อนรับ <?= e($staff['full_name'] ?: $staff['username']) ?> · <?= e($role['description'] ?? '') ?></p>
    </div>
    <div class="staff-hero-meta">
      <span class="staff-chip">🔑 <?= $permCount ?> สิทธิ์ที่ได้รับ</span>
      <span class="staff-chip">🕒 เข้าใช้ล่าสุด <?= $staff['last_login_at'] ? e(date('d/m/Y H:i', strtotime($staff['last_login_at']))) : '—' ?></span>
    </div>
  </section>

  <?php if ($summary): ?>
  <section class="staff-stats">
    <?php if (isset($summary['listings_total'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon navy">▤</span><div><small>ประกาศทั้งหมด</small><strong><?= number_format($summary['listings_total']) ?></strong></div></article>
      <article class="staff-stat"><span class="staff-stat-icon amber">◷</span><div><small>รอตรวจสอบ</small><strong><?= number_format($summary['listings_pending']) ?></strong></div></article>
      <article class="staff-stat"><span class="staff-stat-icon green">✓</span><div><small>เผยแพร่แล้ว</small><strong><?= number_format($summary['listings_approved']) ?></strong></div></article>
    <?php endif; ?>
    <?php if (isset($summary['projects_total'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon blue">⌂</span><div><small>โครงการ</small><strong><?= number_format($summary['projects_total']) ?></strong></div></article>
    <?php endif; ?>
    <?php if (isset($summary['agents_pending'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon amber">✓</span><div><small>Agent รอรับรอง</small><strong><?= number_format($summary['agents_pending']) ?></strong></div></article>
    <?php endif; ?>
    <?php if (isset($summary['visitors_30d'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon blue">◎</span><div><small>ผู้เข้าชม 30 วัน</small><strong><?= number_format($summary['visitors_30d']) ?></strong></div></article>
      <article class="staff-stat"><span class="staff-stat-icon navy">◷</span><div><small>Page views 30 วัน</small><strong><?= number_format($summary['pageviews_30d']) ?></strong></div></article>
    <?php endif; ?>
    <?php if (isset($summary['banners_active'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon amber">🎬</span><div><small>แบนเนอร์ที่ใช้งาน</small><strong><?= number_format($summary['banners_active']) ?></strong></div></article>
    <?php endif; ?>
    <?php if (isset($summary['members_total'])): ?>
      <article class="staff-stat"><span class="staff-stat-icon green">👥</span><div><small>สมาชิกทั้งหมด</small><strong><?= number_format($summary['members_total']) ?></strong></div></article>
      <article class="staff-stat"><span class="staff-stat-icon blue">＋</span><div><small>สมาชิกใหม่ 30 วัน</small><strong><?= number_format($summary['members_new_30d']) ?></strong></div></article>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="staff-panel">
    <div class="staff-panel-head">
      <div><h2>🧰 เครื่องมือที่คุณเข้าถึงได้</h2><p>แสดงเฉพาะส่วนที่ Founder &amp; CEO กำหนดสิทธิ์ให้บัญชีของคุณ</p></div>
    </div>
    <?php if (!$tools): ?>
      <div class="staff-empty">ยังไม่ได้รับสิทธิ์เข้าถึงเครื่องมือใด ๆ กรุณาติดต่อ Founder &amp; CEO</div>
    <?php else: ?>
      <div class="staff-tools">
        <?php foreach ($tools as $tool): ?>
          <a class="staff-tool" href="<?= e($tool['url']) ?>">
            <span class="staff-tool-icon"><?= e($tool['icon']) ?></span>
            <span class="staff-tool-text"><strong><?= e($tool['title']) ?></strong><small><?= e($tool['desc']) ?></small></span>
            <span class="staff-tool-arrow">→</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="staff-panel">
    <div class="staff-panel-head"><div><h2>🔑 สิทธิ์ของฉัน</h2><p>รายละเอียดสิทธิ์ที่ได้รับในแต่ละโมดูล</p></div></div>
    <div class="staff-perm-table-wrap">
      <table class="staff-perm-table">
        <thead>
          <tr>
            <th>โมดูล</th>
            <?php foreach (STAFF_PERMISSIONS as $perm => $meta): ?><th><?= e($meta['label']) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach (STAFF_MODULES as $module => $meta): ?>
            <tr>
              <td><strong><?= e($meta['icon']) ?> <?= e($meta['th']) ?></strong></td>
              <?php foreach (STAFF_PERMISSIONS as $perm => $permMeta): ?>
                <td><?= !empty($perms[$module][$perm]) ? '<span class="perm-yes">✓</span>' : '<span class="perm-no">—</span>' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
