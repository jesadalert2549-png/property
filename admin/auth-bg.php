<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/auth_media.php';
require_admin('content', 'view');

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageAuthBg = $isFounder || staff_can(db(), $staffId, 'content', 'manage') || staff_can(db(), $staffId, 'content', 'edit');

$message = '';
$errors = [];
$pdo = db();
$targetDir = dirname(__DIR__) . '/uploads/auth/';

if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);

$serverLimits = get_server_upload_limit();

// ตรวจสอบกรณีขนาดไฟล์ POST เกินลิมิตเซิร์ฟเวอร์จน $_POST และ CSRF ว่างเปล่า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $uploadedBytes = (int)$_SERVER['CONTENT_LENGTH'];
    $uploadedMb = round($uploadedBytes / (1024 * 1024), 1);
    $errors[] = t(
        "ไฟล์วิดีโอมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์ตั้งค่าไว้ ({$uploadedMb} MB เกินเพดาน post_max_size={$serverLimits['post_max']}) กรุณาบีบอัดไฟล์ หรือปรับค่า post_max_size ใน php.ini เป็น 128M",
        "Uploaded payload ({$uploadedMb} MB) exceeds server post_max_size ({$serverLimits['post_max']}). Please compress the file or increase limits in php.ini."
    );
}

if (request_is_post()) {
    csrf_check();
    if (!$canManageAuthBg) {
        $errors[] = t('สิทธิ์ถูกจำกัด: เฉพาะ Founder & CEO หรือผู้ดูแลฝ่ายสื่อที่ได้รับอนุญาตเท่านั้น', 'Restricted access: Founder & CEO only');
    } else {
        $action = (string)($_POST['action'] ?? '');

        // 1. อัปโหลดภาพหรือวิดีโอพื้นหลัง Login / Register ใหม่ (รองรับไม่เกิน 100 MB)
        if ($action === 'upload_bg') {
            try {
                $res = save_auth_background($_FILES['auth_bg_image'] ?? []);
                $message = t('อัปเดตสื่อพื้นหลัง (' . ($res['type'] === 'video' ? 'วิดีโอลูป MP4' : 'ภาพนิ่ง') . ') หน้าเข้าสู่ระบบและสมัครสมาชิกเรียบร้อยแล้ว! (บันทึกถาวรแบบ Dual-Storage)', 'Login and Register background media updated successfully with permanent backup!');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // 2. คืนค่าภาพพื้นหลังเริ่มต้น
        if ($action === 'reset_bg') {
            reset_auth_background();
            $message = t('รีเซ็ตภาพพื้นหลังกลับเป็นค่าเริ่มต้นเรียบร้อยแล้ว', 'Background image reset to default.');
        }
    }
}

$currentBg = get_auth_background();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('ภาพพื้นหลังหน้า Login & Register', 'Login & Register Background')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .auth-bg-hero {
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
    .auth-bg-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .auth-bg-hero h1 { margin:8px 0 4px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .dash-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:24px 28px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.04); }

    .preview-frame {
      position: relative;
      border-radius: 18px;
      overflow: hidden;
      height: 320px;
      background-size: cover;
      background-position: center;
      border: 2px solid #C5A059;
      box-shadow: 0 12px 30px rgba(0,0,0,0.15);
      display: grid;
      place-items: center;
    }
    .preview-box-mock {
      background: #fff;
      padding: 20px 30px;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      text-align: center;
      max-width: 260px;
      width: 90%;
    }

    .btn-gold {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 0 22px; border: 0; border-radius: 10px;
      background: linear-gradient(135deg,#C5A059,#A8843F); color: #0B1C33; font: inherit; font-size: 13.5px; font-weight: 800; cursor: pointer; text-decoration: none;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">

  <div class="auth-bg-hero">
    <div>
      <span class="eyebrow">BRAND PORTAL &amp; AUTHENTICATION WALLPAPER</span>
      <h1>🖼️ <?= e(t('ภาพพื้นหลังหน้า Login & Register', 'Login & Register Background Manager')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">
        <?= e(t('เปลี่ยนภาพปกพื้นหลังสำหรับหน้าเข้าสู่ระบบและหน้าสมัครสมาชิก ให้สวยงามตามสัดส่วนจอทุกขนาด (Responsive Cover)', 'Customize full-bleed wallpaper for authentication screens with high-definition auto-scaling.')) ?>
      </p>
    </div>
    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
      <span style="font-size:12px; color:#E5CB96; background:rgba(255,255,255,.08); padding:5px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.14);">
        👑 <?= e(t('เอกสิทธิ์การเปลี่ยนภาพเฉพาะ CEO', 'Founder & CEO Authority')) ?>
      </span>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success" style="margin-bottom:20px;">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error" style="margin-bottom:20px;">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- ภาพพรีวิวปัจจุบัน -->
  <section class="dash-card">
    <h2 style="margin:0 0 6px; font-size:18px; color:#0B1C33;">👁️ <?= e(t('ภาพหรือวิดีโอพื้นหลังที่ระบบใช้งานอยู่ปัจจุบัน', 'Live Authentication Media Preview')) ?></h2>
    <p style="margin:0 0 16px; font-size:13px; color:#64748B;">
      <?= e(t('จำลองภาพหน้าจอ Login เมื่อผู้ใช้เปิดเข้ามาใช้งานจริง (รองรับทั้งภาพนิ่งและวิดีโอลูป MP4):', 'Live rendering preview as seen by visitors on login and signup pages:')) ?>
    </p>

    <?php $authMedia = get_auth_media(); ?>
    <div class="preview-frame" style="<?= $authMedia['type']==='image' ? "background-image: linear-gradient(135deg, rgba(9, 24, 39, .84), rgba(24, 50, 75, .58)), url('../" . e($authMedia['url']) . "');" : "" ?>">
      <?php if ($authMedia['type'] === 'video'): ?>
        <video src="../<?= e($authMedia['url']) ?>" autoplay muted loop playsinline style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:0;"></video>
        <div style="position:absolute; inset:0; background:linear-gradient(135deg, rgba(9, 24, 39, .84), rgba(24, 50, 75, .58)); z-index:1;"></div>
      <?php endif; ?>
      <div class="preview-box-mock" style="position:relative; z-index:2;">
        <strong style="display:block; font-size:16px; color:#0B1C33;"><?= e(SITE_NAME) ?></strong>
        <span style="font-size:13px; color:#64748B;"><?= e(t('เข้าสู่ระบบ', 'Sign in')) ?></span>
      </div>
    </div>
  </section>

  <!-- ฟอร์มอัปโหลดภาพหรือวิดีโอใหม่ -->
  <section class="dash-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
      <h2 style="margin:0; font-size:18px; color:#0B1C33;">📤 <?= e(t('อัปโหลดภาพหรือวิดีโอพื้นหลังใหม่ (ไม่เกิน 100 MB)', 'Upload New Background Image or Video (up to 100 MB)')) ?></h2>
      <span style="font-size:12px; color:#475569; background:#f1f5f9; padding:4px 10px; border-radius:6px; font-weight:700;">
        ⚙️ <?= e(t('เพดานเซิร์ฟเวอร์ปัจจุบัน', 'Server PHP Limit')) ?>: upload=<?= e($serverLimits['upload_max']) ?>, post=<?= e($serverLimits['post_max']) ?>
      </span>
    </div>
    <p style="margin:0 0 16px; font-size:13px; color:#64748B;">
      <?= e(t('รองรับไฟล์ภาพ (JPG, PNG, WebP) หรือไฟล์คลิปวิดีโอ (MP4, WebM, MOV) ความละเอียดสูง ขนาดไฟล์สูงสุดไม่เกิน 100 MB', 'Supports high-res images (JPG, PNG, WebP) or video clips (MP4, WebM, MOV) up to 100 MB.')) ?>
    </p>

    <?php if ($canManageAuthBg): ?>
      <form method="post" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:16px; max-width:540px;">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="upload_bg">

        <div>
          <label style="display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px;">
            <?= e(t('เลือกไฟล์รูปภาพหรือวิดีโอใหม่ (MP4, WebM, MOV, JPG, PNG) *', 'Choose Image or Video File (MP4, WebM, MOV, JPG, PNG) *')) ?>
          </label>
          <input type="file" name="auth_bg_image" accept="video/mp4,video/webm,video/quicktime,image/jpeg,image/png,image/webp" required style="padding:10px; border:1px solid #cbd5e1; border-radius:10px; width:100%; box-sizing:border-box; background:#fafbfc;">
          <small style="color:#64748B; font-size:12px; margin-top:4px; display:block;">
            💡 <?= e(t('หากอัปโหลดเป็นไฟล์วิดีโอ ระบบจะเล่นลูปวนซ้ำอัตโนมัติแบบ Fullscreen ทันทีที่เปิดหน้า Login / Register', 'Uploaded video will automatically loop in fullscreen behind the auth card.')) ?>
          </small>
        </div>

        <div style="display:flex; gap:12px; align-items:center;">
          <button type="submit" class="btn-gold">🚀 <?= e(t('บันทึกสื่อพื้นหลังใหม่', 'Save Background Media')) ?></button>
        </div>
      </form>

      <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0;">
        <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(t('ต้องการคืนค่าภาพพื้นหลังเป็นค่าเริ่มต้นใช่หรือไม่?', 'Reset wallpaper to default?')) ?>');">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="reset_bg">
          <button type="submit" class="btn" style="padding:6px 14px; font-size:12px; background:#f1f5f9; border:1px solid #cbd5e1; cursor:pointer;">
            🔄 <?= e(t('คืนค่าเป็นภาพมาตรฐาน', 'Reset to Default Image')) ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  </section>

</main>
</body>
</html>
