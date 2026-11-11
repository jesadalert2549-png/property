<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/profile_schema.php';
require_once __DIR__ . '/includes/profile_media.php';
require_once __DIR__ . '/includes/price_helpers.php';
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

$profileStatement = $pdo->prepare('SELECT id, username, email, full_name, bio, phone, line_id, avatar_path, agent_status, agent_license, agent_agency, agent_document, tier, max_listings, is_banned, ban_reason, created_at FROM users WHERE id = ? LIMIT 1');
$profileStatement->execute([(int)$_SESSION['user_id']]);
$memberProfile = $profileStatement->fetch() ?: ['username' => (string)($_SESSION['username'] ?? ''), 'email' => '', 'full_name' => '', 'bio' => '', 'phone' => '', 'line_id' => '', 'created_at' => null];

// หากบัญชีถูกระงับสิทธิ์ (Banned)
if (!$isOwner && !empty($memberProfile['is_banned'])) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = t('บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ', 'Your account has been suspended. Please contact support.');
    redirect('login.php');
}

if (!$isOwner && request_is_post() && ($_POST['action'] ?? '') === 'save_profile') {
    csrf_check();
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = preg_replace('/[\s-]/', '', trim((string)($_POST['phone'] ?? '')));
    $lineId = clean_line_id($_POST['line_id'] ?? '');
    $agentLicense = trim((string)($_POST['agent_license'] ?? ''));
    $agentAgency = trim((string)($_POST['agent_agency'] ?? ''));
    $agentIntent = ($_POST['agent_application'] ?? '') === '1';
    $newAvatar = '';
    $newDocument = '';
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) $profileError = t('กรุณากรอกชื่อที่แสดง 2–120 ตัวอักษร', 'Please enter a display name of 2-120 characters.');
    elseif (mb_strlen($bio) > 500) $profileError = t('ประวัติส่วนตัวต้องไม่เกิน 500 ตัวอักษร', 'Bio must not exceed 500 characters.');
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
            $saveProfile = $pdo->prepare('UPDATE users SET full_name = ?, bio = ?, email = ?, phone = ?, line_id = ?, avatar_path = ?, agent_status = ?, agent_license = ?, agent_agency = ?, agent_document = ? WHERE id = ?');
            $saveProfile->execute([$fullName, $bio, $email, $phone, $lineId, $avatarPath, $agentStatus, $agentIntent ? $agentLicense : ($memberProfile['agent_license'] ?? ''), $agentIntent ? $agentAgency : ($memberProfile['agent_agency'] ?? ''), $agentDocument, (int)$_SESSION['user_id']]);
            if ($newAvatar !== '') delete_profile_image((string)($memberProfile['avatar_path'] ?? ''), 'avatars');
            if ($newDocument !== '') delete_profile_image((string)($memberProfile['agent_document'] ?? ''), 'agent-documents');
            $memberProfile = array_merge($memberProfile, ['full_name' => $fullName, 'bio' => $bio, 'email' => $email, 'phone' => $phone, 'line_id' => $lineId, 'avatar_path' => $avatarPath, 'agent_status' => $agentStatus, 'agent_license' => $agentLicense, 'agent_agency' => $agentAgency, 'agent_document' => $agentDocument]);
            $profileSuccess = $agentIntent ? t('ส่งข้อมูลขอรับรอง Agent แล้ว รอผู้ดูแลตรวจหลักฐาน', 'Agent verification request submitted — awaiting admin review.') : t('บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว', 'Profile saved successfully.');
        }
    }
    if ($profileError !== '') { if ($newAvatar !== '') delete_profile_image($newAvatar, 'avatars'); if ($newDocument !== '') delete_profile_image($newDocument, 'agent-documents'); $memberProfile = array_merge($memberProfile, ['full_name' => $fullName, 'bio' => $bio, 'email' => $email, 'phone' => $phone, 'line_id' => $lineId]); }
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

// ลบประกาศของตนเอง (Member Delete Listing)
if (!$isOwner && request_is_post() && ($_POST['action'] ?? '') === 'delete_property') {
    csrf_check();
    $deletePropertyId = (int)($_POST['property_id'] ?? 0);
    if ($deletePropertyId > 0) {
        $stCheck = $pdo->prepare('SELECT id FROM properties WHERE id = ? AND user_id = ? LIMIT 1');
        $stCheck->execute([$deletePropertyId, (int)$_SESSION['user_id']]);
        if ($stCheck->fetchColumn()) {
            $imgs = $pdo->prepare('SELECT filename FROM property_images WHERE property_id = ?');
            $imgs->execute([$deletePropertyId]);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $fName) {
                delete_image_file($fName);
            }
            $pdo->prepare('DELETE FROM properties WHERE id = ? AND user_id = ?')->execute([$deletePropertyId, (int)$_SESSION['user_id']]);
            $profileSuccess = t('ลบประกาศเรียบร้อยแล้ว', 'Listing deleted successfully.');
        } else {
            $profileError = t('ไม่พบประกาศ หรือคุณไม่มีสิทธิ์ลบประกาศนี้', 'Listing not found or you do not have permission.');
        }
    }
}

if ($isOwner) {
    $ownerSummary = [
        'listings' => (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn(),
        'pending' => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'pending'")->fetchColumn(),
        'published' => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'approved'")->fetchColumn(),
        'visitors' => (int)$pdo->query('SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn(),
    ];
}

$statement = $pdo->prepare(
    'SELECT p.id, p.title, p.type, p.status, p.approval, p.verification_status, p.verification_notes, p.price, p.original_price, p.views_count, p.clicks_count, p.location, p.created_at, p.video_file,
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

$favCountStmt = $pdo->prepare('SELECT COUNT(*) FROM property_favorites WHERE user_id = ?');
$favCountStmt->execute([(int)$_SESSION['user_id']]);
$myFavCount = (int)$favCountStmt->fetchColumn();

$favItemsStmt = $pdo->prepare(
    "SELECT p.id, p.title, p.type, p.price, p.original_price, p.location, p.created_at, p.views_count, p.clicks_count, p.video_file,
            (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
     FROM property_favorites f
     JOIN properties p ON p.id = f.property_id
     WHERE f.user_id = ? AND p.approval = 'approved'
     ORDER BY f.id DESC LIMIT 30"
);
$favItemsStmt->execute([(int)$_SESSION['user_id']]);
$myFavoriteProperties = $favItemsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalViews = 0;
$totalClicks = 0;
$videoProperties = [];
foreach ($myProperties as $p) {
    $totalViews += (int)($p['views_count'] ?? 0);
    $totalClicks += (int)($p['clicks_count'] ?? 0);
    if (!empty($p['video_file'])) {
        $videoProperties[] = $p;
    }
}
$memberDisplayName = trim((string)($memberProfile['full_name'] ?? '')) ?: (string)$memberProfile['username'];

$avatarFile = basename((string)($memberProfile['avatar_path'] ?? ''));
$hasAvatar = $avatarFile !== '' && is_file(__DIR__ . '/uploads/avatars/' . $avatarFile);
$memberInitial = mb_strtoupper(mb_substr($memberDisplayName, 0, 1));
$agentStatus = $memberProfile['agent_status'] ?? 'none';
$pageTitle = ($memberDisplayName) . ' (@' . $memberProfile['username'] . ') | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/member-portal.css?v=<?= e((string)filemtime(__DIR__ . '/css/member-portal.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
</head>
<body class="member-portal-body member-theme-light">

  <!-- ══ Toast Notification Banner ══ -->
  <div class="tt-toast" id="ttToast">
    <span class="tt-toast-icon">✓</span>
    <span class="tt-toast-msg" id="ttToastMsg">ดำเนินการสำเร็จ</span>
  </div>

  <!-- ══ Top Main Navigation Header (Clean, Luxury, Stable Across All Screens) ══ -->
  <header class="tt-app-header">
    <div class="tt-app-header-inner">
      <div class="tt-header-left">
        <?= render_logo_html('index.php', 'kasa-logo--light') ?>
        <span class="tt-brand-badge"><?= $isOwner ? 'ADMIN' : 'MEMBER' ?></span>
      </div>

      <div class="tt-header-search">
        <form action="search.php" method="get">
          <div class="tt-search-input-wrap">
            <svg class="tt-search-icon" viewBox="0 0 24 24"><path d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.39a1 1 0 0 1-1.42 1.42l-5.38-5.39A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/></svg>
            <input type="text" name="q" class="tt-search-input" placeholder="<?= e(t('ค้นหาบ้าน คอนโด วิดีโอ...', 'Search properties, reels...')) ?>">
          </div>
        </form>
      </div>

      <div class="tt-header-right">
        <a href="submit-reel.php" class="tt-btn-brand tt-btn-gold tt-btn-sm tt-header-btn-reel">
          🔥 <?= e(t('ลงคลิป', 'Reel')) ?>
        </a>
        <a href="submit.php" class="tt-btn-brand tt-btn-navy tt-btn-sm tt-header-btn-post">
          + <?= e(t('ประกาศ', 'Post')) ?>
        </a>
        <?php if ($isOwner): ?>
          <a href="admin/index.php" class="tt-btn-brand tt-btn-dark tt-btn-sm" style="color:#F8D888;">
            ⚙️ <?= e(t('หลังบ้าน', 'Admin')) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- ══ Main Layout with Left Sidebar + Workspace ══ -->
  <div class="tt-app-layout">
    
    <!-- ══ Left Sidebar ══ -->
    <aside class="tt-sidebar">
      <ul class="tt-nav-list">
        <li>
          <a href="reels.php" class="tt-nav-item">
            <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v12h16V6H4zm6 2.5l6 3.5-6 3.5V8.5z"/></svg>
            <span><?= e(t('Reels สำรวจ', 'Explore Reels')) ?></span>
          </a>
        </li>
        <li>
          <a href="#tab-listings" class="tt-nav-item active" data-tab-nav="tab-listings" onclick="switchTab('tab-listings')">
            <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4zm0 9h7v7H4v-7zm9-9h7v7h-7V4zm0 9h7v7h-7v-7z"/></svg>
            <span><?= e(t('ประกาศของฉัน', 'My Listings')) ?></span>
            <span class="tt-nav-badge"><?= count($myProperties) ?></span>
          </a>
        </li>
        <li>
          <a href="#tab-videos" class="tt-nav-item" data-tab-nav="tab-videos" onclick="switchTab('tab-videos')">
            <svg viewBox="0 0 24 24"><path d="M17 10.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5l4 4v-11l-4 4z"/></svg>
            <span><?= e(t('วิดีโอ & Reels', 'My Videos')) ?></span>
            <span class="tt-nav-badge" style="background:rgba(197,160,89,0.25); color:#F8D888;"><?= count($videoProperties) ?></span>
          </a>
        </li>
        <li>
          <a href="#tab-projects" class="tt-nav-item" data-tab-nav="tab-projects" onclick="switchTab('tab-projects')">
            <svg viewBox="0 0 24 24"><path d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"/></svg>
            <span><?= e(t('โครงการของฉัน', 'My Projects')) ?></span>
            <span class="tt-nav-badge"><?= count($myProjects) ?></span>
          </a>
        </li>
        <li>
          <a href="#tab-favorites" class="tt-nav-item" data-tab-nav="tab-favorites" onclick="switchTab('tab-favorites')">
            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            <span><?= e(t('รายการโปรด', 'Saved Favorites')) ?></span>
            <span class="tt-nav-badge" style="background:rgba(239,68,68,0.2); color:#f87171;"><?= $myFavCount ?></span>
          </a>
        </li>
        <li>
          <a href="#tab-profile" class="tt-nav-item" data-tab-nav="tab-profile" onclick="switchTab('tab-profile')">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            <span><?= e(t('โปรไฟล์ & ตั้งค่า', 'Profile & Settings')) ?></span>
          </a>
        </li>
      </ul>

      <div class="tt-sidebar-divider"></div>

      <ul class="tt-nav-list">
        <li>
          <a href="index.php" class="tt-nav-item" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
            <span><?= e(t('ดูหน้าเว็บไซต์', 'View Website')) ?></span>
          </a>
        </li>
        <li>
          <a href="member.php?id=<?= (int)$_SESSION['user_id'] ?>" class="tt-nav-item" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            <span><?= e(t('โปรไฟล์สาธารณะ', 'Public View')) ?></span>
          </a>
        </li>
      </ul>

      <div class="tt-sidebar-bottom-actions">
        <a href="logout.php" class="tt-btn-brand tt-btn-ghost">
          <span>⇥ <?= e(t('ออกจากระบบ', 'Log out')) ?></span>
        </a>
      </div>
    </aside>

    <!-- ══ Main Content Workspace ══ -->
    <main class="tt-main-workspace">
      <div class="tt-profile-hero">

        <!-- Alerts -->
        <?php if ($profileError !== '' || $profileSuccess !== '' || $passwordError !== '' || $passwordSuccess !== ''): ?>
          <div style="margin-bottom:16px;">
            <?php if ($profileError !== ''): ?><div class="alert error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
            <?php if ($profileSuccess !== ''): ?><div class="alert success" role="status"><?= e($profileSuccess) ?></div><?php endif; ?>
            <?php if ($passwordError !== ''): ?><div class="alert error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
            <?php if ($passwordSuccess !== ''): ?><div class="alert success" role="status"><?= e($passwordSuccess) ?></div><?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Hidden One-Click Direct Photo Input -->
        <form id="directAvatarForm" style="display:none;" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="file" id="directAvatarInput" name="avatar" accept="image/jpeg,image/png,image/webp">
        </form>

        <!-- ══ Profile Hero Header (Ultra Compact App Header: Avatar on left, Stats on right) ══ -->
        <section class="tt-hero-top">
          <div class="tt-hero-main-row">
            <div class="tt-hero-avatar-box" id="avatarBoxTrigger" onclick="triggerDirectAvatarUpload()" title="<?= e(t('แตะเพื่อเปลี่ยนรูปโปรไฟล์ทันที', 'Tap to change photo')) ?>">
              <?php if ($hasAvatar): ?>
                <img id="headerAvatarImg" src="uploads/avatars/<?= e($avatarFile) ?>" alt="<?= e($memberDisplayName) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <div class="tt-hero-avatar-fallback" id="headerAvatarFallback"><?= e($memberInitial) ?></div>
              <?php endif; ?>
              <div class="tt-avatar-cam-badge">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M4 4h3l2-2h6l2 2h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm8 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
              </div>
              <div class="tt-avatar-uploading-spinner" id="avatarSpinner">
                <div class="tt-spinner-ring"></div>
              </div>
            </div>

            <div class="tt-hero-meta-col">
              <div class="tt-hero-title-row">
                <h1 class="tt-user-title"><?= e($memberDisplayName) ?></h1>
                <span class="tt-hero-handle"><?= e('@' . ($memberProfile['username'] ?: 'user' . $_SESSION['user_id'])) ?></span>
                <?php if (($memberProfile['agent_status'] ?? '') === 'verified'): ?>
                  <span class="agent-verified-badge" title="<?= e(t('ผู้ดูแลตรวจสอบหลักฐาน Agent แล้ว', 'Agent credentials verified by admin')) ?>">
                    <?= e(t('✓ รับรอง', '✓ Verified')) ?>
                  </span>
                <?php endif; ?>
              </div>

              <!-- Stats Row (Number above label) -->
              <div class="tt-hero-stats">
                <div class="tt-stat-col" onclick="switchTab('tab-listings')">
                  <strong><?= count($myProperties) ?></strong>
                  <span><?= e(t('ประกาศ', 'Listings')) ?></span>
                </div>
                <div class="tt-stat-col" onclick="switchTab('tab-videos')">
                  <strong><?= count($videoProperties) ?></strong>
                  <span><?= e(t('Reels', 'Reels')) ?></span>
                </div>
                <div class="tt-stat-col">
                  <strong><?= number_format($totalViews) ?></strong>
                  <span><?= e(t('เข้าชม', 'Views')) ?></span>
                </div>
                <div class="tt-stat-col" onclick="switchTab('tab-favorites')">
                  <strong><?= number_format($myFavCount) ?></strong>
                  <span><?= e(t('ถูกใจ', 'Saved')) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Buttons Bar (Instant access without scrolling) -->
          <div class="tt-hero-btn-row">
            <button type="button" class="tt-action-btn tt-btn-gold" onclick="switchTab('tab-profile')">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41l-2.34-2.34a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              <span><?= e(t('แก้ไขข้อมูล', 'Edit Profile')) ?></span>
            </button>
            <a href="submit-reel.php" class="tt-action-btn tt-btn-navy">
              <span>🔥 <?= e(t('ลง Reels', 'Post Reel')) ?></span>
            </a>
            <a href="submit.php" class="tt-action-btn tt-btn-dark">
              <span>+ <?= e(t('ลงประกาศ', 'Post')) ?></span>
            </a>
            <button type="button" class="tt-action-btn-icon" onclick="switchTab('tab-profile', true)" title="<?= e(t('ความปลอดภัย & รหัสผ่าน', 'Security Settings')) ?>" aria-label="<?= e(t('ความปลอดภัย', 'Security')) ?>">
              <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            </button>
            <a href="member.php?id=<?= (int)$_SESSION['user_id'] ?>" class="tt-action-btn-icon" target="_blank" rel="noopener" title="<?= e(t('ดูหน้าโปรไฟล์สาธารณะ', 'View Public Profile')) ?>" aria-label="<?= e(t('โปรไฟล์สาธารณะ', 'Public Profile')) ?>">
              <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
            </a>
          </div>
        </section>

        <!-- ══ Tabs Navigation Bar (Full width swipeable tabs) ══ -->
        <nav class="tt-tabs-row" role="tablist">
          <div class="tt-tabs-nav">
            <button type="button" class="tt-tab-button active" data-tab-target="tab-listings" onclick="switchTab('tab-listings')">
              <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4zm0 9h7v7H4v-7zm9-9h7v7h-7V4zm0 9h7v7h-7v-7z"/></svg>
              <span><?= e(t('ประกาศ', 'Listings')) ?></span>
              <span class="tt-tab-badge"><?= count($myProperties) ?></span>
            </button>
            <button type="button" class="tt-tab-button" data-tab-target="tab-videos" onclick="switchTab('tab-videos')">
              <svg viewBox="0 0 24 24"><path d="M17 10.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5l4 4v-11l-4 4z"/></svg>
              <span><?= e(t('Reels', 'Reels')) ?></span>
              <span class="tt-tab-badge"><?= count($videoProperties) ?></span>
            </button>
            <button type="button" class="tt-tab-button" data-tab-target="tab-projects" onclick="switchTab('tab-projects')">
              <svg viewBox="0 0 24 24"><path d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"/></svg>
              <span><?= e(t('โครงการ', 'Projects')) ?></span>
              <span class="tt-tab-badge"><?= count($myProjects) ?></span>
            </button>
            <button type="button" class="tt-tab-button" data-tab-target="tab-favorites" onclick="switchTab('tab-favorites')">
              <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
              <span><?= e(t('รายการโปรด', 'Favorites')) ?></span>
              <span class="tt-tab-badge"><?= $myFavCount ?></span>
            </button>
            <button type="button" class="tt-tab-button" data-tab-target="tab-profile" onclick="switchTab('tab-profile')">
              <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
              <span><?= e(t('ตั้งค่า', 'Settings')) ?></span>
            </button>
          </div>

          <div class="tt-tabs-sort">
            <button type="button" class="tt-sort-pill active" data-sort="latest" onclick="sortActiveCards('latest', this)"><?= e(t('ล่าสุด', 'Latest')) ?></button>
            <button type="button" class="tt-sort-pill" data-sort="popular" onclick="sortActiveCards('popular', this)"><?= e(t('ยอดนิยม', 'Popular')) ?></button>
            <button type="button" class="tt-sort-pill" data-sort="price" onclick="sortActiveCards('price', this)"><?= e(t('ราคา', 'Price')) ?></button>
          </div>
        </nav>

                <!-- ══ Tab Pane 1: ประกาศทั้งหมด (All Listings) ══ -->
        <div class="tt-tab-pane active" id="tab-listings">
          <?php if (!$myProperties): ?>
            <div class="tt-empty-feed">
              <div class="tt-empty-circle">
                <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4zm0 9h7v7H4v-7zm9-9h7v7h-7V4zm0 9h7v7h-7v-7z"/></svg>
              </div>
              <h3 class="tt-empty-title"><?= e(t('อัปโหลดประกาศแรกของคุณ', 'Upload your first listing')) ?></h3>
              <p class="tt-empty-desc"><?= e(t('ประกาศของคุณจะแสดงบนหน้าโปรไฟล์และในผลการค้นหาของผู้ซื้อทันที', 'Your listings will appear on your public profile and in buyer search feeds.')) ?></p>
              <div class="tt-empty-actions">
                <a href="submit-reel.php" class="tt-action-btn tt-btn-gold">🔥 <?= e(t('ลงคลิป Reels ด่วน (30 วิ)', 'Quick Reel (30s)')) ?></a>
                <a href="submit.php" class="tt-action-btn tt-btn-navy">+ <?= e(t('ลงประกาศทรัพย์เต็มรูปแบบ', 'Post full listing')) ?></a>
              </div>
            </div>
          <?php else: ?>
            <div class="tt-video-grid" id="grid-listings">
              <?php foreach ($myProperties as $property): ?>
                <div class="tt-video-card" data-views="<?= (int)($property['views_count'] ?? 0) ?>" data-price="<?= (int)$property['price'] ?>" data-created="<?= strtotime($property['created_at']) ?>">
                  <a href="<?= $property['approval'] === 'approved' ? 'property.php?id=' . (int)$property['id'] : 'edit-listing.php?id=' . (int)$property['id'] ?>" class="tt-card-tile-link">
                    <div class="tt-video-media-wrap">
                      <?php 
                        $hasCover = !empty($property['cover']) && is_file(UPLOAD_DIR . basename($property['cover']));
                        $hasVideo = !empty($property['video_file']) && is_file(UPLOAD_DIR . basename($property['video_file']));
                        $videoSrc = $hasVideo ? 'uploads/' . rawurlencode(basename($property['video_file'])) : '';
                      ?>
                      <?php if ($hasCover): ?>
                        <img src="uploads/<?= e(basename($property['cover'])) ?>" alt="<?= e($property['title']) ?>" loading="lazy" decoding="async">
                      <?php elseif ($hasVideo): ?>
                        <video class="tt-card-video-element" src="<?= e($videoSrc) ?>" autoplay loop muted playsinline preload="metadata"></video>
                      <?php else: ?>
                        <div class="tt-card-empty-bg">⌂</div>
                      <?php endif; ?>

                      <!-- Badges -->
                      <span class="tt-video-badge">
                        <?= e($property['type'] === 'rent' ? t('เช่า', 'Rent') : t('ขาย', 'Sale')) ?>
                      </span>

                      <!-- Bottom info overlay -->
                      <div class="tt-tile-overlay">
                        <div class="tt-tile-price">฿<?= number_format((int)$property['price']) ?></div>
                        <div class="tt-tile-meta">
                          <span class="tt-tile-views">▶ <?= number_format((int)($property['views_count'] ?? 0)) ?></span>
                          <span class="tt-tile-title"><?= e($property['title']) ?></span>
                        </div>
                      </div>

                      <?php if (!$hasCover && $hasVideo): ?>
                        <button type="button" class="tt-video-sound-btn" onclick="toggleCardVideoSound(event, this)" title="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>" aria-label="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>">
                          <svg class="sound-icon-muted" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                          <svg class="sound-icon-unmuted" style="display:none;" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                        </button>
                      <?php endif; ?>
                    </div>
                  </a>

                  <!-- Floating Quick Action Buttons on Tile -->
                  <div class="tt-tile-floating-actions">
                    <a href="edit-listing.php?id=<?= (int)$property['id'] ?>" class="tt-tile-btn edit" title="<?= e(t('แก้ไข', 'Edit')) ?>" aria-label="<?= e(t('แก้ไข', 'Edit')) ?>">
                      ✏️
                    </a>
                    <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('<?= e(t('คุณแน่ใจหรือไม่ว่าต้องการลบประกาศนี้?', 'Delete this listing?')) ?>');">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="delete_property">
                      <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                      <button type="submit" class="tt-tile-btn delete" title="<?= e(t('ลบ', 'Delete')) ?>" aria-label="<?= e(t('ลบ', 'Delete')) ?>">
                        🗑️
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

                <!-- ══ Tab Pane 2: วิดีโอ Reels ══ -->
        <div class="tt-tab-pane" id="tab-videos">
          <?php if (!$videoProperties): ?>
            <div class="tt-empty-feed">
              <div class="tt-empty-circle">
                <svg viewBox="0 0 24 24"><path d="M17 10.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5l4 4v-11l-4 4z"/></svg>
              </div>
              <h3 class="tt-empty-title"><?= e(t('อัปโหลดคลิป Reels สั้นของคุณ', 'Upload your first short reel')) ?></h3>
              <p class="tt-empty-desc"><?= e(t('คลิปวิดีโอสั้นช่วยดึงดูดผู้สนใจได้มากขึ้น 3 เท่า และจะเล่นบนหน้าฟีด Reels ของเว็บไซต์', 'Short video tours attract 3x more views and stream directly on the Reels feed.')) ?></p>
              <div class="tt-empty-actions">
                <a href="submit-reel.php" class="tt-action-btn tt-btn-gold">🔥 <?= e(t('ลงคลิป Reels ด่วน (30 วิ)', 'Quick Reel (30s)')) ?></a>
              </div>
            </div>
          <?php else: ?>
            <div class="tt-video-grid" id="grid-videos">
              <?php foreach ($videoProperties as $property): ?>
                <div class="tt-video-card" data-views="<?= (int)($property['views_count'] ?? 0) ?>" data-price="<?= (int)$property['price'] ?>" data-created="<?= strtotime($property['created_at']) ?>">
                  <a href="reels.php" class="tt-card-tile-link" target="_blank" rel="noopener">
                    <div class="tt-video-media-wrap">
                      <?php 
                        $hasCover = !empty($property['cover']) && is_file(UPLOAD_DIR . basename($property['cover']));
                        $hasVideo = !empty($property['video_file']) && is_file(UPLOAD_DIR . basename($property['video_file']));
                        $videoSrc = $hasVideo ? 'uploads/' . rawurlencode(basename($property['video_file'])) : '';
                      ?>
                      <?php if ($hasCover): ?>
                        <img src="uploads/<?= e(basename($property['cover'])) ?>" alt="<?= e($property['title']) ?>" loading="lazy" decoding="async">
                      <?php elseif ($hasVideo): ?>
                        <video class="tt-card-video-element" src="<?= e($videoSrc) ?>" autoplay loop muted playsinline preload="metadata"></video>
                      <?php else: ?>
                        <div class="tt-card-empty-bg">🎬</div>
                      <?php endif; ?>

                      <span class="tt-video-badge reel">▶ REEL</span>

                      <!-- Bottom info overlay -->
                      <div class="tt-tile-overlay">
                        <div class="tt-tile-price">฿<?= number_format((int)$property['price']) ?></div>
                        <div class="tt-tile-meta">
                          <span class="tt-tile-views">▶ <?= number_format((int)($property['views_count'] ?? 0)) ?></span>
                          <span class="tt-tile-title"><?= e($property['title']) ?></span>
                        </div>
                      </div>

                      <?php if (!$hasCover && $hasVideo): ?>
                        <button type="button" class="tt-video-sound-btn" onclick="toggleCardVideoSound(event, this)" title="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>" aria-label="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>">
                          <svg class="sound-icon-muted" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                          <svg class="sound-icon-unmuted" style="display:none;" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                        </button>
                      <?php endif; ?>
                    </div>
                  </a>

                  <!-- Floating Quick Edit Button on Tile -->
                  <div class="tt-tile-floating-actions">
                    <a href="edit-listing.php?id=<?= (int)$property['id'] ?>" class="tt-tile-btn edit" title="<?= e(t('แก้ไข', 'Edit')) ?>" aria-label="<?= e(t('แก้ไข', 'Edit')) ?>">
                      ✏️
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

                <!-- ══ Tab Pane 3: โครงการของฉัน ══ -->
        <div class="tt-tab-pane" id="tab-projects">
          <?php if (!$myProjects): ?>
            <div class="tt-empty-feed">
              <div class="tt-empty-circle">
                <svg viewBox="0 0 24 24"><path d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"/></svg>
              </div>
              <h3 class="tt-empty-title"><?= e(t('ส่งโครงการพัฒนาอสังหาฯ ของคุณ', 'Submit development project')) ?></h3>
              <p class="tt-empty-desc"><?= e(t('เพิ่มโครงการบ้าน คอนโด หรือทาวน์โฮมเพื่อเข้าถึงกลุ่มผู้ซื้อระดับพรีเมียม', 'Showcase your developments to premium buyers.')) ?></p>
              <div class="tt-empty-actions">
                <a href="submit-project.php" class="tt-action-btn tt-btn-gold">+ <?= e(t('ส่งโครงการใหม่', 'Submit project')) ?></a>
              </div>
            </div>
          <?php else: ?>
            <div class="tt-video-grid" id="grid-projects">
              <?php foreach ($myProjects as $project): ?>
                <div class="tt-video-card" data-views="0" data-price="0" data-created="<?= strtotime($project['created_at']) ?>">
                  <a href="<?= (int)$project['is_published'] === 1 ? 'project.php?id=' . (int)$project['id'] : '#' ?>" class="tt-card-tile-link" target="_blank" rel="noopener">
                    <div class="tt-video-media-wrap">
                      <?php if (!empty($project['cover']) && is_file(UPLOAD_DIR . basename($project['cover']))): ?>
                        <img src="uploads/<?= e(basename($project['cover'])) ?>" alt="<?= e($project['title']) ?>" loading="lazy" decoding="async">
                      <?php else: ?>
                        <div class="tt-card-empty-bg">🏢</div>
                      <?php endif; ?>

                      <span class="tt-video-badge"><?= e($project['category'] ?: t('โครงการ', 'Project')) ?></span>

                      <!-- Bottom info overlay -->
                      <div class="tt-tile-overlay">
                        <div class="tt-tile-price" style="font-size:12px;color:#F8D888;">⌖ <?= e($project['location']) ?></div>
                        <div class="tt-tile-meta">
                          <span class="tt-tile-title"><?= e($project['title']) ?></span>
                        </div>
                      </div>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

                <!-- ══ Tab Pane 4: รายการโปรด ══ -->
        <div class="tt-tab-pane" id="tab-favorites">
          <?php if (!$myFavoriteProperties): ?>
            <div class="tt-empty-feed">
              <div class="tt-empty-circle">
                <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
              </div>
              <h3 class="tt-empty-title"><?= e(t('ยังไม่มีทรัพย์ที่บันทึกไว้ในรายการโปรด', 'No saved favorites yet')) ?></h3>
              <p class="tt-empty-desc"><?= e(t('เมื่อคุณกดไอคอนหัวใจ ❤️ บนประกาศใด ทรัพย์นั้นจะถูกบันทึกมาไว้ที่นี่ทันที', 'When you tap heart ❤️ on any listing, it appears here.')) ?></p>
              <div class="tt-empty-actions">
                <a href="search.php" class="tt-action-btn tt-btn-gold">🔍 <?= e(t('สำรวจอสังหาริมทรัพย์', 'Explore listings')) ?></a>
              </div>
            </div>
          <?php else: ?>
            <div class="tt-video-grid" id="grid-favorites">
              <?php foreach ($myFavoriteProperties as $fav): ?>
                <div class="tt-video-card" data-views="<?= (int)($fav['views_count'] ?? 0) ?>" data-price="<?= (int)$fav['price'] ?>" data-created="<?= strtotime($fav['created_at']) ?>">
                  <a href="property.php?id=<?= (int)$fav['id'] ?>" class="tt-card-tile-link" target="_blank" rel="noopener">
                    <div class="tt-video-media-wrap">
                      <?php 
                        $hasCover = !empty($fav['cover']) && is_file(UPLOAD_DIR . basename($fav['cover']));
                        $hasVideo = !empty($fav['video_file']) && is_file(UPLOAD_DIR . basename($fav['video_file']));
                        $videoSrc = $hasVideo ? 'uploads/' . rawurlencode(basename($fav['video_file'])) : '';
                      ?>
                      <?php if ($hasCover): ?>
                        <img src="uploads/<?= e(basename($fav['cover'])) ?>" alt="<?= e($fav['title']) ?>" loading="lazy" decoding="async">
                      <?php elseif ($hasVideo): ?>
                        <video class="tt-card-video-element" src="<?= e($videoSrc) ?>" autoplay loop muted playsinline preload="metadata"></video>
                      <?php else: ?>
                        <div class="tt-card-empty-bg">⌂</div>
                      <?php endif; ?>

                      <span class="tt-video-badge" style="background:#dc2626;color:#fff;">❤️ SAVED</span>

                      <!-- Bottom info overlay -->
                      <div class="tt-tile-overlay">
                        <div class="tt-tile-price">฿<?= number_format((int)$fav['price']) ?></div>
                        <div class="tt-tile-meta">
                          <span class="tt-tile-title"><?= e($fav['title']) ?></span>
                        </div>
                      </div>

                      <?php if (!$hasCover && $hasVideo): ?>
                        <button type="button" class="tt-video-sound-btn" onclick="toggleCardVideoSound(event, this)" title="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>" aria-label="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>">
                          <svg class="sound-icon-muted" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                          <svg class="sound-icon-unmuted" style="display:none;" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                        </button>
                      <?php endif; ?>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- ══ Tab Pane 5: ข้อมูลส่วนตัว & ความปลอดภัย ══ -->
        <div class="tt-tab-pane" id="tab-profile">
          <div class="tt-dark-form-card">
            <h2><?= e(t('แก้ไขข้อมูลส่วนตัว & ประวัติ', 'Edit Profile & Bio')) ?></h2>
            <p><?= e(t('ข้อมูลนี้จะแสดงต่อสาธารณะบนหน้าโปรไฟล์ของคุณ', 'This information will be displayed publicly on your profile.')) ?></p>

            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="save_profile">

              <div class="tt-dark-form-grid">
                <div class="tt-dark-field full">
                  <label><?= e(t('รูปโปรไฟล์', 'Profile photo')) ?></label>
                  <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" class="tt-action-btn tt-btn-gold" onclick="triggerDirectAvatarUpload()">
                      📷 <?= e(t('เลือกรูปภาพใหม่ทันที', 'Choose Photo Instantly')) ?>
                    </button>
                    <span style="color:#94A3B8;font-size:12px;"><?= e(t('กดเลือกรูปแล้วระบบจะเปลี่ยนให้อัตโนมัติทันที', 'Auto-updates instantly')) ?></span>
                  </div>
                </div>

                <div class="tt-dark-field">
                  <label><?= e(t('ชื่อที่แสดง', 'Display name')) ?> *</label>
                  <input type="text" name="full_name" class="tt-dark-input" required maxlength="120" value="<?= e($memberProfile['full_name']) ?>">
                </div>

                <div class="tt-dark-field">
                  <label><?= e(t('อีเมล', 'Email')) ?> *</label>
                  <input type="email" name="email" class="tt-dark-input" required maxlength="190" value="<?= e($memberProfile['email']) ?>">
                </div>

                <div class="tt-dark-field full">
                  <label><?= e(t('ประวัติย่อ / คำแนะนำตัว (Bio)', 'Bio')) ?></label>
                  <textarea name="bio" id="profileBioInput" class="tt-dark-textarea" maxlength="500" placeholder="<?= e(t('เขียนแนะนำตัวสั้นๆ เช่น ตัวแทนอสังหาริมทรัพย์มืออาชีพ...', 'Short bio...')) ?>"><?= e($memberProfile['bio'] ?? '') ?></textarea>
                </div>

                <div class="tt-dark-field">
                  <label><?= e(t('เบอร์โทรศัพท์', 'Phone')) ?></label>
                  <input type="tel" name="phone" class="tt-dark-input" inputmode="numeric" maxlength="14" value="<?= e($memberProfile['phone']) ?>">
                </div>

                <div class="tt-dark-field">
                  <label>LINE ID</label>
                  <input type="text" name="line_id" class="tt-dark-input" maxlength="40" value="<?= e($memberProfile['line_id']) ?>">
                </div>

                <div class="tt-dark-field full" style="margin-top:8px;">
                  <button type="submit" class="tt-action-btn tt-btn-gold" style="height:44px;padding:0 28px;font-size:14.5px;">
                    💾 <?= e(t('บันทึกข้อมูลส่วนตัว', 'Save Changes')) ?>
                  </button>
                </div>
              </div>
            </form>
          </div>

          <div class="tt-dark-form-card" id="account-security" style="margin-top:20px;">
            <h2><?= e(t('ความปลอดภัย & เปลี่ยนรหัสผ่าน', 'Account Security & Password')) ?></h2>
            <p><?= e(t('เปลี่ยนรหัสผ่านเพื่อรักษาความปลอดภัยของบัญชี', 'Change your password anytime to keep your account safe.')) ?></p>

            <form method="post">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="change_password">

              <div class="tt-dark-form-grid">
                <div class="tt-dark-field full">
                  <label><?= e(t('รหัสผ่านปัจจุบัน', 'Current password')) ?></label>
                  <input type="password" name="current_password" class="tt-dark-input" required autocomplete="current-password">
                </div>
                <div class="tt-dark-field">
                  <label><?= e(t('รหัสผ่านใหม่', 'New password')) ?></label>
                  <input type="password" name="new_password" class="tt-dark-input" minlength="8" required autocomplete="new-password">
                </div>
                <div class="tt-dark-field">
                  <label><?= e(t('ยืนยันรหัสผ่านใหม่', 'Confirm new password')) ?></label>
                  <input type="password" name="confirm_password" class="tt-dark-input" minlength="8" required autocomplete="new-password">
                </div>
                <div class="tt-dark-field full" style="margin-top:8px;">
                  <button type="submit" class="tt-action-btn tt-btn-gold" style="height:42px;padding:0 24px;">
                    🔒 <?= e(t('เปลี่ยนรหัสผ่าน', 'Change Password')) ?>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
    </main>

  </div>

  <!-- ══ Mobile / iPhone / iPad Bottom Navigation Bar ══ -->
  <nav class="tt-mobile-bottom-nav">
    <a href="reels.php" class="tt-bottom-item">
      <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v12h16V6H4zm6 2.5l6 3.5-6 3.5V8.5z"/></svg>
      <span><?= e(t('Reels', 'Reels')) ?></span>
    </a>
    <button type="button" class="tt-bottom-item active" data-bottom-nav="tab-listings" onclick="switchTab('tab-listings')">
      <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4zm0 9h7v7H4v-7zm9-9h7v7h-7V4zm0 9h7v7h-7v-7z"/></svg>
      <span><?= e(t('ประกาศ', 'Listings')) ?></span>
    </button>
    <a href="submit-reel.php" class="tt-bottom-post-btn" aria-label="<?= e(t('ลงคลิป Reels', 'Post Reel')) ?>">
      <div class="tt-bottom-post-plus">+</div>
    </a>
    <button type="button" class="tt-bottom-item" data-bottom-nav="tab-videos" onclick="switchTab('tab-videos')">
      <svg viewBox="0 0 24 24"><path d="M17 10.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5l4 4v-11l-4 4z"/></svg>
      <span><?= e(t('วิดีโอ', 'Videos')) ?></span>
    </button>
    <button type="button" class="tt-bottom-item" data-bottom-nav="tab-profile" onclick="switchTab('tab-profile')">
      <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
      <span><?= e(t('โปรไฟล์', 'Profile')) ?></span>
    </button>
  </nav>

<script>
// ── Fast Toast Notification ──
function showToast(message, isError) {
  var toast = document.getElementById('ttToast');
  var msg = document.getElementById('ttToastMsg');
  var icon = toast ? toast.querySelector('.tt-toast-icon') : null;
  if (!toast || !msg) return;

  msg.textContent = message;
  toast.classList.toggle('error', !!isError);
  if (icon) icon.textContent = isError ? '✕' : '✓';
  toast.classList.add('show');

  setTimeout(function() {
    toast.classList.remove('show');
  }, 3200);
}

// ── 1-Click Instant Photo Upload ──
function triggerDirectAvatarUpload() {
  var fileInput = document.getElementById('directAvatarInput');
  if (fileInput) {
    fileInput.value = '';
    fileInput.click();
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var fileInput = document.getElementById('directAvatarInput');
  if (fileInput) {
    fileInput.addEventListener('change', function() {
      if (!this.files || !this.files[0]) return;
      var file = this.files[0];

      // Instant Client Preview
      var reader = new FileReader();
      reader.onload = function(e) {
        var box = document.querySelector('.tt-hero-avatar-box');
        var fallback = document.getElementById('headerAvatarFallback');
        var img = document.getElementById('headerAvatarImg');
        if (fallback) fallback.style.display = 'none';
        if (!img) {
          img = document.createElement('img');
          img.id = 'headerAvatarImg';
          box.insertBefore(img, box.firstChild);
        }
        img.src = e.target.result;
        img.style.display = 'block';
      };
      reader.readAsDataURL(file);

      // Start upload spinner
      var spinner = document.getElementById('avatarSpinner');
      if (spinner) spinner.classList.add('active');

      // Send Ajax Form
      var formData = new FormData(document.getElementById('directAvatarForm'));
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'api_upload_avatar.php', true);
      xhr.onload = function() {
        if (spinner) spinner.classList.remove('active');
        if (xhr.status === 200) {
          try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
              showToast(res.message || 'เปลี่ยนรูปโปรไฟล์สำเร็จ!', false);
              var img = document.getElementById('headerAvatarImg');
              if (img && res.avatar_url) {
                img.src = res.avatar_url;
              }
            } else {
              showToast(res.error || 'เกิดข้อผิดพลาดในการอัปโหลด', true);
            }
          } catch (err) {
            showToast('บันทึกรูปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', true);
          }
        } else {
          showToast('การเชื่อมต่อขัดข้อง กรุณาลองใหม่', true);
        }
      };
      xhr.onerror = function() {
        if (spinner) spinner.classList.remove('active');
        showToast('การเชื่อมต่อขัดข้อง', true);
      };
      xhr.send(formData);
    });
  }
});

// ── Sound Toggle for Auto-Playing Card Videos ──
function toggleCardVideoSound(e, btn) {
  e.preventDefault();
  e.stopPropagation();

  var wrap = btn.closest('.tt-video-media-wrap');
  if (!wrap) return;
  var video = wrap.querySelector('video.tt-card-video-element');
  if (!video) return;

  var mutedIcon = btn.querySelector('.sound-icon-muted');
  var unmutedIcon = btn.querySelector('.sound-icon-unmuted');

  if (video.muted) {
    // Unmute this video and mute all other videos
    document.querySelectorAll('video.tt-card-video-element').forEach(function(v) {
      if (v !== video) {
        v.muted = true;
        var pBtn = v.closest('.tt-video-media-wrap');
        if (pBtn) {
          var sBtn = pBtn.querySelector('.tt-video-sound-btn');
          if (sBtn) {
            sBtn.classList.remove('unmuted');
            var m = sBtn.querySelector('.sound-icon-muted');
            var u = sBtn.querySelector('.sound-icon-unmuted');
            if (m) m.style.display = '';
            if (u) u.style.display = 'none';
          }
        }
      }
    });

    video.muted = false;
    video.volume = 1.0;
    video.play().catch(function(){});
    btn.classList.add('unmuted');
    if (mutedIcon) mutedIcon.style.display = 'none';
    if (unmutedIcon) unmutedIcon.style.display = '';
  } else {
    // Mute this video
    video.muted = true;
    btn.classList.remove('unmuted');
    if (mutedIcon) mutedIcon.style.display = '';
    if (unmutedIcon) unmutedIcon.style.display = 'none';
  }
}

// ── Tab Navigation ──
function switchTab(tabId, scrollToSecurity) {
  if (typeof scrollToSecurity === 'undefined') scrollToSecurity = false;

  document.querySelectorAll('.tt-tab-button').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-tab-target') === tabId);
  });
  document.querySelectorAll('.tt-nav-item[data-tab-nav]').forEach(function(link) {
    link.classList.toggle('active', link.getAttribute('data-tab-nav') === tabId);
  });
  document.querySelectorAll('.tt-bottom-item[data-bottom-nav]').forEach(function(item) {
    item.classList.toggle('active', item.getAttribute('data-bottom-nav') === tabId);
  });
  document.querySelectorAll('.tt-tab-pane').forEach(function(pane) {
    pane.classList.toggle('active', pane.id === tabId);
  });

  if (history.replaceState) {
    history.replaceState(null, null, '#' + tabId);
  }

  if (scrollToSecurity) {
    setTimeout(function() {
      var sec = document.getElementById('account-security');
      if (sec) sec.scrollIntoView({ behavior: 'smooth' });
    }, 100);
  } else {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function focusBioInput() {
  switchTab('tab-profile');
  setTimeout(function() {
    var bioInput = document.getElementById('profileBioInput');
    if (bioInput) {
      bioInput.focus();
      bioInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, 120);
}

function sortActiveCards(type, btn) {
  document.querySelectorAll('.tt-sort-pill').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');

  var activePane = document.querySelector('.tt-tab-pane.active');
  if (!activePane) return;
  var grid = activePane.querySelector('.tt-video-grid');
  if (!grid) return;

  var cards = Array.from(grid.children);
  cards.sort(function(a, b) {
    if (type === 'popular') {
      return (Number(b.dataset.views) || 0) - (Number(a.dataset.views) || 0);
    } else if (type === 'price') {
      return (Number(a.dataset.price) || 0) - (Number(b.dataset.price) || 0);
    } else {
      return (Number(b.dataset.created) || 0) - (Number(a.dataset.created) || 0);
    }
  });

  cards.forEach(function(card) { grid.appendChild(card); });
}

document.addEventListener('DOMContentLoaded', function() {
  var hash = window.location.hash;
  if (hash === '#my-posts' || hash === '#grid-listings' || hash === '#tab-listings') {
    switchTab('tab-listings');
  } else if (hash === '#my-videos' || hash === '#tab-videos') {
    switchTab('tab-videos');
  } else if (hash === '#my-projects' || hash === '#tab-projects') {
    switchTab('tab-projects');
  } else if (hash === '#favorites' || hash === '#tab-favorites') {
    switchTab('tab-favorites');
  } else if (hash === '#my-account' || hash === '#tab-profile') {
    switchTab('tab-profile');
  } else if (hash === '#account-security') {
    switchTab('tab-profile', true);
  }
});
</script>
</body>
</html>