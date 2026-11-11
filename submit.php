<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/site_shell.php';

start_session();
current_lang();
if (empty($_SESSION['user_id'])) {
    redirect('login.php?return_to=submit.php');
}

$errors = [];
$submissionResult = null;
if (request_method() === 'GET') {
    if (($_GET['posted'] ?? '') === '1') {
        $submissionResult = $_SESSION['listing_submission_result'] ?? null;
    } else {
        unset($_SESSION['listing_submission_result']);
    }
} else {
    unset($_SESSION['listing_submission_result']);
}
$success = is_array($submissionResult) && (int)($submissionResult['id'] ?? 0) > 0;
$publishedId = $success ? (int)$submissionResult['id'] : 0;
$publishedWithFlag = $success && !empty($submissionResult['flagged']);
$profileStatement = db()->prepare('SELECT full_name, phone, line_id FROM users WHERE id = ? LIMIT 1');
$profileStatement->execute([(int)$_SESSION['user_id']]);
$profile = $profileStatement->fetch() ?: [];
$old = [
    'contact_name' => $profile['full_name'] ?? '',
    'phone' => $profile['phone'] ?? '',
    'line_id' => $profile['line_id'] ?? '',
];

if (request_is_post()) {
    csrf_check();

    if (!empty($_POST['website'])) {
        $errors[] = t('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', 'An error occurred. Please try again.');
    }
    if (empty($_POST['agree'])) {
        $errors[] = t('กรุณายอมรับข้อตกลงการใช้งานและนโยบายความเป็นส่วนตัว', 'Please accept the Terms of Use and Privacy Policy.');
    }

    [$data, $inputErrors] = read_listing_input($_POST);
    $errors = array_merge($errors, $inputErrors);
    $errors = array_merge($errors, listing_completeness_errors($data, $_POST));
    $old = array_merge($old, $_POST);

    $fileCount = count_selected_files($_FILES['images'] ?? []);
    if ($fileCount < 1) {
        $errors[] = t('กรุณาอัปโหลดรูปทรัพย์อย่างน้อย 1 รูป', 'Please upload at least 1 property photo.');
    } elseif ($fileCount > MAX_IMAGES) {
        $errors[] = t('อัปโหลดได้สูงสุด ' . MAX_IMAGES . ' รูป', 'You can upload up to ' . MAX_IMAGES . ' photos.');
    }

    if (!$errors) {
        $userCheck = db()->prepare('SELECT is_banned, ban_reason, tier, max_listings FROM users WHERE id = ? LIMIT 1');
        $userCheck->execute([(int)$_SESSION['user_id']]);
        $userQuota = $userCheck->fetch(PDO::FETCH_ASSOC);

        if (!empty($userQuota['is_banned'])) {
            $reason = !empty($userQuota['ban_reason']) ? ' (' . e($userQuota['ban_reason']) . ')' : '';
            $errors[] = t('บัญชีของคุณถูกระงับสิทธิ์การลงประกาศ' . $reason . ' กรุณาติดต่อผู้ดูแลระบบ', 'Your account has been suspended from posting listings' . $reason . '. Please contact support.');
        } else {
            $maxAllowed = isset($userQuota['max_listings']) ? (int)$userQuota['max_listings'] : 5;
            // ถ้าระบุ < 0 ถือว่า Unlimited
            if ($maxAllowed >= 0) {
                $currentCount = db()->prepare('SELECT COUNT(*) FROM properties WHERE user_id = ?');
                $currentCount->execute([(int)$_SESSION['user_id']]);
                $postedTotal = (int)$currentCount->fetchColumn();

                if ($postedTotal >= $maxAllowed) {
                    $errors[] = t(
                        "บัญชีของคุณใช้สิทธิ์ลงประกาศครบตามโควตาแล้ว ({$postedTotal}/{$maxAllowed} รายการ) หากต้องการลงเพิ่มกรุณาติดต่อผู้ดูแลระบบเพื่อปรับเพิ่มสิทธิ์",
                        "You have reached your listing quota ({$postedTotal}/{$maxAllowed} listings). Please contact the administrator to upgrade your plan."
                    );
                }
            }
        }

        $rateLimit = db()->prepare('SELECT COUNT(*) FROM properties WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $rateLimit->execute([(int)$_SESSION['user_id']]);
        if ((int)$rateLimit->fetchColumn() >= 10) {
            $errors[] = t('บัญชีนี้ลงประกาศถี่เกินไป กรุณารอ 1 ชั่วโมงแล้วลองใหม่อีกครั้ง', 'You have posted too frequently. Please wait an hour before posting again.');
        }
    }

    if (!$errors) {
        $uploadedImages = [];
        $uploadedVideo = null;
        $stage = 'upload';
        $coverIndex = max(0, min(MAX_IMAGES - 1, (int)($_POST['cover_index'] ?? 0)));
        try {
            $uploadedImages = save_uploaded_images_ordered($_FILES['images'] ?? [], MAX_IMAGES, $coverIndex);
            if (!empty($_FILES['video']['name'])) {
                $uploadedVideo = save_uploaded_property_video($_FILES['video'] ?? []);
            }
            if (count($uploadedImages) < 1) {
                foreach ($uploadedImages as $file) delete_image_file($file);
                if ($uploadedVideo) delete_image_file($uploadedVideo);
                $uploadedImages = [];
                $errors[] = t('ไฟล์รูปไม่ถูกต้อง กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB', 'Invalid image file. Please choose a JPG, PNG or WebP image no larger than 5 MB.');
            } else {
                $stage = 'database';
                $pdo = db();
                $pdo->beginTransaction();

                $userId = (int)$_SESSION['user_id'];
                $duplicate = $pdo->prepare("SELECT id FROM properties WHERE approval = 'approved' AND LOWER(title) = LOWER(?) AND LOWER(location) = LOWER(?) ORDER BY id DESC LIMIT 1");
                $duplicate->execute([$data['title'], $data['location']]);
                $duplicateId = $duplicate->fetchColumn();
                $verificationStatus = $duplicateId ? 'flagged' : 'passed';
                $verificationNotes = $duplicateId ? t('พบประกาศที่มีหัวข้อและทำเลคล้ายกัน (#' . (int)$duplicateId . ') โปรดตรวจสอบข้อมูลอีกครั้ง', 'A listing with a similar title and location was found (#' . (int)$duplicateId . '). Please review the information again.') : null;

                $stmt = $pdo->prepare(
                    "INSERT INTO properties
                        (title, type, category, property_category, status, approval, verification_status, verification_notes,
                         price, location, lat, lng, description, bedrooms, bathrooms, area_sqm, land_area, parking_spaces, floor_number, furnished,
                         amenities, nearby_places, contact_name, phone, line_id, user_id, video_file)
                     VALUES (?, ?, ?, ?, 'available', 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data['title'], $data['type'], $data['property_category'], $data['property_category'],
                    $verificationStatus, $verificationNotes, $data['price'], $data['location'], $data['lat'], $data['lng'], $data['description'],
                    $data['bedrooms'], $data['bathrooms'], $data['area_sqm'], $data['land_area'], $data['parking_spaces'], $data['floor_number'],
                    $data['furnished'], $data['amenities'], $data['nearby_places'], $data['contact_name'], $data['phone'], $data['line_id'], $userId,
                    $uploadedVideo,
                ]);
                $propertyId = (int)$pdo->lastInsertId();

                $imgStmt = $pdo->prepare('INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, ?)');
                foreach ($uploadedImages as $order => $file) {
                    $imgStmt->execute([$propertyId, $file, $order]);
                }
                $pdo->commit();
                $_SESSION['listing_submission_result'] = [
                    'id' => $propertyId,
                    'flagged' => (bool)$duplicateId,
                ];
                redirect('submit.php?posted=1');
            }

        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($uploadedImages as $file) {
                delete_image_file($file);
            }
            if (!empty($uploadedVideo)) {
                delete_image_file($uploadedVideo);
            }
            error_log('Listing submission failed during ' . $stage . ': ' . $ex->getMessage());
            $errors[] = $stage === 'upload'
                ? t('บันทึกรูปภาพไม่สำเร็จ กรุณาตรวจสิทธิ์เขียนโฟลเดอร์ uploads และลองใหม่', 'Failed to save images. Please check write permissions for the uploads folder and try again.')
                : t('บันทึกประกาศไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หากยังพบปัญหาโปรดแจ้งผู้ดูแลระบบ', 'Failed to save the listing. Please try again. If the problem persists, please contact the administrator.');
        }
    }
}

$token = csrf_token();
$v = fn(string $key) => e($old[$key] ?? '');
$cat = $old['property_category'] ?? '';
// ค่าเดิมภาษาไทยเก็บใน DB คงเดิม (ไม่กระทบ backend) — แสดง label ตามภาษาที่เลือก
$furnishedLabelsEn = [
    'เฟอร์นิเจอร์ครบ' => 'Fully furnished',
    'เฟอร์นิเจอร์บางส่วน' => 'Partially furnished',
    'ไม่มีเฟอร์นิเจอร์' => 'Unfurnished',
];
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('ลงประกาศฟรี', 'Post a Free Listing')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <?php site_controls_head(); lang_toggle_head(); ?>
  <style>
    :root {
      --font: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      --font-editorial: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }

    body,
    .listing-page,
    .listing-page * {
      font-family: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      letter-spacing: normal !important;
    }

    /* KASA Luxury Theme Card Overhaul */
    .listing-page > .form-box {
      border: 1px solid #E2E8F0 !important;
      border-radius: 24px !important;
      background: #ffffff !important;
      box-shadow: 0 16px 44px rgba(11, 28, 51, 0.08) !important;
      overflow: hidden !important;
    }

    /* Hero Banner: Deep Navy & Gold Gradient (Exact same as Reels Studio) */
    .listing-page .listing-intro {
      position: relative !important;
      margin: 0 !important;
      padding: 32px 28px !important;
      background: linear-gradient(135deg, #0B1C33 0%, #152E4D 60%, #8C6B32 100%) !important;
      color: #ffffff !important;
    }
    .listing-page .listing-eyebrow {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      color: #F8D888 !important;
      font-size: 11.5px !important;
      font-weight: 850 !important;
      letter-spacing: .06em !important;
      text-transform: uppercase !important;
      background: rgba(197, 160, 89, 0.18) !important;
      border: 1px solid rgba(197, 160, 89, 0.45) !important;
      padding: 4px 12px !important;
      border-radius: 999px !important;
      margin-bottom: 8px !important;
    }
    .listing-page .listing-intro h1 {
      color: #ffffff !important;
      font-size: clamp(22px, 3.5vw, 30px) !important;
      font-weight: 850 !important;
      letter-spacing: normal !important;
      margin: 4px 0 8px !important;
    }
    .listing-page .listing-intro p {
      color: rgba(255, 255, 255, 0.88) !important;
      font-size: 13.5px !important;
      line-height: 1.6 !important;
      margin: 0 !important;
    }

    /* Step Progress Indicator (Theme Matched) */
    .listing-progress {
      background: #FAF8F5 !important;
      border-bottom: 1px solid #E2E8F0 !important;
      padding: 14px 20px !important;
      gap: 10px !important;
    }
    .listing-progress span {
      font-size: 12.5px !important;
      font-weight: 700 !important;
      color: #0B1C33 !important;
    }
    .listing-progress b {
      background: #0B1C33 !important;
      color: #F8D888 !important;
      border: 1px solid rgba(197, 160, 89, 0.4) !important;
      font-weight: 850 !important;
    }
    .listing-progress i {
      background: #C5A059 !important;
    }

    /* Section Headings */
    .listing-form-section {
      border: 1px solid #E2E8F0 !important;
      border-radius: 18px !important;
      background: #ffffff !important;
      padding: 24px 22px !important;
      box-shadow: 0 4px 14px rgba(11, 28, 51, 0.02) !important;
    }
    .listing-section-heading span {
      background: #0B1C33 !important;
      color: #F8D888 !important;
      border: 1px solid rgba(197, 160, 89, 0.4) !important;
      font-weight: 900 !important;
      font-size: 13px !important;
      border-radius: 10px !important;
    }
    .listing-section-heading h2 {
      color: #0B1C33 !important;
      font-weight: 800 !important;
      font-size: 17px !important;
      margin-bottom: 2px !important;
    }
    .listing-section-heading p {
      color: #64748B !important;
      font-size: 12.5px !important;
    }

    /* Form Fields Styling */
    .listing-form-fields label,
    .listing-type-field legend {
      color: #0B1C33 !important;
      font-weight: 700 !important;
      font-size: 13px !important;
    }
    .listing-form-fields input:not([type="checkbox"]):not([type="file"]):not([type="radio"]),
    .listing-form-fields select,
    .listing-form-fields textarea {
      border: 1px solid #CBD5E1 !important;
      border-radius: 12px !important;
      background: #ffffff !important;
      color: #0F1419 !important;
      font-size: 13.5px !important;
      font-family: inherit !important;
      padding: 10px 14px !important;
      min-height: 44px !important;
      transition: all .2s ease !important;
    }
    .listing-form-fields input:focus,
    .listing-form-fields select:focus,
    .listing-form-fields textarea:focus {
      border-color: #C5A059 !important;
      box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.2) !important;
      background: #ffffff !important;
      outline: none !important;
    }

    /* Radio Options for ขาย / ให้เช่า */
    .listing-type-options label {
      border: 1px solid #E2E8F0 !important;
      border-radius: 12px !important;
      background: #F8FAFC !important;
      font-weight: 700 !important;
      color: #0B1C33 !important;
      font-size: 13.5px !important;
      padding: 11px 16px !important;
      transition: all .18s ease !important;
    }
    .listing-type-options label:hover {
      border-color: #C5A059 !important;
      background: #FAF8F5 !important;
    }
    .listing-type-options label:has(input:checked) {
      border-color: #C5A059 !important;
      background: #FAF8F5 !important;
      color: #8C6B32 !important;
      box-shadow: 0 2px 8px rgba(197, 160, 89, 0.15) !important;
    }

    /* Upload Dropzones (Warm Cream + Gold Dashed Border) */
    .listing-upload {
      position: relative !important;
      border: 2px dashed #C5A059 !important;
      border-radius: 18px !important;
      background: #FAF8F5 !important;
      padding: 30px 20px !important;
      text-align: center !important;
      transition: all .22s ease !important;
      cursor: pointer !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
    }
    .listing-upload:hover {
      border-color: #A8843F !important;
      background: #F5EFE6 !important;
      transform: translateY(-2px) !important;
    }
    .listing-upload-icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 6px;
      border-radius: 50%;
      background: rgba(197, 160, 89, 0.16);
      border: 1px solid rgba(197, 160, 89, 0.4);
      display: grid;
      place-items: center;
      color: #8C6B32;
      font-size: 24px;
    }
    .listing-upload strong {
      color: #0B1C33 !important;
      font-size: 15px !important;
      font-weight: 800 !important;
      display: block !important;
    }
    .listing-upload small {
      color: #64748B !important;
      font-size: 12.5px !important;
      display: block !important;
      line-height: 1.5 !important;
    }
    /* Modern Custom Upload Button inside Dropzone */
    .listing-upload-btn {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      margin-top: 10px !important;
      padding: 8px 18px !important;
      border-radius: 999px !important;
      background: #0B1C33 !important;
      border: 1px solid rgba(197, 160, 89, 0.5) !important;
      color: #F8D888 !important;
      font-size: 12.5px !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 12px rgba(11, 28, 51, 0.15) !important;
      pointer-events: none !important; /* let label capture click */
      transition: all .2s ease !important;
    }
    .listing-upload:hover .listing-upload-btn {
      background: #C5A059 !important;
      color: #0B1C33 !important;
      transform: scale(1.05) !important;
    }
    /* Hide the native ugly browser [Choose File] button */
    .listing-upload input[type="file"] {
      display: none !important;
    }

    /* Live Video Preview in Submit Form */
    .listing-video-preview-box {
      display: none;
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      background: #0B1C33;
      aspect-ratio: 16 / 9;
      max-height: 280px;
      margin: 12px 0;
      border: 1px solid rgba(197, 160, 89, 0.4);
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.15);
    }
    .listing-video-preview-box video {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .listing-video-remove-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
      background: rgba(11, 28, 51, 0.85);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #ffffff;
      font-size: 11.5px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      cursor: pointer;
      backdrop-filter: blur(6px);
      transition: all .18s;
    }
    .listing-video-remove-btn:hover {
      background: #dc2626;
      border-color: #dc2626;
    }

    /* Big Submit Button (Flagship Luxury Navy & Gold) */
    .listing-submit {
      width: 100% !important;
      min-height: 52px !important;
      border-radius: 999px !important;
      background: linear-gradient(135deg, #0B1C33 0%, #152E4D 45%, #C5A059 100%) !important;
      border: 1.5px solid rgba(248, 216, 136, 0.85) !important;
      color: #ffffff !important;
      font-family: inherit !important;
      font-size: 16px !important;
      font-weight: 850 !important;
      letter-spacing: .02em !important;
      cursor: pointer !important;
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.35), 0 4px 14px rgba(197, 160, 89, 0.3) !important;
      transition: all .25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    }
    .listing-submit:hover {
      transform: translateY(-2px) scale(1.01) !important;
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      box-shadow: 0 12px 30px rgba(197, 160, 89, 0.5) !important;
    }
    .listing-submit:active {
      transform: scale(0.98) !important;
    }

    /* Map button styling */
    .listing-map-toggle {
      border-radius: 999px !important;
      background: #0B1C33 !important;
      color: #F8D888 !important;
      border: 1px solid rgba(197, 160, 89, 0.45) !important;
      padding: 6px 14px !important;
      font-weight: 700 !important;
      font-size: 12px !important;
      transition: all .2s !important;
    }
    .listing-map-toggle:hover {
      background: #C5A059 !important;
      color: #0B1C33 !important;
    }

    @media (max-width: 640px) {
      .listing-page .listing-intro {
        padding: 24px 18px !important;
      }
      .listing-form-section {
        padding: 18px 14px !important;
      }
    }
  </style>
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="section">
  <div class="container narrow listing-page">
    <?php if ($success): ?>
      <div class="form-box center">
        <span class="listing-success-mark" aria-hidden="true">✓</span>
        <h1 class="section-title"><?= e(t('ประกาศของคุณเผยแพร่แล้ว', 'Your listing is now live')) ?></h1>
        <?php if ($publishedWithFlag): ?>
          <p><?= e(t('ระบบพบประกาศที่มีชื่อและทำเลคล้ายกัน จึงติดธงให้ผู้ดูแลตรวจสอบเพิ่มเติม ประกาศของคุณยังแสดงบนเว็บไซต์แล้ว', 'The system found a listing with a similar title and location, so it has been flagged for additional review by our team. Your listing is already displayed on the website.')) ?></p>
        <?php else: ?>
          <p><?= e(t('ข้อมูลและรูปภาพผ่านการตรวจความครบถ้วนเบื้องต้น และแสดงบนเว็บไซต์ได้ทันที', 'Your information and photos passed the initial completeness check and are now visible on the website.')) ?></p>
        <?php endif; ?>
        <p>
          <a href="property.php?id=<?= (int)$publishedId ?>" class="btn"><?= e(t('ดูประกาศของฉัน', 'View my listing')) ?></a>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn"><?= e(t('กลับสู่พื้นที่ส่วนตัว', 'Back to my dashboard')) ?></a>
          <?php else: ?>
            <a href="index.php" class="btn"><?= e(t('กลับหน้าแรก', 'Back to home page')) ?></a>
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <div class="form-box">
        <div class="listing-intro">
          <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <span class="listing-eyebrow">✨ <?= e(t('ลงประกาศกับ ' . SITE_NAME, 'List with ' . SITE_NAME)) ?></span>
            <a href="submit-reel.php" style="display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:999px; background:rgba(248,216,136,0.18); border:1px solid rgba(248,216,136,0.6); color:#F8D888; text-decoration:none; font-size:12px; font-weight:800;" title="<?= e(t('ต้องการลงคลิปวิดีโอแบบด่วนใน 30 วิ', 'Quick video upload in 30s')) ?>">
              <span>🔥</span>
              <span><?= e(t('หรือลงคลิป Reels ด่วน (30 วิ) ›', 'Or Quick Post Reel (30s) ›')) ?></span>
            </a>
          </div>
          <h1><?= e(t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')) ?></h1>
          <p><?= e(t('กรอกข้อมูลจำเป็นไม่กี่ช่อง แล้วเพิ่มรูปทรัพย์อย่างน้อย 1 รูป เพื่อให้ผู้ซื้อและผู้เช่าทั่วประเทศเข้าถึงทรัพย์ของคุณได้ทันที', 'Fill in just a few required fields, then add at least 1 photo of the property so buyers nationwide can discover your listing.')) ?></p>
        </div>
        <div class="listing-progress" aria-label="<?= e(t('ขั้นตอนการลงประกาศ', 'Listing steps')) ?>">
          <span><b>1</b> <?= e(t('ข้อมูลและพิกัด', 'Details & location')) ?></span><i></i>
          <span><b>2</b> <?= e(t('รายละเอียดทรัพย์', 'Property details')) ?></span><i></i>
          <span><b>3</b> <?= e(t('รูปจริงและติดต่อ', 'Photos & contact')) ?></span>
        </div>
        <p class="listing-required-note"><span aria-hidden="true">*</span> <?= e(t('ช่องจำเป็นมีเครื่องหมายดอกจัน รายละเอียดอื่นกรอกเพิ่มได้ตามสะดวก · เพื่อป้องกันสแปม บัญชีลงได้ไม่เกิน 5 ประกาศต่อชั่วโมง', 'Required fields are marked with an asterisk. Everything else is optional · To prevent spam, each account can post up to 5 listings per hour.')) ?></p>

        <?php if ($errors): ?>
          <div class="alert error" id="listingFormErrors" role="alert" tabindex="-1" aria-live="assertive">
            <?php foreach ($errors as $err): ?>
              <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-grid listing-form">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">

          <div class="hp" aria-hidden="true">
            <label><?= e(t('เว็บไซต์', 'Website')) ?></label>
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>01</span><div><h2><?= e(t('ข้อมูลอสังหาริมทรัพย์', 'Property information')) ?></h2><p><?= e(t('ข้อมูลหลักที่จะแสดงบนหน้าประกาศ', 'The key details shown on your listing page')) ?></p></div></div>
            <div class="listing-form-fields">
              <label><?= e(t('ประเภทอสังหาฯ', 'Property type')) ?> *
                <select name="property_category" id="propertyCategory" required>
                  <option value=""><?= e(t('เลือกประเภทอสังหาฯ', 'Select property type')) ?></option>
                  <?php foreach (PROPERTY_CATEGORIES as $key => $label):
                    if ($key === 'other') continue; ?>
                    <option value="<?= e($key) ?>" <?= $cat === $key ? 'selected' : '' ?>><?= e(is_en() ? (property_category_en_label($key) ?? $label) : $label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><?= e(t('หัวข้อประกาศ', 'Listing title')) ?> *
                <input type="text" name="title" minlength="5" value="<?= $v('title') ?>" placeholder="<?= e(t('เช่น คอนโดใกล้ BTS อโศก', 'e.g. Condo near BTS Asok')) ?>" required>
              </label>
              <fieldset class="listing-type-field field-full">
                <legend><?= e(t('ต้องการ', 'Looking to')) ?> *</legend>
                <div class="listing-type-options">
                  <label><input type="radio" name="type" value="sale" <?= ($old['type'] ?? '') !== 'rent' ? 'checked' : '' ?>> <?= e(t('ขาย', 'For sale')) ?></label>
                  <label><input type="radio" name="type" value="rent" <?= ($old['type'] ?? '') === 'rent' ? 'checked' : '' ?>> <?= e(t('ให้เช่า', 'For rent')) ?></label>
                </div>
              </fieldset>
              <label><?= e(t('ราคา (บาท)', 'Price (THB)')) ?> *
                <input type="text" inputmode="numeric" name="price" value="<?= $v('price') ?>" placeholder="<?= e(t('เช่น 3500000', 'e.g. 3500000')) ?>" required>
              </label>
              <label class="field-full"><?= e(t('ทำเล / ที่อยู่', 'Location / Address')) ?> *
                <input type="text" name="location" value="<?= $v('location') ?>" placeholder="<?= e(t('เช่น สุขุมวิท 39, กรุงเทพฯ', 'e.g. Sukhumvit 39, Bangkok')) ?>" required>
              </label>
              <label><?= e(t('ห้องนอน', 'Bedrooms')) ?>
                <input type="number" name="bedrooms" id="bedrooms" min="0" max="99" value="<?= $v('bedrooms') ?>" placeholder="<?= e(t('เช่น 2', 'e.g. 2')) ?>">
              </label>
              <label><?= e(t('ห้องน้ำ', 'Bathrooms')) ?>
                <input type="number" name="bathrooms" id="bathrooms" min="0" max="99" value="<?= $v('bathrooms') ?>" placeholder="<?= e(t('เช่น 2', 'e.g. 2')) ?>">
              </label>
              <label><?= e(t('พื้นที่ใช้สอย (ตร.ม.)', 'Floor area (sq.m.)')) ?>
                <input type="number" name="area_sqm" id="area_sqm" min="0" step="0.01" value="<?= $v('area_sqm') ?>" placeholder="<?= e(t('เช่น 85.5', 'e.g. 85.5')) ?>">
              </label>
              <label><?= e(t('พื้นที่ดิน (ตร.ว.)', 'Land area (sq.wa)')) ?>
                <input type="number" name="land_area" id="land_area" min="0" step="0.01" value="<?= $v('land_area') ?>" placeholder="<?= e(t('เช่น 50', 'e.g. 50')) ?>">
              </label>
              <label><?= e(t('ที่จอดรถ (คัน)', 'Parking spaces')) ?>
                <input type="number" name="parking_spaces" id="parking_spaces" min="0" max="99" value="<?= $v('parking_spaces') ?>" placeholder="<?= e(t('ไม่บังคับ', 'Optional')) ?>">
              </label>
              <label><?= e(t('ชั้น', 'Floor')) ?>
                <input type="text" name="floor_number" id="floor_number" value="<?= $v('floor_number') ?>" placeholder="<?= e(t('เช่น 12 หรือ 1-2', 'e.g. 12 or 1-2')) ?>">
              </label>
              <label><?= e(t('เฟอร์นิเจอร์', 'Furnishing')) ?>
                <select name="furnished" id="furnished">
                  <option value=""><?= e(t('เลือกสถานะเฟอร์นิเจอร์', 'Select furnishing status')) ?></option>
                  <?php foreach ($furnishedLabelsEn as $furnishedValue => $furnishedLabel):
                    $furnishedDisplay = is_en() ? $furnishedLabel : $furnishedValue; ?>
                    <option value="<?= e($furnishedValue) ?>" <?= ($old['furnished'] ?? '') === $furnishedValue ? 'selected' : '' ?>><?= e($furnishedDisplay) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field-full"><?= e(t('สิ่งอำนวยความสะดวก (ไม่บังคับ)', 'Amenities (optional)')) ?>
                <textarea name="amenities" rows="3" placeholder="<?= e(t('เช่น สระว่ายน้ำ, ฟิตเนส, รปภ.', 'e.g. Swimming pool, gym, 24-hour security')) ?>"><?= $v('amenities') ?></textarea>
              </label>
              <label class="field-full"><?= e(t('สถานที่ใกล้เคียง (ไม่บังคับ)', 'Nearby places (optional)')) ?>
                <textarea name="nearby_places" rows="3" placeholder="<?= e(t('เช่น BTS อโศก, โรงเรียน, ห้างสรรพสินค้า', 'e.g. BTS Asok, schools, shopping malls')) ?>"><?= $v('nearby_places') ?></textarea>
              </label>
              <div class="field-full listing-map-picker">
                <div class="listing-map-heading">
                  <div><strong><?= e(t('ตำแหน่งบนแผนที่', 'Map location')) ?> <span>(<?= e(t('ไม่บังคับ', 'optional')) ?>)</span></strong><small><?= e(t('เปิดแผนที่แล้วแตะเพื่อปักหมุด หรือลากหมุดไปยังตำแหน่งทรัพย์', 'Open the map, then tap to drop a pin or drag it to the property position')) ?></small></div>
                  <button type="button" class="listing-map-toggle" id="listingMapToggle" aria-expanded="false" aria-controls="listingMapPanel"><?= e(t('เปิดแผนที่', 'Open map')) ?></button>
                </div>
                <div id="listingMapPanel" class="listing-map-panel" hidden>
                  <div id="listingMap" class="listing-map" role="application" aria-label="<?= e(t('แผนที่เลือกตำแหน่งทรัพย์', 'Map for selecting the property position')) ?>"></div>
                  <p class="listing-map-help"><?= e(t('เลื่อนและซูมแผนที่ได้ จากนั้นแตะตำแหน่งเพื่อวางหมุด สามารถลากหมุดเพื่อปรับตำแหน่งได้', 'Pan and zoom the map, then tap a position to place the pin. You can also drag the pin to adjust it.')) ?></p>
                </div>
                <div class="listing-map-selection">
                  <span id="listingMapStatus" role="status" aria-live="polite"><?= e(t('ยังไม่ได้เลือกตำแหน่ง · สามารถลงประกาศโดยไม่เลือกแผนที่ได้', 'No position selected · You can post a listing without choosing a map position')) ?></span>
                  <button type="button" id="listingMapClear" class="listing-map-clear" hidden><?= e(t('ล้างหมุด', 'Clear pin')) ?></button>
                </div>
                <input type="hidden" name="map_lat" id="listingMapLat" value="<?= e((string)($old['map_lat'] ?? '')) ?>">
                <input type="hidden" name="map_lng" id="listingMapLng" value="<?= e((string)($old['map_lng'] ?? '')) ?>">
              </div>
            </div>
          </section>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>02</span><div><h2><?= e(t('รายละเอียดประกาศ', 'Listing description')) ?></h2><p><?= e(t('บอกจุดเด่นของทรัพย์เพื่อช่วยให้ผู้สนใจตัดสินใจ', 'Highlight what makes the property special to help buyers decide')) ?></p></div></div>
            <div class="listing-form-fields">
              <label class="field-full"><?= e(t('รายละเอียดเพิ่มเติม', 'Description')) ?> *
                <textarea name="description" minlength="20" rows="5" required placeholder="<?= e(t('บอกจุดเด่นหรือรายละเอียดที่ผู้สนใจควรรู้ อย่างน้อย 20 ตัวอักษร', 'Describe the highlights or details interested buyers should know. At least 20 characters.')) ?>"><?= $v('description') ?></textarea>
              </label>
            </div>
          </section>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>03</span><div><h2><?= e(t('รูปภาพและข้อมูลติดต่อ', 'Photos &amp; contact information')) ?></h2><p><?= e(t('รูปภาพและเบอร์โทรจะแสดงในหน้ารายละเอียดประกาศ', 'Photos and phone number are shown on the listing page')) ?></p></div></div>
            <div class="listing-form-fields">
              <!-- Modern Photo Upload Zone -->
              <div class="listing-upload field-full" id="listingImagesDropzone">
                <div class="listing-upload-icon">📷</div>
                <strong><?= e(t('เพิ่มรูปภาพอสังหาริมทรัพย์', 'Add property photos')) ?> *</strong>
                <small><?= e(t('แตะเพื่อเลือกรูปภาพ หรือลากไฟล์มาวาง · สูงสุด ', 'Tap to select photos or drag & drop · up to ')) ?><?= MAX_IMAGES ?><?= e(t(' รูป (JPG, PNG, WebP ขนาดไม่เกิน 5 MB ต่อรูป)', ' photos (JPG, PNG, WebP max 5 MB each)')) ?></small>
                <span class="listing-upload-btn">
                  <span>📁</span> <span><?= e(t('เลือกรูปภาพจากเครื่อง', 'Browse Photos')) ?></span>
                </span>
                <input id="listingImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
              </div>
              <input type="hidden" name="cover_index" id="listingCoverIndex" value="0">
              <div id="listingCoverNotice" class="listing-cover-notice field-full" style="display:none;">
                <span class="cover-icon">★</span>
                <span><?= e(t('เลือกรูปภาพเป็นหน้าปกประกาศได้ โดยแตะที่ปุ่ม "ตั้งเป็นหน้าปก" บนรูปที่ต้องการ (ค่าเริ่มต้นคือรูปแรก)', 'Select a cover photo by tapping "Set as cover" on your chosen photo (default is first photo).')) ?></span>
              </div>
              <div id="listingImagePreview" class="listing-image-preview field-full" aria-live="polite"></div>

              <!-- Modern Video Upload Zone -->
              <div class="listing-upload field-full" id="listingVideoDropzone">
                <div class="listing-upload-icon" style="background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.35);">🎬</div>
                <strong><?= e(t('วิดีโอคลิปของประกาศ (ไม่บังคับ - มีพรีวิวบนฟีด Reels และหน้าแรก)', 'Property video clip (Optional - plays on Reels feed & homepage)')) ?></strong>
                <small><?= e(t('แตะเพื่อเลือกคลิปพาทัวร์ทรัพย์ · MP4, WebM หรือ MOV ขนาดไม่เกิน 40 MB', 'Tap to select video tour · MP4, WebM or MOV up to 40 MB')) ?></small>
                <span class="listing-upload-btn" style="background:#0B1C33; color:#F8D888;">
                  <span>📹</span> <span><?= e(t('เลือกไฟล์วิดีโอ', 'Browse Video')) ?></span>
                </span>
                <input id="listingVideo" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" style="display:none;">
              </div>

              <!-- Video Live Preview Player Box -->
              <div class="listing-video-preview-box field-full" id="listingVideoPreviewBox">
                <video id="listingVideoPreviewPlayer" controls playsinline></video>
                <button type="button" class="listing-video-remove-btn" id="listingVideoRemoveBtn">✕ <?= e(t('เปลี่ยนวิดีโอ', 'Change video')) ?></button>
              </div>
              <label><?= e(t('ชื่อผู้ติดต่อ', 'Contact name')) ?> *
                <input type="text" name="contact_name" value="<?= $v('contact_name') ?>" placeholder="<?= e(t('ชื่อที่ให้ผู้สนใจติดต่อ', 'Name for interested buyers to contact')) ?>" required>
                <small><?= e(t('เติมชื่อจากบัญชีของคุณให้แล้ว แก้ไขได้ก่อนส่ง', 'Pre-filled from your account. You can edit it before submitting.')) ?></small>
              </label>
              <label><?= e(t('เบอร์โทรศัพท์', 'Phone number')) ?> *
                <input type="tel" name="phone" value="<?= $v('phone') ?>" placeholder="095-906-5867" required>
              </label>
              <label><?= e(t('LINE ID (ไม่บังคับ)', 'LINE ID (optional)')) ?>
                <input type="text" name="line_id" maxlength="20" value="<?= $v('line_id') ?>" placeholder="<?= e(t('เช่น yourlineid', 'e.g. yourlineid')) ?>">
              </label>
            </div>
          </section>

          <div class="alert" role="note"><?= e(t('ระบบตรวจความครบถ้วนของข้อมูล รูปภาพ พิกัด และประกาศซ้ำ แล้วเผยแพร่ทันทีเมื่อผ่านเกณฑ์ การตรวจนี้ไม่สามารถยืนยันกรรมสิทธิ์หรือรับรองว่าทรัพย์มีอยู่จริงได้ โปรดกรอกข้อมูลตามจริงและใส่ภาพถ่ายของทรัพย์', 'The system checks completeness of information, photos, coordinates and duplicates, then publishes immediately once approved. This check cannot verify ownership or confirm that the property exists. Please provide accurate information and upload real photos of the property.')) ?></div>

          <label class="check listing-agreement">
            <input type="checkbox" name="agree" value="1" required>
            <span><?= e(t('ข้าพเจ้ายืนยันว่าเป็นเจ้าของทรัพย์หรือได้รับอนุญาตให้ลงประกาศ ข้อมูลและรูปภาพเป็นความจริง และยอมรับข้อตกลงการใช้งานกับนโยบายความเป็นส่วนตัว', 'I confirm that I am the owner of this property or have permission to list it. The information and photos are accurate, and I accept the Terms of Use and Privacy Policy.')) ?></span>
          </label>

          <button type="submit" class="btn btn-block listing-submit"><?= e(t('ตรวจข้อมูลและเผยแพร่ทันที', 'Review & publish now')) ?></button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php
render_page_cta([
    'title'   => t('ประกาศเสร็จแล้ว? ลองดูคู่มือทำเลยอดนิยม', 'Listing posted? Explore popular location guides'),
    'text'    => t('เรียนรู้ว่าทำเลแบบไหนปล่อยเช่าได้ดี หรือเริ่มดูราคาตลาดในหมวดยอดนิยมของเรา', 'Learn which locations rent out well, or start comparing market prices in our popular categories'),
    'primary' => ['index.php', t('กลับหน้าแรก', 'Back to home page')],
    'ghost'   => ['dashboard.php', t('จัดการประกาศของฉัน', 'Manage my listings')],
]);
?>

<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>
<script>
  const imageInput = document.getElementById('listingImages');
  const imagePreview = document.getElementById('listingImagePreview');
  const coverIndexInput = document.getElementById('listingCoverIndex');
  const coverNotice = document.getElementById('listingCoverNotice');

  if (imageInput && imagePreview) {
    let previewUrls = [];
    let selectedFiles = [];
    let activeCoverIndex = 0;

    const renderPreviews = () => {
      previewUrls.forEach(url => URL.revokeObjectURL(url));
      previewUrls = [];
      imagePreview.replaceChildren();

      if (selectedFiles.length > 0 && coverNotice) {
        coverNotice.style.display = 'flex';
      } else if (coverNotice) {
        coverNotice.style.display = 'none';
      }

      selectedFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'listing-preview-card' + (index === activeCoverIndex ? ' is-cover' : '');
        item.dataset.index = String(index);

        const img = document.createElement('img');
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        img.src = url;
        img.alt = file.name;

        // Badge หน้าปก
        const badge = document.createElement('span');
        badge.className = 'cover-badge';
        badge.textContent = index === activeCoverIndex ? '★ <?= e(t('หน้าปก', 'Cover')) ?>' : (index + 1);

        // ปุ่มเลือกเป็นหน้าปก
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'set-cover-btn';
        btn.textContent = index === activeCoverIndex ? '✓ <?= e(t('หน้าปกปัจจุบัน', 'Current cover')) ?>' : '<?= e(t('ตั้งเป็นหน้าปก', 'Set as cover')) ?>';
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          activeCoverIndex = index;
          if (coverIndexInput) coverIndexInput.value = String(index);
          renderPreviews();
        });

        item.addEventListener('click', () => {
          activeCoverIndex = index;
          if (coverIndexInput) coverIndexInput.value = String(index);
          renderPreviews();
        });

        item.appendChild(img);
        item.appendChild(badge);
        item.appendChild(btn);
        imagePreview.appendChild(item);
      });
    };

    // Custom Drag & Drop / Click for Photos Zone
    const imagesDropzone = document.getElementById('listingImagesDropzone');
    imagesDropzone?.addEventListener('click', (e) => {
      // Prevent recursive click if clicked on input itself
      if (e.target !== imageInput) {
        imageInput?.click();
      }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      imagesDropzone?.addEventListener(eventName, (e) => {
        e.preventDefault();
        imagesDropzone.style.borderColor = '#A8843F';
        imagesDropzone.style.background = '#F5EFE6';
      });
    });
    ['dragleave', 'drop'].forEach(eventName => {
      imagesDropzone?.addEventListener(eventName, (e) => {
        e.preventDefault();
        imagesDropzone.style.borderColor = '#C5A059';
        imagesDropzone.style.background = '#FAF8F5';
      });
    });
    imagesDropzone?.addEventListener('drop', (e) => {
      if (e.dataTransfer?.files?.length && imageInput) {
        imageInput.files = e.dataTransfer.files;
        imageInput.dispatchEvent(new Event('change'));
      }
    });

    imageInput.addEventListener('change', () => {
      const files = Array.from(imageInput.files || []).slice(0, <?= MAX_IMAGES ?>).filter(f => f.type.startsWith('image/'));
      imageInput.setCustomValidity(files.length < 1 ? '<?= e(t('กรุณาเลือกรูปทรัพย์อย่างน้อย 1 รูป', 'Please select at least 1 property photo.')) ?>' : '');
      selectedFiles = files;
      activeCoverIndex = 0;
      if (coverIndexInput) coverIndexInput.value = '0';
      renderPreviews();
    });
  }

  // Custom Video Upload & Live Video Preview Box (Same sleek studio experience as submit-reel)
  const videoInput = document.getElementById('listingVideo');
  const videoDropzone = document.getElementById('listingVideoDropzone');
  const videoPreviewBox = document.getElementById('listingVideoPreviewBox');
  const videoPreviewPlayer = document.getElementById('listingVideoPreviewPlayer');
  const videoRemoveBtn = document.getElementById('listingVideoRemoveBtn');

  videoDropzone?.addEventListener('click', (e) => {
    if (e.target !== videoInput) {
      videoInput?.click();
    }
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    videoDropzone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      videoDropzone.style.borderColor = '#A8843F';
      videoDropzone.style.background = '#F5EFE6';
    });
  });
  ['dragleave', 'drop'].forEach(eventName => {
    videoDropzone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      videoDropzone.style.borderColor = '#C5A059';
      videoDropzone.style.background = '#FAF8F5';
    });
  });
  videoDropzone?.addEventListener('drop', (e) => {
    if (e.dataTransfer?.files?.length && videoInput) {
      videoInput.files = e.dataTransfer.files;
      videoInput.dispatchEvent(new Event('change'));
    }
  });

  videoInput?.addEventListener('change', () => {
    const file = videoInput.files?.[0];
    if (file) {
      if (file.size > 40 * 1024 * 1024) {
        alert('<?= e(t('ไฟล์วิดีโอต้องมีขนาดไม่เกิน 40 MB', 'Video must be less than 40 MB')) ?>');
        videoInput.value = '';
        return;
      }
      const url = URL.createObjectURL(file);
      if (videoPreviewPlayer) {
        videoPreviewPlayer.src = url;
        videoPreviewPlayer.play().catch(() => {});
      }
      if (videoDropzone) videoDropzone.style.display = 'none';
      if (videoPreviewBox) videoPreviewBox.style.display = 'block';
    }
  });

  videoRemoveBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (videoInput) videoInput.value = '';
    if (videoPreviewPlayer) {
      videoPreviewPlayer.pause();
      videoPreviewPlayer.src = '';
    }
    if (videoPreviewBox) videoPreviewBox.style.display = 'none';
    if (videoDropzone) videoDropzone.style.display = 'flex';
  });
  const propertyCategory = document.getElementById('propertyCategory');
  // Optional property details remain optional for every category.
  const mapToggle = document.getElementById('listingMapToggle');
  const mapPanel = document.getElementById('listingMapPanel');
  const mapElement = document.getElementById('listingMap');
  const mapLatInput = document.getElementById('listingMapLat');
  const mapLngInput = document.getElementById('listingMapLng');
  const mapStatus = document.getElementById('listingMapStatus');
  const mapClear = document.getElementById('listingMapClear');
  let listingMap = null;
  let listingMarker = null;
  const savedLat = Number(mapLatInput?.value);
  const savedLng = Number(mapLngInput?.value);
  const hasSavedCoords = mapLatInput?.value !== '' && mapLngInput?.value !== '' && Number.isFinite(savedLat) && Number.isFinite(savedLng) && Math.abs(savedLat) <= 90 && Math.abs(savedLng) <= 180;
  if (hasSavedCoords) {
    mapStatus.textContent = `<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${savedLat.toFixed(6)}, ${savedLng.toFixed(6)}`;
    mapClear.hidden = false;
  }
  const updateMapSelection = (lat, lng) => {
    const coords = [lat, lng];
    mapLatInput.value = Number(lat).toFixed(6);
    mapLngInput.value = Number(lng).toFixed(6);
    if (!listingMarker) {
      listingMarker = L.marker(coords, { draggable: true }).addTo(listingMap);
      listingMarker.on('dragend', () => {
        const point = listingMarker.getLatLng();
        updateMapSelection(point.lat, point.lng);
      });
    } else {
      listingMarker.setLatLng(coords);
    }
    mapStatus.textContent = `<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
    mapClear.hidden = false;
  };
  const openListingMap = () => {
    mapPanel.hidden = false;
    mapToggle.setAttribute('aria-expanded', 'true');
    mapToggle.textContent = '<?= e(t('ปิดแผนที่', 'Close map')) ?>';
    if (typeof L === 'undefined') {
      mapStatus.textContent = '<?= e(t('โหลดแผนที่ไม่สำเร็จ คุณยังลงประกาศได้โดยไม่เลือกตำแหน่ง', 'Failed to load the map. You can still post a listing without selecting a position.')) ?>';
      return;
    }
    const selectedLat = Number(mapLatInput.value);
    const selectedLng = Number(mapLngInput.value);
    const hasSelection = mapLatInput.value !== '' && mapLngInput.value !== '' && Number.isFinite(selectedLat) && Number.isFinite(selectedLng) && Math.abs(selectedLat) <= 90 && Math.abs(selectedLng) <= 180;
    if (!listingMap) {
      listingMap = L.map(mapElement, { scrollWheelZoom: false }).setView(hasSelection ? [selectedLat, selectedLng] : [13.2, 101.0], hasSelection ? 15 : 6);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
      }).addTo(listingMap);
      listingMap.on('click', event => updateMapSelection(event.latlng.lat, event.latlng.lng));
      if (hasSelection) updateMapSelection(selectedLat, selectedLng);
    }
    requestAnimationFrame(() => listingMap.invalidateSize());
  };
  mapToggle?.addEventListener('click', () => {
    if (mapPanel.hidden) {
      openListingMap();
    } else {
      mapPanel.hidden = true;
      mapToggle.setAttribute('aria-expanded', 'false');
      mapToggle.textContent = '<?= e(t('เปิดแผนที่', 'Open map')) ?>';
    }
  });
  mapClear?.addEventListener('click', () => {
    mapLatInput.value = '';
    mapLngInput.value = '';
    listingMarker?.remove();
    listingMarker = null;
    mapStatus.textContent = '<?= e(t('ยังไม่ได้เลือกตำแหน่ง · สามารถลงประกาศโดยไม่เลือกแผนที่ได้', 'No position selected · You can post a listing without choosing a map position')) ?>';
    mapClear.hidden = true;
  });
  // Form submit validation & upload progress feedback
  const listingForm = document.querySelector('.listing-form');
  const listingSubmitBtn = listingForm?.querySelector('.listing-submit');

  listingForm?.addEventListener('submit', (e) => {
    // Check if at least 1 image is selected
    if (!selectedFiles || selectedFiles.length < 1) {
      e.preventDefault();
      alert('<?= e(t('กรุณาเลือกรูปภาพอสังหาริมทรัพย์อย่างน้อย 1 รูป', 'Please upload at least 1 property photo')) ?>');
      const dropzone = document.getElementById('listingImagesDropzone');
      dropzone?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    // Submit state feedback
    if (listingSubmitBtn) {
      listingSubmitBtn.disabled = true;
      listingSubmitBtn.style.opacity = '0.8';
      listingSubmitBtn.innerHTML = '<span>⏳</span> <span><?= e(t('กำลังตรวจสอบข้อมูลและอัปโหลด... (กรุณารอสักครู่)', 'Reviewing and uploading... please wait')) ?></span>';
    }
  });
</script>
</body>
</html>
