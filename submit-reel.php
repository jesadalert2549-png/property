<?php
/**
 * Submit Quick Reel (โพสต์คลิป Property Reels ด่วนใน 30 วินาที)
 * ออกแบบ UX/UI Mobile-First สไตล์ Reels Studio
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/site_shell.php';

start_session();
current_lang();

if (empty($_SESSION['user_id'])) {
    redirect('login.php?return_to=submit-reel.php');
}

$pdo = db();
$userId = (int)$_SESSION['user_id'];

// ดึงโปรไฟล์ผู้ใช้งาน
$profileStmt = $pdo->prepare('SELECT id, full_name, username, phone, line_id, avatar_path FROM users WHERE id = ? LIMIT 1');
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// ดึงรายการประกาศของสมาชิก (เผื่อต้องการเลือกผูกคลิปกับประกาศที่มีอยู่แล้ว)
$myListingsStmt = $pdo->prepare('SELECT id, title, type, price, location FROM properties WHERE user_id = ? AND approval = "approved" ORDER BY id DESC');
$myListingsStmt->execute([$userId]);
$myListings = $myListingsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$successMessage = '';

if (request_is_post()) {
    csrf_check();

    $mode = $_POST['reel_mode'] ?? 'new'; // 'new' หรือ 'attach'
    $targetListingId = (int)($_POST['target_listing_id'] ?? 0);

    $title = trim((string)($_POST['title'] ?? ''));
    $type = ($_POST['type'] ?? 'sale') === 'rent' ? 'rent' : 'sale';
    $category = trim((string)($_POST['category'] ?? 'condo'));
    if (!array_key_exists($category, PROPERTY_CATEGORIES)) {
        $category = 'condo';
    }
    $price = (int)preg_replace('/[^0-9]/', '', (string)($_POST['price'] ?? '0'));
    $origPriceInput = trim((string)($_POST['original_price'] ?? ''));
    $origPrice = ($origPriceInput !== '' && is_numeric($origPriceInput) && (int)$origPriceInput > $price) ? (int)$origPriceInput : null;
    $location = trim((string)($_POST['location'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $contactName = trim((string)($_POST['contact_name'] ?? '')) ?: ($profile['full_name'] ?: $profile['username']);
    $phone = trim((string)($_POST['phone'] ?? '')) ?: ($profile['phone'] ?? '');
    $lineId = clean_line_id((string)($_POST['line_id'] ?? '')) ?: ($profile['line_id'] ?? '');
    $mapUrl = trim((string)($_POST['map_url'] ?? ''));
    $mapCoords = parse_map_coords($mapUrl);

    // ตรวจสอบไฟล์วิดีโอ
    $videoFile = null;
    try {
        if (!empty($_FILES['video']['name'])) {
            $videoFile = save_uploaded_property_video($_FILES['video'] ?? []);
        } else {
            $errors[] = t('กรุณาเลือกไฟล์วิดีโอสำหรับโพสต์ Reels (MP4, WebM หรือ MOV)', 'Please select a video file for your reel (MP4, WebM or MOV)');
        }
    } catch (RuntimeException $re) {
        $errors[] = $re->getMessage();
    }

    // กรณีผูกกับประกาศเดิม
    if ($mode === 'attach') {
        if ($targetListingId <= 0) {
            $errors[] = t('กรุณาเลือกประกาศที่ต้องการผูกคลิปวิดีโอ', 'Please select a property listing to attach this video to.');
        } else {
            // ตรวจสอบว่าเป็นประกาศของสมาชิกจริง
            $chk = $pdo->prepare('SELECT id, video_file FROM properties WHERE id = ? AND user_id = ? LIMIT 1');
            $chk->execute([$targetListingId, $userId]);
            $existingProp = $chk->fetch();
            if (!$existingProp) {
                $errors[] = t('ไม่พบประกาศที่เลือก หรือคุณไม่มีสิทธิ์แก้ไขประกาศนี้', 'Property not found or unauthorized.');
            }
        }
    } else {
        // กรณีสร้างเป็นคลิปใหม่ด่วน
        if ($title === '') {
            $errors[] = t('กรุณาระบุหัวข้อหรือแคปชันสั้นๆ ของคลิป', 'Please provide a title or caption for your reel.');
        }
        if ($price <= 0) {
            $errors[] = t('กรุณาระบุราคาให้ถูกต้อง', 'Please enter a valid price.');
        }
        if ($location === '') {
            $errors[] = t('กรุณาระบุทำเลที่ตั้งหรือโซน', 'Please enter location or zone.');
        }
    }

    if (empty($errors) && $videoFile) {
        try {
            if ($mode === 'attach') {
                // ลบวิดีโอเก่าถ้ามี
                if (!empty($existingProp['video_file'])) {
                    delete_image_file($existingProp['video_file']);
                }
                $upd = $pdo->prepare('UPDATE properties SET video_file = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
                $upd->execute([$videoFile, $targetListingId, $userId]);
                $newPropId = $targetListingId;
            } else {
                // อัปโหลดรูปหน้าปก (ทางเลือก)
                $uploadedCover = null;
                if (!empty($_FILES['cover_image']['name'])) {
                    $savedCovers = save_uploaded_images($_FILES['cover_image'] ?? [], 1);
                    if ($savedCovers) {
                        $uploadedCover = $savedCovers[0];
                    }
                }

                $ins = $pdo->prepare(
                    "INSERT INTO properties
                        (title, type, category, property_category, status, approval, price, original_price,
                         location, lat, lng, contact_name, phone, line_id, description, user_id, video_file, created_at)
                     VALUES (?, ?, ?, ?, 'available', 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $ins->execute([
                    $title, $type, $category, $category, $price, $origPrice,
                    $location, $mapCoords[0] ?? null, $mapCoords[1] ?? null,
                    $contactName, $phone, $lineId, $description, $userId, $videoFile
                ]);
                $newPropId = (int)$pdo->lastInsertId();

                if ($uploadedCover) {
                    $pdo->prepare('INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, 0)')
                        ->execute([$newPropId, $uploadedCover]);
                }
            }

            set_flash('🎉 โพสต์คลิป Property Reels สำเร็จแล้ว! พร้อมให้ทุกคนรับชมบนฟีดทันที');
            redirect('reels.php');
        } catch (Throwable $ex) {
            if ($videoFile) {
                delete_image_file($videoFile);
            }
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $ex->getMessage();
        }
    }
}

$token = csrf_token();
$pageTitle = t('ลงคลิป Property Reels ด่วน', 'Quick Post Property Reel') . ' - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/site-shell.css?v=<?= e((string)filemtime(__DIR__ . '/css/site-shell.css')) ?>">
  <link rel="stylesheet" href="css/seo-footer.css?v=<?= e((string)filemtime(__DIR__ . '/css/seo-footer.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    :root {
      --font: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      --font-editorial: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }

    * { box-sizing: border-box; }
    body,
    .page-submit-reel,
    .page-submit-reel h1,
    .page-submit-reel h2,
    .page-submit-reel h3,
    .page-submit-reel h4,
    .page-submit-reel p,
    .page-submit-reel span,
    .page-submit-reel a,
    .page-submit-reel button,
    .page-submit-reel input,
    .page-submit-reel select,
    .page-submit-reel textarea {
      font-family: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      letter-spacing: normal !important; /* Fix squished/warped Thai letters */
    }

    body {
      margin: 0;
      padding: 0;
      background: #F9F9FB; /* Theme standard luxury light canvas */
      color: #0F1419;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    /* Fixed header on submit-reel */
    .site-header {
      background: #0B1C33;
      border-bottom: 1px solid rgba(197, 160, 89, 0.32);
      box-shadow: 0 4px 20px rgba(6, 15, 28, 0.45);
    }
    .search-mobile-menu {
      display: none;
    }
    @media (max-width: 900px) {
      .header-inner {
        position: relative;
        overflow: visible !important;
      }
      .search-mobile-menu {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 0 12px;
        border: 1px solid rgba(197, 160, 89, 0.4);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        color: #F8D888;
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        backdrop-filter: blur(6px);
      }
      .site-nav {
        display: none !important;
        position: absolute;
        top: calc(100% - 1px);
        left: 0;
        right: 0;
        z-index: 1100;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 4px !important;
        max-height: 75vh;
        overflow-y: auto;
        padding: 14px 16px;
        background: #0B1C33;
        border: 1px solid rgba(197, 160, 89, 0.3);
        border-radius: 0 0 18px 18px;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
      }
      .site-nav.is-open {
        display: flex !important;
      }
      .site-nav > a,
      .site-nav > .nav-dropdown > .nav-dropdown-toggle {
        width: 100% !important;
        justify-content: flex-start !important;
        padding: 10px 14px !important;
        font-size: 14px !important;
      }
    }

    .reel-studio-wrap {
      max-width: 720px;
      margin: 32px auto 80px;
      padding: 0 16px;
    }

    /* Top Navigation Bar */
    .reel-studio-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding-bottom: 12px;
    }
    .reel-studio-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #64748B;
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 700;
      transition: color .2s;
    }
    .reel-studio-back:hover { color: #C5A059; }
    .reel-studio-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 999px;
      background: rgba(197, 160, 89, 0.15);
      border: 1px solid rgba(197, 160, 89, 0.45);
      color: #8C6B32;
      font-size: 12px;
      font-weight: 850;
      letter-spacing: .04em;
    }

    .reel-studio-card {
      background: #ffffff;
      border: 1px solid #E2E8F0;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 16px 40px rgba(11, 28, 51, 0.07);
    }

    /* Hero Header inside Card (Brand Theme Signature Navy & Gold) */
    .reel-studio-header {
      background: linear-gradient(135deg, #0B1C33 0%, #152E4D 60%, #8C6B32 100%);
      padding: 24px 22px;
      color: #ffffff;
    }

    .reel-studio-title {
      font-size: 21px;
      font-weight: 700 !important;
      color: #ffffff !important;
      letter-spacing: normal !important;
      margin: 0 0 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .reel-studio-sub {
      font-size: 13.5px;
      color: rgba(255, 255, 255, 0.9);
      margin: 0;
      line-height: 1.55;
      letter-spacing: normal !important;
    }

    .reel-studio-body {
      padding: 24px 20px;
    }

    /* Video Upload Dropzone */
    .reel-upload-zone {
      position: relative;
      border: 2px dashed #C5A059;
      border-radius: 18px;
      padding: 30px 18px;
      text-align: center;
      background: #FAF8F5; /* Warm Champagne Cream */
      cursor: pointer;
      transition: all .25s ease;
      overflow: hidden;
      margin-bottom: 22px;
    }
    .reel-upload-zone:hover,
    .reel-upload-zone.dragover {
      border-color: #A8843F;
      background: #F5EFE6;
      transform: translateY(-2px);
    }
    .reel-upload-icon {
      width: 54px;
      height: 54px;
      margin: 0 auto 10px;
      border-radius: 50%;
      background: rgba(197, 160, 89, 0.16);
      border: 1px solid rgba(197, 160, 89, 0.4);
      display: grid;
      place-items: center;
      color: #8C6B32;
      font-size: 24px;
    }
    .reel-upload-text {
      font-size: 15px;
      font-weight: 700 !important;
      letter-spacing: normal !important;
      color: #0B1C33;
      margin-bottom: 4px;
    }
    .reel-upload-hint {
      font-size: 12px;
      color: #64748B;
      letter-spacing: normal !important;
    }

    /* Video Preview Player Box */
    .reel-video-preview-box {
      display: none;
      position: relative;
      border-radius: 18px;
      overflow: hidden;
      background: #0B1C33;
      aspect-ratio: 9 / 16;
      max-height: 380px;
      margin: 0 auto 22px;
      border: 1px solid rgba(197,160,89,0.5);
      box-shadow: 0 10px 30px rgba(11,28,51,0.25);
    }
    .reel-video-preview-box video {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .reel-video-remove-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
      background: rgba(11,28,51,0.85);
      border: 1px solid rgba(255,255,255,0.4);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 5px 12px;
      border-radius: 999px;
      cursor: pointer;
      backdrop-filter: blur(6px);
    }

    /* Mode Switch Tabs */
    .reel-mode-tabs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      background: #F1F5F9;
      padding: 4px;
      border-radius: 14px;
      margin-bottom: 20px;
      border: 1px solid #E2E8F0;
    }
    .reel-mode-tab {
      padding: 9px 6px;
      border-radius: 10px;
      border: none;
      background: none;
      color: #64748B;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      font-size: 12.5px;
      font-weight: 700 !important;
      letter-spacing: normal !important;
      white-space: nowrap;
      cursor: pointer;
      transition: all .2s;
      text-align: center;
    }
    .reel-mode-tab.active {
      background: #0B1C33;
      color: #F8D888;
      box-shadow: 0 2px 8px rgba(11,28,51,0.2);
    }

    /* Form Fields */
    .reel-form-group {
      margin-bottom: 18px;
    }
    .reel-form-group label {
      display: block;
      font-size: 13px;
      font-weight: 700 !important;
      letter-spacing: normal !important;
      color: #0B1C33;
      margin-bottom: 6px;
    }
    .reel-input, .reel-select, .reel-textarea {
      width: 100%;
      background: #ffffff;
      border: 1px solid #CBD5E1;
      border-radius: 12px;
      padding: 10px 14px;
      font-size: 13.5px;
      color: #0F1419;
      outline: none;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      letter-spacing: normal !important;
      transition: all .2s;
    }
    .reel-input:focus, .reel-select:focus, .reel-textarea:focus {
      border-color: #C5A059;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.2);
    }
    .reel-input::placeholder, .reel-textarea::placeholder {
      color: #94A3B8;
      font-weight: 400;
    }

    /* Custom File Picker for Cover Photo */
    .reel-cover-picker-box {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border: 1px solid #CBD5E1;
      border-radius: 12px;
      background: #ffffff;
      cursor: pointer;
      transition: border-color .2s;
    }
    .reel-cover-picker-box:hover {
      border-color: #C5A059;
    }
    .reel-cover-btn-icon {
      padding: 5px 10px;
      border-radius: 8px;
      background: #F1F5F9;
      color: #0B1C33;
      font-size: 12px;
      font-weight: 700;
      border: 1px solid #E2E8F0;
    }
    .reel-cover-text {
      font-size: 13px;
      color: #64748B;
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .reel-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 540px) {
      .reel-grid-2 { grid-template-columns: 1fr; }
    }

    /* Type radio cards */
    .reel-type-radio-label {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #F8FAFC;
      padding: 10px 14px;
      border-radius: 12px;
      border: 1px solid #E2E8F0;
      cursor: pointer;
      font-weight: 700;
      color: #0B1C33;
      font-size: 13.5px;
      letter-spacing: normal !important;
      transition: all .15s;
    }
    .reel-type-radio-label:hover {
      border-color: #C5A059;
      background: #FAF8F5;
    }

    /* Big Submit Button */
    .reel-submit-btn {
      width: 100%;
      padding: 14px 20px;
      border-radius: 999px;
      background: linear-gradient(135deg, #0B1C33 0%, #152E4D 45%, #C5A059 100%);
      border: 1.5px solid rgba(248, 216, 136, 0.85);
      color: #ffffff;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      font-size: 15px;
      font-weight: 700 !important;
      letter-spacing: normal !important;
      cursor: pointer;
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.35), 0 4px 14px rgba(197, 160, 89, 0.3);
      transition: all .25s cubic-bezier(0.34, 1.56, 0.64, 1);
      margin-top: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .reel-submit-btn:hover {
      transform: translateY(-2px) scale(1.02);
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%);
      color: #0B1C33;
      box-shadow: 0 12px 30px rgba(197, 160, 89, 0.5);
    }
    .reel-submit-btn:active { transform: scale(0.98); }

    .error-banner {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .error-banner ul { margin: 0; padding-left: 18px; }
  </style>
</head>
<body class="page-submit-reel">

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <button class="search-mobile-menu" type="button" aria-controls="submitReelSiteNav" aria-expanded="false" id="mobileMenuBtn">
      <span aria-hidden="true">☰</span>
      <span><?= e(t('เมนู', 'Menu')) ?></span>
    </button>
    <nav class="site-nav" id="submitReelSiteNav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="reels.php" style="color:#C5A059; font-weight:800;">🔥 <?= e(t('Reels', 'Reels')) ?></a>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'Projects')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
        <a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <a href="submit.php" class="nav-cta"><?= e(t('ลงประกาศฟรี', 'Post free listing')) ?></a>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="section" style="padding: 20px 0 60px;">
  <div class="reel-studio-wrap">
    <!-- Top Navigation -->
    <div class="reel-studio-nav">
      <a href="reels.php" class="reel-studio-back">
        <span>←</span>
        <span><?= e(t('กลับฟีด Property Reels', 'Back to Reels Feed')) ?></span>
      </a>
      <span class="reel-studio-badge">
        <span>✨</span>
        <span>REELS STUDIO</span>
      </span>
    </div>

    <div class="reel-studio-card">
      <div class="reel-studio-header">
        <h1 class="reel-studio-title">
          <span>🎬</span>
          <span><?= e(t('ลงคลิปพาทัวร์ Reels ด่วน', 'Quick Post Property Reel')) ?></span>
        </h1>
        <p class="reel-studio-sub">
          <?= e(t('โพสต์คลิปสั้นพาทัวร์บ้านหรือคอนโดได้ใน 30 วินาที เพื่อให้ผู้ซื้อเข้ามาปัดชมได้ทันทีบน Property Reels', 'Publish short property video tours in 30 seconds for buyers to discover on Reels feed.')) ?>
        </p>
      </div>

      <div class="reel-studio-body">
        <?php if (!empty($errors)): ?>
          <div class="error-banner">
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="reelUploadForm">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="reel_mode" id="reelModeInput" value="new">

          <!-- Video Upload Dropzone -->
          <div class="reel-upload-zone" id="reelDropzone">
            <div class="reel-upload-icon">📹</div>
            <div class="reel-upload-text"><?= e(t('แตะหรือลากไฟล์วิดีโอมาวางที่นี่', 'Tap or drop your video here')) ?></div>
            <div class="reel-upload-hint"><?= e(t('รองรับไฟล์ MP4, WebM หรือ MOV · ขนาดไม่เกิน 40 MB', 'Supports MP4, WebM or MOV · Up to 40 MB')) ?></div>
            <input type="file" id="videoFileInput" name="video" accept="video/mp4,video/webm,video/quicktime" style="display:none;">
          </div>

          <!-- Video Live Preview Player -->
          <div class="reel-video-preview-box" id="reelPreviewBox">
            <video id="reelPreviewVideo" controls playsinline></video>
            <button type="button" class="reel-video-remove-btn" id="reelRemoveVideoBtn">✕ <?= e(t('เปลี่ยนวิดีโอ', 'Change video')) ?></button>
          </div>

          <!-- Mode Switch: New Post vs Attach to Existing Listing -->
          <?php if (!empty($myListings)): ?>
            <div class="reel-mode-tabs">
              <button type="button" class="reel-mode-tab active" data-mode="new"><?= e(t('⚡ คลิปใหม่ด่วน', '⚡ New Reel')) ?></button>
              <button type="button" class="reel-mode-tab" data-mode="attach"><?= e(t('🔗 ผูกประกาศเดิม', '🔗 Attach Listing')) ?></button>
            </div>

            <div id="attachModeSection" style="display:none; margin-bottom:20px;">
              <div class="reel-form-group">
                <label for="targetListingSelect">📌 <?= e(t('เลือกประกาศของคุณที่ต้องการใส่คลิปวิดีโอนี้:', 'Select your existing listing:')) ?></label>
                <select id="targetListingSelect" name="target_listing_id" class="reel-select">
                  <option value=""><?= e(t('-- เลือกประกาศที่ต้องการ --', '-- Select a listing --')) ?></option>
                  <?php foreach ($myListings as $l): ?>
                    <option value="<?= (int)$l['id'] ?>">#<?= (int)$l['id'] ?> - <?= e(mb_substr($l['title'], 0, 45)) ?> (฿<?= number_format((int)$l['price']) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <small style="color:#64748B; font-size:11.5px; display:block; margin-top:4px;"><?= e(t('ระบบจะอัปเดตคลิปนี้เข้าไปในประกาศเดิม และดึงราคา/ทำเลมาแสดงบนฟีด Reels อัตโนมัติ', 'Will attach to this listing and use its info on Reels feed.')) ?></small>
              </div>
            </div>
          <?php endif; ?>

          <!-- Quick Reel Form Fields (Mode: New) -->
          <div id="newReelFieldsSection">
            <div class="reel-form-group">
              <label for="reelTitle">🏷️ <?= e(t('หัวข้อ / แคปชันสั้นๆ ของคลิป', 'Caption / Title')) ?> *</label>
              <input type="text" id="reelTitle" name="title" class="reel-input" maxlength="255" placeholder="<?= e(t('เช่น คอนโดติด BTS วิวแม่น้ำ พร้อมอยู่', 'e.g. Condo near BTS with river view')) ?>">
            </div>

            <div class="reel-grid-2">
              <div class="reel-form-group">
                <label><?= e(t('รูปแบบการเสนอ', 'Listing Type')) ?></label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                  <label class="reel-type-radio-label">
                    <input type="radio" name="type" value="sale" checked style="accent-color:#C5A059;">
                    <span><?= e(t('ขาย', 'Sale')) ?></span>
                  </label>
                  <label class="reel-type-radio-label">
                    <input type="radio" name="type" value="rent" style="accent-color:#C5A059;">
                    <span><?= e(t('ให้เช่า', 'Rent')) ?></span>
                  </label>
                </div>
              </div>

              <div class="reel-form-group">
                <label for="reelCategory"><?= e(t('หมวดหมู่อสังหาฯ', 'Category')) ?></label>
                <select id="reelCategory" name="category" class="reel-select">
                  <?php foreach (PROPERTY_CATEGORIES as $slug => $label): ?>
                    <?php if ($slug === 'other') continue; ?>
                    <option value="<?= e($slug) ?>"><?= e(category_label_bi($slug)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="reel-grid-2">
              <div class="reel-form-group">
                <label for="reelPrice">💰 <?= e(t('ราคา (บาท)', 'Price (THB)')) ?> *</label>
                <input type="number" id="reelPrice" name="price" class="reel-input" placeholder="e.g. 3500000" min="0">
              </div>

              <div class="reel-form-group">
                <label for="reelOrigPrice">🏷️ <?= e(t('ราคาเดิมก่อนลด (ถ้ามี)', 'Original Price (for discount)')) ?></label>
                <input type="number" id="reelOrigPrice" name="original_price" class="reel-input" placeholder="<?= e(t('แสดงป้ายลดราคา 🔥', 'Shows discount tag 🔥')) ?>" min="0">
                <div id="liveDiscountBadge" style="display:none; margin-top:6px; font-size:11.5px; font-weight:800; color:#ef4444; background:#fef2f2; padding:3px 8px; border-radius:6px; width:fit-content; border:1px solid #fca5a5;"></div>
              </div>
            </div>

            <div class="reel-form-group">
              <label for="reelLocation">📍 <?= e(t('ทำเลที่ตั้ง / โซน / สถานีรถไฟฟ้า', 'Location / Zone / BTS/MRT')) ?> *</label>
              <input type="text" id="reelLocation" name="location" class="reel-input" placeholder="<?= e(t('เช่น BTS ช้างเอราวัณ, สุขุมวิท', 'e.g. BTS Chang Erawan, Sukhumvit')) ?>">
            </div>

            <div class="reel-form-group">
              <label for="reelCoverImage">🖼️ <?= e(t('รูปภาพหน้าปกคลิป (ทางเลือก - สำหรับแสดงตอนวิดีโอยังไม่โหลด)', 'Cover photo (optional - shown before video loads)')) ?></label>
              <label class="reel-cover-picker-box" for="reelCoverImage">
                <span class="reel-cover-btn-icon">📁 <?= e(t('เลือกรูปภาพ', 'Browse')) ?></span>
                <span class="reel-cover-text" id="reelCoverText"><?= e(t('รองรับไฟล์ JPG, PNG หรือ WebP', 'Supports JPG, PNG or WebP')) ?></span>
                <input type="file" id="reelCoverImage" name="cover_image[]" accept="image/jpeg,image/png,image/webp" style="display:none;">
              </label>
              <div id="reelCoverPreviewBox" style="display:none; margin-top:8px; align-items:center; gap:10px;">
                <img id="reelCoverPreviewImg" src="" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid #cbd5e1;">
                <span id="reelCoverPreviewName" style="font-size:12px; color:#475569; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;"></span>
                <button type="button" id="reelCoverRemoveBtn" style="background:none; border:none; color:#ef4444; font-size:12px; font-weight:700; cursor:pointer;">✕ <?= e(t('ยกเลิก', 'Remove')) ?></button>
              </div>
            </div>

            <div class="reel-form-group">
              <label for="reelMapUrl">🗺️ <?= e(t('ลิงก์ Google Maps (ทางเลือก - จะแสดงปุ่ม "ดูแผนที่" ในคลิป)', 'Google Maps Link (Optional - adds "View Map" button)')) ?></label>
              <input type="url" id="reelMapUrl" name="map_url" class="reel-input" placeholder="https://maps.app.goo.gl/... หรือพิกัด">
            </div>

            <div class="reel-grid-2">
              <div class="reel-form-group">
                <label for="reelPhone">📞 <?= e(t('เบอร์โทรติดต่อ', 'Phone Number')) ?></label>
                <input type="tel" id="reelPhone" name="phone" class="reel-input" value="<?= e($profile['phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
              </div>

              <div class="reel-form-group">
                <label for="reelLineId">💬 <?= e(t('LINE ID (สำหรับปุ่มทักแชตใน Reels)', 'LINE ID (for chat button)')) ?></label>
                <input type="text" id="reelLineId" name="line_id" class="reel-input" value="<?= e($profile['line_id'] ?? '') ?>" placeholder="yourlineid">
              </div>
            </div>

            <div class="reel-form-group">
              <label for="reelDesc">📄 <?= e(t('รายละเอียดเพิ่มเติม (เมื่อผู้ชมแตะ "...ดูเพิ่มเติม" ใน Reels)', 'Additional Details (shown on "...more")')) ?></label>
              <textarea id="reelDesc" name="description" class="reel-textarea" rows="3" placeholder="<?= e(t('ระบุจุดเด่น ขนาดห้อง ชั้น หรือข้อเสนอพิเศษ...', 'Highlights, size, floor, features...')) ?>"></textarea>
            </div>
          </div>

          <button type="submit" class="reel-submit-btn">
            <span>🚀</span>
            <span><?= e(t('โพสต์คลิป Property Reels ทันที', 'Publish Property Reel Now')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</main>

<?php render_to_top(); render_site_footer(); ?>
<script>
(() => {
  const dropzone = document.getElementById('reelDropzone');
  const fileInput = document.getElementById('videoFileInput');
  const previewBox = document.getElementById('reelPreviewBox');
  const previewVideo = document.getElementById('reelPreviewVideo');
  const removeBtn = document.getElementById('reelRemoveVideoBtn');

  // Trigger file dialog on dropzone click
  dropzone?.addEventListener('click', () => fileInput?.click());

  // Handle Drag & Drop
  ['dragenter', 'dragover'].forEach(name => {
    dropzone?.addEventListener(name, (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach(name => {
    dropzone?.addEventListener(name, (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
    });
  });
  dropzone?.addEventListener('drop', (e) => {
    if (e.dataTransfer?.files?.length) {
      fileInput.files = e.dataTransfer.files;
      handleFileSelected();
    }
  });

  fileInput?.addEventListener('change', handleFileSelected);

  function handleFileSelected() {
    const file = fileInput?.files?.[0];
    if (file) {
      if (file.size > 40 * 1024 * 1024) {
        alert('<?= e(t('ไฟล์วิดีโอต้องมีขนาดไม่เกิน 40 MB', 'Video must be less than 40 MB')) ?>');
        fileInput.value = '';
        return;
      }
      const url = URL.createObjectURL(file);
      previewVideo.src = url;
      dropzone.style.display = 'none';
      previewBox.style.display = 'block';
      previewVideo.play().catch(() => {});
    }
  }

  removeBtn?.addEventListener('click', () => {
    fileInput.value = '';
    previewVideo.pause();
    previewVideo.src = '';
    previewBox.style.display = 'none';
    dropzone.style.display = 'block';
  });

  // Real-time discount calculation preview
  const priceInput = document.getElementById('reelPrice');
  const origPriceInput = document.getElementById('reelOrigPrice');
  const liveDiscountBadge = document.getElementById('liveDiscountBadge');

  function updateDiscountBadge() {
    const p = parseFloat(priceInput?.value || 0);
    const op = parseFloat(origPriceInput?.value || 0);
    if (op > p && p > 0 && liveDiscountBadge) {
      const diff = op - p;
      const pct = Math.round((diff / op) * 100);
      liveDiscountBadge.style.display = 'block';
      liveDiscountBadge.textContent = `🔥 ประหยัด ฿${diff.toLocaleString()} (-${pct}%)`;
    } else if (liveDiscountBadge) {
      liveDiscountBadge.style.display = 'none';
    }
  }

  priceInput?.addEventListener('input', updateDiscountBadge);
  origPriceInput?.addEventListener('input', updateDiscountBadge);

  // Cover photo picker preview
  const coverInput = document.getElementById('reelCoverImage');
  const coverText = document.getElementById('reelCoverText');
  const coverPreviewBox = document.getElementById('reelCoverPreviewBox');
  const coverPreviewImg = document.getElementById('reelCoverPreviewImg');
  const coverPreviewName = document.getElementById('reelCoverPreviewName');
  const coverRemoveBtn = document.getElementById('reelCoverRemoveBtn');

  coverInput?.addEventListener('change', () => {
    const file = coverInput.files?.[0];
    if (file) {
      const url = URL.createObjectURL(file);
      coverPreviewImg.src = url;
      coverPreviewName.textContent = file.name;
      coverPreviewBox.style.display = 'flex';
      coverText.textContent = file.name;
    }
  });

  coverRemoveBtn?.addEventListener('click', () => {
    coverInput.value = '';
    coverPreviewImg.src = '';
    coverPreviewBox.style.display = 'none';
    coverText.textContent = '<?= e(t('รองรับไฟล์ JPG, PNG หรือ WebP', 'Supports JPG, PNG or WebP')) ?>';
  });

  // Mobile Menu Toggle
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const siteNav = document.getElementById('submitReelSiteNav');
  mobileMenuBtn?.addEventListener('click', () => {
    const isOpen = siteNav?.classList.toggle('is-open');
    mobileMenuBtn.setAttribute('aria-expanded', String(!!isOpen));
  });

  // Client-side validation & submit button loading state to prevent double submits / freeze
  const uploadForm = document.getElementById('reelUploadForm');
  const submitBtn = uploadForm?.querySelector('.reel-submit-btn');

  uploadForm?.addEventListener('submit', (e) => {
    const isAttach = modeInput?.value === 'attach';
    const hasVideo = fileInput?.files && fileInput.files.length > 0;

    if (!hasVideo) {
      e.preventDefault();
      alert('<?= e(t('กรุณาเลือกไฟล์วิดีโอสำหรับคลิป Reels', 'Please select a video file')) ?>');
      dropzone?.scrollIntoView({ behavior: 'smooth' });
      return;
    }

    if (isAttach) {
      const targetSelect = document.getElementById('targetListingSelect');
      if (!targetSelect || !targetSelect.value) {
        e.preventDefault();
        alert('<?= e(t('กรุณาเลือกประกาศที่ต้องการผูกคลิปวิดีโอ', 'Please select a listing to attach')) ?>');
        targetSelect?.focus();
        return;
      }
    } else {
      const title = document.getElementById('reelTitle')?.value.trim();
      const price = parseFloat(document.getElementById('reelPrice')?.value || 0);
      const loc = document.getElementById('reelLocation')?.value.trim();

      if (!title) {
        e.preventDefault();
        alert('<?= e(t('กรุณากรอกหัวข้อหรือแคปชันสั้นๆ ของคลิป', 'Please enter a title or caption')) ?>');
        document.getElementById('reelTitle')?.focus();
        return;
      }
      if (!price || price <= 0) {
        e.preventDefault();
        alert('<?= e(t('กรุณากรอกราคาให้ถูกต้อง', 'Please enter a valid price')) ?>');
        document.getElementById('reelPrice')?.focus();
        return;
      }
      if (!loc) {
        e.preventDefault();
        alert('<?= e(t('กรุณากรอกทำเลที่ตั้งหรือโซน', 'Please enter a location')) ?>');
        document.getElementById('reelLocation')?.focus();
        return;
      }
    }

    // Show loading state
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.8';
      submitBtn.innerHTML = '<span>⏳</span> <span><?= e(t('กำลังอัปโหลดวิดีโอและเผยแพร่คลิป... (กรุณารอสักครู่)', 'Uploading video... please wait')) ?></span>';
    }
  });
})();
</script>
</body>
</html>
