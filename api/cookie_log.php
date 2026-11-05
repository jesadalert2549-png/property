<?php
require_once __DIR__ . '/../includes/helpers.php';
start_session();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$choice = is_array($data) ? ($data['choice'] ?? '') : '';
if (!in_array($choice, ['accepted', 'denied'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid consent choice']);
    exit;
}

if ($choice === 'denied' && preg_match('/^[a-f0-9]{64}$/', $_COOKIE['sp_visitor_id'] ?? '')) {
    db()->prepare('DELETE FROM visits WHERE visitor_hash = ?')
        ->execute([hash('sha256', $_COOKIE['sp_visitor_id'])]);
    setcookie('sp_visitor_id', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

setcookie('sp_analytics_consent', $choice, [
    'expires' => time() + 86400 * 365,
    'path' => '/',
    'secure' => request_is_https(),
    'httponly' => false,
    'samesite' => 'Lax',
]);
echo json_encode(['status' => 'success', 'choice' => $choice]);