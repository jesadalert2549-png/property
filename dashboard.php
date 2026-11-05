<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/profile_schema.php';
require_once __DIR__ . '/includes/profile_media.php';
current_lang();

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$pdo = db();
ensure_profile_schema($pdo);
$isOwner = ($_SESSION['role'] ?? '') === 'admin';
$myProperties = [];
$myProjects = [];
$ownerSummary = null;
$passwordError = '';
$passwordSuccess = '';
$profileError = '';
$profileSuccess = '';
$csrf = csrf_token();
$profileStatement = $pdo->prepare('SELECT id, username, email, full_name, phone, line_id, avatar_path, agent_status, agent_license, agent_agency, agent_document, created_at FROM users WHERE id = ? LIMIT 1');
$profileStatement->execute([(int)$_SESSION['user_id']]);
$memberProfile = $profileStatement->fetch() ?: ['username' => (string)($_SESSION['username'] ?? ''), 'email' => '', 'full_name' => '', 'phone' => '', 'line_id' => '', 'created_at' => null];

if (!$isOwner && request_is_post() && ($_POST['action'] ?? '') === 'save_profile') {
    csrf_check();
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = preg_replace('/[\s-]/', '', trim((string)($_POST['phone'] ?? '')));
    $lineId = clean_line_id($_POST['line_id'] ?? '');
    $agentLicense = trim((string)($_POST['agent_license'] ?? ''));
    $agentAgency = trim((string)($_POST['agent_agency'] ?? ''));
    $agentIntent = ($_POST['agent_application'] ?? '') === '1';
    $newAvatar = '';
    $newDocument = '';
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) $profileError = t('กรุณากรอกชื่อที่แสดง 2–120 ตัวอักษร', 'Please enter a display name of 2-120 characters.');
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $profileError = t('กรุณากรอกอีเมลให้ถูกต้อง', 'Please enter a valid email address.');
    elseif ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) $profileError = t('เบอร์โทรศัพท์ต้องมีตัวเลข 9–10 หลัก', 'Phone number must contain 9-10 digits.');
    elseif ($lineId !== '' && !valid_line_id($lineId)) $profileError = t('LINE ID ไม่ถูกต้อง', 'Invalid LINE ID.');
    else {
        try {
            $newAvatar = save_profile_image($_FILES['avatar'] ?? [], 'avatars');
            if ($agentIntent && !empty($_FILES['agent_document']['name'])) $newDocument = save_profile_image($_FILES['agent_document'], 'agent-documents');
        } catch (RuntimeException $exception) { $profileError = $exception->getMessage(); }
        if ($agentIntent && ($agentLicense === '' || $agentAgency === '')) $profileError = t('กรุณากรอกเลขใบอนุญาตและชื่อบริษัท/สังกัดก่อนส่งตรวจ', 'Please enter your license number and company/agency before submitting.');
        if ($agentIntent && ($memberProfile['agent_status'] ?? 'none') === 'verified') $profileError = t('บัญชีนี้ได้รับการรับรอง Agent แล้ว', 'This account is already a verified agent.');
        if ($agentIntent && empty($memberProfile['agent_document']) && $newDocument === '') $profileError = t('กรุณาแนบหลักฐานใบอนุญาตหรือเอกสารยืนยันตัวแทน', 'Please attach a license document or agent verification evidence.');
    }
    if ($profileError === '') {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $emailCheck->execute([$email, (int)$_SESSION['user_id']]);
        if ($emailCheck->fetchColumn()) $profileError = t('อีเมลนี้ถูกใช้งานแล้ว', 'This email is already in use.');
        else {
            $avatarPath = $newAvatar ?: ($memberProfile['avatar_path'] ?? null);
            $agentStatus = $agentIntent ? 'pending' : ($memberProfile['agent_status'] ?? 'none');
            $agentDocument = $newDocument ?: ($memberProfile['agent_document'] ?? null);
            $saveProfile = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, line_id = ?, avatar_path = ?, agent_status = ?, agent_license = ?, agent_agency = ?, agent_document = ? WHERE id = ?');
            $saveProfile->execute([$fullName, $email, $phone, $lineId, $avatarPath, $agentStatus, $agentIntent ? $agentLicense : ($memberProfile['agent_license'] ?? ''), $agentIntent ? $agentAgency : ($memberProfile['agent_agency'] ?? ''), $agentDocument, (int)$_SESSION['user_id']]);
            if ($newAvatar !== '') delete_profile_image((string)($memberProfile['avatar_path'] ?? ''), 'avatars');
            if ($newDocument !== '') delete_profile_image((string)($memberProfile['agent_document'] ?? ''), 'agent-documents');
            $memberProfile = array_merge($memberProfile, ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'line_id' => $lineId, 'avatar_path' => $avatarPath, 'agent_status' => $agentStatus, 'agent_license' => $agentLicense, 'agent_agency' => $agentAgency, 'agent_document' => $agentDocument]);
            $profileSuccess = $agentIntent ? t('ส่งข้อมูลขอรับรอง Agent แล้ว รอผู้ดูแลตรวจหลักฐาน', 'Agent verification request submitted — awaiting admin review.') : t('บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว', 'Profile saved successfully.');
        }
    }
    if ($profileError !== '') { if ($newAvatar !== '') delete_profile_image($newAvatar, 'avatars'); if ($newDocument !== '') delete_profile_image($newDocument, 'agent-documents'); $memberProfile = array_merge($memberProfile, ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'line_id' => $lineId]); }
}

if (request_is_post() && ($_POST['action'] ?? '') === 'change_password') {
    csrf_check();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $check = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $check->execute([(int)$_SESSION['user_id']]);
    $passwordHash = $check->fetchColumn();

    if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
        $passwordError = t('รหัสผ่านปัจจุบันไม่ถูกต้อง', 'Current password is incorrect.');
    } elseif (strlen($newPassword) < 8) {
        $passwordError = t('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 'New password must be at least 8 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = t('ยืนยันรหัสผ่านใหม่ไม่ตรงกัน', 'New password confirmation does not match.');
    } else {
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
        $passwordSuccess = t('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว', 'Password changed successfully.');
    }
}

if ($isOwner) {
    $ownerSummary = [
        'listings' => (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn(),
        'pending' => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'pending'")->fetchColumn(),
        'published' => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'approved'")->fetchColumn(),
        'visitors' => (int)$pdo->query('SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn(),
    ];
} else {
    $statement = $pdo->prepare(
        'SELECT p.id, p.title, p.type, p.status, p.approval, p.verification_status, p.verification_notes, p.price, p.location, p.created_at,
                (SELECT i.filename FROM property_images i
                 WHERE i.property_id = p.id
                 ORDER BY i.sort_order, i.id LIMIT 1) AS cover
         FROM properties p
         WHERE p.user_id = ?
         ORDER BY p.created_at DESC'
    );
    $statement->execute([(int)$_SESSION['user_id']]);
    $myProperties = $statement->fetchAll();

    $projectStatement = $pdo->prepare(
        'SELECT p.id, p.title, p.category, p.project_status, p.location, p.is_published, p.created_at,
                (SELECT i.filename FROM project_images i WHERE i.project_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
         FROM development_projects p WHERE p.owner_user_id = ? ORDER BY p.created_at DESC'
    );
    $projectStatement->execute([(int)$_SESSION['user_id']]);
    $myProjects = $projectStatement->fetchAll();
}
$memberDisplayName = trim((string)($memberProfile['full_name'] ?? '')) ?: (string)$memberProfile['username'];
$memberStats = [
    'posts' => count($myProperties) + count($myProjects),
    'published' => count(array_filter($myProperties, static fn($post) => $post['approval'] === 'approved')) + count(array_filter($myProjects, static fn($post) => (int)$post['is_published'] === 1)),
    'pending' => count(array_filter($myProperties, static fn($post) => $post['approval'] === 'pending')),
    'projects' => count($myProjects),
];
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isOwner ? e(t('แดชบอร์ดเจ้าของเว็บไซต์', 'Owner dashboard')) : e(t('พื้นที่ของฉัน', 'My space')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/member-portal.css?v=<?= e((string)filemtime(__DIR__ . '/css/member-portal.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
</head>
<body class="member-portal-body<?= $isOwner ? ' is-owner' : '' ?>">

<?php
$avatarFile = basename((string)($memberProfile['avatar_path'] ?? ''));
$hasAvatar = $avatarFile !== '' && is_file(__DIR__ . '/uploads/avatars/' . $avatarFile);
$memberInitial = mb_strtoupper(mb_substr($memberDisplayName, 0, 1));
$agentStatus = $memberProfile['agent_status'] ?? 'none';
?>

<!-- ══ Member Portal Top Navigation Bar ══ -->
<header class="portal-navbar">
  <div class="portal-nav-left">
    <button type="button" class="portal-mobile-toggle" id="portalMenuToggle" aria-label="<?= e(t('เปิดเมนู', 'Open menu')) ?>" aria-expanded="false" aria-controls="portalSidebar">☰</button>
    <?= render_logo_html('index.php') ?>
    <span class="portal-badge"><?= $isOwner ? e(t('OWNER', 'OWNER')) : e(t('MEMBER', 'MEMBER')) ?></span>
    <nav class="portal-nav-links" aria-label="<?= e(t('เมนูสมาชิก', 'Member menu')) ?>">
      <?php if (!$isOwner): ?>
        <a href="#my-posts" class="portal-nav-link"><?= e(t('โพสต์ของฉัน', 'My posts')) ?></a>
        <a href="#my-projects" class="portal-nav-link"><?= e(t('โครงการ', 'Projects')) ?></a>
        <a href="#my-account" class="portal-nav-link"><?= e(t('บัญชีของฉัน', 'My account')) ?></a>
      <?php else: ?>
        <a href="admin/index.php" class="portal-nav-link"><?= e(t('จัดการประกาศ', 'Manage listings')) ?></a>
        <a href="admin/agents.php" class="portal-nav-link"><?= e(t('รับรอง Agent', 'Verify agents')) ?></a>
        <a href="admin/analytics.php" class="portal-nav-link"><?= e(t('สถิติ', 'Analytics')) ?></a>
      <?php endif; ?>
      <a href="index.php" class="portal-nav-link" target="_blank" rel="noopener"><?= e(t('ดูหน้าเว็บ ↗', 'View site ↗')) ?></a>
    </nav>
  </div>
  <div class="portal-nav-actions">
    <a href="submit.php" class="portal-btn-post">＋ <?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
    <a href="#my-account" class="portal-user-chip">
      <?php if ($hasAvatar): ?>
        <img class="portal-user-avatar" src="uploads/avatars/<?= e($avatarFile) ?>" alt="">
      <?php else: ?>
        <span class="portal-user-avatar"><?= e($memberInitial) ?></span>
      <?php endif; ?>
      <span><?= e($memberDisplayName) ?></span>
    </a>
    <?php render_lang_toggle(); ?>
  </div>
</header>

<!-- ══ Member Portal Sidebar ══ -->
<div class="portal-backdrop" id="portalBackdrop"></div>
<aside class="portal-sidebar" id="portalSidebar" aria-label="<?= e(t('เมนูด้านข้าง', 'Sidebar menu')) ?>">
  <div class="portal-user-card">
    <?php if ($hasAvatar): ?>
      <img class="portal-user-avatar-large" src="uploads/avatars/<?= e($avatarFile) ?>" alt="">
    <?php else: ?>
      <span class="portal-user-avatar-large"><?= e($memberInitial) ?></span>
    <?php endif; ?>
    <div class="portal-user-meta">
      <span class="portal-user-name"><?= e($memberDisplayName) ?></span>
      <span class="portal-user-sub"><?= e($memberProfile['email'] ?: $memberProfile['username']) ?></span>
    </div>
  </div>

  <div class="portal-menu-group">
    <span class="portal-menu-header"><?= e(t('เมนูหลัก', 'Main menu')) ?></span>
    <a href="#my-posts" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">▤</span><?= $isOwner ? e(t('จัดการประกาศ', 'Manage listings')) : e(t('ประกาศของฉัน', 'My listings')) ?></span>
      <span class="portal-menu-badge"><?= number_format($memberStats['posts']) ?></span>
    </a>
    <a href="#my-projects" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">⌂</span><?= e(t('โครงการของฉัน', 'My projects')) ?></span>
      <span class="portal-menu-badge"><?= number_format($memberStats['projects']) ?></span>
    </a>
    <a href="submit.php" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">＋</span><?= e(t('ลงประกาศใหม่', 'New listing')) ?></span>
    </a>
    <a href="submit-project.php" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">✦</span><?= e(t('ส่งโครงการใหม่', 'New project')) ?></span>
    </a>
  </div>

  <div class="portal-menu-group">
    <span class="portal-menu-header"><?= e(t('บัญชีและการตั้งค่า', 'Account & settings')) ?></span>
    <a href="#my-account" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">⚙</span><?= e(t('ข้อมูลส่วนตัว', 'Profile settings')) ?></span>
    </a>
    <a href="#account-security" class="portal-menu-item">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">🔒</span><?= e(t('ความปลอดภัย', 'Security')) ?></span>
    </a>
    <a href="member.php?id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" class="portal-menu-item" target="_blank" rel="noopener">
      <span class="portal-menu-item-left"><span class="portal-menu-icon">👤</span><?= e(t('โปรไฟล์สาธารณะ', 'Public profile')) ?></span>
    </a>
    <?php if ($agentStatus === 'verified'): ?>
      <span class="portal-menu-item" style="cursor:default;">
        <span class="portal-menu-item-left"><span class="portal-menu-icon">✓</span><?= e(t('Agent รับรองแล้ว', 'Verified agent')) ?></span>
      </span>
    <?php elseif ($agentStatus === 'pending'): ?>
      <span class="portal-menu-item" style="cursor:default;">
        <span class="portal-menu-item-left"><span class="portal-menu-icon">◷</span><?= e(t('รอตรวจ Agent', 'Agent under review')) ?></span>
      </span>
    <?php else: ?>
      <a href="#my-account" class="portal-menu-item">
        <span class="portal-menu-item-left"><span class="portal-menu-icon">◈</span><?= e(t('ขอรับรอง Agent', 'Apply for agent')) ?></span>
      </a>
    <?php endif; ?>
  </div>

  <div class="portal-sidebar-footer">
    <div class="admin-sidebar-version" style="display:inline-flex;align-items:center;gap:7px;align-self:center;padding:5px 13px;border:1px solid rgba(197,160,89,.4);border-radius:999px;background:rgba(197,160,89,.14);color:#e5cb96;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.05em;">
      <?= e(SITE_NAME) ?> · <?= e(SITE_VERSION) ?>
    </div>
    <a href="logout.php" class="portal-btn-logout">⇥ <?= e(t('ออกจากระบบ', 'Log out')) ?></a>
  </div>
</aside>

<?php if ($profileError !== '' || $profileSuccess !== '' || $passwordError !== '' || $passwordSuccess !== ''): ?>
<div class="portal-alerts" style="margin-left:var(--portal-sidebar-w);padding:16px 36px 0;">
  <?php if ($profileError !== ''): ?><div class="alert error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
  <?php if ($profileSuccess !== ''): ?><div class="alert success" role="status"><?= e($profileSuccess) ?></div><?php endif; ?>
  <?php if ($passwordError !== ''): ?><div class="alert error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
  <?php if ($passwordSuccess !== ''): ?><div class="alert success" role="status"><?= e($passwordSuccess) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<main class="portal-workspace">
  <div>
    <?php if ($isOwner): ?>
      <section class="dashboard-hero">
        <div><span class="dashboard-kicker">PROPERTY ADMINISTRATION</span>
          <h1><?= e(t('แดชบอร์ดเจ้าของเว็บไซต์', 'Owner dashboard')) ?></h1>
          <p><?= e(t('สวัสดี ', 'Hello ')) . e($_SESSION['username']) ?> <span aria-hidden="true">·</span> <?= e(t('ภาพรวมและเครื่องมือจัดการเว็บไซต์ของคุณ', 'Overview and management tools for your website.')) ?></p>
        </div>
        <a href="admin/analytics.php" class="dashboard-hero-link"><span aria-hidden="true">↗</span> <?= e(t('ดูรายงานผู้เข้าชม', 'View visitor report')) ?></a>
      </section>
      <section class="dashboard-stats" aria-label="<?= e(t('ภาพรวมเว็บไซต์', 'Website overview')) ?>">
        <article class="dashboard-stat stat-listings"><span class="dashboard-stat-icon" aria-hidden="true">⌂</span><div class="dashboard-stat-label"><?= e(t('ประกาศทั้งหมด', 'Total listings')) ?></div><div class="dashboard-stat-value"><?= number_format($ownerSummary['listings']) ?></div><div class="dashboard-stat-note"><?= e(t('รายการในเว็บไซต์', 'items on the website')) ?></div></article>
        <article class="dashboard-stat stat-pending"><span class="dashboard-stat-icon" aria-hidden="true">◷</span><div class="dashboard-stat-label"><?= e(t('รอตรวจสอบ', 'Pending review')) ?></div><div class="dashboard-stat-value"><?= number_format($ownerSummary['pending']) ?></div><div class="dashboard-stat-note"><?= e(t('รายการที่ต้องดำเนินการ', 'items needing action')) ?></div></article>
        <article class="dashboard-stat stat-published"><span class="dashboard-stat-icon" aria-hidden="true">✓</span><div class="dashboard-stat-label"><?= e(t('เผยแพร่แล้ว', 'Published')) ?></div><div class="dashboard-stat-value"><?= number_format($ownerSummary['published']) ?></div><div class="dashboard-stat-note"><?= e(t('พร้อมให้ผู้เข้าชมดู', 'visible to visitors')) ?></div></article>
        <article class="dashboard-stat stat-visitors"><span class="dashboard-stat-icon" aria-hidden="true">◎</span><div class="dashboard-stat-label"><?= e(t('ผู้เข้าชมไม่ซ้ำ', 'Unique visitors')) ?></div><div class="dashboard-stat-value"><?= number_format($ownerSummary['visitors']) ?></div><div class="dashboard-stat-note"><?= e(t('ในช่วง 30 วันที่ผ่านมา', 'in the last 30 days')) ?></div></article>
      </section>
      <section class="dashboard-panel">
        <div class="dashboard-section-heading"><div><span class="dashboard-kicker">QUICK ACCESS</span><h2><?= e(t('เครื่องมือจัดการ', 'Management tools')) ?></h2><p><?= e(t('ไปยังส่วนที่คุณใช้งานบ่อยได้อย่างรวดเร็ว', 'Quickly reach the sections you use most.')) ?></p></div></div>
        <div class="dashboard-tools">
          <a class="dashboard-tool" href="admin/index.php"><span class="tool-icon" aria-hidden="true">▤</span><span><strong><?= e(t('จัดการประกาศ', 'Manage listings')) ?></strong><small><?= e(t('ตรวจสอบและแก้ไขรายการอสังหาริมทรัพย์', 'Review and edit property listings')) ?></small></span><b aria-hidden="true">→</b></a>
          <a class="dashboard-tool" href="admin/featured.php"><span class="tool-icon" aria-hidden="true">✦</span><span><strong><?= e(t('หมวดแนะนำ', 'Featured categories')) ?></strong><small><?= e(t('เลือกหมวดหมู่ที่ต้องการแสดงแนะนำ', 'Choose categories to feature')) ?></small></span><b aria-hidden="true">→</b></a>
          <a class="dashboard-tool" href="admin/category-images.php"><span class="tool-icon" aria-hidden="true">▧</span><span><strong><?= e(t('ภาพหมวดหมู่', 'Category images')) ?></strong><small><?= e(t('จัดการรูปภาพประกอบหมวดหมู่', 'Manage category cover images')) ?></small></span><b aria-hidden="true">→</b></a>
        </div>
      </section>
      <section class="dashboard-panel dashboard-security">
        <details>
          <summary><span class="security-icon" aria-hidden="true">♧</span><span><strong><?= e(t('ความปลอดภัยของบัญชี', 'Account security')) ?></strong><small><?= e(t('เปลี่ยนรหัสผ่านบัญชีเจ้าของเว็บไซต์', 'Change the owner account password')) ?></small></span><span class="summary-chevron" aria-hidden="true">⌄</span></summary>
          <?php if ($passwordError !== ''): ?><div class="alert error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
          <?php if ($passwordSuccess !== ''): ?><div class="alert success" role="status"><?= e($passwordSuccess) ?></div><?php endif; ?>
          <form method="post" class="form-grid dashboard-password-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="change_password">
            <label><?= e(t('รหัสผ่านปัจจุบัน', 'Current password')) ?><input type="password" name="current_password" autocomplete="current-password" required></label>
            <label><?= e(t('รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)', 'New password (at least 8 characters)')) ?><input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
            <label><?= e(t('ยืนยันรหัสผ่านใหม่', 'Confirm new password')) ?><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label>
            <button class="btn" type="submit"><?= e(t('บันทึกรหัสผ่านใหม่', 'Save new password')) ?></button>
          </form>
        </details>
      </section>
    <?php else: ?>
      <section class="member-welcome">
        <div class="member-welcome-copy"><div class="member-identity"><?php if (!empty($memberProfile['avatar_path']) && is_file(__DIR__ . '/uploads/avatars/' . basename($memberProfile['avatar_path']))): ?><img class="member-profile-photo" src="uploads/avatars/<?= e(basename($memberProfile['avatar_path'])) ?>" alt="<?= e(t('รูปโปรไฟล์', 'Profile photo')) ?>"><?php else: ?><span class="member-profile-photo member-profile-fallback"><?= e(mb_strtoupper(mb_substr($memberDisplayName, 0, 1))) ?></span><?php endif; ?><div><span class="dashboard-kicker">MY SPACE · <?= e(t('พื้นที่ส่วนตัว', 'Personal space')) ?></span><h1><?= e(t('ยินดีต้อนรับ, ', 'Welcome, ')) . e($memberDisplayName) ?> <?php if (($memberProfile['agent_status'] ?? '') === 'verified'): ?><span class="agent-verified-badge" title="<?= e(t('ผู้ดูแลตรวจสอบหลักฐาน Agent แล้ว', 'Agent credentials verified by admin')) ?>"><?= e(t('✓ Agent รับรองแล้ว', '✓ Verified agent')) ?></span><?php endif; ?></h1></div></div><p><?= e(t('ที่นี่คือพื้นที่ของคุณ จัดการประกาศอสังหาฯ และโครงการของตัวเองได้อย่างอิสระ', 'This is your space — manage your property listings and projects freely.')) ?></p><div class="member-private-note"><span aria-hidden="true">🔒</span> <?= e(t('ข้อมูลและรายการในบัญชีนี้เป็นของคุณ', 'The information and listings in this account belong to you.')) ?></div></div>
        <div class="member-welcome-actions"><a href="submit.php" class="member-primary-action"><span aria-hidden="true">＋</span><span><strong><?= e(t('ลงประกาศทรัพย์', 'Post a property listing')) ?></strong><small><?= e(t('ขายหรือให้เช่า', 'for sale or rent')) ?></small></span></a><a href="submit-project.php" class="member-secondary-action"><span aria-hidden="true">⌂</span><span><strong><?= e(t('ลงโครงการ', 'Submit a project')) ?></strong><small><?= e(t('เพิ่มโครงการของคุณ', 'add your project')) ?></small></span></a></div>
        <div class="member-welcome-art" aria-hidden="true"><span>⌂</span><i></i><b></b></div>
      </section>

      <section class="member-stat-grid" aria-label="<?= e(t('ภาพรวมพื้นที่ของฉัน', 'My space overview')) ?>">
        <article><span class="member-stat-icon blue">▤</span><div><small><?= e(t('โพสต์ทั้งหมด', 'Total posts')) ?></small><strong><?= number_format($memberStats['posts']) ?></strong><span><?= e(t('ทรัพย์และโครงการของคุณ', 'your properties and projects')) ?></span></div></article>
        <article><span class="member-stat-icon green">✓</span><div><small><?= e(t('เผยแพร่แล้ว', 'Published')) ?></small><strong><?= number_format($memberStats['published']) ?></strong><span><?= e(t('มองเห็นได้บนเว็บไซต์', 'visible on the website')) ?></span></div></article>
        <article><span class="member-stat-icon amber">◷</span><div><small><?= e(t('รอตรวจสอบ', 'Pending review')) ?></small><strong><?= number_format($memberStats['pending']) ?></strong><span><?= e(t('ประกาศที่กำลังดำเนินการ', 'listings being processed')) ?></span></div></article>
        <article><span class="member-stat-icon violet">⌂</span><div><small><?= e(t('โครงการ', 'Projects')) ?></small><strong><?= number_format($memberStats['projects']) ?></strong><span><?= e(t('โครงการที่คุณลงไว้', 'projects you have submitted')) ?></span></div></article>
      </section>

      <section class="member-posts-section" id="my-posts">
        <div class="member-section-heading"><div><span class="member-section-eyebrow">YOUR CONTENT</span><h2><?= e(t('โพสต์ของฉัน', 'My posts')) ?></h2><p><?= e(t('ดูสถานะของโพสต์ทั้งหมดได้จากที่เดียว', 'See the status of all your posts in one place.')) ?></p></div><a href="submit.php" class="member-inline-link">＋ <?= e(t('เพิ่มโพสต์', 'Add a post')) ?></a></div>
        <?php if (!$myProperties && !$myProjects): ?>
          <div class="member-empty"><div class="member-empty-icon">✦</div><h3><?= e(t('พื้นที่นี้เป็นของคุณ เริ่มโพสต์ชิ้นแรกได้เลย', 'This space is yours — create your first post now')) ?></h3><p><?= e(t('ลงขายหรือให้เช่าทรัพย์ หรือเพิ่มโครงการของคุณได้ตามต้องการ', 'List a property for sale or rent, or add your own project.')) ?></p><div><a class="member-empty-button" href="submit.php"><?= e(t('ลงประกาศทรัพย์', 'Post a listing')) ?></a><a class="member-empty-button light" href="submit-project.php"><?= e(t('ลงโครงการ', 'Submit a project')) ?></a></div></div>
        <?php else: ?>
          <?php if ($myProperties): ?>
            <div class="member-subsection-heading"><h3><?= e(t('ประกาศทรัพย์', 'Property listings')) ?></h3><span><?= e(number_format(count($myProperties)) . t(' รายการ', ' items')) ?></span></div>
            <div class="member-post-grid">
              <?php foreach ($myProperties as $property): ?>
                <article class="member-post-card">
                  <div class="member-post-cover">
                    <?php if (!empty($property['cover']) && is_file(UPLOAD_DIR . basename($property['cover']))): ?><img src="uploads/<?= e(basename($property['cover'])) ?>" alt="<?= e($property['title']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">⌂</span><?php endif; ?>
                    <span class="member-post-type"><?= e($property['type'] === 'rent' ? t('ให้เช่า', 'For rent') : t('ขาย', 'For sale')) ?></span>
                  </div>
                  <div class="member-post-info"><div class="member-post-title-row"><h3><?= e($property['title']) ?></h3><span class="listing-status <?= $property['approval'] === 'approved' ? 'is-approved' : ($property['approval'] === 'rejected' ? 'is-rejected' : 'is-pending') ?>"><i></i><?= $property['approval'] === 'approved' ? (($property['verification_status'] ?? 'passed') === 'flagged' ? e(t('ตรวจซ้ำ', 'Re-review')) : e(t('เผยแพร่', 'Published'))) : ($property['approval'] === 'rejected' ? e(t('ซ่อนอยู่', 'Hidden')) : e(t('รอตรวจ', 'Pending'))) ?></span></div><p class="member-post-price"><?= e(is_en() ? number_format((int)$property['price']) . ($property['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($property['type'], (int)$property['price'])) ?></p><p class="member-post-location">⌖ <?= e($property['location']) ?></p><?php if (($property['verification_status'] ?? '') === 'flagged' && !empty($property['verification_notes'])): ?><small class="member-review-note"><?= e($property['verification_notes']) ?></small><?php endif; ?><div class="member-post-footer"><small><?= e(t('ลงเมื่อ ', 'Posted on ')) . e(date('d/m/Y', strtotime($property['created_at']))) ?></small><span class="member-post-actions"><?php if ($property['approval'] === 'approved'): ?><a href="property.php?id=<?= (int)$property['id'] ?>"><?= e(t('ดูโพสต์', 'View post')) ?></a><?php endif; ?><a class="member-post-edit" href="edit-listing.php?id=<?= (int)$property['id'] ?>"><?= e(t('แก้ไข', 'Edit')) ?></a></span></div></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($myProjects): ?>
            <div id="my-projects" style="scroll-margin-top:90px;"></div>
            <div class="member-subsection-heading project-heading"><h3><?= e(t('โครงการของฉัน', 'My projects')) ?></h3><span><?= e(number_format(count($myProjects)) . t(' รายการ', ' items')) ?></span></div>
            <div class="member-post-grid">
              <?php foreach ($myProjects as $project): ?>
                <article class="member-post-card">
                  <div class="member-post-cover project-cover">
                    <?php if (!empty($project['cover']) && is_file(UPLOAD_DIR . basename($project['cover']))): ?><img src="uploads/<?= e(basename($project['cover'])) ?>" alt="<?= e($project['title']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">⌂</span><?php endif; ?>
                    <span class="member-post-type"><?= e(t('โครงการ', 'Project')) ?></span>
                  </div>
                  <div class="member-post-info"><div class="member-post-title-row"><h3><?= e($project['title']) ?></h3><span class="listing-status <?= (int)$project['is_published'] === 1 ? 'is-approved' : 'is-pending' ?>"><i></i><?= (int)$project['is_published'] === 1 ? e(t('เผยแพร่', 'Published')) : e(t('ฉบับร่าง', 'Draft')) ?></span></div><p class="member-post-location">⌖ <?= e($project['location']) ?><?= $project['category'] ? ' · ' . e($project['category']) : '' ?></p><div class="member-post-footer"><small><?= e(t('ลงเมื่อ ', 'Posted on ')) . e(date('d/m/Y', strtotime($project['created_at']))) ?></small><?php if ((int)$project['is_published'] === 1): ?><a href="project.php?id=<?= (int)$project['id'] ?>"><?= e(t('ดูโครงการ', 'View project')) ?> <span aria-hidden="true">→</span></a><?php endif; ?></div></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="member-account-grid" id="my-account">
        <div class="member-account-card">
          <div class="member-section-heading"><div><span class="member-section-eyebrow">YOUR PROFILE</span><h2><?= e(t('ข้อมูลส่วนตัว', 'Profile')) ?></h2><p><?= e(t('เลือกข้อมูลติดต่อที่ใช้กับโพสต์ของคุณ', 'Choose the contact details used with your posts.')) ?></p></div><?php if (!empty($memberProfile['avatar_path']) && is_file(__DIR__ . '/uploads/avatars/' . basename($memberProfile['avatar_path']))): ?><img class="member-account-avatar member-account-avatar-image" src="uploads/avatars/<?= e(basename($memberProfile['avatar_path'])) ?>" alt="<?= e(t('รูปโปรไฟล์', 'Profile photo')) ?>"><?php else: ?><span class="member-account-avatar"><?= e(mb_strtoupper(mb_substr($memberDisplayName, 0, 1))) ?></span><?php endif; ?></div>
          <?php if ($profileError !== ''): ?><div class="alert error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
          <?php if ($profileSuccess !== ''): ?><div class="alert success" role="status"><?= e($profileSuccess) ?></div><?php endif; ?>
          <form method="post" enctype="multipart/form-data" class="member-profile-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_profile"><label class="profile-wide-field"><?= e(t('รูปโปรไฟล์', 'Profile photo')) ?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG หรือ WebP ไม่เกิน 3 MB', 'JPG, PNG or WebP, max 3 MB')) ?></small></label><label><?= e(t('ชื่อที่แสดง', 'Display name')) ?><input name="full_name" required maxlength="120" value="<?= e($memberProfile['full_name']) ?>"></label><label><?= e(t('อีเมล', 'Email')) ?><input type="email" name="email" required maxlength="190" value="<?= e($memberProfile['email']) ?>"></label><label><?= e(t('เบอร์โทรศัพท์', 'Phone')) ?><input type="tel" name="phone" inputmode="numeric" maxlength="14" value="<?= e($memberProfile['phone']) ?>"></label><label>LINE ID<input name="line_id" maxlength="40" value="<?= e($memberProfile['line_id']) ?>"></label><div class="agent-application-box profile-wide-field"><strong><?= e(t('ขอเครื่องหมาย Agent ที่ผ่านการตรวจสอบ', 'Apply for a verified agent badge')) ?></strong><p><?= e(t('ส่งเลขใบอนุญาต ชื่อบริษัท/สังกัด และรูปหลักฐาน ผู้ดูแลจะตรวจสอบก่อนอนุมัติเครื่องหมาย', 'Submit your license number, company/agency name and evidence photo. An admin will review before approving the badge.')) ?></p><?php $agentStatus = $memberProfile['agent_status'] ?? 'none'; ?><span class="agent-status-label <?= e($agentStatus) ?>"><?= $agentStatus === 'verified' ? e(t('✓ รับรองแล้ว', '✓ Verified')) : ($agentStatus === 'pending' ? e(t('กำลังตรวจสอบหลักฐาน', 'Under review')) : ($agentStatus === 'rejected' ? e(t('ยังไม่ผ่านการตรวจ · แก้ข้อมูลแล้วส่งใหม่ได้', 'Not approved yet · You may update and resubmit')) : e(t('ยังไม่ได้ยื่นขอรับรอง', 'Not applied yet')))) ?></span><?php if ($agentStatus !== 'verified'): ?><label class="agent-apply-toggle"><input type="checkbox" name="agent_application" value="1" <?= $agentStatus === 'pending' ? 'checked' : '' ?>> <?= e(t('ส่ง/ส่งข้อมูล Agent เพื่อตรวจรับรอง', 'Submit agent details for verification')) ?></label><div class="agent-field-grid"><label><?= e(t('เลขใบอนุญาต/เลขทะเบียน', 'License/registration number')) ?><input name="agent_license" maxlength="100" value="<?= e($memberProfile['agent_license'] ?? '') ?>"></label><label><?= e(t('บริษัทหรือสังกัด', 'Company or agency')) ?><input name="agent_agency" maxlength="160" value="<?= e($memberProfile['agent_agency'] ?? '') ?>"></label><label class="profile-wide-field"><?= e(t('หลักฐานใบอนุญาตหรือบัตรตัวแทน', 'License document or agent card')) ?><input type="file" name="agent_document" accept="image/jpeg,image/png,image/webp"><small><?= e(t('รูป JPG, PNG หรือ WebP ไม่เกิน 3 MB', 'JPG, PNG or WebP image, max 3 MB')) ?><?= !empty($memberProfile['agent_document']) ? ' · ' . e(t('มีไฟล์เดิมแล้ว', 'previous file attached')) : '' ?></small></label></div><?php endif; ?></div><button type="submit">บันทึกโปรไฟล์</button></form>
        </div>
        <div class="member-account-card member-security-card" id="account-security" style="scroll-margin-top:90px;">
          <span class="member-section-eyebrow">ACCOUNT SECURITY</span><h2><?= e(t('ความปลอดภัยบัญชี', 'Account security')) ?></h2><p><?= e(t('เปลี่ยนรหัสผ่านได้ทุกเมื่อ เพื่อให้บัญชีของคุณปลอดภัย', 'Change your password anytime to keep your account secure.')) ?></p>
          <?php if ($passwordError !== ''): ?><div class="alert error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
          <?php if ($passwordSuccess !== ''): ?><div class="alert success" role="status"><?= e($passwordSuccess) ?></div><?php endif; ?>
          <form method="post" class="member-password-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="change_password"><label><?= e(t('รหัสผ่านปัจจุบัน', 'Current password')) ?><input type="password" name="current_password" autocomplete="current-password" required></label><label><?= e(t('รหัสผ่านใหม่', 'New password')) ?><input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label><label><?= e(t('ยืนยันรหัสผ่านใหม่', 'Confirm new password')) ?><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label><button type="submit"><?= e(t('เปลี่ยนรหัสผ่าน', 'Change password')) ?></button></form>
          <div class="member-account-meta"><span><?= e(t('สมาชิกตั้งแต่', 'Member since')) ?></span><strong><?= !empty($memberProfile['created_at']) ? e(date('d/m/Y', strtotime($memberProfile['created_at']))) : '—' ?></strong></div>
        </div>
      </section>

    <?php endif; ?>
  </div>
</main>

<script>
(function () {
  var toggle = document.getElementById('portalMenuToggle');
  var sidebar = document.getElementById('portalSidebar');
  var backdrop = document.getElementById('portalBackdrop');
  if (!toggle || !sidebar || !backdrop) return;
  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop.classList.toggle('active', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? '✕' : '☰';
    document.body.style.overflow = open ? 'hidden' : '';
  }
  toggle.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('open'));
  });
  backdrop.addEventListener('click', function () { setOpen(false); });
  sidebar.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
})();
</script>
</body>
</html>
