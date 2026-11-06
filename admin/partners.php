<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin(); // ตรวจสอบว่าเป็นแอดมินหรือพาร์ทเนอร์

// เฉพาะ Founder & CEO เท่านั้นที่มีสิทธิ์เข้าถึงหน้านี้
if (($_SESSION['role'] ?? '') !== 'admin') {
    redirect('index.php');
}

$pdo = db();
ensure_staff_schema($pdo);

$message = '';
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;

// ตำแหน่งผู้บริหารและหุ้นส่วนเริ่มต้น
$partnerRoles = [
    'partner'    => ['th' => 'หุ้นส่วนธุรกิจ (Business Partner)', 'en' => 'Business Partner', 'icon' => '🤝', 'desc' => 'มีส่วนร่วมในการบริหารและดูแลระบบตามที่ได้รับมอบหมาย'],
    'co_founder' => ['th' => 'ผู้ร่วมก่อตั้ง (Co-Founder)', 'en' => 'Co-Founder', 'icon' => '👑', 'desc' => 'ผู้ร่วมก่อตั้งแพลตฟอร์ม มีสิทธิ์กว้างขวาง'],
    'gm'         => ['th' => 'ผู้จัดการทั่วไป (General Manager)', 'en' => 'General Manager', 'icon' => '🏢', 'desc' => 'ดูแลงานบริหารภาพรวมและทีมงาน'],
    'ops'        => ['th' => 'ผู้จัดการฝ่ายปฏิบัติการ (Operations)', 'en' => 'Operations Manager', 'icon' => '⚙️', 'desc' => 'ดูแลการดำเนินงาน ตรวจสอบประกาศและโครงการ'],
    'marketing'  => ['th' => 'ผู้จัดการฝ่ายการตลาด (Marketing Director)', 'en' => 'Marketing Director', 'icon' => '📣', 'desc' => 'ดูแลสื่อ โฆษณา แบนเนอร์ และหมวดหมู่แนะนำ'],
    'sales'      => ['th' => 'ผู้จัดการฝ่ายขาย (Sales Director)', 'en' => 'Sales Director', 'icon' => '💼', 'desc' => 'ดูแลทีมตัวแทนอสังหาฯ และลูกค้า'],
];

if (request_is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    // ── 1. สร้างบัญชีหุ้นส่วน / ผู้จัดการใหม่ ──
    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleSlug = (string)($_POST['role_slug'] ?? 'partner');
        $perms = $_POST['perm'] ?? [];

        if (!staff_validate_username($username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษหรือตัวเลข 4-50 ตัวอักษร';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
        if (mb_strlen($fullName) < 2) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';

        if (!$errors) {
            $dup = $pdo->prepare('SELECT 1 FROM staff_accounts WHERE username = ? OR email = ? LIMIT 1');
            $dup->execute([$username, $email]);
            if ($dup->fetchColumn()) {
                $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
            } else {
                try {
                    $newId = staff_create($pdo, [
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $fullName,
                        'password' => $password,
                        'role_slug' => $roleSlug,
                        'status' => 'active',
                    ], is_array($perms) ? $perms : [], (int)$_SESSION['user_id']);

                    staff_log($pdo, $newId, (string)($_SESSION['username'] ?? 'founder'), 'partner_created', $username . ' / ' . $roleSlug);
                    $message = 'สร้างบัญชี "' . $fullName . '" เรียบร้อยแล้ว สมาชิกสามารถล็อกอินเข้าทำงานได้ทันที';
                } catch (Throwable $e) {
                    $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                }
            }
        }
    }

    // ── 2. แก้ไขสิทธิ์และข้อมูล ──
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $errors[] = 'ไม่พบบัญชีนี้ในระบบ';
        } else {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $roleSlug = (string)($_POST['role_slug'] ?? 'partner');
            $status = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
            $password = (string)($_POST['password'] ?? '');
            $perms = $_POST['perm'] ?? [];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
            if (mb_strlen($fullName) < 2) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
            if ($password !== '' && strlen($password) < 8) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';

            if (!$errors) {
                $dup = $pdo->prepare('SELECT 1 FROM staff_accounts WHERE email = ? AND id <> ? LIMIT 1');
                $dup->execute([$email, $id]);
                if ($dup->fetchColumn()) {
                    $errors[] = 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว';
                } else {
                    if ($password !== '') {
                        $pdo->prepare('UPDATE staff_accounts SET email=?, full_name=?, role_slug=?, status=?, password_hash=? WHERE id=?')
                            ->execute([$email, $fullName, $roleSlug, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
                    } else {
                        $pdo->prepare('UPDATE staff_accounts SET email=?, full_name=?, role_slug=?, status=? WHERE id=?')
                            ->execute([$email, $fullName, $roleSlug, $status, $id]);
                    }
                    staff_set_permissions($pdo, $id, is_array($perms) ? $perms : []);
                    staff_log($pdo, $id, (string)($_SESSION['username'] ?? 'founder'), 'partner_updated', $target['username']);
                    $message = 'บันทึกการแก้ไขข้อมูลและสิทธิ์ของ "' . $target['username'] . '" เรียบร้อยแล้ว';
                    $editId = 0;
                }
            }
        }
    }

    // ── 3. พักการใช้งาน / เปิดใช้งาน ──
    elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE staff_accounts SET status = IF(status='active','suspended','active') WHERE id = ?")->execute([$id]);
        $message = 'เปลี่ยนสถานะบัญชีเรียบร้อยแล้ว';
    }

    // ── 4. ลบบัญชี ──
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT username FROM staff_accounts WHERE id = ?');
        $stmt->execute([$id]);
        $uname = (string)$stmt->fetchColumn();
        if ($uname !== '') {
            $pdo->prepare('DELETE FROM staff_permissions WHERE staff_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM staff_accounts WHERE id = ?')->execute([$id]);
            staff_log($pdo, null, (string)($_SESSION['username'] ?? 'founder'), 'partner_deleted', $uname);
            $message = 'ลบบัญชี "' . $uname . '" ออกจากระบบแล้ว';
        }
    }
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$editingPerms = $editing ? staff_permissions($pdo, (int)$editing['id']) : [];

$teamList = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM staff_permissions p WHERE p.staff_id = s.id) AS perm_count
     FROM staff_accounts s ORDER BY s.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$activeCount = count(array_filter($teamList, static fn($s) => $s['status'] === 'active'));
$suspendedCount = count($teamList) - $activeCount;
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการหุ้นส่วนและผู้จัดการ (Executive Management) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .partner-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:28px 32px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.35); margin-bottom:24px; box-shadow:0 12px 32px rgba(11,28,51,.12); }
    .partner-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .partner-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }
    .partner-badge-top { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:999px; background:rgba(197,160,89,.18); border:1px solid rgba(197,160,89,.45); color:#E5CB96; font-size:13px; font-weight:800; }

    .partner-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:28px 30px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .partner-card h2 { margin:0 0 6px; color:#0B1C33; font-size:19px; }
    .partner-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; line-height:1.6; }

    .grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .field { display:grid; gap:6px; font-size:13px; font-weight:700; color:#34495d; }
    .field.wide { grid-column:1/-1; }
    .field input, .field select { width:100%; min-height:46px; padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-size:14px; background:#fff; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); }
    .field small { font-weight:500; color:#8090a0; font-size:11.5px; }

    .perm-grid { display:grid; gap:8px; margin-top:10px; }
    .perm-row { display:grid; grid-template-columns:minmax(160px,1.2fr) repeat(5,minmax(64px,1fr)); gap:6px; align-items:center; padding:9px 12px; border:1px solid #eef2f6; border-radius:12px; background:#F9F9FB; }
    .perm-row.head { background:#0B1C33; color:#E5CB96; font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .perm-row label { display:flex; align-items:center; justify-content:center; gap:5px; font-size:12px; font-weight:700; color:#475569; cursor:pointer; }
    .perm-row input[type=checkbox] { width:18px; height:18px; accent-color:#C5A059; cursor:pointer; }
    .perm-row .mod { font-size:13.5px; font-weight:800; color:#0B1C33; }

    .btn-main { border:0; border-radius:12px; min-height:48px; padding:0 26px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-size:15px; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); transition:transform .18s, box-shadow .18s; }
    .btn-main:hover { transform:translateY(-1px); box-shadow:0 8px 22px rgba(197,160,89,.45); }

    .partner-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .partner-table th, .partner-table td { padding:13px 12px; border-bottom:1px solid #eef2f6; text-align:left; vertical-align:middle; }
    .partner-table th { background:#F8FAFC; color:#475569; font-size:12.5px; font-weight:700; }

    .pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:800; }
    .pill.active { background:#E8FDF0; color:#04A747; border:1px solid #06C755; }
    .pill.suspended { background:#FFF1F1; color:#ae3333; border:1px solid #EF4444; }
    .pill.role { background:rgba(197,160,89,.14); color:#8C6B32; border:1px solid rgba(197,160,89,.45); }

    .mini { border:0; border-radius:8px; padding:6px 12px; font:inherit; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
    .mini.edit { background:#EBF3FE; color:#1A73E8; }
    .mini.warn { background:#FFF8E6; color:#8C6B32; }
    .mini.del { background:#FFF1F1; color:#ae3333; }

    .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:22px; }
    .stat-box { padding:18px 20px; border-radius:16px; background:#fff; border:1px solid #e3e9ef; box-shadow:0 4px 14px rgba(11,28,51,.04); }
    .stat-box small { display:block; color:#64748b; font-size:12px; font-weight:600; }
    .stat-box strong { display:block; font-size:26px; color:#0B1C33; font-weight:800; margin-top:2px; }

    .alert-ok { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#effaf3; border:1px solid #bbebcd; color:#187243; font-size:14px; font-weight:600; }
    .alert-err { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:14px; font-weight:600; }

    @media (max-width:900px) {
      .grid2 { grid-template-columns:1fr; }
      .perm-row { grid-template-columns:1fr repeat(5,44px); }
      .perm-row .mod { font-size:12px; }
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">
  <div class="partner-hero">
    <div>
      <span class="eyebrow">FOUNDER &amp; CEO · EXECUTIVE ACCESS CONTROL</span>
      <h1>👑 จัดการบัญชีหุ้นส่วน &amp; ผู้จัดการ</h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">กำหนดตำแหน่งงานและเลือกเปิดสิทธิ์การเข้าใช้งานระบบให้หุ้นส่วนหรือผู้จัดการแต่ละท่านได้อย่างอิสระ</p>
    </div>
    <span class="partner-badge-top">👑 บัญชีสูงสุด: Founder &amp; CEO</span>
  </div>

  <?php if ($message): ?><div class="alert-ok">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert-err">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <div class="stat-row">
    <div class="stat-box"><small>หุ้นส่วน/ผู้จัดการทั้งหมด</small><strong><?= count($teamList) ?></strong></div>
    <div class="stat-box"><small>เปิดใช้งานอยู่</small><strong style="color:#04A747;"><?= $activeCount ?></strong></div>
    <div class="stat-box"><small>ระงับชั่วคราว</small><strong style="color:#ae3333;"><?= $suspendedCount ?></strong></div>
  </div>

  <!-- ฟอร์มเพิ่ม / แก้ไขบัญชีหุ้นส่วนและผู้จัดการ -->
  <section class="partner-card">
    <h2><?= $editing ? '✏️ แก้ไขข้อมูลและสิทธิ์: ' . e($editing['full_name'] ?: $editing['username']) : '➕ เพิ่มบัญชีหุ้นส่วน / ผู้จัดการใหม่' ?></h2>
    <p class="intro">
      <?= $editing
        ? 'ปรับปรุงตำแหน่ง สถานะการใช้งาน และติ๊กเลือกสิทธิ์ในแต่ละโมดูลที่ต้องการให้หุ้นส่วนหรือผู้จัดการท่านนี้เข้าถึงได้'
        : 'สร้างบัญชีเข้าใช้งานระบบบริหารจัดการ โดยคุณ (Founder & CEO) จะเป็นผู้กำหนดตำแหน่งและเลือกสิทธิ์ได้เองอย่างอิสระ' ?>
    </p>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

      <div class="grid2">
        <label class="field">ชื่อผู้ใช้สำหรับเข้าสู่ระบบ (Username)
          <input type="text" name="username" value="<?= e($editing['username'] ?? '') ?>" <?= $editing ? 'readonly style="background:#F1F5F9;"' : 'required' ?> placeholder="เช่น partner.kasa หรือ gm.somchai">
          <small>ใช้สำหรับเข้าสู่ระบบ</small>
        </label>
        <label class="field">อีเมล
          <input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>" required placeholder="partner@kasa.co">
        </label>
        <label class="field">ชื่อ-นามสกุล / ชื่อผู้บริหาร
          <input type="text" name="full_name" value="<?= e($editing['full_name'] ?? '') ?>" required maxlength="120" placeholder="เช่น คุณวิชัย พัฒนพาณิชย์">
        </label>
        <label class="field">ตำแหน่ง (Executive Role)
          <select name="role_slug" required>
            <?php foreach ($partnerRoles as $slug => $r): ?>
              <option value="<?= e($slug) ?>" <?= ($editing['role_slug'] ?? '') === $slug ? 'selected' : '' ?>>
                <?= e($r['icon'] . ' ' . $r['th']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small>แสดงเป็นป้ายตำแหน่งในแถบหัวและระบบบริหาร</small>
        </label>
        <label class="field"><?= $editing ? 'รหัสผ่านใหม่ (หากไม่ต้องการเปลี่ยนให้เว้นว่างไว้)' : 'รหัสผ่านเข้าสู่ระบบ' ?>
          <input type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="8" autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร">
        </label>
        <?php if ($editing): ?>
          <label class="field">สถานะบัญชี
            <select name="status">
              <option value="active" <?= $editing['status'] === 'active' ? 'selected' : '' ?>>● ใช้งานอยู่ (Active)</option>
              <option value="suspended" <?= $editing['status'] === 'suspended' ? 'selected' : '' ?>>○ ระงับการใช้งาน (Suspended)</option>
            </select>
          </label>
        <?php endif; ?>
      </div>

      <!-- สิทธิ์การเข้าถึงแยกรายโมดูล -->
      <h3 style="margin:24px 0 8px;font-size:15.5px;color:#0B1C33;">🔑 กำหนดสิทธิ์การเข้าใช้งาน (Module Permissions)</h3>
      <p style="margin:0 0 12px;font-size:12.5px;color:#64748b;">ติ๊กถูกในช่องที่ต้องการเปิดสิทธิ์ให้หุ้นส่วนหรือผู้จัดการท่านนี้เข้าถึงและจัดการได้</p>

      <div class="perm-grid">
        <div class="perm-row head">
          <span>โมดูลการทำงาน</span>
          <?php foreach (STAFF_PERMISSIONS as $perm => $meta): ?>
            <span style="text-align:center;"><?= e($meta['label']) ?><br><small style="font-size:9.5px;text-transform:none;opacity:.8;"><?= e($meta['th']) ?></small></span>
          <?php endforeach; ?>
        </div>
        <?php foreach (STAFF_MODULES as $module => $meta): ?>
          <div class="perm-row">
            <span class="mod"><?= e($meta['icon'] . ' ' . $meta['th']) ?></span>
            <?php foreach (STAFF_PERMISSIONS as $perm => $permMeta): ?>
              <label title="<?= e($permMeta['th']) ?>">
                <input type="checkbox" name="perm[<?= e($module) ?>][<?= e($perm) ?>]" value="1" <?= !empty($editingPerms[$module][$perm]) ? 'checked' : '' ?>>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:12px;margin-top:22px;flex-wrap:wrap;">
        <button type="submit" class="btn-main"><?= $editing ? '💾 บันทึกการแก้ไขสิทธิ์' : '➕ สร้างบัญชีและเปิดสิทธิ์' ?></button>
        <?php if ($editing): ?><a href="partners.php" class="mini" style="background:#F1F5F9;color:#475569;padding:14px 22px;border-radius:12px;">✕ ยกเลิก</a><?php endif; ?>
      </div>
    </form>
  </section>

  <!-- รายชื่อหุ้นส่วนและผู้จัดการทั้งหมด -->
  <section class="partner-card">
    <h2>👥 รายชื่อหุ้นส่วนและผู้จัดการในระบบ (<?= count($teamList) ?>)</h2>
    <p class="intro">บัญชีเหล่านี้สามารถล็อกอินผ่านหน้า <code>login.php</code> ปกติได้ทันที ระบบจะพาเข้าสู่ห้องบริหารจัดการตามสิทธิ์ที่ได้รับ</p>

    <?php if (!$teamList): ?>
      <p class="intro" style="font-style:italic;padding:24px;text-align:center;background:#F9F9FB;border-radius:14px;">ยังไม่มีบัญชีหุ้นส่วนหรือผู้จัดการ — สร้างบัญชีแรกได้จากฟอร์มด้านบน</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="partner-table">
          <thead>
            <tr>
              <th>ชื่อ-นามสกุล / ผู้บริหาร</th>
              <th>ชื่อผู้ใช้ (Username)</th>
              <th>อีเมล</th>
              <th>ตำแหน่ง</th>
              <th>สิทธิ์ที่เปิด</th>
              <th>สถานะ</th>
              <th>เข้าใช้ล่าสุด</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teamList as $s):
              $rInfo = $partnerRoles[$s['role_slug']] ?? ['th' => $s['role_slug'], 'icon' => '👤'];
            ?>
              <tr>
                <td><strong><?= e($s['full_name'] ?: $s['username']) ?></strong></td>
                <td><code><?= e($s['username']) ?></code></td>
                <td><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></td>
                <td><span class="pill role"><?= e($rInfo['icon'] . ' ' . $rInfo['th']) ?></span></td>
                <td><span style="font-weight:800;color:#0B1C33;"><?= (int)$s['perm_count'] ?></span> สิทธิ์</td>
                <td>
                  <span class="pill <?= $s['status'] === 'active' ? 'active' : 'suspended' ?>">
                    <?= $s['status'] === 'active' ? '● ใช้งานอยู่' : '○ ระงับ' ?>
                  </span>
                </td>
                <td><small><?= $s['last_login_at'] ? e(date('d/m/Y H:i', strtotime($s['last_login_at']))) : 'ยังไม่เคยเข้า' ?></small></td>
                <td style="white-space:nowrap;">
                  <a href="partners.php?edit=<?= (int)$s['id'] ?>" class="mini edit">✏️ แก้ไขสิทธิ์</a>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="mini warn"><?= $s['status'] === 'active' ? '⏸ พักการใช้งาน' : '▶ เปิดใช้งาน' ?></button>
                  </form>
                  <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันลบบัญชีหุ้นส่วนนี้ออกจากระบบ?');">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="mini del">🗑 ลบ</button>
                  </form>
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
