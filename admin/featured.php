<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin('marketing', 'view');

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageMarketing = $isFounder || staff_can(db(), $staffId, 'marketing', 'edit') || staff_can(db(), $staffId, 'marketing', 'manage');

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS featured_sections (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(30) NOT NULL,
        title VARCHAR(120) NOT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_featured_order (enabled, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// ปลดล็อก index เก่าที่จำกัด 1 หมวด 1 อันออกอย่างปลอดภัย
try {
    $pdo->exec("ALTER TABLE featured_sections DROP INDEX uq_featured_category");
} catch (Throwable $ignore) {
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;

if (request_is_post()) {
    csrf_check();
    if (!$canManageMarketing) {
        set_flash('สิทธิ์ถูกจำกัด: คุณไม่ได้รับสิทธิ์จัดการหมวดแนะนำ กรุณาติดต่อ Founder & CEO');
        redirect('featured.php');
    }
    $action = $_POST['action'] ?? '';

    // ── 1. บันทึก (เพิ่มใหม่ หรือ แก้ไขของเดิม) ──
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $category = trim((string)($_POST['category'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if (!array_key_exists($category, PROPERTY_CATEGORIES) || mb_strlen($title) < 2) {
            set_flash('กรุณาเลือกประเภทอสังหาฯ และกรอกชื่อหัวข้อให้ครบถ้วน');
        } else {
            if ($id > 0) {
                // แก้ไขรายการเดิม
                $stmt = $pdo->prepare('UPDATE featured_sections SET category = ?, title = ?, sort_order = ?, enabled = ? WHERE id = ?');
                $stmt->execute([$category, $title, $sortOrder, $enabled, $id]);
                set_flash('อัปเดตหมวดแนะนำ "' . $title . '" เรียบร้อยแล้ว');
            } else {
                // เพิ่มรายการใหม่ (สามารถเพิ่มได้หลายหมวดได้ไม่จำกัด!)
                $stmt = $pdo->prepare('INSERT INTO featured_sections (category, title, sort_order, enabled) VALUES (?, ?, ?, ?)');
                $stmt->execute([$category, $title, $sortOrder, $enabled]);
                set_flash('เพิ่มหมวดแนะนำใหม่ "' . $title . '" เรียบร้อยแล้ว (สามารถเพิ่มหลายหมวดได้อย่างอิสระ)');
            }
        }
        redirect('featured.php');
    }

    // ── 1.1 บันทึกหลายหมวดพร้อมกัน (Multi-Add Bulk Mode) ──
    if ($action === 'save_bulk') {
        $bulkCategories = $_POST['bulk_category'] ?? [];
        $bulkTitles = $_POST['bulk_title'] ?? [];
        $bulkSorts = $_POST['bulk_sort'] ?? [];
        $addedCount = 0;
        $stmt = $pdo->prepare('INSERT INTO featured_sections (category, title, sort_order, enabled) VALUES (?, ?, ?, 1)');
        foreach ($bulkCategories as $idx => $cat) {
            $cat = trim((string)$cat);
            $title = trim((string)($bulkTitles[$idx] ?? ''));
            $sort = max(0, (int)($bulkSorts[$idx] ?? 0));
            if (array_key_exists($cat, PROPERTY_CATEGORIES) && mb_strlen($title) >= 2) {
                $stmt->execute([$cat, $title, $sort]);
                $addedCount++;
            }
        }
        if ($addedCount > 0) {
            set_flash("เพิ่มหมวดแนะนำใหม่พร้อมกันเรียบร้อยแล้ว $addedCount หมวด");
        } else {
            set_flash("กรุณากรอกข้อมูลหมวดหมู่และชื่อหัวข้ออย่างน้อย 1 รายการ");
        }
        redirect('featured.php');
    }

    // ── 2. เปิด-ปิด การแสดงผล ──
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE featured_sections SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
        set_flash('เปลี่ยนสถานะการแสดงผลแล้ว');
        redirect('featured.php');
    }

    // ── 3. จัดลำดับ เลื่อนขึ้น/ลง ──
    if ($action === 'sort') {
        $id = (int)($_POST['id'] ?? 0);
        $dir = ($_POST['dir'] ?? 'up') === 'down' ? 1 : -1;
        $pdo->prepare('UPDATE featured_sections SET sort_order = GREATEST(0, sort_order + ?) WHERE id = ?')
            ->execute([$dir, $id]);
        set_flash('ปรับลำดับเรียบร้อยแล้ว');
        redirect('featured.php');
    }

    // ── 4. ลบหมวดแนะนำ ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM featured_sections WHERE id = ?')->execute([$id]);
        set_flash('ลบหมวดแนะนำแล้ว');
        redirect('featured.php');
    }
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM featured_sections WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$sections = $pdo->query(
    'SELECT id, category, title, sort_order, enabled, created_at,
            (SELECT COUNT(*) FROM properties p WHERE p.property_category = featured_sections.category AND p.approval = "approved" AND p.status = "available") AS active_count
     FROM featured_sections
     ORDER BY sort_order, id'
)->fetchAll(PDO::FETCH_ASSOC);

$categoryIcons = [
    'condo' => '🏢', 'house' => '🏠', 'townhouse' => '🏘️',
    'land' => '🌳', 'commercial' => '🏬', 'warehouse' => '🏭', 'other' => '🏡',
];

$token = csrf_token();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('จัดการหมวดแนะนำหน้าแรก', 'Manage Homepage Featured Categories')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .feat-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 30px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.35); margin-bottom:24px; box-shadow:0 12px 32px rgba(11,28,51,.12); }
    .feat-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .feat-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .feat-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:28px 30px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .feat-card h2 { margin:0 0 6px; color:#0B1C33; font-size:19px; }
    .feat-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; line-height:1.6; }

    .feat-form-grid { display:grid; grid-template-columns:minmax(180px,1.2fr) minmax(240px,2fr) 110px auto; gap:14px; align-items:end; }
    .field { display:grid; gap:6px; font-size:13px; font-weight:700; color:#34495d; }
    .field input, .field select { width:100%; min-height:46px; padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-size:14px; background:#fff; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); }

    .check-label { display:inline-flex; align-items:center; gap:8px; height:46px; font-size:13px; font-weight:700; color:#34495d; cursor:pointer; white-space:nowrap; }
    .check-label input { width:18px; height:18px; accent-color:#0B1C33; cursor:pointer; }

    .btn-submit-feat { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:46px; padding:0 24px; border:0; border-radius:10px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-size:15px; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); transition:transform .18s, box-shadow .18s; white-space:nowrap; }
    .btn-submit-feat:hover { transform:translateY(-1px); box-shadow:0 8px 22px rgba(197,160,89,.45); }

    .feat-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .feat-table th, .feat-table td { padding:13px 14px; border-bottom:1px solid #eef2f6; text-align:left; vertical-align:middle; }
    .feat-table th { background:#F8FAFC; color:#475569; font-size:12.5px; font-weight:700; }

    .bulk-row {
      display: grid;
      grid-template-columns: minmax(140px, 1.2fr) minmax(180px, 2fr) 90px 42px;
      gap: 10px;
      align-items: center;
    }
    @media (max-width: 640px) {
      .bulk-row {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
      }
      .bulk-row select { grid-column: span 2; }
      .bulk-row input[type="text"] { grid-column: span 2; }
    }

    .pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:800; }
    .pill.on { background:#E8FDF0; color:#04A747; border:1px solid #06C755; }
    .pill.off { background:#F1F5F9; color:#64748B; border:1px solid #CBD5E1; }
    .pill.cat { background:rgba(197,160,89,.14); color:#8C6B32; border:1px solid rgba(197,160,89,.4); }

    .mini { border:0; border-radius:8px; padding:6px 12px; font:inherit; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
    .mini.edit { background:#EBF3FE; color:#1A73E8; }
    .mini.gray { background:#F1F5F9; color:#475569; border:1px solid #E2E8F0; }
    .mini.del { background:#FFF1F1; color:#ae3333; }
    .mini.live { background:rgba(197,160,89,.15); color:#8C6B32; border:1px solid rgba(197,160,89,.4); }

    .flash { background:#effaf3; color:#187243; border:1px solid #bbebcd; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:600; font-size:14px; }

    @media (max-width:960px) {
      .feat-form-grid { grid-template-columns:1fr; }
      .check-label { height:auto; }
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">
  <div class="feat-hero">
    <div>
      <span class="eyebrow">HOMEPAGE CONTENT &amp; PROMOTION</span>
      <h1><?= e(t('หมวดหมู่แนะนำหน้าแรก (Featured Categories)', 'Homepage Featured Categories')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;"><?= e(t('สร้างหมวดหมู่แนะนำได้หลายหมวดอย่างอิสระ ไม่จำกัดจำนวน แต่ละหมวดจะดึงประกาศ 4 ชิ้นมาแสดงบนหน้าแรกทันที', 'Create unlimited custom featured categories on the homepage. Each section displays 4 matching property listings.')) ?></p>
    </div>
    <a href="../index.php" target="_blank" rel="noopener" style="padding:10px 18px;border-radius:999px;background:rgba(197,160,89,.18);border:1px solid rgba(197,160,89,.45);color:#E5CB96;font-size:13px;font-weight:700;text-decoration:none;">👁️ <?= e(t('ดูผลบนหน้าแรก ↗', 'View on Homepage ↗')) ?></a>
  </div>

  <?php if ($flash): ?><div class="flash">✅ <?= e($flash) ?></div><?php endif; ?>

  <!-- ฟอร์มเพิ่ม / แก้ไขหมวดแนะนำ -->
  <section class="feat-card">
    <h2><?= $editing ? e(t('✏️ แก้ไขหมวดแนะนำ: ', '✏️ Edit Featured Section: ')) . e($editing['title']) : e(t('➕ เพิ่มหมวดแนะนำใหม่', '➕ Add New Featured Section')) ?></h2>
    <p class="intro">
      <?= $editing ? e(t('ปรับปรุงประเภทอสังหาฯ ชื่อหัวข้อ หรือลำดับการแสดงผล', 'Update property category, display title or sort order.')) : e(t('คุณสามารถสร้างได้หลายหมวด เช่น "คอนโดแนะนำ", "บ้านเดี่ยวแนะนำ", "คอนโดหรูสุขุมวิท" หรือ "ที่ดินทำเลทอง" ระบบจะนำไปจัดแสดงบนหน้าแรกตามลำดับที่ตั้งไว้', 'Create custom highlighted categories such as "Featured Condos", "Sukhumvit Luxury", or "Prime Land".')) ?>
    </p>

    <?php if ($canManageMarketing): ?>
      <form method="post" class="feat-form-grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

        <label class="field"><?= e(t('ประเภทอสังหาฯ', 'Property Category')) ?>
          <select name="category" required>
            <?php foreach (PROPERTY_CATEGORIES as $key => $label): if ($key === 'other') continue; ?>
              <option value="<?= e($key) ?>" <?= ($editing['category'] ?? '') === $key ? 'selected' : '' ?>>
                <?= e(($categoryIcons[$key] ?? '🏡') . ' ' . category_label_bi($key)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="field"><?= e(t('ชื่อหัวข้อที่ต้องการแสดงบนหน้าแรก', 'Display Title on Homepage')) ?>
          <input type="text" name="title" maxlength="120" value="<?= e($editing['title'] ?? '') ?>" placeholder="<?= e(t('เช่น คอนโดแนะนำ หรือ บ้านเดี่ยวพร้อมอยู่', 'e.g. Featured Condos or Move-in Ready Houses')) ?>" required>
        </label>

        <label class="field"><?= e(t('ลำดับการแสดง', 'Sort Order')) ?>
          <input type="number" name="sort_order" min="0" value="<?= (int)($editing['sort_order'] ?? count($sections)) ?>">
        </label>

        <label class="check-label">
          <input type="checkbox" name="enabled" value="1" <?= (!isset($editing['enabled']) || !empty($editing['enabled'])) ? 'checked' : '' ?>>
          <?= e(t('เปิดแสดงบนหน้าแรก', 'Visible on Homepage')) ?>
        </label>

        <div style="grid-column:1/-1; display:flex; gap:10px; margin-top:8px;">
          <button type="submit" class="btn-submit-feat"><?= $editing ? e(t('💾 บันทึกการแก้ไข', '💾 Save Changes')) : e(t('➕ เพิ่มหมวดนี้ลงหน้าแรก', '➕ Add Section to Homepage')) ?></button>
          <?php if ($editing): ?>
            <a href="featured.php" class="mini gray" style="padding:12px 20px;border-radius:10px;">✕ <?= e(t('ยกเลิกการแก้ไข', 'Cancel')) ?></a>
          <?php endif; ?>
        </div>
      </form>

      <?php if (!$editing): ?>
        <!-- ส่วนเพิ่มหลายหมวดพร้อมกัน (Multi-Add Bulk Mode) -->
        <div style="margin-top:28px; padding-top:24px; border-top:1px dashed #cbd5e1;">
          <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
              <strong style="font-size:15px; color:#0B1C33;">⚡ เพิ่มทีละหลายหมวดพร้อมกัน (Multi-Add Mode)</strong>
              <p style="margin:2px 0 0; font-size:12.5px; color:#64748B;">เพิ่มหมวดแนะนำ 3, 5 หรือ 10 หมวดได้พร้อมกันในคลิกเดียว</p>
            </div>
            <button type="button" id="btnPrefill5" style="border:1px solid #C5A059; background:rgba(197,160,89,.12); color:#8C6B32; font-weight:700; font-size:12.5px; padding:7px 14px; border-radius:8px; cursor:pointer;">⚡ เติม 5 หมวดหลักอัตโนมัติ</button>
          </div>

          <form method="post" id="bulkForm">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="save_bulk">
            
            <div id="bulkRowsContainer" style="display:grid; gap:10px;">
              <!-- แถวที่ 1 -->
              <div class="bulk-row">
                <select name="bulk_category[]" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                  <?php foreach (PROPERTY_CATEGORIES as $key => $label): if ($key === 'other') continue; ?>
                    <option value="<?= e($key) ?>"><?= e(($categoryIcons[$key] ?? '🏡') . ' ' . $label) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="bulk_title[]" placeholder="เช่น คอนโดแนะนำ" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                <input type="number" name="bulk_sort[]" value="1" placeholder="ลำดับ" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                <button type="button" onclick="this.closest('.bulk-row').remove()" style="min-height:42px; border:0; background:#FFF1F1; color:#c00; font-weight:bold; border-radius:8px; cursor:pointer;" title="ลบแถวนี้">✕</button>
              </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:14px; align-items:center;">
              <button type="button" id="btnAddRow" style="border:1px solid #cbd5e1; background:#fff; color:#475569; font-weight:700; font-size:13px; padding:8px 16px; border-radius:8px; cursor:pointer;">➕ เพิ่มอีก 1 แถว</button>
              <button type="submit" class="btn-submit-feat" style="padding:0 22px; min-height:42px;">💾 บันทึกทุกหมวดพร้อมกัน</button>
            </div>
          </form>

          <script>
            (function() {
              const container = document.getElementById('bulkRowsContainer');
              const btnAdd = document.getElementById('btnAddRow');
              const btnPrefill = document.getElementById('btnPrefill5');

              function createRow(cat, title, sort) {
                const div = document.createElement('div');
                div.className = 'bulk-row';
                div.innerHTML = `
                  <select name="bulk_category[]" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                    <option value="condo" ${cat==='condo'?'selected':''}>🏢 คอนโด</option>
                    <option value="house" ${cat==='house'?'selected':''}>🏠 บ้านเดี่ยว</option>
                    <option value="townhouse" ${cat==='townhouse'?'selected':''}>🏘️ ทาวน์เฮ้าส์ / ทาวน์โฮม</option>
                    <option value="land" ${cat==='land'?'selected':''}>🌳 ที่ดิน</option>
                    <option value="commercial" ${cat==='commercial'?'selected':''}>🏬 อาคารพาณิชย์</option>
                    <option value="warehouse" ${cat==='warehouse'?'selected':''}>🏭 โกดัง / โรงงาน</option>
                  </select>
                  <input type="text" name="bulk_title[]" value="${title||''}" placeholder="เช่น คอนโดแนะนำ" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                  <input type="number" name="bulk_sort[]" value="${sort||0}" placeholder="ลำดับ" style="min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
                  <button type="button" onclick="this.closest('.bulk-row').remove()" style="min-height:42px; border:0; background:#FFF1F1; color:#c00; font-weight:bold; border-radius:8px; cursor:pointer;" title="ลบแถวนี้">✕</button>
                `;
                return div;
              }

              if (btnAdd) {
                btnAdd.addEventListener('click', function() {
                  const currentCount = container.querySelectorAll('.bulk-row').length;
                  container.appendChild(createRow('house', '', currentCount + 1));
                });
              }

              if (btnPrefill) {
                btnPrefill.addEventListener('click', function() {
                  container.innerHTML = '';
                  const presets = [
                    { cat: 'condo', title: 'คอนโดมิเนียมยอดนิยม', sort: 1 },
                    { cat: 'house', title: 'บ้านเดี่ยวพร้อมอยู่ ทำเลดี', sort: 2 },
                    { cat: 'townhouse', title: 'ทาวน์โฮมราคาคุ้มค่า', sort: 3 },
                    { cat: 'land', title: 'ที่ดินแปลงสวย เหมาะลงทุน', sort: 4 },
                    { cat: 'commercial', title: 'อาคารพาณิชย์ทำเลค้าขาย', sort: 5 }
                  ];
                  presets.forEach(p => container.appendChild(createRow(p.cat, p.title, p.sort)));
                });
              }
            })();
          </script>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div style="font-size:13px;color:#64748B;font-style:italic;padding:14px;background:#F9F9FB;border-radius:12px;">
        🔒 ดูข้อมูลได้อย่างเดียว (ต้องได้รับสิทธิ์จาก Founder &amp; CEO เพื่อเพิ่มหรือแก้ไขหมวดแนะนำ)
      </div>
    <?php endif; ?>
  </section>

  <!-- ตารางหมวดแนะนำทั้งหมดที่ตั้งไว้ -->
  <section class="feat-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
      <h2>📋 หมวดหมู่แนะนำทั้งหมดในระบบ (<?= count($sections) ?> หมวด)</h2>
      <span style="font-size:12.5px;color:#64748B;">แสดงผลบนหน้าแรกตามลำดับ</span>
    </div>
    <p class="intro">จัดลำดับ เลื่อนขึ้น/ลง หรือเปิด-ปิดการแสดงผลได้ทันที ทุกหมวดที่เปิดจะดึงประกาศที่เกี่ยวข้องมาแสดงบนหน้าแรก</p>

    <?php if (!$sections): ?>
      <p class="intro" style="font-style:italic;padding:24px;text-align:center;background:#F9F9FB;border-radius:14px;">
        ยังไม่มีหมวดแนะนำที่ตั้งไว้ — เริ่มเพิ่มหมวดแรกของคุณจากแบบฟอร์มด้านบนได้เลยครับ
      </p>
    <?php else: ?>
      <div class="table-wrap" style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
        <table class="feat-table">
          <thead>
            <tr>
              <th style="width:70px;">ลำดับ</th>
              <th>ประเภท</th>
              <th>ชื่อหัวข้อที่แสดงหน้าแรก</th>
              <th>ประกาศพร้อมขาย/เช่า</th>
              <th>สถานะ</th>
              <th style="text-align:right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sections as $sec): ?>
              <tr>
                <td><strong>#<?= (int)$sec['sort_order'] ?></strong></td>
                <td>
                  <span class="pill cat">
                    <?= e(($categoryIcons[$sec['category']] ?? '🏡') . ' ' . (PROPERTY_CATEGORIES[$sec['category']] ?? $sec['category'])) ?>
                  </span>
                </td>
                <td>
                  <strong style="font-size:14.5px;color:#0B1C33;"><?= e($sec['title']) ?></strong>
                </td>
                <td>
                  <span style="font-weight:700;color:#0B1C33;"><?= number_format((int)$sec['active_count']) ?></span> รายการ
                </td>
                <td>
                  <span class="pill <?= !empty($sec['enabled']) ? 'on' : 'off' ?>">
                    <?= !empty($sec['enabled']) ? '● กำลังแสดง' : '○ ปิดการแสดง' ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <?php if ($canManageMarketing): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$sec['id'] ?>">
                      <button type="submit" class="mini gray" title="เปิด/ปิด"><?= !empty($sec['enabled']) ? '⏸ ซ่อน' : '▶ แสดง' ?></button>
                    </form>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="sort">
                      <input type="hidden" name="id" value="<?= (int)$sec['id'] ?>">
                      <input type="hidden" name="dir" value="up">
                      <button type="submit" class="mini gray" title="เลื่อนขึ้น">▲</button>
                    </form>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="sort">
                      <input type="hidden" name="id" value="<?= (int)$sec['id'] ?>">
                      <input type="hidden" name="dir" value="down">
                      <button type="submit" class="mini gray" title="เลื่อนลง">▼</button>
                    </form>
                    <a href="featured.php?edit=<?= (int)$sec['id'] ?>" class="mini edit">✏️ แก้ไข</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันลบหมวดแนะนำนี้?');">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$sec['id'] ?>">
                      <button type="submit" class="mini del">🗑 ลบ</button>
                    </form>
                  <?php else: ?>
                    <span style="color:#94a3b8;font-size:12px;">(ดูอย่างเดียว)</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
