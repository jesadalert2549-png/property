<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// แสดงเฉพาะประกาศที่อนุมัติแล้ว และเรียงให้ที่ยังไม่ขายอยู่ก่อน
$rows = db()->query(
    "SELECT id, title, type, status, price, location, description, contact_name, phone
     FROM properties
     WHERE approval = 'approved'
     ORDER BY (status = 'done'), created_at DESC"
)->fetchAll();

// ดึงรูปของทุกประกาศในคำสั่งเดียว
$images = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT property_id, filename FROM property_images
         WHERE property_id IN ($marks) ORDER BY sort_order, id"
    );
    $stmt->execute($ids);
    foreach ($stmt as $img) {
        $filename = basename($img['filename']);
        if (!is_file(UPLOAD_DIR . $filename)) {
            continue;
        }
        $images[$img['property_id']][] = 'uploads/' . $filename;
    }
}

$result = [];
foreach ($rows as $row) {
    $result[] = [
        'id'          => (int)$row['id'],
        'title'       => $row['title'],
        'type'        => $row['type'],        // sale หรือ rent
        'status'      => $row['status'],      // available หรือ done
        'price'       => (int)$row['price'],
        'location'    => $row['location'],
        'desc'        => $row['description'],
        'contactName' => $row['contact_name'],
        'phone'       => $row['phone'],
        'images'      => $images[$row['id']] ?? [],
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
