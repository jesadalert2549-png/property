<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin();

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
if (!$isFounder) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>สิทธิ์ถูกจำกัด</title><link rel="stylesheet" href="admin.css"></head><body style="padding:40px;text-align:center;font-family:sans-serif;"><h2>🔒 หน้านี้สงวนสิทธิ์เฉพาะ Founder & CEO (เจ้าของเว็บไซต์) เท่านั้น</h2><p><a href="index.php" class="btn">← กลับแดชบอร์ด</a></p></body></html>';
    exit;
}

$pdo = db();

// ตรวจสอบโครงสร้างตาราง
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ceo_changelog (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entry_type ENUM('feature_add', 'feature_remove', 'bug_fix', 'optimization', 'security', 'note') NOT NULL DEFAULT 'feature_add',
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        module VARCHAR(100) NOT NULL DEFAULT 'core',
        version VARCHAR(30) NOT NULL DEFAULT 'v.3.16.0',
        created_by VARCHAR(120) NOT NULL DEFAULT 'Founder & CEO',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_type (entry_type),
        KEY idx_created (created_at),
        KEY idx_version (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

$message = '';
$errors = [];

if (request_is_post()) {
    csrf_check();
    $action = trim((string)($_POST['action'] ?? ''));

    // 1. เพิ่มบันทึกฟีเจอร์หรือการเปลี่ยนแปลงใหม่
    if ($action === 'add_entry') {
        $type = in_array($_POST['entry_type'] ?? '', ['feature_add', 'feature_remove', 'bug_fix', 'optimization', 'security', 'note'], true) ? $_POST['entry_type'] : 'feature_add';
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $module = trim((string)($_POST['module'] ?? 'Core System')) ?: 'Core System';
        $version = trim((string)($_POST['version'] ?? SITE_VERSION)) ?: SITE_VERSION;

        if (mb_strlen($title) < 2) {
            $errors[] = t('กรุณาระบุหัวข้อการบันทึก', 'Please specify entry title');
        } elseif (mb_strlen($description) < 5) {
            $errors[] = t('กรุณากรอกรายละเอียดให้ครบถ้วน', 'Please enter full description');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO ceo_changelog (entry_type, title, description, module, version, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 'Founder & CEO', NOW())"
            );
            $stmt->execute([$type, $title, $description, $module, $version]);
            $message = t('บันทึกการเปลี่ยนแปลงของระบบเรียบร้อยแล้ว!', 'System changelog entry recorded successfully!');
        }
    }

    // 2. ลบบันทึก
    if ($action === 'delete_entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM ceo_changelog WHERE id = ?")->execute([$id]);
            $message = t('ลบรายการบันทึกเรียบร้อยแล้ว', 'Log entry deleted successfully.');
        }
    }
}

// ฟิลเตอร์ตัวกรอง
$filterType = trim((string)($_GET['type'] ?? ''));
$where = ['1=1'];
$params = [];

if ($filterType !== '' && in_array($filterType, ['feature_add', 'feature_remove', 'bug_fix', 'optimization', 'security', 'note'], true)) {
    $where[] = "entry_type = ?";
    $params[] = $filterType;
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM ceo_changelog WHERE $whereSql ORDER BY id DESC");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สถิติตัวเลข
$statAdds = (int)$pdo->query("SELECT COUNT(*) FROM ceo_changelog WHERE entry_type = 'feature_add'")->fetchColumn();
$statRemoves = (int)$pdo->query("SELECT COUNT(*) FROM ceo_changelog WHERE entry_type = 'feature_remove'")->fetchColumn();
$statFixes = (int)$pdo->query("SELECT COUNT(*) FROM ceo_changelog WHERE entry_type = 'bug_fix'")->fetchColumn();
$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM ceo_changelog")->fetchColumn();

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('สมุดบันทึกนวัตกรรม & ฟีเจอร์ระบบ (CEO System Notebook)', 'CEO System & Feature Logbook')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .ceo-log-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
      padding: 32px 36px;
      border-radius: 24px;
      background: linear-gradient(135deg, #0B1C33 0%, #172D4D 100%);
      color: #fff;
      border: 1px solid rgba(197, 160, 89, 0.4);
      margin-bottom: 28px;
      box-shadow: 0 16px 36px rgba(11, 28, 51, 0.15);
    }
    .ceo-log-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.2em; text-transform:uppercase; }
    .ceo-log-hero h1 { margin:8px 0 6px; color:#fff; font-size:clamp(22px,3vw,30px); font-weight:800; }

    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .stat-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 4px 14px rgba(11,28,51,.04); }
    .stat-box small { display: block; font-size: 11.5px; color: #64748B; font-weight: 700; text-transform: uppercase; }
    .stat-box strong { font-size: 26px; color: #0B1C33; font-weight: 800; margin-top: 2px; display: block; }

    .dash-card { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:24px 28px; margin-bottom:24px; box-shadow:0 8px 24px rgba(11,28,51,.04); }

    /* Timeline Styling */
    .timeline-container { position: relative; padding-left: 28px; margin-top: 20px; }
    .timeline-container::before {
      content: '';
      position: absolute;
      left: 10px;
      top: 10px;
      bottom: 10px;
      width: 2px;
      background: #e2e8f0;
    }
    .timeline-item {
      position: relative;
      margin-bottom: 24px;
      background: #fafbfc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 18px 22px;
      transition: all .18s ease;
    }
    .timeline-item:hover {
      background: #fff;
      border-color: #C5A059;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(11,28,51,.06);
    }
    .timeline-dot {
      position: absolute;
      left: -24px;
      top: 22px;
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: #0B1C33;
      border: 3px solid #C5A059;
      box-shadow: 0 0 0 3px rgba(255,255,255,1);
    }

    .badge-add    { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; }
    .badge-remove { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .badge-fix    { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
    .badge-opt    { background:#fdf4ff; color:#c026d3; border:1px solid #f5d0fe; }
    .badge-sec    { background:#fffbeb; color:#d97706; border:1px solid #fde68a; }
    .badge-note   { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; }

    .tag-pill { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px; text-transform:uppercase; }

    .btn-gold {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 0 22px; border: 0; border-radius: 10px;
      background: linear-gradient(135deg,#C5A059,#A8843F); color: #0B1C33; font: inherit; font-size: 13.5px; font-weight: 800; cursor: pointer; text-decoration: none;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">

  <!-- Hero Header -->
  <div class="ceo-log-hero">
    <div>
      <span class="eyebrow"><?= e(t('FOUNDER & CEO · EXCLUSIVE SYSTEM NOTEBOOK', 'FOUNDER & CEO · EXCLUSIVE SYSTEM NOTEBOOK')) ?></span>
      <h1>📓 <?= e(t('สมุดบันทึกนวัตกรรม & ประวัติฟีเจอร์ของระบบ', 'CEO System Innovation & Changelog Notebook')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">
        <?= e(t('พื้นที่บันทึกเอกสิทธิ์เฉพาะ Founder & CEO สำหรับจดประวัติการเพิ่มฟีเจอร์, ลด/ตัดฟีเจอร์, แก้ไขบั๊ก และการพัฒนาแพลตฟอร์ม เรียงตามไทม์ไลน์อัตโนมัติ', 'Exclusive Founder-only logbook to document feature additions, removals, bug fixes and platform milestones chronologically.')) ?>
      </p>
    </div>
    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
      <span style="font-size:12px; color:#E5CB96; background:rgba(255,255,255,.08); padding:5px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.14);">
        👑 <?= e(t('เอกสิทธิ์เฉพาะเจ้าของเว็บไซต์ (CEO Only)', 'Exclusive CEO Authority')) ?>
      </span>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success" style="margin-bottom:20px;">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error" style="margin-bottom:20px;">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- สถิติการพัฒนา -->
  <div class="stat-row">
    <div class="stat-box">
      <small><?= e(t('บันทึกทั้งหมด', 'Total Logged')) ?></small>
      <strong><?= number_format($statTotal) ?> <span style="font-size:13px; font-weight:normal;"><?= e(t('รายการ', 'entries')) ?></span></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('เพิ่มฟีเจอร์ใหม่', 'Features Added')) ?></small>
      <strong style="color:#059669;">+<?= number_format($statAdds) ?></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('ตัด/ลดฟีเจอร์', 'Features Removed')) ?></small>
      <strong style="color:#dc2626;">-<?= number_format($statRemoves) ?></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('แก้ไขบั๊ก & ปรับปรุง', 'Bug Fixes & Refactors')) ?></small>
      <strong style="color:#2563eb;"><?= number_format($statFixes) ?></strong>
    </div>
  </div>

  <!-- ฟอร์มเพิ่มบันทึกใหม่ -->
  <section class="dash-card">
    <h2 style="margin:0 0 6px; font-size:18px; color:#0B1C33;">✍️ <?= e(t('จดบันทึกการเปลี่ยนแปลงใหม่ (New Log Entry)', 'Record New System Milestone')) ?></h2>
    <p style="margin:0 0 18px; font-size:13px; color:#64748B;">
      <?= e(t('เลือกประเภทการเปลี่ยนแปลงและระบุรายละเอียดเพื่อเก็บเป็นประวัติการพัฒนาของเว็บไซต์คุณ', 'Select the change type and enter technical or operational notes to preserve your website development history.')) ?>
    </p>

    <form method="post" style="display:flex; flex-direction:column; gap:16px;">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="add_entry">

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ประเภทการเปลี่ยนแปลง *', 'Change Type *')) ?></label>
          <select name="entry_type" required style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
            <option value="feature_add">✨ <?= e(t('เพิ่มฟีเจอร์ใหม่ (+ Feature Add)', '✨ Add New Feature (+ Add)')) ?></option>
            <option value="feature_remove">🗑️ <?= e(t('ตัด / ลดฟีเจอร์ออก (- Feature Remove)', '🗑️ Remove Feature (- Remove)')) ?></option>
            <option value="bug_fix">🛠️ <?= e(t('แก้ไขบั๊ก / ปรับปรุงโค้ด (Bug Fix)', '🛠️ Bug Fix / Refactor')) ?></option>
            <option value="optimization">⚡ <?= e(t('เพิ่มความเร็ว / ประสิทธิภาพ (Optimization)', '⚡ Speed Optimization')) ?></option>
            <option value="security">🔒 <?= e(t('ความปลอดภัย & สิทธิ์ (Security & Access)', '🔒 Security & Access Control')) ?></option>
            <option value="note">📝 <?= e(t('บันทึกทั่วไป / แผนงาน (General Note / Roadmap)', '📝 General Note / Roadmap')) ?></option>
          </select>
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ส่วนงาน / โมดูลระบบ', 'System Module')) ?></label>
          <input type="text" name="module" placeholder="<?= e(t('เช่น SEO Engine, Listings, Auth, Member Quota', 'e.g. SEO Engine, Listings, Auth')) ?>" value="Core System" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('เวอร์ชันระบบ (Version)', 'Version Stamp')) ?></label>
          <input type="text" name="version" value="<?= e(SITE_VERSION) ?>" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
        </div>
      </div>

      <div>
        <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('หัวข้อการเปลี่ยนแปลง *', 'Title / Summary *')) ?></label>
        <input type="text" name="title" placeholder="<?= e(t('เช่น ระบบราคาขีดฆ่าพร้อมป้ายส่วนลด และแจ้งเตือนลดราคาอัตโนมัติ', 'e.g. Price Strikethrough & Auto Price Drop Alerts')) ?>" required style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
      </div>

      <div>
        <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('รายละเอียดทางเทคนิค / สิ่งที่ทำ *', 'Detailed Description / Notes *')) ?></label>
        <textarea name="description" rows="3" placeholder="<?= e(t('บันทึกรายละเอียดของฟีเจอร์นี้ เช่น ไฟล์ที่แก้ไข, การทำงาน, เหตุผลที่เพิ่มหรือลดฟีเจอร์ออก...', 'Document technical details, affected files, business rationale...')) ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px; box-sizing:border-box;"></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end;">
        <button type="submit" class="btn-gold">💾 <?= e(t('บันทึกลงสมุดประจำระบบ', 'Save to CEO Notebook')) ?></button>
      </div>
    </form>
  </section>

  <!-- ไทม์ไลน์ประวัติระบบ -->
  <section class="dash-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#0B1C33;">📜 <?= e(t('ไทม์ไลน์ประวัติการพัฒนาระบบ (System Timeline)', 'System Evolution Timeline')) ?></h2>
        <span style="font-size:12.5px; color:#64748B;"><?= e(t('เรียงลำดับจากล่าสุดลงไป บันทึกไว้เป็นทรัพย์สินทางปัญญาของแพลตฟอร์มคุณ', 'Chronologically ordered from newest to oldest.')) ?></span>
      </div>

      <div style="display:flex; gap:8px;">
        <a href="notebook.php" class="mini" style="<?= $filterType===''?'background:#0B1C33; color:#fff;':'background:#f1f5f9; color:#475569;' ?>"><?= e(t('ทั้งหมด', 'All')) ?></a>
        <a href="notebook.php?type=feature_add" class="mini" style="<?= $filterType==='feature_add'?'background:#059669; color:#fff;':'background:#f1f5f9; color:#475569;' ?>">+ <?= e(t('เพิ่มฟีเจอร์', 'Add')) ?></a>
        <a href="notebook.php?type=feature_remove" class="mini" style="<?= $filterType==='feature_remove'?'background:#dc2626; color:#fff;':'background:#f1f5f9; color:#475569;' ?>">- <?= e(t('ลดฟีเจอร์', 'Remove')) ?></a>
        <a href="notebook.php?type=bug_fix" class="mini" style="<?= $filterType==='bug_fix'?'background:#2563eb; color:#fff;':'background:#f1f5f9; color:#475569;' ?>"><?= e(t('แก้บั๊ก', 'Fixes')) ?></a>
      </div>
    </div>

    <?php if (!$logs): ?>
      <p style="text-align:center; padding:32px; background:#f8fafc; border-radius:12px; color:#64748B;">
        <?= e(t('ยังไม่มีบันทึกในหมวดหมู่นี้', 'No entries in this category yet.')) ?>
      </p>
    <?php else: ?>
      <div class="timeline-container">
        <?php foreach ($logs as $log): ?>
          <?php
            $typeClass = match($log['entry_type']) {
                'feature_add'    => 'badge-add',
                'feature_remove' => 'badge-remove',
                'bug_fix'        => 'badge-fix',
                'optimization'   => 'badge-opt',
                'security'       => 'badge-sec',
                default          => 'badge-note'
            };
            $typeLabel = match($log['entry_type']) {
                'feature_add'    => t('✨ เพิ่มฟีเจอร์ใหม่', '✨ Feature Added'),
                'feature_remove' => t('🗑️ ตัด / ลดฟีเจอร์', '🗑️ Feature Removed'),
                'bug_fix'        => t('🛠️ แก้ไขบั๊ก', '🛠️ Bug Fix'),
                'optimization'   => t('⚡ เพิ่มประสิทธิภาพ', '⚡ Optimization'),
                'security'       => t('🔒 ความปลอดภัย', '🔒 Security'),
                default          => t('📝 บันทึก', '📝 Note')
            };
          ?>
          <article class="timeline-item">
            <span class="timeline-dot"></span>

            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span class="tag-pill <?= $typeClass ?>"><?= $typeLabel ?></span>
                <span style="font-size:11px; font-weight:700; color:#64748B; background:#e2e8f0; padding:2px 8px; border-radius:4px;">
                  📁 <?= e($log['module']) ?>
                </span>
                <span style="font-size:11px; font-weight:800; color:#A8843F; font-family:monospace;">
                  <?= e($log['version']) ?>
                </span>
              </div>

              <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:12px; color:#94a3b8;">
                  🕒 <?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
                </span>
                <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('<?= e(t('ต้องการลบรายการบันทึกนี้ใช่หรือไม่?', 'Delete this changelog entry?')) ?>');">
                  <input type="hidden" name="csrf" value="<?= e($token) ?>">
                  <input type="hidden" name="action" value="delete_entry">
                  <input type="hidden" name="entry_id" value="<?= (int)$log['id'] ?>">
                  <button type="submit" class="mini del" style="padding:2px 8px; font-size:11px;" title="<?= e(t('ลบ', 'Delete')) ?>">✕</button>
                </form>
              </div>
            </div>

            <h3 style="margin:0 0 6px; font-size:15.5px; color:#0B1C33; font-weight:800; line-height:1.4;">
              <?= e($log['title']) ?>
            </h3>

            <p style="margin:0; font-size:13px; color:#475569; line-height:1.65; white-space:pre-line;">
              <?= e($log['description']) ?>
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>
</body>
</html>
