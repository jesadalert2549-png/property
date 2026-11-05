<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS featured_sections (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(30) NOT NULL,
        title VARCHAR(120) NOT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_featured_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (request_is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $category = trim($_POST['category'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if (!array_key_exists($category, PROPERTY_CATEGORIES) || mb_strlen($title) < 2) {
            set_flash('กรุณาเลือกหมวดและกรอกชื่อหัวข้อให้ครบ');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO featured_sections (category, title, sort_order, enabled)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), sort_order = VALUES(sort_order), enabled = VALUES(enabled)'
            );
            $stmt->execute([$category, $title, $sortOrder, $enabled]);
            set_flash('บันทึกหมวดแนะนำเรียบร้อยแล้ว');
        }
        redirect('featured.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM featured_sections WHERE id = ?')->execute([$id]);
        set_flash('ลบหมวดแนะนำแล้ว');
        redirect('featured.php');
    }
}

$sections = $pdo->query(
    'SELECT id, category, title, sort_order, enabled
     FROM featured_sections
     ORDER BY sort_order, id'
)->fetchAll();
$token = csrf_token();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการหมวดแนะนำหน้าแรก | หลังบ้าน</title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    :root { --primary:#2b2150; --line:#e5e9ee; --muted:#697586; }
    body { margin:0; background:#f8fafc; font-family:system-ui,sans-serif; color:#334155; }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 24px; background:var(--primary); color:#fff; }
    .topbar a { color:#cbd5e1; text-decoration:none; margin-left:14px; }
    .wrap { max-width:1000px; margin:28px auto; padding:0 20px; }
    .box { background:#fff; border:1px solid var(--line); border-radius:12px; padding:24px; margin-bottom:20px; }
    .box h2 { margin:0 0 6px; font-size:20px; color:var(--primary); }
    .intro { color:var(--muted); margin:0 0 20px; }
    .section-form { display:grid; grid-template-columns:minmax(160px,1fr) minmax(220px,2fr) 110px auto auto; gap:12px; align-items:end; }
    label { display:grid; gap:6px; font-size:13px; font-weight:600; }
    input, select { width:100%; height:42px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px; font:inherit; }
    .check { display:flex; height:42px; align-items:center; gap:8px; white-space:nowrap; }
    .check input { width:18px; height:18px; }
    .btn { height:42px; border:0; border-radius:8px; padding:0 18px; background:#7955d9; color:#fff; font-weight:700; cursor:pointer; }
    .list { width:100%; border-collapse:collapse; }
    th, td { padding:14px 12px; border-bottom:1px solid var(--line); text-align:left; }
    th { color:var(--muted); font-size:13px; }
    .pill { display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; background:#dcfce7; color:#166534; }
    .pill.off { background:#e2e8f0; color:#475569; }
    .delete { border:0; background:none; color:#c64e42; cursor:pointer; font-weight:700; }
    .flash { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:12px 16px; border-radius:8px; margin-bottom:16px; }
    @media (max-width:760px) {
      .topbar { align-items:flex-start; flex-direction:column; }
      .topbar nav { display:flex; flex-wrap:wrap; gap:10px; }
      .topbar a { margin:0; }
      .section-form { grid-template-columns:1fr; }
      .table-wrap { overflow-x:auto; }
      .list { min-width:620px; }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/_sidebar.php'; ?>
<div class="topbar">
  <strong>🏠 หมวดแนะนำหน้าแรก</strong>
  <nav>
    <a href="index.php">จัดการประกาศ</a>
    <a href="analytics.php">สถิติ</a>
    <a href="../index.php">ดูหน้าเว็บไซต์</a>
  </nav>
</div>
<div class="wrap">
  <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

  <section class="box">
    <h2>เพิ่มหรือแก้ไขหมวด</h2>
    <p class="intro">แต่ละหมวดแสดง 4 ประกาศล่าสุดที่อนุมัติหน้าแรก และลิงก์ดูประกาศทั้งหมดไปยังค้นหาตามหมวด</p>
    <form method="post" class="section-form">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="save">
      <label>หมวดอสังหาฯ
        <select name="category" required>
          <?php foreach (PROPERTY_CATEGORIES as $key => $label): ?>
            <?php if ($key !== 'other'): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endif; ?>
          <?php endforeach; ?>
        </select>
      </label>
      <label>ชื่อหัวข้อบนหน้าแรก
        <input type="text" name="title" maxlength="120" placeholder="เช่น คอนโดแนะนำ" required>
      </label>
      <label>ลำดับ
        <input type="number" name="sort_order" min="0" value="0">
      </label>
      <label class="check"><input type="checkbox" name="enabled" checked>เปิดแสดง</label>
      <button type="submit" class="btn">บันทึกหมวด</button>
    </form>
  </section>

  <section class="box">
    <h2>หมวดที่ตั้งไว้</h2>
    <?php if (!$sections): ?>
      <p class="intro" style="margin-top:16px">ยังไม่มีหมวด แนะนำให้เพิ่มอย่างน้อย 4 หมวดจากแบบฟอร์มด้านบน</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="list">
          <thead><tr><th>ลำดับ</th><th>หมวด</th><th>ชื่อหัวข้อ</th><th>สถานะ</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($sections as $section): ?>
              <tr>
                <td><?= (int)$section['sort_order'] ?></td>
                <td><?= e(PROPERTY_CATEGORIES[$section['category']] ?? $section['category']) ?></td>
                <td><strong><?= e($section['title']) ?></strong></td>
                <td><span class="pill <?= $section['enabled'] ? '' : 'off' ?>"><?= $section['enabled'] ? 'เปิดแสดง' : 'ปิด' ?></span></td>
                <td>
                  <form method="post" onsubmit="return confirm('ลบหมวดนี้หรือไม่?')">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$section['id'] ?>">
                    <button class="delete" type="submit">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
</body>
</html>

