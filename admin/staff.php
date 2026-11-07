<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin(); // Founder & CEO เท่านั้น (users.role = 'admin')

$pdo = db();
ensure_staff_schema($pdo);

$message = '';
$errors = [];
$roles = staff_roles($pdo, false);
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;

if (request_is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    // ── เพิ่มบัญชีทีมงาน ──
    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleSlug = (string)($_POST['role_slug'] ?? '');
        $perms = $_POST['perm'] ?? [];

        if (!staff_validate_username($username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, จุด, ขีด 4-50 ตัวอักษร';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
        if (mb_strlen($fullName) < 2) $errors[] = 'กรุณากรอกชื่อ-นามสกุลทีมงาน';
        if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        if (!staff_role_by_slug($pdo, $roleSlug)) $errors[] = 'กรุณาเลือกตำแหน่งที่ถูกต้อง';

        if (!$errors) {
            $dup = $pdo->prepare('SELECT 1 FROM staff_accounts WHERE username = ? OR email = ? LIMIT 1');
            $dup->execute([$username, $email]);
            if ($dup->fetchColumn()) {
                $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบทีมงานแล้ว';
            } else {
                try {
                    $newId = staff_create($pdo, [
                        'username' => $username, 'email' => $email, 'full_name' => $fullName,
                        'password' => $password, 'role_slug' => $roleSlug, 'status' => 'active',
                    ], is_array($perms) ? $perms : [], (int)$_SESSION['user_id']);
                    staff_log($pdo, $newId, (string)($_SESSION['username'] ?? 'founder'), 'staff_created', $username . ' / ' . $roleSlug);
                    $message = 'สร้างบัญชีทีมงาน "' . $username . '" เรียบร้อยแล้ว';
                } catch (Throwable $e) {
                    $errors[] = 'ไม่สามารถสร้างบัญชีได้: ' . $e->getMessage();
                }
            }
        }
    }

    // ── แก้ไขบัญชี/สิทธิ์ ──
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $errors[] = 'ไม่พบบัญชีทีมงานนี้';
        } else {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $roleSlug = (string)($_POST['role_slug'] ?? '');
            $status = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
            $password = (string)($_POST['password'] ?? '');
            $perms = $_POST['perm'] ?? [];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
            if (mb_strlen($fullName) < 2) $errors[] = 'กรุณากรอกชื่อ-นามสกุลทีมงาน';
            if (!staff_role_by_slug($pdo, $roleSlug)) $errors[] = 'ตำแหน่งไม่ถูกต้อง';
            if ($password !== '' && strlen($password) < 8) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';

            if (!$errors) {
                $dup = $pdo->prepare('SELECT 1 FROM staff_accounts WHERE email = ? AND id <> ? LIMIT 1');
                $dup->execute([$email, $id]);
                if ($dup->fetchColumn()) {
                    $errors[] = 'อีเมลนี้ถูกใช้กับบัญชีทีมงานอื่นแล้ว';
                } else {
                    if ($password !== '') {
                        $pdo->prepare('UPDATE staff_accounts SET email=?, full_name=?, role_slug=?, status=?, password_hash=? WHERE id=?')
                            ->execute([$email, $fullName, $roleSlug, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
                    } else {
                        $pdo->prepare('UPDATE staff_accounts SET email=?, full_name=?, role_slug=?, status=? WHERE id=?')
                            ->execute([$email, $fullName, $roleSlug, $status, $id]);
                    }
                    staff_set_permissions($pdo, $id, is_array($perms) ? $perms : []);
                    staff_log($pdo, $id, (string)($_SESSION['username'] ?? 'founder'), 'staff_updated', $target['username'] . ' / ' . $roleSlug . ' / ' . $status);
                    $message = 'อัปเดตบัญชีทีมงาน "' . $target['username'] . '" เรียบร้อยแล้ว';
                    $editId = 0;
                }
            }
        }
    }

    // ── ระงับ / เปิดใช้งาน ──
    elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE staff_accounts SET status = IF(status='active','suspended','active') WHERE id = ?")->execute([$id]);
        staff_log($pdo, $id, (string)($_SESSION['username'] ?? 'founder'), 'staff_toggle_status');
        $message = 'เปลี่ยนสถานะบัญชีทีมงานแล้ว';
    }

    // ── ลบบัญชี ──
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT username FROM staff_accounts WHERE id = ?');
        $stmt->execute([$id]);
        $uname = (string)$stmt->fetchColumn();
        if ($uname !== '') {
            $pdo->prepare('DELETE FROM staff_permissions WHERE staff_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM staff_accounts WHERE id = ?')->execute([$id]);
            staff_log($pdo, null, (string)($_SESSION['username'] ?? 'founder'), 'staff_deleted', $uname);
            $message = 'ลบบัญชีทีมงาน "' . $uname . '" แล้ว';
        }
    }

    // ── เพิ่มตำแหน่งใหม่ ──
    elseif ($action === 'add_role') {
        $slug = strtolower(trim((string)($_POST['role_slug'] ?? '')));
        $nameTh = trim((string)($_POST['role_name_th'] ?? ''));
        $nameEn = trim((string)($_POST['role_name_en'] ?? ''));
        $icon = trim((string)($_POST['role_icon'] ?? '👤')) ?: '👤';
        $desc = trim((string)($_POST['role_desc'] ?? ''));

        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $slug)) $errors[] = 'รหัสตำแหน่งใช้ a-z, 0-9, _ , - 2-40 ตัวอักษร';
        if (mb_strlen($nameTh) < 2) $errors[] = 'กรุณากรอกชื่อตำแหน่ง (ไทย)';

        if (!$errors) {
            $dup = $pdo->prepare('SELECT 1 FROM staff_roles WHERE slug = ? LIMIT 1');
            $dup->execute([$slug]);
            if ($dup->fetchColumn()) {
                $errors[] = 'รหัสตำแหน่งนี้มีอยู่แล้ว';
            } else {
                $next = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM staff_roles')->fetchColumn();
                $pdo->prepare('INSERT INTO staff_roles (slug, name_th, name_en, icon, description, sort_order) VALUES (?,?,?,?,?,?)')
                    ->execute([$slug, $nameTh, $nameEn ?: $nameTh, mb_substr($icon, 0, 8), mb_substr($desc, 0, 255), $next]);
                $message = 'เพิ่มตำแหน่ง "' . $nameTh . '" เรียบร้อยแล้ว';
            }
        }
    }

    // ── ลบตำแหน่ง ──
    elseif ($action === 'delete_role') {
        $slug = (string)($_POST['role_slug'] ?? '');
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM staff_accounts WHERE role_slug = ?');
        $inUse->execute([$slug]);
        if ((int)$inUse->fetchColumn() > 0) {
            $errors[] = 'ลบไม่ได้: ยังมีทีมงานใช้ตำแหน่งนี้อยู่';
        } else {
            $pdo->prepare('DELETE FROM staff_roles WHERE slug = ?')->execute([$slug]);
            $message = 'ลบตำแหน่งแล้ว';
        }
    }

    $roles = staff_roles($pdo, false);
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$editingPerms = $editing ? staff_permissions($pdo, (int)$editing['id']) : [];

$staffList = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM staff_permissions p WHERE p.staff_id = s.id) AS perm_count
     FROM staff_accounts s ORDER BY s.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$roleMap = [];
foreach ($roles as $r) { $roleMap[$r['slug']] = $r; }

$activeCount = count(array_filter($staffList, static fn($s) => $s['status'] === 'active'));
$suspendedCount = count($staffList) - $activeCount;
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการทีมงาน (Staff) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .staff-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 30px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.3); margin-bottom:22px; }
    .staff-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .staff-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }
    .staff-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:26px 28px; margin-bottom:22px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .staff-card h2 { margin:0 0 6px; color:#0B1C33; font-size:18px; }
    .staff-card .intro { margin:0 0 18px; color:#64748b; font-size:13.5px; }
    .grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .field { display:grid; gap:6px; font-size:12.5px; font-weight:700; color:#34495d; }
    .field.wide { grid-column:1/-1; }
    .field input, .field select { min-height:44px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-size:14px; background:#fff; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); }
    .field small { font-weight:500; color:#8090a0; font-size:11px; }
    .btn-main { border:0; border-radius:10px; min-height:46px; padding:0 24px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); }
    .btn-main:hover { transform:translateY(-1px); }
    .perm-grid { display:grid; gap:8px; margin-top:6px; }
    .perm-row { display:grid; grid-template-columns:minmax(150px,1.2fr) repeat(5,minmax(64px,1fr)); gap:6px; align-items:center; padding:8px 10px; border:1px solid #eef2f6; border-radius:10px; background:#F9F9FB; }
    .perm-row.head { background:#0B1C33; color:#E5CB96; font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .perm-row label { display:flex; align-items:center; justify-content:center; gap:5px; font-size:11.5px; font-weight:700; color:#475569; cursor:pointer; }
    .perm-row input[type=checkbox] { width:17px; height:17px; accent-color:#C5A059; cursor:pointer; }
    .perm-row .mod { font-size:13px; font-weight:800; color:#0B1C33; }
    .staff-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .staff-table th, .staff-table td { padding:12px 10px; border-bottom:1px solid #eef2f6; text-align:left; vertical-align:middle; }
    .staff-table th { background:#F8FAFC; color:#475569; font-size:12.5px; font-weight:700; }
    .pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:800; }
    .pill.active { background:#E8FDF0; color:#04A747; border:1px solid #06C755; }
    .pill.suspended { background:#FFF1F1; color:#ae3333; border:1px solid #EF4444; }
    .pill.role { background:#F9F5EC; color:#8C6B32; border:1px solid rgba(197,160,89,.45); }
    .mini { border:0; border-radius:8px; padding:6px 11px; font:inherit; font-size:11.5px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; }
    .mini.edit { background:#EBF3FE; color:#1A73E8; }
    .mini.warn { background:#FFF8E6; color:#8C6B32; }
    .mini.del { background:#FFF1F1; color:#ae3333; }
    .alert-ok { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#effaf3; border:1px solid #bbebcd; color:#187243; font-size:14px; font-weight:600; }
    .alert-err { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:14px; font-weight:600; }
    .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
    .stat-box { padding:16px 18px; border-radius:14px; background:#fff; border:1px solid #e3e9ef; }
    .stat-box small { display:block; color:#64748b; font-size:12px; font-weight:600; }
    .stat-box strong { display:block; font-size:24px; color:#0B1C33; font-weight:800; }
    @media (max-width:900px) { .grid2 { grid-template-columns:1fr; } .perm-row { grid-template-columns:1fr repeat(5,44px); } .perm-row .mod { font-size:12px; } }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1120px); margin:38px auto 70px;">
  <div class="staff-hero">
    <div>
      <span class="eyebrow">FOUNDER &amp; CEO · TEAM MANAGEMENT</span>
      <h1>จัดการบัญชีทีมงาน (Staff)</h1>
      <p style="margin:0;color:rgba(255,255,255,.78);font-size:13.5px;">เพิ่ม แก้ไข ระงับ ลบบัญชีทีมงาน กำหนดตำแหน่งและสิทธิ์การเข้าถึงระบบ</p>
    </div>
    <a href="../staff/login.php" target="_blank" rel="noopener" style="padding:10px 18px;border-radius:999px;background:rgba(197,160,89,.18);border:1px solid rgba(197,160,89,.45);color:#E5CB96;font-size:13px;font-weight:700;text-decoration:none;">🔐 เปิดหน้าล็อกอินทีมงาน ↗</a>
  </div>

  <?php if ($message): ?><div class="alert-ok">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert-err">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <div class="stat-row">
    <div class="stat-box"><small>ทีมงานทั้งหมด</small><strong><?= count($staffList) ?></strong></div>
    <div class="stat-box"><small>ใช้งานอยู่</small><strong style="color:#04A747;"><?= $activeCount ?></strong></div>
    <div class="stat-box"><small>ถูกระงับ</small><strong style="color:#ae3333;"><?= $suspendedCount ?></strong></div>
    <div class="stat-box"><small>ตำแหน่งในระบบ</small><strong><?= count($roles) ?></strong></div>
  </div>

  <!-- ฟอร์มเพิ่ม / แก้ไขบัญชีทีมงาน -->
  <section class="staff-card">
    <h2><?= $editing ? '✏️ แก้ไขบัญชีทีมงาน: ' . e($editing['username']) : '➕ เพิ่มบัญชีทีมงานใหม่' ?></h2>
    <p class="intro"><?= $editing ? 'แก้ไขข้อมูล ตำแหน่ง สถานะ และสิทธิ์การเข้าถึงของทีมงาน' : 'สร้างบัญชีให้ทีมงานพร้อมกำหนดตำแหน่งและสิทธิ์ (เฉพาะ Founder & CEO เท่านั้น)' ?></p>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

      <div class="grid2">
        <label class="field">ชื่อผู้ใช้ (Username)
          <input type="text" name="username" value="<?= e($editing['username'] ?? '') ?>" <?= $editing ? 'readonly style="background:#F1F5F9;"' : 'required' ?> placeholder="เช่น somchai.marketing">
          <small>a-z, 0-9, จุด, ขีด 4-50 ตัวอักษร</small>
        </label>
        <label class="field">อีเมลทีมงาน
          <input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>" required placeholder="staff@propertygo.co">
        </label>
        <label class="field">ชื่อ-นามสกุล
          <input type="text" name="full_name" value="<?= e($editing['full_name'] ?? '') ?>" required maxlength="120" placeholder="เช่น สมชาย ใจดี">
        </label>
        <label class="field">ตำแหน่ง (Role)
          <select name="role_slug" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= e($r['slug']) ?>" <?= ($editing['role_slug'] ?? '') === $r['slug'] ? 'selected' : '' ?>><?= e(($r['icon'] ?? '👤') . ' ' . $r['name_th']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><?= $editing ? 'รหัสผ่านใหม่ (เว้นว่าง = ไม่เปลี่ยน)' : 'รหัสผ่าน' ?>
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

      <h3 style="margin:22px 0 8px;font-size:15px;color:#0B1C33;">🔑 กำหนดสิทธิ์การเข้าถึง (Permissions)</h3>
      <div class="perm-grid">
        <div class="perm-row head">
          <span>โมดูล</span>
          <?php foreach (STAFF_PERMISSIONS as $perm => $meta): ?><span style="text-align:center;"><?= e($meta['label']) ?></span><?php endforeach; ?>
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

      <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
        <button type="submit" class="btn-main"><?= $editing ? '💾 บันทึกการแก้ไข' : '➕ สร้างบัญชีทีมงาน' ?></button>
        <?php if ($editing): ?><a href="staff.php" class="mini" style="background:#F1F5F9;color:#475569;padding:14px 20px;border-radius:10px;">✕ ยกเลิก</a><?php endif; ?>
      </div>
    </form>
  </section>

  <!-- รายชื่อทีมงาน -->
  <section class="staff-card">
    <h2>👥 บัญชีทีมงานทั้งหมด (<?= count($staffList) ?>)</h2>
    <p class="intro">จัดการสถานะ แก้ไขสิทธิ์ หรือลบบัญชีทีมงาน</p>
    <?php if (!$staffList): ?>
      <p class="intro" style="font-style:italic;">ยังไม่มีบัญชีทีมงาน — สร้างบัญชีแรกได้จากฟอร์มด้านบน</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="staff-table">
          <thead>
            <tr><th>ชื่อ-นามสกุล</th><th>ชื่อผู้ใช้ / อีเมล</th><th>ตำแหน่ง</th><th>สิทธิ์</th><th>สถานะ</th><th>เข้าใช้ล่าสุด</th><th>จัดการ</th></tr>
          </thead>
          <tbody>
            <?php foreach ($staffList as $s): $r = $roleMap[$s['role_slug']] ?? null; ?>
              <tr>
                <td><strong><?= e($s['full_name'] ?: $s['username']) ?></strong></td>
                <td><?= e($s['username']) ?><br><small style="color:#64748B;"><?= e($s['email']) ?></small></td>
                <td><span class="pill role"><?= e(($r['icon'] ?? '👤') . ' ' . ($r['name_th'] ?? $s['role_slug'])) ?></span></td>
                <td><?= (int)$s['perm_count'] ?> รายการ</td>
                <td><span class="pill <?= $s['status'] === 'active' ? 'active' : 'suspended' ?>"><?= $s['status'] === 'active' ? '● ใช้งาน' : '○ ระงับ' ?></span></td>
                <td><small><?= $s['last_login_at'] ? e(date('d/m/Y H:i', strtotime($s['last_login_at']))) : 'ยังไม่เคยเข้า' ?></small></td>
                <td style="white-space:nowrap;">
                  <a href="staff.php?edit=<?= (int)$s['id'] ?>" class="mini edit">✏️ แก้ไข</a>
                  <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" class="mini warn"><?= $s['status'] === 'active' ? '⏸ ระงับ' : '▶ เปิด' ?></button></form>
                  <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันลบบัญชีทีมงานนี้?');"><input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" class="mini del">🗑 ลบ</button></form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- จัดการตำแหน่ง -->
  <section class="staff-card">
    <h2>🏷️ ตำแหน่งทีมงาน (Roles)</h2>
    <p class="intro">เพิ่มตำแหน่งใหม่ได้ตามต้องการ (เช่น Designer, Customer Support) — ตำแหน่งเป็นเพียงป้ายกำกับ ไม่ผูกกับสิทธิ์อัตโนมัติ</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
      <?php foreach ($roles as $r): ?>
        <span class="pill role" style="padding:7px 12px;font-size:12.5px;">
          <?= e(($r['icon'] ?? '👤') . ' ' . $r['name_th']) ?>
          <form method="post" style="display:inline;margin-left:6px;" onsubmit="return confirm('ลบตำแหน่งนี้?');">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_slug" value="<?= e($r['slug']) ?>">
            <button type="submit" style="border:0;background:none;color:#ae3333;cursor:pointer;font-weight:900;">×</button>
          </form>
        </span>
      <?php endforeach; ?>
    </div>
    <form method="post" class="grid2">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="add_role">
      <label class="field">รหัสตำแหน่ง (slug)
        <input type="text" name="role_slug" required placeholder="เช่น designer">
      </label>
      <label class="field">ชื่อตำแหน่ง (ไทย)
        <input type="text" name="role_name_th" required placeholder="เช่น Designer">
      </label>
      <label class="field">ชื่อตำแหน่ง (EN)
        <input type="text" name="role_name_en" placeholder="Designer">
      </label>
      <label class="field">ไอคอน
        <input type="text" name="role_icon" maxlength="8" placeholder="🎨">
      </label>
      <label class="field wide">คำอธิบาย
        <input type="text" name="role_desc" maxlength="255" placeholder="เครื่องมือและข้อมูลด้านการออกแบบ">
      </label>
      <div class="wide"><button type="submit" class="btn-main">➕ เพิ่มตำแหน่ง</button></div>
    </form>
  </section>
</main>
</body>
</html>
