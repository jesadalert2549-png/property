<?php
/**
 * PropertyGO — Smart Property Insights & Notification Engine
 * จัดการกล่องข้อความแจ้งเตือน: ทรัพย์ลดราคา, ทำเลทองเด่น, แจ้งเตือนสิทธิประโยชน์
 */

require_once __DIR__ . '/helpers.php';

function ensure_notification_tables(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) return;
    try {
        $pdo = $pdo ?: db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL DEFAULT 0,
            property_id INT NULL,
            type ENUM('price_drop', 'hot_deal', 'prime_location', 'new_match', 'system') NOT NULL DEFAULT 'system',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) NULL,
            icon VARCHAR(20) NOT NULL DEFAULT '🔔',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_read (user_id, is_read),
            KEY idx_created (created_at),
            KEY idx_prop (property_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
            user_id INT UNSIGNED NOT NULL,
            notification_id INT UNSIGNED NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, notification_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
    } catch (Throwable $e) {}
}

/**
 * ส่งการแจ้งเตือนหาผู้ใช้เฉพาะบุคคล หรือ Broadcast (user_id = 0)
 */
function send_user_notification(int $userId, string $type, string $title, string $message, ?string $linkUrl = null, ?int $propertyId = null, string $icon = '🔔'): int
{
    try {
        $pdo = db();
        ensure_notification_tables($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO user_notifications (user_id, property_id, type, title, message, link_url, icon, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$userId, $propertyId, $type, $title, $message, $linkUrl, $icon]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * นับจำนวนข้อความแจ้งเตือนที่ยังไม่ได้อ่านของผู้ใช้ปัจจุบัน
 */
function unread_notification_count(int $userId = 0): int
{
    if ($userId <= 0) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
    }
    if ($userId <= 0) return 0;

    try {
        $pdo = db();
        ensure_notification_tables($pdo);

        // นับทั้งข้อความเฉพาะ user_id ที่ยัง is_read = 0
        // และข้อความ Broadcast (user_id = 0) ที่ user ยังไม่ได้อ่านในตาราง notification_reads
        $sql = "SELECT COUNT(*) FROM user_notifications n
                WHERE (n.user_id = ? AND n.is_read = 0)
                   OR (n.user_id = 0 AND NOT EXISTS (
                       SELECT 1 FROM notification_reads nr WHERE nr.user_id = ? AND nr.notification_id = n.id
                   ))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * ดึงรายการการแจ้งเตือนของผู้ใช้
 */
function get_user_notifications(int $userId = 0, int $limit = 30): array
{
    if ($userId <= 0) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
    }

    try {
        $pdo = db();
        ensure_notification_tables($pdo);

        if ($userId > 0) {
            $sql = "SELECT n.*,
                           p.price AS property_price,
                           p.location AS property_location,
                           (SELECT filename FROM property_images i WHERE i.property_id = n.property_id ORDER BY i.sort_order, i.id LIMIT 1) AS property_cover,
                           IF(n.user_id = 0,
                              IF(nr.read_at IS NOT NULL, 1, 0),
                              n.is_read
                           ) AS read_status
                    FROM user_notifications n
                    LEFT JOIN properties p ON p.id = n.property_id
                    LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
                    WHERE n.user_id = ? OR n.user_id = 0
                    ORDER BY n.created_at DESC
                    LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Guest mode: ดึงเฉพาะ Broadcast ล่าสุด
            $sql = "SELECT n.*,
                           p.price AS property_price,
                           p.location AS property_location,
                           (SELECT filename FROM property_images i WHERE i.property_id = n.property_id ORDER BY i.sort_order, i.id LIMIT 1) AS property_cover,
                           0 AS read_status
                    FROM user_notifications n
                    LEFT JOIN properties p ON p.id = n.property_id
                    WHERE n.user_id = 0
                    ORDER BY n.created_at DESC
                    LIMIT 10";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * สร้างข้อความแจ้งเตือนอัตโนมัติเกี่ยวกับทรัพย์ลดราคา หรือทำเลทอง (สำหรับแสดงผลจริง)
 */
function ensure_smart_property_insights(): void
{
    static $seeded = false;
    if ($seeded) return;

    try {
        $pdo = db();
        ensure_notification_tables($pdo);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM user_notifications")->fetchColumn();
        if ($count === 0) {
            // ดึงทรัพย์เด่นๆ ในระบบมาสร้าง Insights ข้อความที่เป็นประโยชน์จริง
            $featured = $pdo->query("SELECT id, title, price, location, property_category FROM properties WHERE approval = 'approved' ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($featured[0])) {
                $p1 = $featured[0];
                send_user_notification(
                    0,
                    'hot_deal',
                    '🔥 โอกาสทอง! มีทรัพย์แนะนำราคาคุ้มค่าในทำเล ' . ($p1['location'] ?: 'ทำเลเด่น'),
                    'พบประกาศ "' . $p1['title'] . '" ราคาพิเศษ ฿' . number_format((int)$p1['price']) . ' ทำเลดี เดินทางสะดวก เหมาะสำหรับอยู่อาศัยหรือลงทุน',
                    'property.php?id=' . (int)$p1['id'],
                    (int)$p1['id'],
                    '🔥'
                );
            }

            if (!empty($featured[1])) {
                $p2 = $featured[1];
                send_user_notification(
                    0,
                    'prime_location',
                    '🚇 ทำเลศักยภาพสูง ใกล้สิ่งอำนวยความสะดวก: ' . ($p2['location'] ?: 'ย่านเศรษฐกิจ'),
                    'ประกาศใหม่ในทำเลที่คนตามหามากที่สุด "' . $p2['title'] . '" เปรียบเทียบราคาและติดต่อเจ้าของได้โดยตรงแล้ววันนี้',
                    'property.php?id=' . (int)$p2['id'],
                    (int)$p2['id'],
                    '📍'
                );
            }

            send_user_notification(
                0,
                'system',
                '💡 เคล็ดลับ: กดหัวใจ ❤️ ทรัพย์ที่สนใจเพื่อติดตามราคาและโปรโมชัน',
                'เมื่อคุณบันทึกทรัพย์ไว้ในรายการที่ชอบ ระบบจะช่วยจัดเก็บไว้ในกล่องความจำเพื่อให้คุณกลับมาเปรียบเทียบราคาและทำเลได้สะดวกรวดเร็ว',
                'favorites.php',
                null,
                '❤️'
            );
        }
        $seeded = true;
    } catch (Throwable $e) {}
}
