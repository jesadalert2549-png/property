<?php
require_once __DIR__ . '/helpers.php';

function get_auth_media(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $default = [
        'type' => 'image',
        'url'  => 'uploads/prop_6ad2e524889cc6.57339303.jpg',
        'filename' => 'prop_6ad2e524889cc6.57339303.jpg'
    ];

    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'auth_background' LIMIT 1");
        $st->execute();
        $val = $st->fetchColumn();
        if ($val) {
            $parsed = json_decode((string)$val, true);
            if (is_array($parsed)) {
                $filename = basename((string)($parsed['filename'] ?? ''));
                $type = ($parsed['type'] ?? 'image') === 'video' ? 'video' : 'image';
            } else {
                $filename = basename((string)$val);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
            }

            if ($filename !== '') {
                $p1 = dirname(__DIR__) . '/uploads/auth/' . $filename;
                $p2 = dirname(__DIR__) . '/images/' . $filename;
                if (is_file($p1)) return $cached = ['type' => $type, 'url' => 'uploads/auth/' . $filename, 'filename' => $filename];
                if (is_file($p2)) return $cached = ['type' => $type, 'url' => 'images/' . $filename, 'filename' => $filename];
            }
        }
    } catch (Throwable $e) {}

    // ตรวจสอบ fallback รูปภาพ default
    if (is_file(dirname(__DIR__) . '/' . $default['url'])) {
        return $cached = $default;
    }
    if (is_file(dirname(__DIR__) . '/images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg')) {
        return $cached = ['type' => 'image', 'url' => 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg', 'filename' => 'Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg'];
    }

    return $cached = $default;
}

function get_auth_background(): string
{
    $m = get_auth_media();
    return $m['url'];
}

function render_auth_background_media(): void
{
    $m = get_auth_media();
    if ($m['type'] !== 'video') {
        return;
    }

    /* วิดีโอพื้นหลังหน้า login เดิมใช้ preload="auto" + src ตรง ๆ ทำให้เบราว์เซอร์
       เริ่มโหลด MP4 ขนาดหลาย MB ก่อนที่ฟอร์มจะแสดง (วัดได้ ~7 วินาทีกว่าจะ load เสร็จ
       และเกิด ERR_ABORTED ซ้ำ เพราะถูกยกเลิกกลางทาง)
       แก้แบบเดียวกับ hero ของหน้าแรก: preload="none" + data-src + poster
       แล้วค่อยเริ่มโหลดหลังหน้าพร้อม (ดู render_auth_background_script) */

    /* ใช้ภาพพื้นหลังจากตัว CSS ของ .login-wrap เป็น poster แทน
       (Gemini_Generated_Image เป็น PNG พื้นโปร่ง _ใช้เป็น poster ไม่ได้ จะเห็นเป็นตาราง)_ */
    $poster = '';
    $candidates = [
        'uploads/prop_6ad2e524889cc6.57339303.jpg', // ภาพเดียวกับที่ .login-wrap ใช้
        'images/auth-poster.jpg',
        'images/auth-poster.webp',
    ];
    foreach ($candidates as $candidate) {
        if (is_file(dirname(__DIR__) . '/' . $candidate)) {
            $poster = $candidate;
            break;
        }
    }

    echo '<video class="auth-video-bg js-auth-video" muted loop playsinline preload="none"'
        . ($poster !== '' ? ' poster="' . e($poster) . '"' : '')
        . ' data-src="' . e($m['url']) . '" aria-hidden="true" tabindex="-1"></video>' . "\n";
}

/**
 * สคริปต์โหลดวิดีโอพื้นหลังหน้า login แบบไม่บล็อกการแสดงผล
 * วางไว้ท้าย <body> หรือ调用 render_auth_background_media() แล้วต่อด้วยฟังก์ชันนี้
 */
function render_auth_background_script(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
<script>
(function () {
  var v = document.querySelector('.js-auth-video');
  if (!v) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var conn = navigator.connection || {};
  if (reduce || conn.saveData === true || /(^|-)2g$/.test(conn.effectiveType || '')) return;
  var started = false;
  function start() {
    if (started) return;
    started = true;
    var src = v.getAttribute('data-src');
    if (!src) return;
    // รอให้วิดีโอโหลด metadata เสร็จก่อนค่อยเล่น (กัน abort เมื่อเปลี่ยน src ถี่)
    v.addEventListener('loadeddata', function once() {
      v.removeEventListener('loadeddata', once);
      var p = v.play();
      if (p && p.catch) p.catch(function () {});
    });
    v.preload = 'auto';
    v.src = src;
    v.load();
  }
  var kick = function () {
    setTimeout(function () {
      // ถ้าผู้ใช้เริ่มพิมพ์หรือเลื่อนหน้าไปแล้ว ไม่ต้องโหลดวิดีโอให้เปลืองแบนด์วิดท์
      if (document.hidden) return;
      if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 5000 });
      else start();
    }, 3500);
  };
  if (document.readyState === 'complete') kick();
  else window.addEventListener('load', kick, { once: true });
})();
</script>
    <?php
}


/**
 * ฟังก์ชันช่วยตรวจสอบและแปลงขนาดไฟล์เป็น Bytes
 */
function parse_ini_bytes(string $val): int
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int)$val;
    switch ($last) {
        case 'g': $num *= 1024 * 1024 * 1024; break;
        case 'm': $num *= 1024 * 1024; break;
        case 'k': $num *= 1024; break;
    }
    return $num;
}

function get_server_upload_limit(): array
{
    $postMax = parse_ini_bytes(ini_get('post_max_size') ?: '64M');
    $uploadMax = parse_ini_bytes(ini_get('upload_max_filesize') ?: '64M');
    $minBytes = min($postMax, $uploadMax);
    $limitMb = round($minBytes / (1024 * 1024));

    return [
        'limit_bytes' => $minBytes,
        'limit_mb'    => (int)$limitMb,
        'post_max'    => ini_get('post_max_size'),
        'upload_max'  => ini_get('upload_max_filesize'),
    ];
}


function save_auth_background(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ไม่ได้เลือกไฟล์หรืออัปโหลดไม่สำเร็จ');
    }
    if ($file['size'] > 100 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์สื่อพื้นหลังต้องมีขนาดไม่เกิน 100 MB');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);

    $imageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $videoTypes = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    if (isset($imageTypes[$mime])) {
        $ext = $imageTypes[$mime];
        $type = 'image';
    } elseif (isset($videoTypes[$mime])) {
        $ext = $videoTypes[$mime];
        $type = 'video';
    } else {
        throw new RuntimeException('รองรับเฉพาะไฟล์ภาพ (JPG, PNG, WebP) หรือวิดีโอ (MP4, WebM, MOV)');
    }

    // เซฟลง 2 ที่: uploads/auth/ และ images/auth/ (ป้องกันไฟล์หายเวลา deploy หรือ git pull)
    $dirUpload = dirname(__DIR__) . '/uploads/auth/';
    $dirImages = dirname(__DIR__) . '/images/auth/';

    if (!is_dir($dirUpload)) @mkdir($dirUpload, 0755, true);
    if (!is_dir($dirImages)) @mkdir($dirImages, 0755, true);

    $current = get_auth_media();
    if (!empty($current['filename']) && $current['filename'] !== 'prop_6ad2e524889cc6.57339303.jpg') {
        $oldFile = basename($current['filename']);
        if (is_file($dirUpload . $oldFile)) { @unlink($dirUpload . $oldFile); }
        if (is_file($dirImages . $oldFile)) { @unlink($dirImages . $oldFile); }
    }

    $filename = 'auth-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPathUpload = $dirUpload . $filename;
    $targetPathImages = $dirImages . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPathUpload)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์สื่อพื้นหลังได้');
    }
    // สำรองไฟล์ไว้ที่ images/auth/ ด้วยเพื่อความคงทนถาวร
    @copy($targetPathUpload, $targetPathImages);

    $configData = [
        'type' => $type,
        'filename' => $filename,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $jsonString = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // 1. บันทึกลงฐานข้อมูล site_settings (ถาวร ไม่โดนไฟล์ทับ)
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $st = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('auth_background', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $st->execute([$jsonString]);
    } catch (Throwable $e) {}

    // 2. บันทึกลง auth_config.json ควบคู่ไปด้วย
    $cfgFile = dirname(__DIR__) . '/auth_config.json';
    @file_put_contents($cfgFile, $jsonString);

    return ['type' => $type, 'filename' => $filename];
}

function reset_auth_background(): void
{
    $dirUpload = dirname(__DIR__) . '/uploads/auth/';
    $dirImages = dirname(__DIR__) . '/images/auth/';

    $current = get_auth_media();
    if (!empty($current['filename']) && $current['filename'] !== 'prop_6ad2e524889cc6.57339303.jpg') {
        $oldFile = basename($current['filename']);
        if (is_file($dirUpload . $oldFile)) { @unlink($dirUpload . $oldFile); }
        if (is_file($dirImages . $oldFile)) { @unlink($dirImages . $oldFile); }
    }

    try {
        $pdo = db();
        $pdo->prepare("DELETE FROM site_settings WHERE setting_key = 'auth_background'")->execute();
    } catch (Throwable $e) {}

    $cfgFile = dirname(__DIR__) . '/auth_config.json';
    if (is_file($cfgFile)) @unlink($cfgFile);
}
