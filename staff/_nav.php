<?php
/** แถบนำทางทีมงาน (ใช้ร่วมทุกหน้าใน /staff) */
$staffNavRole = $staffRole ?? null;
$staffNavName = $_SESSION['staff_name'] ?? ($_SESSION['staff_username'] ?? 'Staff');
$staffNavInitial = mb_strtoupper(mb_substr((string)$staffNavName, 0, 1));
$staffCurrentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
?>
<header class="staff-navbar">
  <div class="staff-nav-left">
    <?= render_logo_html('../index.php', 'sp-logo--dark') ?>
    <span class="staff-nav-badge">STAFF</span>
    <?php if ($staffNavRole): ?>
      <span class="staff-nav-role"><?= e($staffNavRole['icon'] ?? '👤') ?> <?= e($staffNavRole['name_th'] ?? '') ?></span>
    <?php endif; ?>
  </div>
  <div class="staff-nav-right">
    <a href="index.php" class="staff-nav-link <?= $staffCurrentPage === 'index.php' ? 'active' : '' ?>"><?= e(t('▦ แดชบอร์ด', '▦ Dashboard')) ?></a>
    <a href="../index.php" target="_blank" rel="noopener" class="staff-nav-link"><?= e(t('↗ ดูเว็บไซต์', '↗ View Site')) ?></a>
    <span class="staff-nav-user">
      <span class="staff-nav-avatar"><?= e($staffNavInitial) ?></span>
      <span><?= e($staffNavName) ?></span>
    </span>
    <?php render_lang_toggle(); ?>
    <a href="logout.php" class="staff-nav-logout"><?= e(t('⇥ ออกจากระบบ', '⇥ Log out')) ?></a>
  </div>
</header>
