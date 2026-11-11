<?php
require_once __DIR__ . '/../includes/helpers.php';
start_session();

header('Content-Type: application/json; charset=utf-8');

// ไม่นับการเข้าชมของแอดมิน (cookie นี้ตั้งตอนล็อกอิน)
if (($_SESSION['role'] ?? '') === 'admin' || isset($_COOKIE['no_track'])) {
    echo json_encode(['ok' => true, 'skipped' => 'admin']);
    exit;
}

if (($_COOKIE['sp_analytics_consent'] ?? '') !== 'accepted') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'skipped' => 'consent_required']);
    exit;
}

// ไม่นับบอทและเครื่องมือค้นหา
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
if (preg_match('/bot|crawl|spider|slurp|headless/i', $ua)) {
    echo json_encode(['ok' => true, 'skipped' => 'bot']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$event = ($data['event'] ?? '') === 'property' ? 'property' : 'page';
$propertyId = $event === 'property' ? (int)($data['property_id'] ?? 0) : null;

if ($event === 'property' && $propertyId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// Track repeat visits with a random first-party token; never persist a visitor's raw IP.
$visitorToken = $_COOKIE['sp_visitor_id'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $visitorToken)) {
    $visitorToken = bin2hex(random_bytes(32));
    setcookie('sp_visitor_id', $visitorToken, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
$visitorHash = hash('sha256', $visitorToken);
$device = preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? 'mobile' : 'desktop';
$browser = preg_match('/Edg\//i', $ua) ? 'Edge'
    : (preg_match('/Firefox\//i', $ua) ? 'Firefox'
    : (preg_match('/Chrome\//i', $ua) ? 'Chrome'
    : (preg_match('/Safari\//i', $ua) ? 'Safari' : 'Other')));
$referrerHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?: '';
$referrer = substr($referrerHost, 0, 255);
$pagePath = '';
if ($event === 'page') {
    $requestPath = (string)($data['path'] ?? '');
    $path = parse_url($requestPath, PHP_URL_PATH) ?: '';
    $page = basename($path);
    $allowedPages = ['index.php', 'search.php', 'category.php', 'property.php', 'project.php', 'submit.php', 'submit-project.php', 'login.php', 'register.php', 'dashboard.php'];
    if (in_array($page, $allowedPages, true)) {
        $pagePath = $page;
        parse_str((string)(parse_url($requestPath, PHP_URL_QUERY) ?? ''), $query);
        foreach (['page', 'category', 'type'] as $key) {
            if (isset($query[$key]) && is_string($query[$key]) && preg_match('/^[a-z_-]{1,30}$/i', $query[$key])) {
                $pagePath .= ($pagePath === $page ? '?' : '&') . $key . '=' . $query[$key];
            }
        }
    }
    if ($pagePath === '') {
        echo json_encode(['ok' => true, 'skipped' => 'unsupported_page']);
        exit;
    }

    // ── ตรวจจับคำค้นหาจาก Google / เครื่องมือค้นหาภายนอก หรือพารามิเตอร์ q ──
    try {
        parse_str((string)(parse_url($requestPath, PHP_URL_QUERY) ?? ''), $urlParams);
        $externalQuery = trim((string)($urlParams['q'] ?? $urlParams['keyword'] ?? $urlParams['search'] ?? ''));
        if ($externalQuery !== '') {
            require_once __DIR__ . '/../includes/seo_provinces.php';
            $src = preg_match('/google\.|bing\.|yahoo\.|duckduckgo\./i', $referrer) ? 'external' : 'internal';
            record_search_query($externalQuery, 0, (string)($urlParams['category'] ?? ''), (string)($urlParams['type'] ?? ''), $src);
        }
    } catch (Throwable $e) {}
}

// ── ระบบป้องกัน Bug ยอดวิวพุ่งจากการรีเฟรชหน้าจอ (Anti-Refresh Spam / Cooldown Protection) ──
$isDuplicateView = false;
$cooldownSeconds = 7200; // คูลดาวน์ 2 ชั่วโมงต่อทรัพย์/คลิป ต่อผู้ชม

if ($event === 'property' && $propertyId > 0) {
    // 1. ตรวจสอบว่าผู้ใช้งานเป็นเจ้าของทรัพย์นี้หรือไม่ (ไม่นับยอดวิวเมื่อเจ้าของเปิดดูหรือรีเฟรชทรัพย์ตัวเอง)
    if (!empty($_SESSION['user_id'])) {
        try {
            $chkOwner = db()->prepare('SELECT user_id FROM properties WHERE id = ? LIMIT 1');
            $chkOwner->execute([$propertyId]);
            $ownerId = $chkOwner->fetchColumn();
            if ($ownerId !== false && (int)$ownerId === (int)$_SESSION['user_id']) {
                $isDuplicateView = true;
            }
        } catch (Throwable $e) {}
    }

    // 2. ตรวจสอบผ่าน Session ป้องกันการกด F5 รีเฟรชหน้าจอรัวๆ
    if (!$isDuplicateView) {
        if (!isset($_SESSION['viewed_properties']) || !is_array($_SESSION['viewed_properties'])) {
            $_SESSION['viewed_properties'] = [];
        }
        if (isset($_SESSION['viewed_properties'][$propertyId])) {
            $lastViewTime = (int)$_SESSION['viewed_properties'][$propertyId];
            if ((time() - $lastViewTime) < $cooldownSeconds) {
                $isDuplicateView = true;
            }
        }
    }

    // 3. ตรวจสอบผ่าน First-party Cookie
    if (!$isDuplicateView && isset($_COOKIE['sp_view_p_' . $propertyId])) {
        $isDuplicateView = true;
    }

    // 4. ตรวจสอบย้อนหลังจากตาราง visits ภายใน 2 ชั่วโมง สำหรับ visitor_hash เดียวกัน
    if (!$isDuplicateView) {
        try {
            $chkVisits = db()->prepare(
                'SELECT id FROM visits 
                 WHERE visitor_hash = ? AND event_type = "property" AND property_id = ? 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
                 LIMIT 1'
            );
            $chkVisits->execute([$visitorHash, $propertyId]);
            if ($chkVisits->fetchColumn()) {
                $isDuplicateView = true;
            }
        } catch (Throwable $e) {}
    }
}

if (!$isDuplicateView) {
    // บันทึกการเข้าชมจริง
    try {
        db()->prepare(
            'INSERT INTO visits (visitor_hash, user_id, event_type, property_id, page_path, referrer, user_agent, device)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$visitorHash, !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $event, $propertyId, $pagePath, $referrer, $browser, $device]);
    } catch (Throwable $e) {}

    // อัปเดตนับยอดวิวสะสมเฉพาะวิวแท้ที่ไม่ใช่การรีเฟรชซ้ำ
    if ($event === 'property' && $propertyId > 0) {
        try {
            db()->prepare('UPDATE properties SET views_count = views_count + 1 WHERE id = ?')->execute([$propertyId]);
        } catch (Throwable $e) {}

        // จำค่าใน Session ของผู้ใช้
        $_SESSION['viewed_properties'][$propertyId] = time();

        // ตั้ง First-party Cookie คูลดาวน์ 2 ชั่วโมง
        setcookie('sp_view_p_' . $propertyId, '1', [
            'expires' => time() + $cooldownSeconds,
            'path' => '/',
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    echo json_encode(['ok' => true, 'counted' => true]);
} else {
    // กรณีเป็นการรีเฟรชหน้าจอ (ไม่นับยอดเพิ่ม และไม่บันทึก visits ซ้ำซ้อน)
    if ($event === 'property' && $propertyId > 0) {
        $_SESSION['viewed_properties'][$propertyId] = time();
    }
    echo json_encode(['ok' => true, 'counted' => false, 'skipped' => 'refresh_cooldown']);
}
