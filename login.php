<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/oauth.php';
require_once __DIR__ . '/includes/auth_media.php';
current_lang();

$authBgUrl = get_auth_background();

$error = $_SESSION['oauth_error'] ?? ($_SESSION['flash_error'] ?? '');
unset($_SESSION['oauth_error'], $_SESSION['flash_error']);
$requestedReturnTo = (string)($_GET['return_to'] ?? $_POST['return_to'] ?? '');
$adminReturnTargets = array_map(
    static fn(string $page): string => 'admin/' . $page,
    ['index.php', 'analytics.php', 'pdf-report.php', 'category-images.php', 'featured.php', 'projects.php', 'agents.php', 'agent-document.php', 'add.php', 'edit.php', 'view.php', 'guestbook.php', 'cookie_stats.php', 'dashboard.php', 'profile.php']
);
$validAdminReturn = in_array($requestedReturnTo, $adminReturnTargets, true)
    || preg_match('#^admin/(?:edit|view|agent-document)\.php\?id=[1-9][0-9]{0,9}$#D', $requestedReturnTo);
$validListingEditReturn = (bool)preg_match('#^edit-listing\.php\?id=[1-9][0-9]{0,9}$#D', $requestedReturnTo);
$validMemberReturn = in_array($requestedReturnTo, ['submit.php', 'submit-project.php'], true) || $validListingEditReturn;
$returnTo = $validMemberReturn || $validAdminReturn ? $requestedReturnTo : '';
$token = csrf_token();
if (request_is_post()) {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = t('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'Please enter your username and password.');
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_banned, ban_reason FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ตรวจสอบสถานะการระงับบัญชี (Banned Status)
            if (!empty($user['is_banned']) && $user['role'] !== 'admin') {
                $reason = !empty($user['ban_reason']) ? ': ' . $user['ban_reason'] : '';
                $error = t('บัญชีของคุณถูกระงับการใช้งาน' . $reason . ' กรุณาติดต่อผู้ดูแลระบบ', 'Your account has been suspended' . $reason . '. Please contact support.');
            } else {
                $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    $_SESSION['is_founder'] = true;
                    $_SESSION['partner_title'] = 'FOUNDER & CEO';
                    setcookie('no_track', '1', [
                        'expires' => time() + 60 * 60 * 24 * 30,
                        'path' => '/',
                        'secure' => request_is_https(),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('no_track', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'secure' => request_is_https(),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
                $destination = $validMemberReturn
                    ? $returnTo
                    : ($user['role'] === 'admin'
                        ? (str_starts_with($returnTo, 'admin/') ? $returnTo : 'admin/index.php')
                        : 'dashboard.php');
                header('Location: ' . $destination);
                exit;
            }
        }

        // ตรวจสอบบัญชีหุ้นส่วนและผู้จัดการ (Partners & Management)
        require_once __DIR__ . '/includes/staff.php';
        ensure_staff_schema($pdo);
        $partnerStmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE username = ? OR email = ? LIMIT 1');
        $partnerStmt->execute([$username, $username]);
        $partner = $partnerStmt->fetch(PDO::FETCH_ASSOC);

        if ($partner && password_verify($password, $partner['password_hash'])) {
            if ($partner['status'] !== 'active') {
                $error = t('บัญชีผู้บริหาร/หุ้นส่วนนี้ถูกระงับการใช้งาน กรุณาติดต่อ Founder & CEO', 'This account has been suspended. Please contact Founder & CEO.');
            } else {
                $pdo->prepare('UPDATE staff_accounts SET last_login_at = CURRENT_TIMESTAMP, last_login_ip = ? WHERE id = ?')
                    ->execute([substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), (int)$partner['id']]);
                staff_log($pdo, (int)$partner['id'], $partner['username'], 'partner_login', 'role: ' . $partner['role_slug']);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$partner['id'];
                $_SESSION['staff_id'] = (int)$partner['id'];
                $_SESSION['username'] = $partner['username'];
                $_SESSION['role'] = 'partner';
                $_SESSION['is_founder'] = false;

                $roleInfo = staff_role_by_slug($pdo, (string)$partner['role_slug']);
                $_SESSION['partner_title'] = $roleInfo['name_th'] ?? 'หุ้นส่วน / ผู้จัดการ';
                $_SESSION['partner_role_slug'] = $partner['role_slug'];

                setcookie('no_track', '1', [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/',
                    'secure' => request_is_https(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                header('Location: admin/index.php');
                exit;
            }
        } else {
            $error = t('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'Incorrect username or password.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('เข้าสู่ระบบ', 'Log in')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/home.css?v=<?= e((string) filemtime(__DIR__ . '/css/home.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
</head>
<body class="auth-page">
<div class="login-wrap" style="background-image: linear-gradient(135deg, rgba(9, 24, 39, .84), rgba(24, 50, 75, .58)), url('<?= e($authBgUrl) ?>');">
  <?php render_auth_background_media(); ?>
  <div class="login-box">
    <?= render_logo_html('index.php') ?>
    <h2><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></h2>
    <?php if ($returnTo): ?>
      <p class="auth-prompt"><?= e(t('เข้าสู่ระบบหรือสมัครสมาชิกเพื่อดำเนินการลงประกาศ ระบบจะพากลับไปยังแบบฟอร์มหลังเข้าสู่ระบบ', 'Log in or register to continue posting your listing. You will be returned to the form after logging in.')) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" id="login-form">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
      <label><?= e(t('ชื่อผู้ใช้ หรืออีเมล', 'Username or email')) ?></label>
      <input type="text" name="username" required autocomplete="username" placeholder="Username" autofocus>
      
      <label><?= e(t('รหัสผ่าน', 'Password')) ?></label>
      <input type="password" name="password" required autocomplete="current-password" placeholder="Password">
      
      <button type="submit" class="btn btn-block" id="login-submit"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></button>
    </form>
    <script>
      document.getElementById('login-form').addEventListener('submit', function () {
        const button = document.getElementById('login-submit');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = '<?= e(t('กำลังเข้าสู่ระบบ…', 'Logging in…')) ?>';
      });
    </script>
    <?php oauth_buttons($returnTo); ?>
    <p class="auth-links">
      <?= e(t('ยังไม่มีบัญชี?', 'No account yet?')) ?> <a href="register.php<?= $returnTo ? '?return_to=' . rawurlencode($returnTo) : '' ?>"><?= e(t('สมัครสมาชิกที่นี่', 'Register here')) ?></a> · <a href="index.php"><?= e(t('กลับหน้าแรก', 'Back to home')) ?></a>
    </p>
    <?php render_lang_toggle(); ?>
  </div>
</div>
<?php
render_page_cta([
    'title'   => t('ยังไม่มีบัญชี? สมัครสมาชิกฟรี', 'No account? Register for free'),
    'text'    => t('ใช้เวลาไม่ถึงนาที เริ่มลงประกาศและจัดการประกาศได้ทันที', 'Takes less than a minute — start posting and managing listings right away.'),
    'primary' => ['register.php', t('สมัครสมาชิกฟรี', 'Register for free')],
    'ghost'   => ['search.php', t('ดูประกาศก่อนได้', 'Browse listings first')],
]);
render_to_top(); render_ux_scripts();
render_site_footer(); ?>
</body>
</html>
