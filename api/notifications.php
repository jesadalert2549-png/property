<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
start_session();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pdo = db();
ensure_notification_tables($pdo);
ensure_smart_property_insights();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. ดึงรายการแจ้งเตือน + จำนวนยังไม่อ่าน (GET)
if ($method === 'GET') {
    $unread = unread_notification_count($userId);
    $list = get_user_notifications($userId, 20);

    echo json_encode([
        'ok' => true,
        'is_logged_in' => $userId > 0,
        'unread_count' => $unread,
        'notifications' => $list
    ]);
    exit;
}

// 2. ทำเครื่องหมายว่าอ่านแล้ว (POST)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'mark_read';

    if ($userId <= 0) {
        echo json_encode(['ok' => true, 'guest' => true]);
        exit;
    }

    if ($action === 'mark_read') {
        $notifId = (int)($input['notification_id'] ?? 0);
        if ($notifId > 0) {
            // เช็คว่าเป็นของ user หรือ broadcast
            $st = $pdo->prepare('SELECT user_id FROM user_notifications WHERE id = ? LIMIT 1');
            $st->execute([$notifId]);
            $ownerId = $st->fetchColumn();

            if ($ownerId !== false) {
                if ((int)$ownerId === $userId) {
                    $pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE id = ?')->execute([$notifId]);
                } elseif ((int)$ownerId === 0) {
                    $pdo->prepare('INSERT IGNORE INTO notification_reads (user_id, notification_id, read_at) VALUES (?, ?, NOW())')->execute([$userId, $notifId]);
                }
            }
        }
    } elseif ($action === 'mark_all_read') {
        // ทำเครื่องหมายอ่านแล้วทั้งหมด
        $pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);

        // มาร์ก broadcast ทั้งหมด
        $allBroadcast = $pdo->query('SELECT id FROM user_notifications WHERE user_id = 0')->fetchAll(PDO::FETCH_COLUMN);
        if ($allBroadcast) {
            $insert = $pdo->prepare('INSERT IGNORE INTO notification_reads (user_id, notification_id, read_at) VALUES (?, ?, NOW())');
            foreach ($allBroadcast as $bId) {
                $insert->execute([$userId, (int)$bId]);
            }
        }
    }

    $unread = unread_notification_count($userId);
    echo json_encode(['ok' => true, 'unread_count' => $unread]);
    exit;
}
