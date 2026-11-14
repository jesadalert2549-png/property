<?php
require_once __DIR__ . '/../includes/helpers.php';
start_session();
header('Content-Type: application/json; charset=utf-8');

/* ⚠ ต้องใช้ path เดียวกับที่ JS อ่าน (document.cookie กรองตาม path)
   เดิมฮาร์ดโค้ดเป็น '/' → บน localhost/kasatest/ cookie จึงถูกเก็บที่
   localhost/ แต่หน้าเว็บอยู่ที่ /kasatest/ ทำให้อ่านไม่เจอ
   แล้ว banner จะเด้งขึ้นใหม่ทุกครั้ง (คืออาการ "cookie ค้าง")
   คำนวณจากตำแหน่งไฟล์จริง เพื่อให้ย้ายโฟลเดอร์ได้โดยไม่ต้องแก้ */
$cookiePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';
if ($cookiePath === '/' || $cookiePath === '') {
    $cookiePath = '/';
}

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
        'path' => $cookiePath,
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    /* ล้าง cookie path เก่า ('/') ที่ค้างจากเวอร์ชันก่อนด้วย
       ไม่เช่นนั้นเบราว์เซอร์ที่เคยเข้าจะยังมีตัวเก่าอยู่ */
    if ($cookiePath !== '/') {
        setcookie('sp_visitor_id', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

setcookie('sp_analytics_consent', $choice, [
    'expires' => time() + 86400 * 365,
    'path' => $cookiePath,
    'secure' => request_is_https(),
    'httponly' => false,
    'samesite' => 'Lax',
]);

/* ล้างค่าเก่าที่ถูกเก็บไว้ที่ path '/' จากเวอร์ชันก่อน */
if ($cookiePath !== '/') {
    setcookie('sp_analytics_consent', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
echo json_encode(['status' => 'success', 'choice' => $choice]);