<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin('content', 'view');

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageBrand = $isFounder || staff_can(db(), $staffId, 'content', 'edit') || staff_can(db(), $staffId, 'content', 'manage');

$message = '';
$errors = [];
$targetDir = dirname(__DIR__) . '/images/';
$rootDir   = dirname(__DIR__) . '/';

if (request_is_post()) {
    csrf_check();
    if (!$canManageBrand) {
        $errors[] = 'สิทธิ์ถูกจำกัด: เฉพาะ Founder & CEO หรือผู้ดูแลฝ่ายสื่อที่ได้รับอนุญาตเท่านั้น';
    } else {
        $action = (string)($_POST['action'] ?? '');

        // 1. อัปโหลด Favicon / Brand Icon ใหม่ (ขนาดจัตุรัสสำหรับ Google Search)
        if ($action === 'upload_favicon') {
            $file = $_FILES['favicon_image'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'กรุณาเลือกไฟล์ภาพก่อนทำการอัปโหลด';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'ขนาดไฟล์ภาพต้องไม่เกิน 5 MB';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

                if (!in_array($mime, $allowed, true)) {
                    $errors[] = 'รองรับเฉพาะไฟล์ PNG, JPG, WebP หรือ SVG เท่านั้น';
                } else {
                    $rawBytes = file_get_contents($file['tmp_name']);
                    if ($mime === 'image/svg+xml') {
                        // บันทึก SVG
                        file_put_contents($targetDir . 'favicon.svg', $rawBytes);
                        file_put_contents($rootDir . 'favicon.svg', $rawBytes);
                        $message = 'อัปเดตไฟล์ Favicon SVG สำเร็จแล้ว (Google จะรีเฟรชแคชตามรอบบอท)';
                    } else {
                        // บันทึก PNG และสร้างไฟล์ตามขนาดหลักที่ Google แนะนำ
                        // ใช้ base64 image data สำหรับ favicon.svg
                        $base64 = base64_encode($rawBytes);
                        $svgWrap = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192" width="100%" height="100%">' . "\n"
                                 . '  <image href="data:' . $mime . ';base64,' . $base64 . '" width="192" height="192" />' . "\n"
                                 . '</svg>';

                        file_put_contents($targetDir . 'favicon.svg', $svgWrap);
                        file_put_contents($rootDir . 'favicon.svg', $svgWrap);

                        // บันทึกภาพต้นฉบับคุณภาพสูงเป็น favicon.png และขนาดหลัก
                        file_put_contents($targetDir . 'favicon.png', $rawBytes);
                        file_put_contents($rootDir . 'favicon.png', $rawBytes);
                        file_put_contents($targetDir . 'favicon-48x48.png', $rawBytes);
                        file_put_contents($targetDir . 'favicon-32x32.png', $rawBytes);
                        file_put_contents($targetDir . 'favicon-16x16.png', $rawBytes);
                        file_put_contents($targetDir . 'favicon-96x96.png', $rawBytes);
                        file_put_contents($targetDir . 'android-chrome-192x192.png', $rawBytes);
                        file_put_contents($targetDir . 'apple-touch-icon.png', $rawBytes);
                        file_put_contents($rootDir . 'apple-touch-icon.png', $rawBytes);
                        file_put_contents($rootDir . 'favicon.ico', $rawBytes);
                        file_put_contents($targetDir . 'favicon.ico', $rawBytes);

                        $message = 'อัปเดต Favicon ทุกขนาด (16x16, 32x32, 48x48, SVG, Apple Touch Icon) เรียบร้อยแล้ว';
                    }
                }
            }
        }

        // 2. ใช้ไอคอน Kasa/PropertyGO Gold (โลโก้สีทองหรูหราปัจจุบัน)
        if ($action === 'use_kasa_gold') {
            $goldPath = $targetDir . 'gologo.png';
            if (is_file($goldPath)) {
                $rawBytes = file_get_contents($goldPath);
                $base64 = base64_encode($rawBytes);
                $svgWrap = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192" width="100%" height="100%">' . "\n"
                         . '  <image href="data:image/png;base64,' . $base64 . '" width="192" height="192" />' . "\n"
                         . '</svg>';

                file_put_contents($targetDir . 'favicon.svg', $svgWrap);
                file_put_contents($rootDir . 'favicon.svg', $svgWrap);
                file_put_contents($targetDir . 'favicon.png', $rawBytes);
                file_put_contents($rootDir . 'favicon.png', $rawBytes);
                file_put_contents($targetDir . 'favicon-48x48.png', $rawBytes);
                file_put_contents($targetDir . 'favicon-32x32.png', $rawBytes);
                file_put_contents($targetDir . 'favicon-16x16.png', $rawBytes);
                file_put_contents($targetDir . 'favicon.ico', $rawBytes);
                file_put_contents($rootDir . 'favicon.ico', $rawBytes);
                $message = 'นำสัญลักษณ์ PropertyGO Gold มาตั้งเป็น Favicon หลักเรียบร้อยแล้ว';
            }
        }
    }
}

// ตรวจสอบสถานะไฟล์ปัจจุบัน
$curFaviconVer = is_file($targetDir . 'favicon.png') ? filemtime($targetDir . 'favicon.png') : time();
$curIcoVer     = is_file($rootDir . 'favicon.ico') ? filemtime($rootDir . 'favicon.ico') : time();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการ Favicon & ไอคอนแสดงบน Google SEO | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .brand-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
      padding: 28px 32px;
      border-radius: 24px;
      background: linear-gradient(135deg,#0B1C33,#162B4A);
      color: #fff;
      border: 1px solid rgba(197,160,89,.35);
      margin-bottom: 26px;
      box-shadow: 0 14px 34px rgba(11,28,51,.12);
    }
    .brand-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .brand-hero h1 { margin:8px 0 4px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .brand-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:24px 28px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.04); }
    .brand-card h2 { margin:0 0 6px; color:#0B1C33; font-size:18px; display:flex; align-items:center; gap:8px; }
    .brand-card .intro { margin:0 0 18px; color:#64748b; font-size:13px; line-height:1.6; }

    /* ตัวจำลองผลลัพธ์ Google Search SERP */
    .google-serp-sim {
      border: 1px solid #dadce0;
      border-radius: 16px;
      padding: 20px;
      background: #fff;
      max-width: 640px;
      font-family: Arial, sans-serif;
    }
    .google-serp-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 6px;
    }
    .google-serp-fav-box {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #f1f3f4;
      display: grid;
      place-items: center;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      flex-shrink: 0;
    }
    .google-serp-fav-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .google-serp-brand {
      display: flex;
      flex-direction: column;
      line-height: 1.25;
    }
    .google-serp-name {
      font-size: 14px;
      font-weight: bold;
      color: #202124;
    }
    .google-serp-url {
      font-size: 12px;
      color: #4d5156;
    }
    .google-serp-title {
      font-size: 20px;
      color: #1a0dab;
      line-height: 1.3;
      margin: 4px 0 6px;
      font-weight: 400;
      cursor: pointer;
    }
    .google-serp-title:hover {
      text-decoration: underline;
    }
    .google-serp-snippet {
      font-size: 13px;
      color: #4d5156;
      line-height: 1.5;
    }

    .btn-gold {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 42px;
      padding: 0 20px;
      border: 0;
      border-radius: 10px;
      background: linear-gradient(135deg,#C5A059,#A8843F);
      color: #0B1C33;
      font: inherit;
      font-size: 13.5px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(197,160,89,.35);
      text-decoration: none;
      transition: transform .16s, box-shadow .16s;
    }
    .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(197,160,89,.45); }

    .current-icons-row {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      margin: 16px 0;
    }
    .icon-preview-box {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px;
      background: #fafbfc;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      min-width: 100px;
    }
    .icon-preview-box img {
      background: #fff;
      padding: 4px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }
    .icon-preview-label {
      font-size: 11px;
      color: #64748B;
      font-weight: 700;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">

  <!-- Hero Header -->
  <div class="brand-hero">
    <div>
      <span class="eyebrow"><?= e(t('GOOGLE SERP & BROWSER IDENTITY', 'GOOGLE SERP & BROWSER IDENTITY')) ?></span>
      <h1>🎨 <?= e(t('จัดการ Favicon & ไอคอนแสดงบน Google SEO', 'Favicon & Google SERP Brand Icon Manager')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;"><?= e(t('กำหนดรูปไอคอนประจำแบรนด์เพื่อให้ Google Search นำไปแสดงด้านหน้าชื่อเว็บในหน้าผลการค้นหา และบนแท็บเบราว์เซอร์', 'Manage your brand icon displayed alongside your website title in Google Search and browser tabs.')) ?></p>
    </div>
    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
      <span style="font-size:12px; color:#E5CB96; background:rgba(255,255,255,.08); padding:5px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.14);">
        ✓ <?= e(t('เกณฑ์ Google Search: ภาพสัดส่วน 1:1 ขนาด 48x48px ขึ้นไป', 'Google Guidelines: 1:1 ratio, min 48x48px')) ?>
      </span>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success" style="margin-bottom:20px;">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error" style="margin-bottom:20px;">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- 1. ตัวอย่างผลลัพธ์บน Google Search จริง (Live SERP Preview) -->
  <section class="brand-card">
    <h2>👁️ <?= e(t('ตัวอย่างผลลัพธ์บนหน้า Google Search (จำลองขนาดจริง)', 'Google Search Simulation (Live SERP Preview)')) ?></h2>
    <p class="intro"><?= e(t('เมื่อมีผู้ค้นหาชื่อแบรนด์หรือคีย์เวิร์ดอสังหาฯ ของคุณ Google Search จะดึงรูป Favicon นี้ไปวางด้านหน้าชื่อเว็บและ URL ตามรูปตัวอย่างด้านล่าง:', 'When visitors search your brand or real estate queries, Google SERP renders this icon next to your site title and URL:')) ?></p>

    <div class="google-serp-sim">
      <div class="google-serp-header">
        <div class="google-serp-fav-box">
          <img src="../images/favicon-48x48.png?v=<?= $curFaviconVer ?>" alt="<?= e(SITE_NAME) ?> Favicon">
        </div>
        <div class="google-serp-brand">
          <span class="google-serp-name"><?= e(SITE_NAME) ?></span>
          <span class="google-serp-url">https://standardproperty.cloud</span>
        </div>
      </div>
      <div class="google-serp-title">
        <?= e(t('ซื้อขายบ้าน บ้านเดี่ยว คอนโด ที่ดิน ทาวน์เฮ้าส์ อาคารพาณิชย์ | ' . SITE_NAME, 'Buy & Rent Houses, Condos, Land & Properties in Thailand | ' . SITE_NAME)) ?>
      </div>
      <div class="google-serp-snippet">
        <?= e(t('ศูนย์รวมประกาศซื้อ ขาย เช่า อสังหาริมทรัพย์ครบวงจร ทั้งบ้าน คอนโด ทาวน์โฮม ที่ดิน และอาคารพาณิชย์ ทั่วไทย ดูรูป ราคา และทำเล ติดต่อผู้ขายได้ทันที', 'Find verified houses, condos, townhouses and land for sale or rent across Thailand. Compare prices, view locations, or contact owners directly.')) ?>
      </div>
    </div>
  </section>

  <!-- 2. อัปโหลดภาพ Favicon ใหม่ -->
  <section class="brand-card">
    <h2>📤 <?= e(t('อัปโหลดภาพ Favicon ใหม่สำหรับแบรนด์ของคุณ', 'Upload New Brand Favicon')) ?></h2>
    <p class="intro"><?= e(t('แนะนำให้อัปโหลดภาพจัตุรัส (1:1) เช่น ขนาด 512x512px หรือ 192x192px เป็นไฟล์ PNG หรือ SVG เพื่อให้ไอคอนมีความคมชัดสูงสุดบนหน้าจอ Retina และ Google Search', 'Recommended square 1:1 image (512x512px or 192x192px PNG/SVG) for maximum sharpness on Retina displays and Google SERP.')) ?></p>

    <?php if ($canManageBrand): ?>
      <form method="post" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:16px; max-width:540px;">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="upload_favicon">

        <div>
          <label style="display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px;">
            <?= e(t('เลือกไฟล์รูปภาพ (PNG, JPG, WebP หรือ SVG) *', 'Select Image File (PNG, JPG, WebP or SVG) *')) ?>
          </label>
          <input type="file" name="favicon_image" accept="image/png,image/jpeg,image/webp,image/svg+xml" required style="padding:10px; border:1px solid #cbd5e1; border-radius:10px; width:100%; box-sizing:border-box; background:#fafbfc;">
          <small style="color:#64748B; font-size:12px; margin-top:4px; display:block;">
            💡 <?= e(t('เมื่ออัปโหลดเสร็จ ระบบจะอัปเดตไฟล์ Favicon ทุกขนาด (16px, 32px, 48px, 96px, 192px, ICO, SVG) ให้พร้อมใช้งานทันที', 'The system will generate all multi-resolution icon sizes automatically.')) ?>
          </small>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
          <button type="submit" class="btn-gold">🚀 <?= e(t('บันทึกและอัปเดต Favicon ทันที', 'Upload & Update Favicon Now')) ?></button>
        </div>
      </form>
    <?php else: ?>
      <p style="color:#64748B; font-size:13px;"><?= e(t('คุณมีสิทธิ์เข้าดูอย่างเดียว กรุณาติดต่อ Founder & CEO หากต้องการแก้ไข', 'View-only access. Contact Founder & CEO to make updates.')) ?></p>
    <?php endif; ?>
  </section>

  <!-- 3. คลังไอคอนที่ระบบใช้งานอยู่ ณ ปัจจุบัน -->
  <section class="brand-card">
    <h2>📁 <?= e(t('ไฟล์ Favicon ที่ระบบนำไปใช้จริง', 'Active System Favicon Package')) ?></h2>
    <p class="intro"><?= e(t('ระบบส่งออกไอคอนเหล่านี้ใน <head> ของทุกหน้าเว็บ เพื่อรองรับทั้งเบราว์เซอร์บนเดสก์ท็อป สมาร์ตโฟน iOS/Android และ Google Search Bot:', 'Exported in the <head> of all public pages to support mobile, desktop, and Googlebot crawlers:')) ?></p>

    <div class="current-icons-row">
      <div class="icon-preview-box">
        <img src="../images/favicon-48x48.png?v=<?= $curFaviconVer ?>" width="48" height="48" alt="48x48">
        <span class="icon-preview-label">48x48 px (Google)</span>
      </div>
      <div class="icon-preview-box">
        <img src="../images/favicon-32x32.png?v=<?= $curFaviconVer ?>" width="32" height="32" alt="32x32">
        <span class="icon-preview-label">32x32 px (Browser)</span>
      </div>
      <div class="icon-preview-box">
        <img src="../images/favicon-16x16.png?v=<?= $curFaviconVer ?>" width="16" height="16" alt="16x16">
        <span class="icon-preview-label">16x16 px (Tab)</span>
      </div>
      <div class="icon-preview-box">
        <img src="../images/favicon.svg?v=<?= $curFaviconVer ?>" width="48" height="48" alt="SVG">
        <span class="icon-preview-label">SVG (Vector)</span>
      </div>
    </div>

    <?php if ($canManageBrand): ?>
      <div style="margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
        <span style="font-size:13px; color:#475569; font-weight:600;">ต้องการสลับกลับมาใช้ไอคอนสีทองเริ่มต้น (PropertyGO Gold)?</span>
        <form method="post" style="display:inline; margin-left:10px;">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="use_kasa_gold">
          <button type="submit" class="btn" style="padding:6px 14px; font-size:12px; background:#f1f5f9; border:1px solid #cbd5e1; cursor:pointer;">
            ✨ รีเซ็ตเป็น PropertyGO Gold
          </button>
        </form>
      </div>
    <?php endif; ?>
  </section>

</main>
</body>
</html>
