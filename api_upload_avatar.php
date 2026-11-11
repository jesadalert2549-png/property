<?php
/**
 * Standalone Fast Ajax Profile Photo Uploader
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/profile_schema.php';
require_once __DIR__ . '/includes/profile_media.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    csrf_check();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าเว็บแล้วลองใหม่อีกครั้ง']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo = db();

try {
    if (empty($_FILES['avatar']['name'])) {
        throw new RuntimeException('กรุณาเลือกไฟล์รูปภาพ');
    }

    $newAvatar = save_profile_image($_FILES['avatar'], 'avatars');
    if ($newAvatar === '') {
        throw new RuntimeException('บันทึกรูปภาพไม่สำเร็จ');
    }

    // Get old avatar to delete
    $oldStmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ? LIMIT 1');
    $oldStmt->execute([$userId]);
    $oldAvatar = (string)$oldStmt->fetchColumn();

    // Update DB
    $updateStmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
    $updateStmt->execute([$newAvatar, $userId]);

    // Delete old avatar
    if ($oldAvatar !== '' && $oldAvatar !== $newAvatar) {
        delete_profile_image($oldAvatar, 'avatars');
    }

    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตรูปโปรไฟล์สำเร็จเรียบร้อยแล้ว!',
        'avatar_url' => 'uploads/avatars/' . rawurlencode($newAvatar) . '?v=' . time(),
        'filename' => $newAvatar
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
