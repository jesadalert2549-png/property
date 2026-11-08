<?php
require_once __DIR__ . '/helpers.php';

// ── สล็อตที่รองรับสำหรับตกแต่งเว็บ ──
function banner_slots(): array
{
    return [
        'home_hero_top' => [
            'icon' => '🏅',
            'label' => t('แถบบนสุดของหน้าแรก (Top Announcement)', 'Top Announcement Bar'),
            'page' => 'index.php',
            'target_url' => 'index.php',
            'badge' => t('หน้าแรก', 'Homepage'),
            'desc' => t('แถบยาวกว้างเต็มหน้าจอด้านบนสุด เหมาะกับโปรโมชันด่วนหรือประกาศพิเศษของเว็บ', 'Full-width top bar for urgent promotions or special announcements'),
            'rec_size' => '1200 x 90 px',
            'height' => 'compact'
        ],
        'home_below_hero' => [
            'icon' => '🖼️',
            'label' => t('ใต้ช่องค้นหาหน้าแรก (Hero Billboard)', 'Below Search (Hero Billboard)'),
            'page' => 'index.php',
            'target_url' => 'index.php',
            'badge' => t('หน้าแรก', 'Homepage'),
            'desc' => t('ตำแหน่งเด่นและใหญ่ที่สุดบนหน้าแรก อยู่ใต้กล่องค้นหาหลัก เหมาะกับวิดีโอลูปและโปสเตอร์โฆษณาใหญ่', 'Prime billboard banner below search box, ideal for video and posters'),
            'rec_size' => '1200 x 320 px',
            'height' => 'medium'
        ],
        'home_category_gap' => [
            'icon' => '🎞️',
            'label' => t('ระหว่างกลุ่มประกาศหน้าแรก (Mid-Page Gap)', 'Homepage In-Feed Mid-Page Gap'),
            'page' => 'index.php',
            'target_url' => 'index.php#listings',
            'badge' => t('หน้าแรก', 'Homepage'),
            'desc' => t('คั่นระหว่างแถวประกาศแนะนำกับประกาศทั่วไป ดึงดูดสายตาขณะเลื่อนดูบ้าน', 'Placed between listing rows to catch visitors as they browse homes'),
            'rec_size' => '1200 x 240 px',
            'height' => 'medium'
        ],
        'home_footer_above' => [
            'icon' => '📣',
            'label' => t('เหนือ Footer หน้าแรก (Pre-Footer)', 'Homepage Pre-Footer Banner'),
            'page' => 'index.php',
            'target_url' => 'index.php#footer',
            'badge' => t('หน้าแรก', 'Homepage'),
            'desc' => t('แบนเนอร์แนวยาวก่อนถึงส่วนท้ายเว็บไซต์ เหมาะกับแคมเปญกระตุ้นการติดต่อหรือโปรโมตโครงการ', 'Wide banner right above footer, ideal for contact CTA or brand campaigns'),
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'property_detail_top' => [
            'icon' => '🏠',
            'label' => t('หน้ารายละเอียดทรัพย์ (ใต้แกลเลอรีรูป)', 'Property Detail (Under Photo Gallery)'),
            'page' => 'property.php?id=2',
            'target_url' => 'property.php?id=2',
            'badge' => t('รายละเอียดทรัพย์', 'Property Detail'),
            'desc' => t('แสดงบนหน้ารายละเอียดทรัพย์สินทุกชิ้น ผู้ชมที่กำลังสนใจจะมองเห็นทันที', 'Prime ad slot below property photos, visible to all active buyers/tenants'),
            'rec_size' => '900 x 260 px',
            'height' => 'medium'
        ],
        'property_sidebar' => [
            'icon' => '📌',
            'label' => t('หน้ารายละเอียดทรัพย์ (คอลัมน์ด้านข้าง)', 'Property Detail (Sidebar Sticky)'),
            'page' => 'property.php?id=2',
            'target_url' => 'property.php?id=2',
            'badge' => t('รายละเอียดทรัพย์', 'Property Detail'),
            'desc' => t('ป้ายโฆษณาข้างฟอร์มติดต่อเจ้าของทรัพย์ เหมาะกับโปรโมชันสินเชื่อหรือบริการตกแต่งบ้าน', 'Sidebar slot next to inquiry form, ideal for mortgage or home decor sponsors'),
            'rec_size' => '400 x 250 px',
            'height' => 'compact'
        ],
        'category_top' => [
            'icon' => '🏬',
            'label' => t('หน้าหมวดหมู่คอนโด/บ้าน (ด้านบน)', 'Category Landing Pages (Top Banner)'),
            'page' => 'category.php?page=condo',
            'target_url' => 'category.php?page=condo',
            'badge' => t('หน้าหมวดหมู่', 'Category Page'),
            'desc' => t('แสดงด้านบนของหน้ารวมทรัพย์สินตามหมวดหมู่', 'Displays above filtered category results'),
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'search_top' => [
            'icon' => '🔍',
            'label' => t('หน้าค้นหาทรัพย์สิน (ด้านบนผลลัพธ์)', 'Search Results (Top Banner)'),
            'page' => 'search.php',
            'target_url' => 'search.php',
            'badge' => t('หน้าค้นหา', 'Search Page'),
            'desc' => t('แสดงด้านบนของหน้าผลการค้นหา เหมาะกับโปรโมชันทำเลเด่นหรือโปรโมตทรัพย์เร่งด่วน', 'Top slot above search results, ideal for featured location deals'),
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'project_page' => [
            'icon' => '🏢',
            'label' => t('หน้าโครงการพัฒนาใหม่', 'Project Portfolio Page'),
            'page' => 'project.php',
            'target_url' => 'project.php',
            'badge' => t('หน้าโครงการ', 'Project Page'),
            'desc' => t('แสดงบนหน้ารวมโครงการและรายละเอียดโครงการ', 'Appears on new property development portfolio pages'),
            'rec_size' => '1200 x 180 px',
            'height' => 'compact'
        ],
        'generic' => [
            'icon' => '✨',
            'label' => t('พื้นที่อเนกประสงค์ (Fallback)', 'Generic / Fallback Slot'),
            'page' => 'All pages',
            'target_url' => 'index.php',
            'badge' => t('ทั่วไป', 'General'),
            'desc' => t('สล็อตทั่วไปสำหรับใช้งานอเนกประสงค์', 'General fallback slot for all public pages'),
            'rec_size' => 'Self-scaling responsive',
            'height' => 'medium'
        ],
    ];
}

function banner_slot_label(string $slot): string
{
    $slots = banner_slots();
    return $slots[$slot]['label'] ?? $slots['generic']['label'];
}

// ── สร้างตารางอัตโนมัติ ใช้ได้ทั้ง admin และหน้าเว็บ ──
function ensure_banner_table(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promo_banners (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slot VARCHAR(40) NOT NULL DEFAULT 'generic',
            title VARCHAR(120) NOT NULL DEFAULT '',
            media_type ENUM('image','video') NOT NULL DEFAULT 'image',
            media_file VARCHAR(255) NOT NULL,
            link_url VARCHAR(500) NOT NULL DEFAULT '',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_banner_slot (slot, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ── ดึงแบนเนอร์ตามสล็อต ──
function get_banners(string $slot, int $limit = 5): array
{
    static $cache = [];
    $cacheKey = $slot . '|' . $limit;
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    try {
        $pdo = db();
        ensure_banner_table($pdo);
        $stmt = $pdo->prepare(
            "SELECT id, slot, title, media_type, media_file, link_url
             FROM promo_banners
             WHERE slot = ? AND enabled = 1
             ORDER BY sort_order, id
             LIMIT " . max(1, $limit)
        );
        $stmt->execute([$slot]);
        return $cache[$cacheKey] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $cache[$cacheKey] = [];
    }
}

function banner_media_url(string $file): string
{
    return 'uploads/promo/' . basename($file);
}

/**
 * วาดแบนเนอร์ของสล็อตหนึ่ง (ครอบด้วย link ถ้ามี)
 * @param string $slot   ชื่อสล็อต
 * @param string $height compact | medium
 */
function render_banner_slot(string $slot, string $height = 'medium'): void
{
    $banners = get_banners($slot);
    if (!$banners) return;
    $count = count($banners);
    $countClass = $count >= 4 ? 'count-4' : ($count === 3 ? 'count-3' : ($count === 2 ? 'count-2' : 'count-1'));
    echo '<div class="promo-banner-slot promo-slot-' . e($height) . ' ' . $countClass . '">';
    foreach ($banners as $banner) {
        $url = banner_media_url((string)$banner['media_file']);
        $isVideo = ($banner['media_type'] ?? 'image') === 'video';
        $link = trim((string)($banner['link_url'] ?? ''));
        $alt = trim((string)($banner['title'] ?? '')) ?: banner_slot_label($slot);
        $inner = $isVideo
            ? '<video class="promo-banner-media" src="' . e($url) . '" autoplay muted loop playsinline preload="metadata"></video>'
            : '<img class="promo-banner-media" src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" decoding="async">';
        if ($link !== '') {
            $isExternal = (bool)preg_match('#^https?://#i', $link);
            echo '<a class="promo-banner-item" href="' . e($link) . '"' . ($isExternal ? ' target="_blank" rel="noopener noreferrer"' : '') . ' aria-label="' . e($alt) . '">' . $inner . '</a>';
        } else {
            echo '<div class="promo-banner-item">' . $inner . '</div>';
        }
    }
    echo '</div>';
}

// ── อัปโหลดสื่อ (รูป jpg/png/webp/gif หรือคลิป mp4/webm ≤ 40MB) ──
function save_banner_media(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ไม่ได้เลือกไฟล์หรืออัปโหลดไม่สำเร็จ');
    }
    if ($file['size'] > 40 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 40 MB');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);

    $imageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $videoTypes = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    if (isset($imageTypes[$mime])) {
        $ext = $imageTypes[$mime];
        $type = 'image';
    } elseif (isset($videoTypes[$mime])) {
        $ext = $videoTypes[$mime];
        $type = 'video';
    } else {
        throw new RuntimeException('รองรับเฉพาะไฟล์ภาพ (JPG, PNG, WebP, GIF) หรือวิดีโอ (MP4, WebM)');
    }

    $dir = UPLOAD_DIR . 'promo/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บสื่อได้');
    }

    $filename = 'promo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return ['filename' => $filename, 'type' => $type];
}

function delete_banner_media(string $filename): void
{
    $path = UPLOAD_DIR . 'promo/' . basename($filename);
    if (is_file($path)) { @unlink($path); }
}

// ── สื่อพื้นหลังหลัก Hero Section หน้าแรก ──
function get_hero_background(bool $forceRefresh = false): array
{
    static $cached = null;
    if ($cached !== null && !$forceRefresh) return $cached;
    $default = [
        'type' => 'image',
        'url' => 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
        'is_default' => true,
        'opacity' => 0.45,
        'filename' => 'Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
    ];

    $cfg = null;

    // 1. อ่านจากฐานข้อมูล (site_settings) ก่อนเสมอ — ป้องกันค่าหายเมื่อ deploy หรือ git pull
    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'hero_background' LIMIT 1");
        $st->execute();
        $dbVal = $st->fetchColumn();
        if ($dbVal) {
            $parsed = json_decode((string)$dbVal, true);
            if (is_array($parsed)) {
                $cfg = $parsed;
            }
        }
    } catch (Throwable $e) {}

    // 2. ถ้าใน DB ยังไม่มี ให้อ่านจาก hero_config.json เป็น Fallback
    if (!$cfg) {
        $cfgFile = dirname(__DIR__) . '/hero_config.json';
        if (is_file($cfgFile)) {
            $jsonParsed = json_decode((string)file_get_contents($cfgFile), true);
            if (is_array($jsonParsed)) {
                $cfg = $jsonParsed;
            }
        }
    }

    if (!is_array($cfg)) return $cached = $default;

    $filename = basename((string)($cfg['media_file'] ?? ''));
    $type = ($cfg['media_type'] ?? 'image') === 'video' ? 'video' : 'image';
    $opacity = isset($cfg['overlay_opacity']) ? max(0.1, min(0.9, (float)$cfg['overlay_opacity'])) : 0.45;

    if (!empty($cfg['is_default']) || $filename === 'Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg') {
        return $cached = [
            'type' => 'image',
            'url' => 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
            'is_default' => true,
            'opacity' => $opacity,
            'filename' => 'Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
        ];
    }

    // ตรวจสอบทั้งใน uploads/hero/ และ images/hero/
    $uploadPath = UPLOAD_DIR . 'hero/' . $filename;
    $imagesPath = dirname(__DIR__) . '/images/hero/' . $filename;

    if ($filename !== '') {
        if (is_file($uploadPath)) {
            return $cached = [
                'type' => $type,
                'url' => 'uploads/hero/' . $filename,
                'is_default' => false,
                'opacity' => $opacity,
                'filename' => $filename,
            ];
        } elseif (is_file($imagesPath)) {
            return $cached = [
                'type' => $type,
                'url' => 'images/hero/' . $filename,
                'is_default' => false,
                'opacity' => $opacity,
                'filename' => $filename,
            ];
        }
    }

    return $cached = $default;
}

function save_hero_background(array $file, float $opacity): array
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
        throw new RuntimeException('รองรับเฉพาะไฟล์ภาพ (JPG, PNG, WebP) หรือวิดีโอ (MP4, WebM)');
    }

    // เซฟลง 2 ที่: uploads/hero/ และ images/hero/ (ป้องกันไฟล์หายเวลา deploy หรือ git pull)
    $dirUpload = UPLOAD_DIR . 'hero/';
    $dirImages = dirname(__DIR__) . '/images/hero/';

    if (!is_dir($dirUpload)) @mkdir($dirUpload, 0755, true);
    if (!is_dir($dirImages)) @mkdir($dirImages, 0755, true);

    $current = get_hero_background();
    if (!$current['is_default'] && !empty($current['filename'])) {
        $oldFile = basename($current['filename']);
        if (is_file($dirUpload . $oldFile)) { @unlink($dirUpload . $oldFile); }
        if (is_file($dirImages . $oldFile)) { @unlink($dirImages . $oldFile); }
    }

    $filename = 'hero-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPathUpload = $dirUpload . $filename;
    $targetPathImages = $dirImages . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPathUpload)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์สื่อพื้นหลังได้');
    }
    // ทำสำเนาไว้ที่ images/hero/ ด้วยเพื่อความคงทน
    @copy($targetPathUpload, $targetPathImages);

    $configData = [
        'media_type' => $type,
        'media_file' => $filename,
        'is_default' => false,
        'overlay_opacity' => max(0.1, min(0.9, $opacity)),
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

        $st = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('hero_background', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $st->execute([$jsonString]);
    } catch (Throwable $e) {}

    // 2. บันทึกลง hero_config.json ควบคู่ไปด้วย
    $cfgFile = dirname(__DIR__) . '/hero_config.json';
    file_put_contents($cfgFile, $jsonString);

    get_hero_background(true);

    return ['type' => $type, 'filename' => $filename];
}

function reset_hero_background(): void
{
    $dirUpload = UPLOAD_DIR . 'hero/';
    $dirImages = dirname(__DIR__) . '/images/hero/';

    $current = get_hero_background();
    if (!$current['is_default'] && !empty($current['filename'])) {
        $oldFile = basename($current['filename']);
        if (is_file($dirUpload . $oldFile)) { @unlink($dirUpload . $oldFile); }
        if (is_file($dirImages . $oldFile)) { @unlink($dirImages . $oldFile); }
    }

    $configData = [
        'media_type' => 'image',
        'media_file' => 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
        'is_default' => true,
        'overlay_opacity' => 0.45,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $jsonString = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    try {
        $pdo = db();
        $st = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('hero_background', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $st->execute([$jsonString]);
    } catch (Throwable $e) {}

    $cfgFile = dirname(__DIR__) . '/hero_config.json';
    file_put_contents($cfgFile, $jsonString);

    get_hero_background(true);
}
