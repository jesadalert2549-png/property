<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_schema.php';
require_once __DIR__ . '/../includes/profile_media.php';
require_admin();
$pdo = db();
ensure_profile_schema($pdo);
$adminId = (int)$_SESSION['user_id'];
$statement = $pdo->prepare('SELECT id, username, email, full_name, avatar_path, password_hash, created_at, last_login_at FROM users WHERE id = ? AND role = "admin" LIMIT 1');
$statement->execute([$adminId]);
$account = $statement->fetch();
if (!$account) redirect('index.php');

$errors = [];
$notice = get_flash();
if (request_is_post()) {
    csrf_check();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $newAvatar = '';
    try { $newAvatar = save_profile_image($_FILES['avatar'] ?? [], 'avatars'); }
    catch (RuntimeException $exception) { $errors[] = $exception->getMessage(); }

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = t('กรุณากรอกชื่อที่แสดง 2–120 ตัวอักษร', 'Please enter a display name of 2-120 characters.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = t('กรุณากรอกอีเมลให้ถูกต้อง', 'Please enter a valid email address.');
    if ($email !== $account['email']) {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $emailCheck->execute([$email, $adminId]);
        if ($emailCheck->fetchColumn()) $errors[] = t('อีเมลนี้ถูกใช้งานแล้ว', 'This email is already in use.');
    }
    $changingPassword = $newPassword !== '' || $confirmPassword !== '';
    if ($changingPassword) {
        if (!password_verify($currentPassword, $account['password_hash'])) $errors[] = t('รหัสผ่านปัจจุบันไม่ถูกต้อง', 'Current password is incorrect.');
        if (strlen($newPassword) < 8) $errors[] = t('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 'New password must be at least 8 characters.');
        if ($newPassword !== $confirmPassword) $errors[] = t('ยืนยันรหัสผ่านใหม่ไม่ตรงกัน', 'New password confirmation does not match.');
    }

    if (!$errors) {
        if ($changingPassword) {
            $save = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password_hash = ?, avatar_path = COALESCE(?, avatar_path) WHERE id = ? AND role = "admin"');
            $save->execute([$name, $email, password_hash($newPassword, PASSWORD_DEFAULT), $newAvatar ?: null, $adminId]);
        } else {
            $save = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, avatar_path = COALESCE(?, avatar_path) WHERE id = ? AND role = "admin"');
            $save->execute([$name, $email, $newAvatar ?: null, $adminId]);
        }
        if ($newAvatar !== '') delete_profile_image((string)$account['avatar_path'], 'avatars');
        set_flash(t('บันทึกโปรไฟล์และตั้งค่าบัญชีเรียบร้อยแล้ว', 'Profile and account settings saved successfully.'));
        redirect('profile.php');
    }
    $account['full_name'] = $name;
    $account['email'] = $email;
    if ($newAvatar !== '') delete_profile_image($newAvatar, 'avatars');
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('โปรไฟล์และบัญชีผู้ดูแล', 'Profile & Admin Account')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php site_controls_head(); ?>
</head>
<body class="admin-body">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main class="admin-profile-page">
  <div class="admin-profile-top"><div><span class="admin-profile-kicker">ADMIN ACCOUNT</span><h1><?= e(t('โปรไฟล์และตั้งค่าบัญชี', 'Profile & Account Settings')) ?></h1><p><?= e(t('จัดการข้อมูลส่วนตัวและความปลอดภัยของบัญชีผู้ดูแล', 'Manage personal information and administrator account security.')) ?></p></div><span class="admin-profile-admin-tag"><?= e(t('ผู้ดูแลระบบ · ADMIN', 'Administrator · ADMIN')) ?></span></div>
  <?php if ($notice): ?><div class="admin-profile-alert success"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="admin-profile-alert error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
  <form class="admin-profile-card" method="post" enctype="multipart/form-data" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <div class="admin-profile-card-heading"><span class="admin-profile-icon">◎</span><div><h2><?= e(t('ข้อมูลโปรไฟล์', 'Profile Information')) ?></h2><p><?= e(t('ข้อมูลที่ใช้แสดงในแถบเมนูผู้ดูแล', 'Information displayed in the administrative topbar.')) ?></p></div></div>
    <div class="admin-profile-avatar-row"><?php if (!empty($account['avatar_path']) && is_file(__DIR__ . '/../uploads/avatars/' . basename($account['avatar_path']))): ?><img src="../uploads/avatars/<?= e(basename($account['avatar_path'])) ?>" alt="<?= e(t('รูปโปรไฟล์ผู้ดูแล', 'Admin profile photo')) ?>"><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string)($account['full_name'] ?: $account['username']), 0, 1))) ?></span><?php endif; ?><label><?= e(t('รูปโปรไฟล์', 'Profile photo')) ?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG หรือ WebP ไม่เกิน 3 MB', 'JPG, PNG or WebP, max 3 MB')) ?></small></label></div>
    <div class="admin-profile-form-grid">
      <label><?= e(t('ชื่อที่แสดง', 'Display name')) ?><input name="full_name" required minlength="2" maxlength="120" autocomplete="name" value="<?= e($account['full_name']) ?>"></label>
      <label><?= e(t('อีเมลบัญชีผู้ดูแล', 'Admin email')) ?><input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($account['email']) ?>"></label>
      <label class="admin-readonly-field"><?= e(t('ชื่อผู้ใช้', 'Username')) ?><input value="<?= e($account['username']) ?>" readonly><small><?= e(t('ชื่อผู้ใช้สำหรับเข้าระบบ ไม่สามารถเปลี่ยนจากหน้านี้', 'Login username cannot be changed here')) ?></small></label>
      <label class="admin-readonly-field"><?= e(t('สิทธิ์บัญชี', 'Role')) ?><input value="<?= e(t('ผู้ดูแลระบบ (ADMIN)', 'Administrator (ADMIN)')) ?>" readonly><small><?= e(t('คงสิทธิ์ผู้ดูแลไว้เพื่อจัดการเว็บไซต์', 'Full administrative authority')) ?></small></label>
    </div>
    <hr>
    <div class="admin-profile-card-heading"><span class="admin-profile-icon lock">⌑</span><div><h2><?= e(t('เปลี่ยนรหัสผ่าน', 'Change Password')) ?></h2><p><?= e(t('เว้นช่องรหัสผ่านใหม่ไว้ หากยังไม่ต้องการเปลี่ยน', 'Leave blank if you do not want to change password')) ?></p></div></div>
    <div class="admin-profile-form-grid">
      <label><?= e(t('รหัสผ่านปัจจุบัน', 'Current password')) ?><input type="password" name="current_password" autocomplete="current-password"></label>
      <span></span>
      <label><?= e(t('รหัสผ่านใหม่', 'New password')) ?><input type="password" name="new_password" minlength="8" autocomplete="new-password"><small><?= e(t('อย่างน้อย 8 ตัวอักษร', 'At least 8 characters')) ?></small></label>
      <label><?= e(t('ยืนยันรหัสผ่านใหม่', 'Confirm new password')) ?><input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
    </div>
    <div class="admin-profile-actions"><button class="admin-save-button" type="submit"><?= e(t('บันทึกการตั้งค่า', 'Save Settings')) ?></button><a href="index.php"><?= e(t('กลับแดชบอร์ด', 'Back to Dashboard')) ?></a></div>
  </form>
</main>
</body>
</html>
