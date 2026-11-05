<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$message = '';
$errors = [];
$imageDirectory = UPLOAD_DIR . 'categories/';

if (request_is_post()) {
    csrf_check();
    $category = (string)($_POST['category'] ?? '');
    $action = (string)($_POST['action'] ?? '');

    if (!array_key_exists($category, PROPERTY_CATEGORIES) || $category === 'other') {
        $errors[] = 'เลือกหมวดหมู่ที่ถูกต้อง';
    } elseif ($action === 'delete') {
        $statement = $pdo->prepare('SELECT filename FROM category_images WHERE category = ?');
        $statement->execute([$category]);
        $filename = $statement->fetchColumn();
        if ($filename) {
            $pdo->prepare('DELETE FROM category_images WHERE category = ?')->execute([$category]);
            $path = $imageDirectory . basename($filename);
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('ไม่สามารถลบไฟล์ภาพหมวดหมู่ได้');
            }
            $message = 'ลบภาพหมวดหมู่เรียบร้อยแล้ว';
        }
    } elseif ($action === 'save') {
        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'เลือกไฟล์ภาพก่อนบันทึก';
        } elseif ($file['size'] > MAX_IMAGE_BYTES) {
            $errors[] = 'ขนาดภาพต้องไม่เกิน 5 MB';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
                $errors[] = 'รองรับเฉพาะไฟล์ภาพ JPG, PNG หรือ WebP';
            } else {
                if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
                    throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บภาพหมวดหมู่ได้');
                }
                $filename = 'category-' . $category . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($file['tmp_name'], $imageDirectory . $filename)) {
                    throw new RuntimeException('ไม่สามารถบันทึกไฟล์ภาพหมวดหมู่ได้');
                }

                $statement = $pdo->prepare('SELECT filename FROM category_images WHERE category = ?');
                $statement->execute([$category]);
                $oldFilename = $statement->fetchColumn();
                try {
                    $pdo->prepare(
                        'INSERT INTO category_images (category, filename) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE filename = VALUES(filename)'
                    )->execute([$category, $filename]);
                } catch (Throwable $saveError) {
                    // Do not leave an unreferenced uploaded file if saving metadata failed.
                    $newPath = $imageDirectory . $filename;
                    if (is_file($newPath)) { unlink($newPath); }
                    throw $saveError;
                }

                if ($oldFilename) {
                    $oldPath = $imageDirectory . basename($oldFilename);
                    if (is_file($oldPath) && !unlink($oldPath)) {
                        throw new RuntimeException('ภาพใหม่บันทึกแล้ว แต่ไม่สามารถลบภาพเดิมได้');
                    }
                }
                $message = 'บันทึกภาพประจำหมวดหมู่เรียบร้อยแล้ว';
            }
        }
    } else {
        $errors[] = 'คำสั่งไม่ถูกต้อง';
    }
}

$images = $pdo->query('SELECT category, filename FROM category_images')->fetchAll(PDO::FETCH_KEY_PAIR);
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการภาพหมวดหมู่ | หลังบ้าน</title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .category-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:18px; }
    .category-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px; box-shadow:0 4px 16px rgba(11,28,51,.05); }
    .category-card h2 { margin:0; font-size:16px; color:#0B1C33; display:flex; align-items:center; gap:8px; }
    .category-preview-wrap { position:relative; }
    .category-preview { width:100%; height:155px; object-fit:cover; border-radius:10px; background:#f1f5f9; display:block; }
    .category-preview-empty { width:100%; height:155px; border-radius:10px; background:linear-gradient(135deg,#FDF8EE,#F1E4C8); border:1px dashed #d8c9a4; display:grid; place-items:center; color:#A8843F; font-size:12.5px; font-weight:700; }
    .category-icon-preview { position:absolute; right:8px; bottom:8px; width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 4px 12px rgba(11,28,51,.25); background:#fff; }
    .category-card input[type=file] { max-width:100%; margin:6px 0; font-size:12.5px; }
    .category-card label { display:grid; gap:6px; font-size:12.5px; font-weight:700; color:#475569; }
    .category-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:auto; }
  </style>
</head>
<body>
<?php require __DIR__ . '/_sidebar.php'; ?>
<div class="topbar">
  <strong>🖼️ ภาพประจำหมวดหมู่</strong>
  <nav>
    <a href="index.php">จัดการประกาศ</a>
    <a href="analytics.php">วิเคราะห์สถิติ</a>
    <a href="../dashboard.php">แดชบอร์ด</a>
  </nav>
</div>
<main class="wrap">
  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
  <p class="muted">อัปโหลดภาพ JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB ภาพจะแสดงเป็น <strong>ไอคอนหมวดหมู่บนหน้าแรก</strong> (วงกลมมุมขวาล่างของตัวอย่าง) และส่วนหัวของหน้าหมวดหมู่นั้น</p>
  <div class="category-grid">
    <?php foreach (PROPERTY_CATEGORIES as $category => $label): if ($category === 'other') continue; ?>
      <?php $filename = $images[$category] ?? ''; $validImage = $filename !== '' && is_file($imageDirectory . basename($filename)); ?>
      <section class="category-card">
        <h2><?= e($label) ?></h2>
        <?php if ($validImage): ?>
          <div class="category-preview-wrap">
            <img class="category-preview" src="../uploads/categories/<?= e(basename($filename)) ?>" alt="ภาพหมวด<?= e($label) ?>">
            <img class="category-icon-preview" src="../uploads/categories/<?= e(basename($filename)) ?>" alt="ไอคอนหมวด<?= e($label) ?>" title="ตัวอย่างไอคอนบนหน้าแรก">
          </div>
        <?php else: ?>
          <div class="category-preview-wrap">
            <div class="category-preview-empty">ยังไม่มีภาพหมวดหมู่</div>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="category" value="<?= e($category) ?>">
          <input type="hidden" name="action" value="save">
          <label>เลือกรูปภาพใหม่
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          </label>
          <button type="submit" class="btn-action btn-accent">บันทึกภาพ</button>
        </form>
        <?php if ($validImage): ?>
          <form method="post" class="category-actions" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="category" value="<?= e($category) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-action btn-red">ลบภาพ</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
