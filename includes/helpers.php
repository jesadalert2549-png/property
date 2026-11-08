<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// แปลงข้อความให้ปลอดภัยก่อนแสดงบนหน้าเว็บ
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/i18n.php';

/**
 * Renders the KASA animated luxury logo HTML (text/emblem only, transparent background).
 * @param string $href   URL to link to (default 'index.php')
 * @param string $class  Extra CSS classes (e.g. 'kasa-logo--dark')
 */
function render_logo_html(string $href = 'index.php', string $class = ''): string
{
    $name = 'PropertyGO';
    $cls  = 'kasa-logo sp-logo' . ($class !== '' ? ' ' . $class : '');
    $eName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $eHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $inAdmin = (strpos($scriptPath, '/admin/') !== false) || (strpos($href, '../') === 0);
    $imgPrefix = $inAdmin ? '../images/' : 'images/';
    $v = (string) @filemtime(__DIR__ . '/../images/kasa_logo.png');

    return '<a href="' . $eHref . '" class="' . $cls . '" aria-label="' . $eName . '">'
        . '<img src="' . $imgPrefix . 'kasa_logo_light.png?v=' . $v . '" alt="' . $eName . '" class="kasa-logo-img kasa-logo-img--light" width="200" height="52" loading="eager">'
        . '<img src="' . $imgPrefix . 'kasa_logo.png?v=' . $v . '" alt="' . $eName . '" class="kasa-logo-img kasa-logo-img--dark" width="200" height="52" loading="eager">'
        . '</a>';
}

// ชิปโปรไฟล์เล็กมุมซ้ายล่างของรูปโพสต์: รูปโปรไฟล์ + ชื่อ + ที่ตั้ง
function render_publisher_mini(array $post): void
{
    $name = trim((string)($post['publisher_name'] ?? '')) ?: (trim((string)($post['contact_name'] ?? '')) ?: 'ผู้ประกาศ');
    $avatar = basename((string)($post['publisher_avatar'] ?? ''));
    $avatarExists = $avatar !== '' && defined('UPLOAD_DIR') && is_file(UPLOAD_DIR . 'avatars/' . $avatar);
    $location = trim((string)($post['location'] ?? ''));
    $verified = ($post['publisher_agent_status'] ?? '') === 'verified';

    // คำนวณตัวอักษรย่อสำหรับ Avatar โดยรองรับสระหน้าภาษาไทย (เ แ โ ใ ไ)
    $initial = 'P';
    if ($name !== '') {
        $firstChar = mb_substr($name, 0, 1);
        if (in_array($firstChar, ['เ', 'แ', 'โ', 'ใ', 'ไ'], true) && mb_strlen($name) > 1) {
            $initial = mb_substr($name, 1, 1);
        } else {
            $initial = $firstChar;
        }
    }
    $initial = mb_strtoupper($initial);
    ?>
    <div class="publisher-mini">
      <span class="publisher-mini-avatar" aria-hidden="true"><?php if ($avatarExists): ?><img src="uploads/avatars/<?= e($avatar) ?>" alt=""><?php else: ?><?= e($initial) ?><?php endif; ?></span>
      <span class="publisher-mini-text">
        <span class="publisher-mini-name"><?= e($name) ?><?php if ($verified): ?><i class="publisher-verified-dot" role="img" aria-label="Agent ผ่านการรับรอง" title="Agent ผ่านการรับรองจากผู้ดูแล">✓</i><?php endif; ?></span>
        <?php if ($location !== ''): ?><span class="publisher-mini-loc"><svg width="11" height="11" class="publisher-mini-pin" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;width:11px;height:11px;max-width:11px;max-height:11px;vertical-align:-1px;flex-shrink:0;opacity:0.8;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg> <?= e($location) ?></span><?php endif; ?>
      </span>
    </div>
    <?php
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => request_is_https(),
        ]);
        @session_start();
    }
}

function request_is_https(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return $https !== '' && $https !== 'off';
}

function request_method(): string
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function request_is_post(): bool
{
    return request_method() === 'POST';
}

function site_asset_url(string $asset): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $basePath = rtrim(dirname($scriptPath), '/');
    foreach (['/admin', '/api'] as $suffix) {
        if (str_ends_with($basePath, $suffix)) {
            $basePath = substr($basePath, 0, -strlen($suffix));
            break;
        }
    }
    return $basePath . '/' . ltrim($asset, '/');
}

function site_controls_head(): void
{
    $cssVersion = filemtime(__DIR__ . '/../css/site-controls.css');
    $footerCssVersion = filemtime(__DIR__ . '/../css/seo-footer.css');
    $jsVersion = filemtime(__DIR__ . '/../js/site-controls.js');
    $css = e(site_asset_url('css/site-controls.css') . '?v=' . $cssVersion);
    $footerCss = e(site_asset_url('css/seo-footer.css') . '?v=' . $footerCssVersion);
    $js = e(site_asset_url('js/site-controls.js') . '?v=' . $jsVersion);
    $favVersion = is_file(__DIR__ . '/../images/favicon.png') ? filemtime(__DIR__ . '/../images/favicon.png') : time();
    $faviconSvg = e(site_asset_url('images/favicon.svg') . '?v=' . $favVersion);
    $favicon48  = e(site_asset_url('images/favicon-48x48.png') . '?v=' . $favVersion);
    $favicon32  = e(site_asset_url('images/favicon-32x32.png') . '?v=' . $favVersion);
    $favicon16  = e(site_asset_url('images/favicon-16x16.png') . '?v=' . $favVersion);
    $appleTouch = e(site_asset_url('images/apple-touch-icon.png') . '?v=' . $favVersion);
    $faviconIco = e(site_asset_url('favicon.ico') . '?v=' . $favVersion);
    $manifest   = e(site_asset_url('site.webmanifest'));
    echo '<link rel="icon" type="image/svg+xml" href="' . $faviconSvg . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="48x48" href="' . $favicon48 . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $favicon32 . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $favicon16 . '">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $appleTouch . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . $faviconIco . '">' . "\n";
    echo '<link rel="manifest" href="' . $manifest . '">' . "\n";
    echo '<meta name="theme-color" content="#0B1C33">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&family=Prompt:wght@300;400;500;600;700;800&display=swap">' . "\n";
    echo '<link rel="stylesheet" href="' . $css . '">' . "\n";
    echo '<link rel="stylesheet" href="' . $footerCss . '">' . "\n";
    echo '<link rel="stylesheet" href="' . e(site_asset_url('css/promo-banners.css') . '?v=' . filemtime(__DIR__ . '/../css/promo-banners.css')) . '">' . "\n";
    echo '<script defer src="' . $js . '"></script>' . "\n";
    echo '<script defer src="' . e(site_asset_url('js/instant-page.js')) . '"></script>' . "\n";
    echo '<script>window.spPendingEvents=window.spPendingEvents||[];window.spPendingEvents.push({event:"page",path:window.location.pathname+window.location.search});if(window.spFlushTracking){window.spFlushTracking();}</script>' . "\n";
}

function redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href=' . json_encode($url) . ';</script><noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    }
    exit;
}

function set_flash(string $message): void
{
    start_session();
    $_SESSION['flash'] = $message;
}

function get_flash(): ?string
{
    start_session();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    start_session();
    $sent = $_POST['csrf'] ?? '';
    if ($sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

function type_label(string $type): string
{
    return $type === 'rent' ? t('ให้เช่า', 'For rent') : t('ขาย', 'For sale');
}

function price_label(string $type, int $price): string
{
    return number_format($price) . ($type === 'rent' ? t(' บาท/เดือน', ' THB/mo') : t(' บาท', ' THB'));
}

function category_label(string $key): string
{
    return PROPERTY_CATEGORIES[$key] ?? PROPERTY_CATEGORIES['other'];
}

// ดึงพิกัดจากลิงก์ Google Maps รูปแบบ @lat,lng หรือ ?q=lat,lng
function parse_map_coords(string $url): ?array
{
    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)
        || preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
        if (abs($lat) <= 90 && abs($lng) <= 180) {
            return [$lat, $lng];
        }
    }
    return null;
}

function valid_property_map_url(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts)) return false;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $googleHost = $host === 'maps.app.goo.gl' || $host === 'goo.gl'
        || $host === 'google.com' || str_ends_with($host, '.google.com');
    return in_array($scheme, ['http', 'https'], true) && $googleHost && parse_map_coords($url) !== null;
}

function property_detail_errors(array $input): array
{
    $errors = [];
    $limits = [
        'bedrooms' => ['ห้องนอน', 99, true],
        'bathrooms' => ['ห้องน้ำ', 99, true],
        'area_sqm' => ['พื้นที่ใช้สอย', 999999, false],
        'land_area' => ['พื้นที่ดิน', 999999, false],
        'parking_spaces' => ['ที่จอดรถ', 99, true],
    ];
    foreach ($limits as $field => [$label, $maximum, $integer]) {
        $raw = $input[$field] ?? '';
        if (!is_scalar($raw) || trim((string)$raw) === '') {
            continue;
        }
        $value = (string)$raw;
        if (!is_numeric($value) || (float)$value < 0 || (float)$value > $maximum
            || ($integer && floor((float)$value) !== (float)$value)) {
            $errors[] = $label . 'ไม่ถูกต้อง';
        }
    }
    return $errors;
}

function listing_completeness_errors(array $data, array $input): array
{
    $errors = [];
    if (mb_strlen(trim((string)($data['description'] ?? ''))) < 20) {
        $errors[] = 'กรุณาเขียนรายละเอียดอย่างน้อย 20 ตัวอักษร';
    }

    return $errors;
}

function clean_line_id($value): string
{
    $lineId = trim((string)$value);
    if (str_starts_with($lineId, '@')) {
        $lineId = substr($lineId, 1);
    }
    return $lineId;
}

function valid_line_id(string $lineId): bool
{
    return $lineId === '' || (bool)preg_match('/^[A-Za-z0-9._-]{4,20}$/', $lineId);
}

// ตรวจข้อมูลประกาศจากฟอร์ม คืนค่า [ข้อมูลที่ทำความสะอาดแล้ว, รายการข้อผิดพลาด]
function read_listing_input(array $post): array
{
    start_session();
    $phone    = preg_replace('/[\s-]/', '', trim($post['phone'] ?? ''));
    $category = $post['property_category'] ?? '';
    $mapUrl   = is_scalar($post['map_url'] ?? null) ? trim((string)($post['map_url'] ?? '')) : '';
    $coords   = null;
    $postedLat = $post['map_lat'] ?? '';
    $postedLng = $post['map_lng'] ?? '';
    if (is_scalar($postedLat) && is_scalar($postedLng) && is_numeric($postedLat) && is_numeric($postedLng)) {
        $lat = (float)$postedLat;
        $lng = (float)$postedLng;
        if (abs($lat) <= 90 && abs($lng) <= 180) {
            $coords = [$lat, $lng];
        }
    }
    $coords ??= parse_map_coords($mapUrl);

    $data = [
        'title'             => trim($post['title'] ?? ''),
        'type'              => ($post['type'] ?? '') === 'rent' ? 'rent' : 'sale',
        'property_category' => array_key_exists($category, PROPERTY_CATEGORIES) ? $category : 'other',
        'price'             => (int)preg_replace('/[^0-9]/', '', $post['price'] ?? ''),
        'location'          => trim($post['location'] ?? ''),
        'description'       => trim($post['description'] ?? ''),
        'bedrooms'          => ($value = trim($post['bedrooms'] ?? '')) === '' ? null : max(0, min(99, (int)$value)),
        'bathrooms'         => ($value = trim($post['bathrooms'] ?? '')) === '' ? null : max(0, min(99, (int)$value)),
        'area_sqm'          => ($value = trim($post['area_sqm'] ?? '')) === '' ? null : max(0, min(999999, (float)$value)),
        'land_area'         => ($value = trim($post['land_area'] ?? '')) === '' ? null : max(0, min(999999, (float)$value)),
        'parking_spaces'    => ($value = trim($post['parking_spaces'] ?? '')) === '' ? null : max(0, min(99, (int)$value)),
        'floor_number'      => trim($post['floor_number'] ?? ''),
        'furnished'         => trim($post['furnished'] ?? ''),
        'amenities'         => trim($post['amenities'] ?? ''),
        'nearby_places'     => trim($post['nearby_places'] ?? ''),
        'contact_name'      => trim($post['contact_name'] ?? ''),
        'phone'             => $phone,
        'line_id'           => clean_line_id($post['line_id'] ?? ''),
        'lat'               => $coords[0] ?? null,
        'lng'               => $coords[1] ?? null,
    ];

    $errors = [];
    if (mb_strlen($data['title']) < 5) {
        $errors[] = 'หัวข้อประกาศต้องมีอย่างน้อย 5 ตัวอักษร';
    }
    if ($data['property_category'] === 'other') {
        $errors[] = 'กรุณาเลือกประเภทอสังหาริมทรัพย์';
    }
    if ($data['price'] <= 0) {
        $errors[] = 'กรุณากรอกราคาเป็นตัวเลข';
    }
    $errors = array_merge($errors, property_detail_errors($post));
    if ($data['location'] === '') {
        $errors[] = 'กรุณากรอกทำเล';
    }
    if (mb_strlen($data['contact_name']) < 2) {
        $errors[] = 'กรุณากรอกชื่อผู้ติดต่อ';
    }
    if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
        $errors[] = 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก';
    }
    if (!valid_line_id($data['line_id'])) {
        $errors[] = 'LINE ID ต้องเป็น 4-20 ตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด หรือขีดล่าง';
    }
    return [$data, $errors];
}

function count_selected_files(array $files): int
{
    if (!isset($files['name'])) {
        return 0;
    }

    $names = $files['name'];
    if (!is_array($names)) {
        return trim((string)$names) !== '' ? 1 : 0;
    }

    $count = 0;
    foreach ($names as $name) {
        if (is_string($name) && trim($name) !== '') {
            $count++;
        }
    }
    return $count;
}

// บันทึกรูปที่อัปโหลด รับเฉพาะ jpg, png, webp และข้ามไฟล์ที่ใหญ่เกินหรือไม่ใช่รูป
function save_uploaded_images(array $files, int $limit): array
{
    $saved = [];
    if (!isset($files['name'])) {
        return $saved;
    }

    $names = $files['name'];
    if (!is_array($names)) {
        $names = [$names];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

    foreach (array_keys($names) as $i) {
        if (count($saved) >= $limit) {
            break;
        }

        $name = $names[$i] ?? '';
        if (!is_string($name) || trim($name) === '') {
            continue;
        }

        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $size  = (int)($files['size'][$i] ?? 0);
        $tmp   = $files['tmp_name'][$i] ?? '';
        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > MAX_IMAGE_BYTES || !is_string($tmp) || $tmp === '' || !is_file($tmp)) {
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $finfo instanceof finfo ? strtolower((string)$finfo->file($tmp)) : '';
        $isValidExt = in_array($ext, $allowedExt, true);
        if ($finfo instanceof finfo) {
            if (!isset($allowedMime[$mime])) {
                continue;
            }
            $chosenExt = $allowedMime[$mime];
        } else {
            if (!$isValidExt) {
                continue;
            }
            $chosenExt = $ext === 'jpeg' ? 'jpg' : $ext;
        }
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $chosenExt;

        if (move_uploaded_file($tmp, UPLOAD_DIR . $filename)) {
            $saved[] = $filename;
        }
    }
    return $saved;
}

function delete_image_file(string $filename): void
{
    $path = UPLOAD_DIR . basename($filename);
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * บันทึกรูปที่อัปโหลดพร้อมจัดเรียงรูปภาพตาม order ที่ผู้ใช้เลือก
 * โดย $coverIndex ระบุ index ของรูปที่จะให้เป็นหน้าปก (จะถูกย้ายมาอยู่อันดับ 0)
 *
 * @param array $files ตัวแปร $_FILES['images']
 * @param int $limit จำนวนสูงสุด
 * @param int $coverIndex ลำดับของรูปหน้าปก (0-based)
 * @return array รายชื่อไฟล์รูปภาพที่บันทึกแล้ว โดยรูปปกจะอยู่ที่ index 0
 */
function save_uploaded_images_ordered(array $files, int $limit, int $coverIndex = 0): array
{
    $saved = [];
    if (!isset($files['name'])) {
        return $saved;
    }

    $names = $files['name'];
    if (!is_array($names)) {
        $names = [$names];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

    $indexedFiles = [];
    foreach (array_keys($names) as $i) {
        if (count($indexedFiles) >= $limit) {
            break;
        }

        $name = $names[$i] ?? '';
        if (!is_string($name) || trim($name) === '') {
            continue;
        }

        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $size  = (int)($files['size'][$i] ?? 0);
        $tmp   = $files['tmp_name'][$i] ?? '';
        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > MAX_IMAGE_BYTES || !is_string($tmp) || $tmp === '' || !is_file($tmp)) {
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $finfo instanceof finfo ? strtolower((string)$finfo->file($tmp)) : '';
        $isValidExt = in_array($ext, $allowedExt, true);
        if ($finfo instanceof finfo) {
            if (!isset($allowedMime[$mime])) {
                continue;
            }
            $chosenExt = $allowedMime[$mime];
        } else {
            if (!$isValidExt) {
                continue;
            }
            $chosenExt = $ext === 'jpeg' ? 'jpg' : $ext;
        }
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $chosenExt;

        if (move_uploaded_file($tmp, UPLOAD_DIR . $filename)) {
            $indexedFiles[$i] = $filename;
        }
    }

    if (empty($indexedFiles)) {
        return [];
    }

    // จัดลำดับ: ถ้าระบุ $coverIndex และมีรูปนั้นอยู่ ให้เอารูปปกขึ้นอันดับ 1 (index 0)
    $fileList = array_values($indexedFiles);
    if (isset($indexedFiles[$coverIndex])) {
        $coverFile = $indexedFiles[$coverIndex];
        $remaining = array_values(array_filter($fileList, static fn($f) => $f !== $coverFile));
        array_unshift($remaining, $coverFile);
        return $remaining;
    }

    return $fileList;
}
