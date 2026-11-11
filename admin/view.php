<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    die(t('ไม่พบรายการประกาศนี้ในระบบ', 'Listing not found in the system'));
}

$img_stmt = $pdo->prepare("SELECT filename FROM property_images WHERE property_id = ? ORDER BY sort_order, id");
$img_stmt->execute([$id]);
$images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('รายละเอียดประกาศ #', 'Listing Details #')) . (int)$property['id'] ?> | Admin</title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .view-page-wrap { max-width: 900px; margin: 32px auto 70px; padding: 0 20px; }
    .view-page-box { background: #fff; padding: 36px; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(23,27,61,.06); }
    .view-page-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #edf2f7; }
    .view-page-top h1 { margin: 0; font-size: 22px; color: #251c42; }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>

<main class="view-page-wrap">
  <div class="view-page-box">
    
    <div class="view-page-top">
      <h1>📄 <?= e(t('รายละเอียดประกาศ #', 'Listing Details #')) . (int)$property['id'] ?></h1>
      <div style="display: flex; gap: 8px; align-items: center;">
        <a href="index.php" class="btn-action btn-gray"><?= e(t('← กลับหน้ารายการ', '← Back to Listings')) ?></a>
        <?php if ($property['approval'] === 'pending'): ?>
          <a href="index.php?action=approve&id=<?= (int)$property['id'] ?>" class="btn-action btn-green">✅ <?= e(t('อนุมัติประกาศนี้', 'Approve Listing')) ?></a>
        <?php else: ?>
          <span class="tag <?= $property['approval'] === 'approved' ? 'approved' : 'rejected' ?>"><?= e(t('สถานะ: ', 'Status: ')) . e($property['approval']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
      <tr><td style="padding: 8px; font-weight: bold; width: 140px; color: #64748b;"><?= e(t('หัวข้อประกาศ:', 'Listing Title:')) ?></td><td style="padding: 8px; font-size: 18px; font-weight: bold; color: #2b2150;"><?= e($property['title']) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('ประเภท / หมวดหมู่:', 'Type / Category:')) ?></td><td style="padding: 8px;"><?= e(type_label($property['type'])) ?> / <?= e(category_label_bi($property['category'])) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('ราคา:', 'Price:')) ?></td><td style="padding: 8px; color: #c62828; font-weight: bold; font-size: 18px;"><?= e(price_label($property['type'], (int)$property['price'])) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('ทำเล / สถานที่:', 'Location:')) ?></td><td style="padding: 8px;">📍 <?= e($property['location']) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('ชื่อผู้ติดต่อ:', 'Contact Name:')) ?></td><td style="padding: 8px;"><?= e($property['contact_name'] ?? t('ไม่ระบุ', 'Not specified')) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('เบอร์โทร / ไลน์:', 'Phone / LINE:')) ?></td><td style="padding: 8px; color: #1565c0; font-weight: bold;"><?= e($property['contact_phone'] ?? t('ไม่ระบุ', 'Not specified')) ?></td></tr>
      <tr><td style="padding: 8px; font-weight: bold; color: #64748b;"><?= e(t('วันที่ลงประกาศ:', 'Posted Date:')) ?></td><td style="padding: 8px;"><?= e($property['created_at']) ?></td></tr>
    </table>

    <h3 style="font-size: 16px; color: #2b2150; margin-bottom: 8px;"><?= e(t('รายละเอียดอสังหาริมทรัพย์', 'Property Description')) ?></h3>
    <div style="white-space: pre-line; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; line-height: 1.7;">
      <?= e($property['description']) ?>
    </div>

    <h3 style="font-size: 16px; color: #2b2150; margin-bottom: 12px;"><?= e(t('รูปภาพทั้งหมด (', 'All Photos (')) . count($images) . e(t(' รูป)', ' photos)')) ?></h3>
    <?php if (empty($images)): ?>
      <p style="color: #94a3b8;"><?= e(t('ไม่มีรูปภาพแนบในประกาศนี้', 'No photos attached')) ?></p>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <?php foreach ($images as $img): ?>
          <a href="../uploads/<?= e($img) ?>" target="_blank" style="display: block; height: 130px; border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <img src="../uploads/<?= e($img) ?>" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" decoding="async" alt="<?= e(t('รูปประกาศ', 'Listing photo')) ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 24px;">
      <a href="index.php" class="btn" style="background: #64748b;"><?= e(t('← กลับหน้ารายการทั้งหมด', '← Back to All Listings')) ?></a>
      <a href="edit.php?id=<?= (int)$property['id'] ?>" class="btn" style="background: #7955d9; margin-left: 8px;"><?= e(t('✏️ แก้ไขข้อมูลนี้', '✏️ Edit This Listing')) ?></a>
    </div>

  </div>
</main>

</body>
</html>
