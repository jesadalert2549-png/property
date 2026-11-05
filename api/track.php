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
}

db()->prepare(
    'INSERT INTO visits (visitor_hash, user_id, event_type, property_id, page_path, referrer, user_agent, device)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([$visitorHash, !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $event, $propertyId, $pagePath, $referrer, $browser, $device]);

echo json_encode(['ok' => true]);
