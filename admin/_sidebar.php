<?php
require_once __DIR__ . '/../includes/i18n.php';
current_lang();
$adminNavItems = [
    ['index.php', '▦', t('แดชบอร์ด', 'Dashboard')],
    ['add.php', '＋', t('เพิ่มประกาศ', 'Add Listing')],
    ['projects.php', '⌂', t('โครงการ', 'Projects')],
    ['agents.php', '✓', t('รับรอง Agent', 'Verify Agents')],
    ['featured.php', '✦', t('หมวดแนะนำ', 'Featured')],
    ['category-images.php', '▧', t('ภาพหมวดหมู่', 'Category Images')],
    ['banners.php', '🎬', t('โปรโมชัน/สื่อเว็บ', 'Promo Media')],
    ['version.php', '⬇', t('เวอร์ชันระบบ', 'Version')],
    ['oauth.php', '🔒', t('Social Login', 'Social Login')],
    ['analytics.php', '◷', t('สถิติผู้เข้าชม', 'Analytics')],
    ['pdf-report.php', '▤', t('รายงาน PDF', 'PDF Report')],
];
$adminProfile = db()->prepare('SELECT full_name, username, email, avatar_path FROM users WHERE id = ? LIMIT 1');
$adminProfile->execute([(int)($_SESSION['user_id'] ?? 0)]);
$adminProfile = $adminProfile->fetch() ?: ['full_name' => '', 'username' => 'admin', 'email' => '', 'avatar_path' => null];
$adminDisplayName = trim((string)$adminProfile['full_name']) ?: (string)$adminProfile['username'];
$adminInitial = mb_strtoupper(mb_substr($adminDisplayName, 0, 1));
$currentAdminPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<header class="admin-desktop-navbar">
  <?= render_logo_html('index.php', 'sp-logo--dark') ?><span class="admin-brand-admin-label">ADMIN</span>
  <nav class="admin-navbar-links" aria-label="<?= e(t('เมนูผู้ดูแล', 'Admin menu')) ?>">
    <?php foreach ($adminNavItems as [$href, $icon, $label]): ?>
      <a href="<?= e($href) ?>" class="<?= $currentAdminPage === $href || ($href === 'add.php' && $currentAdminPage === 'edit.php') ? 'active' : '' ?>"><span aria-hidden="true"><?= e($icon) ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="profile.php" class="<?= $currentAdminPage === 'profile.php' ? 'active' : '' ?>">⚙ <?= e(t('โปรไฟล์', 'Profile')) ?></a>
  </nav>
  <div class="admin-navbar-account"><?php if (!empty($adminProfile['avatar_path']) && is_file(__DIR__ . '/../uploads/avatars/' . basename($adminProfile['avatar_path']))): ?><img class="admin-navbar-avatar" src="../uploads/avatars/<?= e(basename($adminProfile['avatar_path'])) ?>" alt=""><?php else: ?><span class="admin-navbar-avatar admin-navbar-avatar-fallback"><?= e($adminInitial) ?></span><?php endif; ?><span><?= e($adminDisplayName) ?></span><a href="../index.php" target="_blank" rel="noopener"><?= e(t('ดูเว็บ', 'View Site')) ?></a><?php ob_start(); render_lang_toggle(); $langToggleHtml = ob_get_clean(); echo $langToggleHtml; ?><a href="../logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a></div>
</header>
<button class="admin-sidebar-toggle" type="button" aria-label="<?= e(t('เปิดเมนูผู้ดูแล', 'Open admin menu')) ?>" aria-expanded="false" aria-controls="adminSidebar">☰</button>
<div class="admin-sidebar-backdrop" data-admin-sidebar-close hidden></div>
<aside class="admin-sidebar" id="adminSidebar" aria-label="<?= e(t('เมนูผู้ดูแลระบบ', 'Admin sidebar')) ?>">
  <?= render_logo_html('index.php', 'sp-logo--dark') ?><span class="admin-brand-admin-label">ADMIN</span>
  <section class="admin-user-card" aria-label="<?= e(t('บัญชีผู้ดูแล', 'Admin account')) ?>">
    <?php if (!empty($adminProfile['avatar_path']) && is_file(__DIR__ . '/../uploads/avatars/' . basename($adminProfile['avatar_path']))): ?><img class="admin-avatar admin-avatar-photo" src="../uploads/avatars/<?= e(basename($adminProfile['avatar_path'])) ?>" alt="รูปผู้ดูแล"><?php else: ?><span class="admin-avatar"><?= e($adminInitial) ?></span><?php endif; ?>
    <span class="admin-user-meta"><strong><?= e($adminDisplayName) ?></strong><small><?= e($adminProfile['email']) ?></small></span>
    <span class="admin-role-badge">ADMIN</span>
  </section>
  <div class="admin-sidebar-label"><?= e(t('เมนูหลัก', 'Main Menu')) ?></div>
  <nav class="admin-sidebar-nav">
    <?php foreach ($adminNavItems as [$href, $icon, $label]): ?>
      <a href="<?= e($href) ?>" class="<?= $currentAdminPage === $href || ($href === 'add.php' && $currentAdminPage === 'edit.php') ? 'active' : '' ?>"><span class="admin-nav-icon" aria-hidden="true"><?= e($icon) ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-label"><?= e(t('บัญชีของฉัน', 'My Account')) ?></div>
  <nav class="admin-sidebar-nav">
    <a href="profile.php" class="<?= $currentAdminPage === 'profile.php' ? 'active' : '' ?>"><span class="admin-nav-icon" aria-hidden="true">⚙</span><?= e(t('โปรไฟล์และบัญชี', 'Profile & Account')) ?></a>
    <a href="../index.php" target="_blank" rel="noopener"><span class="admin-nav-icon" aria-hidden="true">↗</span><?= e(t('ดูหน้าเว็บไซต์', 'View Website')) ?></a>
  </nav>
  <div class="admin-sidebar-footer-actions" style="display:flex;align-items:center;gap:8px;margin-top:auto;padding-top:10px;">
    <?php render_lang_toggle(); ?>
    <a class="admin-sidebar-logout" href="../logout.php" style="flex:1;margin-top:0;"><span aria-hidden="true">⇥</span> <?= e(t('ออกจากระบบ', 'Log out')) ?></a>
  </div>
  <div class="admin-sidebar-foot"><span class="admin-online-dot"></span> <?= e(t('ระบบผู้ดูแลพร้อมใช้งาน', 'Admin system ready')) ?></div>
  <div style="display:flex;justify-content:center;width:100%;">
    <div class="admin-sidebar-version"><?= e(SITE_NAME) ?> · <?= e(SITE_VERSION) ?></div>
  </div>
</aside>
<script>
(() => {
  const sidebar = document.getElementById('adminSidebar');
  const toggle = document.querySelector('.admin-sidebar-toggle');
  const backdrop = document.querySelector('.admin-sidebar-backdrop');
  if (!sidebar || !toggle || !backdrop) return;
  document.body.classList.add('has-admin-sidebar');
  const close = () => {
    sidebar.classList.remove('open');
    backdrop.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'เปิดเมนูผู้ดูแล');
    toggle.textContent = '☰';
    document.body.classList.remove('admin-sidebar-open');
  };
  toggle.addEventListener('click', () => {
    const open = sidebar.classList.toggle('open');
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'ปิดเมนูผู้ดูแล' : 'เปิดเมนูผู้ดูแล');
    toggle.textContent = open ? '×' : '☰';
    document.body.classList.toggle('admin-sidebar-open', open);
  });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 900) close(); });
})();
</script>
