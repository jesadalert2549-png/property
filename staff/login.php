<?php
require_once __DIR__ . '/../includes/staff.php';
start_session();

$pdo = db();
ensure_staff_schema($pdo);

// ถ้าล็อกอินทีมงานอยู่แล้ว ไปแดชบอร์ดเลย
if (is_staff()) {
    redirect('index.php');
}

$error = '';
$username = '';
$token = csrf_token();

if (request_is_post()) {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff || !password_verify($password, $staff['password_hash'])) {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            staff_log($pdo, null, $username, 'staff_login_failed', 'invalid credentials');
        } elseif ($staff['status'] !== 'active') {
            $error = 'บัญชีทีมงานนี้ถูกระงับการใช้งาน กรุณาติดต่อ Founder & CEO';
            staff_log($pdo, (int)$staff['id'], $staff['username'], 'staff_login_blocked', 'suspended account');
        } else {
            session_regenerate_id(true);
            $_SESSION['staff_id'] = (int)$staff['id'];
            $_SESSION['staff_username'] = $staff['username'];
            $_SESSION['staff_name'] = $staff['full_name'] ?: $staff['username'];
            $_SESSION['staff_role'] = 'staff';
            $_SESSION['staff_role_slug'] = $staff['role_slug'];

            $pdo->prepare('UPDATE staff_accounts SET last_login_at = CURRENT_TIMESTAMP, last_login_ip = ? WHERE id = ?')
                ->execute([substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), (int)$staff['id']]);
            staff_log($pdo, (int)$staff['id'], $staff['username'], 'staff_login', 'role: ' . $staff['role_slug']);

            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบทีมงาน | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/home.css">
  <style>
    .staff-login-wrap { min-height:100vh; display:grid; place-items:center; padding:32px 16px; background:radial-gradient(circle at 20% 10%, #182C48, #0B1C33 60%); font-family:"Prompt","Inter",system-ui,sans-serif; }
    .staff-login-card { width:min(440px,100%); background:#fff; border-radius:22px; padding:36px 32px; box-shadow:0 24px 60px rgba(0,0,0,.35); border:1px solid rgba(197,160,89,.35); }
    .staff-login-badge { display:inline-flex; align-items:center; gap:7px; padding:5px 13px; border-radius:999px; background:rgba(197,160,89,.14); border:1px solid rgba(197,160,89,.45); color:#8C6B32; font-size:11px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    .staff-login-card h1 { margin:16px 0 6px; font-size:24px; color:#0B1C33; }
    .staff-login-card p.sub { margin:0 0 24px; color:#64748B; font-size:13.5px; line-height:1.6; }
    .staff-field { display:grid; gap:6px; margin-bottom:16px; font-size:13px; font-weight:700; color:#334155; }
    .staff-field input { width:100%; min-height:48px; padding:10px 14px; border:1px solid #CBD5E1; border-radius:11px; font:inherit; font-size:15px; box-sizing:border-box; }
    .staff-field input:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.22); }
    .staff-submit { width:100%; min-height:52px; border:0; border-radius:12px; background:linear-gradient(135deg,#D4AF67,#B58F48); color:#0B1C33; font:inherit; font-size:16px; font-weight:900; cursor:pointer; box-shadow:0 6px 18px rgba(197,160,89,.4); transition:transform .18s, box-shadow .18s; }
    .staff-submit:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(197,160,89,.5); }
    .staff-alert { padding:12px 15px; margin-bottom:18px; border-radius:11px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:13.5px; font-weight:600; }
    .staff-links { margin-top:20px; padding-top:18px; border-top:1px solid #EEF2F6; display:flex; justify-content:space-between; gap:10px; font-size:12.5px; }
    .staff-links a { color:#64748B; text-decoration:none; font-weight:600; }
    .staff-links a:hover { color:#A8843F; }
    .staff-note { margin-top:16px; padding:12px 14px; border-radius:10px; background:#F9F9FB; border:1px dashed #CBD5E1; color:#64748B; font-size:11.5px; line-height:1.6; }
  </style>
</head>
<body>
  <div class="staff-login-wrap">
    <div class="staff-login-card">
      <?= render_logo_html('../index.php') ?>
      <div style="margin-top:18px;"><span class="staff-login-badge">🔐 Staff Portal</span></div>
      <h1>เข้าสู่ระบบทีมงาน</h1>
      <p class="sub">สำหรับทีมงานที่ได้รับอนุญาตจาก Founder &amp; CEO เท่านั้น<br>สมาชิกทั่วไปกรุณาเข้าสู่ระบบที่หน้า <a href="../login.php" style="color:#A8843F;font-weight:700;">เข้าสู่ระบบสมาชิก</a></p>

      <?php if ($error !== ''): ?><div class="staff-alert">⚠️ <?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label class="staff-field">ชื่อผู้ใช้ หรือ อีเมลทีมงาน
          <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="staff-field">รหัสผ่าน
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="staff-submit">เข้าสู่ระบบทีมงาน</button>
      </form>

      <div class="staff-note">🔒 บัญชีทีมงานสร้างได้โดย Founder &amp; CEO เท่านั้น ไม่สามารถสมัครเองได้</div>

      <div class="staff-links">
        <a href="../index.php">← กลับหน้าเว็บไซต์</a>
        <a href="../login.php">เข้าสู่ระบบสมาชิก</a>
      </div>
    </div>
  </div>
</body>
</html>
