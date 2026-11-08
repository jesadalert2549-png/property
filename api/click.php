<?php
require_once __DIR__ . '/../includes/helpers.php';
start_session();

header('Content-Type: application/json; charset=utf-8');

// ไม่นับคลิกของแอดมินเจ้าของระบบ
if (($_SESSION['role'] ?? '') === 'admin' || isset($_COOKIE['no_track'])) {
    echo json_encode(['ok' => true, 'skipped' => 'admin']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$propertyId = (int)($input['property_id'] ?? 0);

if ($propertyId > 0) {
    try {
        $pdo = db();
        $st = $pdo->prepare('UPDATE properties SET clicks_count = clicks_count + 1 WHERE id = ?');
        $st->execute([$propertyId]);

        // ดึงสถิติล่าสุด
        $res = $pdo->prepare('SELECT views_count, clicks_count FROM properties WHERE id = ? LIMIT 1');
        $res->execute([$propertyId]);
        $row = $res->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'property_id' => $propertyId,
            'views' => (int)($row['views_count'] ?? 0),
            'clicks' => (int)($row['clicks_count'] ?? 0)
        ]);
        exit;
    } catch (Throwable $e) {}
}

echo json_encode(['ok' => false]);
