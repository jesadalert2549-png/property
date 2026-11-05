<?php
require_once __DIR__ . '/helpers.php';

// ── สล็อตที่รองรับสำหรับตกแต่งเว็บ ──
function banner_slots(): array
{
    return [
        'home_hero_top' => [
            'icon' => '🏅',
            'label' => 'แถบบนสุดของหน้าแรก (Top Announcement)',
            'page' => 'index.php',
            'target_url' => 'index.php',
            'badge' => 'หน้าแรก',
            'desc' => 'แถบยาวกว้างเต็มหน้าจอด้านบนสุด เหมาะกับโปรโมชันด่วนหรือประกาศพิเศษของเว็บ',
            'rec_size' => '1200 x 90 px หรือคลิปวนลูปแนวนอนสั้น',
            'height' => 'compact'
        ],
        'home_below_hero' => [
            'icon' => '🖼️',
            'label' => 'ใต้ช่องค้นหาหน้าแรก (Hero Billboard)',
            'page' => 'index.php',
            'target_url' => 'index.php',
            'badge' => 'หน้าแรก',
            'desc' => 'ตำแหน่งเด่นและใหญ่ที่สุดบนหน้าแรก อยู่ใต้กล่องค้นหาหลัก เหมาะกับวิดีโอลูปและโปสเตอร์โฆษณาใหญ่',
            'rec_size' => '1200 x 320 px หรือคลิป MP4 16:9 แนวนอน',
            'height' => 'medium'
        ],
        'home_category_gap' => [
            'icon' => '🎞️',
            'label' => 'ระหว่างกลุ่มประกาศหน้าแรก (Mid-Page Gap)',
            'page' => 'index.php',
            'target_url' => 'index.php#listings',
            'badge' => 'หน้าแรก',
            'desc' => 'คั่นระหว่างแถวประกาศแนะนำกับประกาศทั่วไป ดึงดูดสายตาขณะเลื่อนดูบ้าน',
            'rec_size' => '1200 x 240 px (รองรับการใส่ 2 ชิ้นซ้าย-ขวา)',
            'height' => 'medium'
        ],
        'home_footer_above' => [
            'icon' => '📣',
            'label' => 'เหนือ Footer หน้าแรก (Pre-Footer)',
            'page' => 'index.php',
            'target_url' => 'index.php#footer',
            'badge' => 'หน้าแรก',
            'desc' => 'แบนเนอร์แนวยาวก่อนถึงส่วนท้ายเว็บไซต์ เหมาะกับแคมเปญกระตุ้นการติดต่อหรือโปรโมตโครงการ',
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'property_detail_top' => [
            'icon' => '🏠',
            'label' => 'หน้ารายละเอียดทรัพย์ (ใต้แกลเลอรีรูป)',
            'page' => 'property.php?id=2',
            'target_url' => 'property.php?id=2',
            'badge' => 'รายละเอียดทรัพย์',
            'desc' => 'แสดงบนหน้ารายละเอียดทรัพย์สินทุกชิ้น ผู้ชมที่กำลังสนใจจะมองเห็นทันที',
            'rec_size' => '900 x 260 px',
            'height' => 'medium'
        ],
        'property_sidebar' => [
            'icon' => '📌',
            'label' => 'หน้ารายละเอียดทรัพย์ (คอลัมน์ด้านข้าง)',
            'page' => 'property.php?id=2',
            'target_url' => 'property.php?id=2',
            'badge' => 'รายละเอียดทรัพย์',
            'desc' => 'ป้ายโฆษณาข้างฟอร์มติดต่อเจ้าของทรัพย์ เหมาะกับโปรโมชันสินเชื่อหรือบริการตกแต่งบ้าน',
            'rec_size' => '400 x 250 px',
            'height' => 'compact'
        ],
        'category_top' => [
            'icon' => '🏬',
            'label' => 'หน้าหมวดหมู่คอนโด/บ้าน (ด้านบน)',
            'page' => 'category.php?page=condo',
            'target_url' => 'category.php?page=condo',
            'badge' => 'หน้าหมวดหมู่',
            'desc' => 'แสดงด้านบนของหน้ารวมทรัพย์สินตามหมวดหมู่',
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'search_top' => [
            'icon' => '🔍',
            'label' => 'หน้าค้นหาทรัพย์สิน (ด้านบนผลลัพธ์)',
            'page' => 'search.php',
            'target_url' => 'search.php',
            'badge' => 'หน้าค้นหา',
            'desc' => 'แสดงด้านบนของหน้าผลการค้นหา เหมาะกับโปรโมชันทำเลเด่นหรือโปรโมตทรัพย์เร่งด่วน',
            'rec_size' => '1200 x 140 px',
            'height' => 'compact'
        ],
        'project_page' => [
            'icon' => '🏢',
            'label' => 'หน้าโครงการพัฒนาใหม่',
            'page' => 'project.php',
            'target_url' => 'project.php',
            'badge' => 'หน้าโครงการ',
            'desc' => 'แสดงบนหน้ารวมโครงการและรายละเอียดโครงการ',
            'rec_size' => '1200 x 180 px',
            'height' => 'compact'
        ],
        'generic' => [
            'icon' => '✨',
            'label' => 'พื้นที่อเนกประสงค์ (Fallback)',
            'page' => 'ทุกหน้า',
            'target_url' => 'index.php',
            'badge' => 'ทั่วไป',
            'desc' => 'สล็อตทั่วไปสำหรับใช้งานอเนกประสงค์',
            'rec_size' => 'ไม่จำกัดขนาด ปรับตามหน้าจออัตโนมัติ',
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

    $cfgFile = dirname(__DIR__) . '/hero_config.json';
    if (!is_file($cfgFile)) return $cached = $default;

    $cfg = json_decode((string)file_get_contents($cfgFile), true);
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

    $uploadPath = UPLOAD_DIR . 'hero/' . $filename;
    if ($filename !== '' && is_file($uploadPath)) {
        return $cached = [
            'type' => $type,
            'url' => 'uploads/hero/' . $filename,
            'is_default' => false,
            'opacity' => $opacity,
            'filename' => $filename,
        ];
    }

    return $cached = $default;
}

function save_hero_background(array $file, float $opacity): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ไม่ได้เลือกไฟล์หรืออัปโหลดไม่สำเร็จ');
    }
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์สื่อพื้นหลังต้องมีขนาดไม่เกิน 50 MB');
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

    $dir = UPLOAD_DIR . 'hero/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บสื่อพื้นหลังได้');
    }

    $current = get_hero_background();
    if (!$current['is_default'] && !empty($current['filename'])) {
        $oldPath = $dir . basename($current['filename']);
        if (is_file($oldPath)) { @unlink($oldPath); }
    }

    $filename = 'hero-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์สื่อพื้นหลังได้');
    }

    $cfgFile = dirname(__DIR__) . '/hero_config.json';
    file_put_contents($cfgFile, json_encode([
        'media_type' => $type,
        'media_file' => $filename,
        'is_default' => false,
        'overlay_opacity' => max(0.1, min(0.9, $opacity)),
        'updated_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    get_hero_background(true);

    return ['type' => $type, 'filename' => $filename];
}

function reset_hero_background(): void
{
    $dir = UPLOAD_DIR . 'hero/';
    $current = get_hero_background();
    if (!$current['is_default'] && !empty($current['filename'])) {
        $oldPath = $dir . basename($current['filename']);
        if (is_file($oldPath)) { @unlink($oldPath); }
    }

    $cfgFile = dirname(__DIR__) . '/hero_config.json';
    file_put_contents($cfgFile, json_encode([
        'media_type' => 'image',
        'media_file' => 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg',
        'is_default' => true,
        'overlay_opacity' => 0.45,
        'updated_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    get_hero_background(true);
}
