<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/oauth.php';
require_once __DIR__ . '/includes/auth_media.php';
current_lang();

/* ⚠ background-image ใช้ได้เฉพาะรูปภาพ — ถ้าเป็นการตั้งค่าวีดีโอ ค่า url จะเป็น .mp4
   เบราว์เซอร์จะโหลด MP4 ทั้งไฟล์ (75 MB) แล้วทิ้ง เพราะใช้เป็น background ไม่ได้
   ที่นี่จึงส่งเฉพาะเมื่อเป็นไฟล์รูป ส่วนวิดีโอจัดการผ่าน <video> ใน render_auth_background_media() */
$authBgUrl = get_auth_background();
$authBgIsImage = $authBgUrl !== '' && preg_match('/\.(jpe?g|png|webp|avif|gif|svg)$/i', $authBgUrl) === 1;
$authBgImageCss = $authBgIsImage
    ? "background-image: linear-gradient(135deg, rgba(9, 24, 39, .84), rgba(24, 50, 75, .58)), url('" . e($authBgUrl) . "');"
    : 'background-image: linear-gradient(135deg, rgba(9, 24, 39, .84), rgba(24, 50, 75, .58));';

$error = '';
$success = '';
$requestedReturnTo = (string)($_GET['return_to'] ?? $_POST['return_to'] ?? '');
$validListingEditReturn = (bool)preg_match('#^edit-listing\.php\?id=[1-9][0-9]{0,9}$#D', $requestedReturnTo);
$returnTo = in_array($requestedReturnTo, ['submit.php', 'submit-project.php'], true) || $validListingEditReturn ? $requestedReturnTo : '';
$token = csrf_token();

if (request_is_post()) {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $normalizedPhone = preg_replace('/[\s-]/', '', $phone);
    $lineId = clean_line_id($_POST['line_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($_POST['privacy_accept'])) {
        $error = t('กรุณายอมรับนโยบายความเป็นส่วนตัวเพื่อสร้างบัญชี', 'Please accept the Privacy Policy to create an account.');
    } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
        $error = t('กรุณากรอกชื่อ-นามสกุล 2-120 ตัวอักษร', 'Please enter a full name of 2-120 characters.');
    } elseif ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $normalizedPhone)) {
        $error = t('เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก หรือเว้นว่างไว้', 'Phone number must be 9-10 digits, or leave it empty.');
    } elseif (!valid_line_id($lineId)) {
        $error = t('LINE ID ต้องเป็น 4-20 ตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด หรือขีดล่าง', 'LINE ID must be 4-20 letters, numbers, dots, dashes or underscores.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('รูปแบบอีเมลไม่ถูกต้อง', 'Invalid email format.');
    } elseif (strlen($username) < 3) {
        $error = t('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร', 'Username must be at least 3 characters.');
    } elseif (strlen($password) < 8) {
        $error = t('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร', 'Password must be at least 8 characters.');
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = t('อีเมลหรือชื่อผู้ใช้นี้ถูกใช้งานไปแล้ว', 'This email or username is already in use.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO users (email, username, full_name, phone, line_id, privacy_accepted_at, password_hash, role) VALUES (?, ?, ?, ?, ?, NOW(), ?, "user")');
            if ($ins->execute([$email, $username, $fullName, $phone, $lineId, $hash])) {
                $success = t('สมัครสมาชิกสำเร็จ! สามารถ ', 'Registration successful! You can ') . '<a href="login.php' . ($returnTo ? '?return_to=' . rawurlencode($returnTo) : '') . '">' . e(t('เข้าสู่ระบบ', 'log in')) . '</a>' . t(' ได้ทันที', ' right away.');
            } else {
                $error = t('เกิดข้อผิดพลาดในการสมัครสมาชิก', 'An error occurred during registration.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('สมัครสมาชิก', 'Register')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/home.css?v=<?= e((string) filemtime(__DIR__ . '/css/home.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
</head>
<body class="auth-page">
<div class="login-wrap" style="<?= $authBgImageCss ?>">
  <?php render_auth_background_media(); ?>
  <div class="login-box auth-card--register">
    <?= render_logo_html('index.php') ?>
    <h2><?= e(t('สมัครสมาชิกเพื่อลงประกาศ', 'Register to post listings')) ?></h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
      <label><?= e(t('ชื่อ-นามสกุล', 'Full name')) ?></label>
      <input type="text" name="full_name" required maxlength="120" autocomplete="name" value="<?= e($_POST['full_name'] ?? '') ?>">

      <label><?= e(t('อีเมล', 'Email')) ?></label>
      <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      
      <label><?= e(t('ชื่อผู้ใช้ (Username)', 'Username')) ?></label>
      <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">

      <label><?= e(t('เบอร์โทรศัพท์ (ไม่บังคับ)', 'Phone (optional)')) ?></label>
      <input type="tel" name="phone" inputmode="numeric" value="<?= e($_POST['phone'] ?? '') ?>">

      <label><?= e(t('LINE ID (ไม่บังคับ)', 'LINE ID (optional)')) ?></label>
      <input type="text" name="line_id" maxlength="20" value="<?= e($_POST['line_id'] ?? '') ?>" placeholder="<?= e(t('เช่น yourlineid', 'e.g. yourlineid')) ?>">
      
      <label><?= e(t('รหัสผ่าน (Password)', 'Password')) ?></label>
      <input type="password" name="password" minlength="8" required>

      <label class="auth-consent">
        <input type="checkbox" name="privacy_accept" value="1" required <?= !empty($_POST['privacy_accept']) ? 'checked' : '' ?>>
        <span><?= e(t('ยอมรับให้จัดเก็บชื่อ อีเมล ชื่อผู้ใช้ และช่องทางติดต่อเพื่อจัดการบัญชีและประกาศ โดยเจ้าของเว็บไซต์เท่านั้นที่ดูข้อมูลบัญชีได้', 'I agree to allow the storage of my name, email, username and contact details for account and listing management. Only the website owner can view account data.')) ?></span>
      </label>
      
      <button type="submit" class="btn btn-block"><?= e(t('สมัครสมาชิก', 'Register')) ?></button>
    </form>
    <?php oauth_buttons($returnTo); ?>
    <p class="auth-links"><a href="login.php<?= $returnTo ? '?return_to=' . rawurlencode($returnTo) : '' ?>"><?= e(t('มีบัญชีอยู่แล้ว? เข้าสู่ระบบ', 'Already have an account? Log in')) ?></a></p>
    <?php render_lang_toggle(); ?>
  </div>
</div>
<?php
render_page_cta([
    'title'   => t('มีบัญชีอยู่แล้ว? เข้าสู่ระบบเลย', 'Already have an account? Log in'),
    'text'    => t('เข้าสู่ระบบเพื่อจัดการประกาศเดิม หรือเริ่มค้นหาทรัพย์ที่ถูกใจก่อนก็ได้', 'Log in to manage your listings, or start browsing properties you like first.'),
    'primary' => ['login.php', t('เข้าสู่ระบบ', 'Log in')],
    'ghost'   => ['search.php', t('ดูประกาศทั้งหมด', 'View all listings')],
]);
render_to_top(); render_ux_scripts();
render_site_footer();
render_auth_background_script(); ?>
</body>
</html>
