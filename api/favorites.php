<?php
require_once __DIR__ . '/../includes/helpers.php';
start_session();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pdo = db();

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS property_favorites (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        property_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_user_prop (user_id, property_id),
        KEY idx_user (user_id),
        KEY idx_prop (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. ดึงรายการ ID ทรัพย์ที่บันทึกไว้ (GET)
if ($method === 'GET') {
    $favIds = [];
    if ($userId > 0) {
        $st = $pdo->prepare('SELECT property_id FROM property_favorites WHERE user_id = ? ORDER BY id DESC');
        $st->execute([$userId]);
        $favIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    echo json_encode(['ok' => true, 'is_logged_in' => $userId > 0, 'favorites' => $favIds]);
    exit;
}

// 2. จัดการคำขอ POST
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    // 2.1 ซิงก์รายการจาก Guest เข้าสู่บัญชีสมาชิกทันทีหลังล็อกอิน (Sync Guest to User)
    if (($input['action'] ?? '') === 'sync_guest') {
        if ($userId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Login required for sync']);
            exit;
        }
        $guestIds = array_filter(array_map('intval', (array)($input['guest_ids'] ?? [])), fn($id) => $id > 0);
        $inserted = 0;
        if (!empty($guestIds)) {
            $st = $pdo->prepare('INSERT IGNORE INTO property_favorites (user_id, property_id) VALUES (?, ?)');
            foreach ($guestIds as $gid) {
                $st->execute([$userId, $gid]);
                if ($st->rowCount() > 0) $inserted++;
            }
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM property_favorites WHERE user_id = ?');
        $count->execute([$userId]);
        $totalFavs = (int)$count->fetchColumn();

        $stList = $pdo->prepare('SELECT property_id FROM property_favorites WHERE user_id = ? ORDER BY id DESC');
        $stList->execute([$userId]);
        $favIds = array_map('intval', $stList->fetchAll(PDO::FETCH_COLUMN));

        echo json_encode(['ok' => true, 'synced' => $inserted, 'total' => $totalFavs, 'favorites' => $favIds]);
        exit;
    }

    // 2.2 สลับการกดใจ บันทึก/ยกเลิก (Toggle Favorite)
    $propertyId = (int)($input['property_id'] ?? 0);

    if ($propertyId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid property ID']);
        exit;
    }

    // ตรวจสอบว่าทรัพย์นั้นมีอยู่จริงและอนุมัติแล้ว
    $check = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND approval = 'approved' LIMIT 1");
    $check->execute([$propertyId]);
    if (!$check->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Property not found']);
        exit;
    }

    if ($userId > 0) {
        // บันทึกลง Database
        $find = $pdo->prepare('SELECT id FROM property_favorites WHERE user_id = ? AND property_id = ? LIMIT 1');
        $find->execute([$userId, $propertyId]);
        $favId = $find->fetchColumn();

        if ($favId) {
            $pdo->prepare('DELETE FROM property_favorites WHERE id = ?')->execute([(int)$favId]);
            $isFav = false;
        } else {
            $pdo->prepare('INSERT INTO property_favorites (user_id, property_id) VALUES (?, ?)')->execute([$userId, $propertyId]);
            $isFav = true;
        }

        $count = $pdo->prepare('SELECT COUNT(*) FROM property_favorites WHERE user_id = ?');
        $count->execute([$userId]);
        $totalFavs = (int)$count->fetchColumn();

        echo json_encode(['ok' => true, 'is_logged_in' => true, 'is_favorite' => $isFav, 'total' => $totalFavs]);
        exit;
    } else {
        // หากยังไม่ได้ล็อกอิน ส่งสถานะกลับเพื่อให้ JS จัดการใน LocalStorage
        echo json_encode(['ok' => true, 'is_logged_in' => false, 'guest_mode' => true, 'property_id' => $propertyId]);
        exit;
    }
}
