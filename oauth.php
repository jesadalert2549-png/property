<?php
require_once __DIR__ . '/includes/oauth.php';

start_session();
$provider = strtolower((string)($_POST['provider'] ?? $_GET['provider'] ?? ''));
$cfg = oauth_provider_config($provider);
if (!$cfg) oauth_fail('ไม่รู้จักผู้ให้บริการเข้าสู่ระบบนี้');

// ── 1. ดำเนินการในโหมดทดสอบ (Sandbox Mode - 1 Click Instant Login) ──
if (!empty($_GET['sandbox']) || !empty($_POST['sandbox_login'])) {
    $returnTo = (string)($_POST['return_to'] ?? $_GET['return_to'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sandbox_login'])) {
        csrf_check();
        $name = trim((string)($_POST['mock_name'] ?? 'ผู้ใช้ทดสอบ'));
        $email = strtolower(trim((string)($_POST['mock_email'] ?? '')));
        $lineId = trim((string)($_POST['mock_line_id'] ?? ''));
        $sub = 'sandbox_' . $provider . '_' . md5($email ?: $name);

        $user = oauth_get_or_create_user(db(), $provider, $sub, $email, $name, true, $lineId);
        oauth_finish_login($user, $returnTo);
    }

    // หน้าจอจำลองการยืนยันตัวตนของ Social Provider
    $token = csrf_token();
    $providerName = $cfg['name'];
    $brandColor = $provider === 'line' ? '#06C755' : ($provider === 'google' ? '#4285F4' : '#1877F2');
    $defaultEmail = $provider === 'line' ? 'somchai.line@gmail.com' : ($provider === 'google' ? 'somchai.google@gmail.com' : 'somchai.fb@gmail.com');
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>เข้าสู่ระบบด้วย <?= e($providerName) ?> (โหมดทดสอบ) | <?= e(SITE_NAME) ?></title>
      <link rel="stylesheet" href="css/style.css">
      <link rel="stylesheet" href="css/home.css">
      <style>
        .sandbox-wrap { min-height:100vh; display:grid; place-items:center; background:#F9F9FB; padding:24px 16px; font-family:"Prompt","Inter",system-ui,sans-serif; }
        .sandbox-box { width:min(440px,100%); background:#fff; border-radius:20px; border:1px solid #E2E8F0; box-shadow:0 16px 40px rgba(11,28,51,.1); overflow:hidden; }
        .sandbox-header { background:<?= e($brandColor) ?>; color:#fff; padding:24px 20px; text-align:center; }
        .sandbox-header h1 { color:#fff; font-size:20px; margin:8px 0 0; }
        .sandbox-body { padding:28px 24px; }
        .sandbox-notice { background:#FFF8E6; border:1px solid #F5D480; border-radius:10px; padding:12px 14px; color:#8C6B32; font-size:12.5px; line-height:1.6; margin-bottom:20px; }
        .sandbox-field { display:grid; gap:6px; margin-bottom:16px; font-size:13px; font-weight:700; color:#334155; text-align:left; }
        .sandbox-field input { width:100%; min-height:44px; border:1px solid #CBD5E1; border-radius:10px; padding:8px 12px; font:inherit; }
        .sandbox-btn-submit { width:100%; min-height:50px; border:0; border-radius:12px; background:<?= e($brandColor) ?>; color:#fff; font:inherit; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 6px 18px rgba(0,0,0,.15); transition:transform .18s; }
        .sandbox-btn-submit:hover { transform:translateY(-1px); }
        .sandbox-cancel { display:block; text-align:center; margin-top:14px; color:#64748B; font-size:13px; text-decoration:none; }
      </style>
    </head>
    <body>
      <div class="sandbox-wrap">
        <div class="sandbox-box">
          <div class="sandbox-header">
            <?php if ($provider === 'line'): ?>
              <svg width="40" height="40" viewBox="0 0 24 24" fill="#FFFFFF"><path d="M19.365 9.864c0-4.043-3.906-7.332-8.706-7.332-4.8 0-8.706 3.289-8.706 7.332 0 3.623 3.102 6.671 7.294 7.234.284.061.67.188.767.432.088.22.057.564.028.786l-.121.729c-.037.225-.173.88.77.48 1.045-.443 5.641-3.322 7.7-5.688 1.488-1.708 2.074-3.467 2.074-4.973z"/><path fill="<?= e($brandColor) ?>" d="M8.532 12.016H6.965a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v2.616h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44zm1.884-.44a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.902 0a.44.44 0 0 1-.373.22.44.44 0 0 1-.362-.187l-1.57-2.18v1.927a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .373.22l1.558 2.164V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.323-1.928h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.567a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44h1.567a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44z"/></svg>
            <?php elseif ($provider === 'google'): ?>
              <svg width="36" height="36" viewBox="0 0 24 24"><path fill="#fff" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#fff" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/></svg>
            <?php else: ?>
              <svg width="36" height="36" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <?php endif; ?>
            <h1>เข้าสู่ระบบด้วย <?= e($providerName) ?></h1>
            <p style="margin:4px 0 0;font-size:12.5px;opacity:.9;">เชื่อมต่อบัญชีกับ <?= e(SITE_NAME) ?></p>
          </div>
          <div class="sandbox-body">
            <div class="sandbox-notice">
              ⚡ <strong>กำลังทดสอบในโหมด Sandbox:</strong> คุณสามารถแก้ไขข้อมูลโปรไฟล์ด้านล่าง หรือกดปุ่มเข้าสู่ระบบทันทีเพื่อสร้าง/ล็อกอินบัญชีลงในฐานข้อมูล
            </div>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e($token) ?>">
              <input type="hidden" name="provider" value="<?= e($provider) ?>">
              <input type="hidden" name="sandbox_login" value="1">
              <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

              <div class="sandbox-field">
                <span>ชื่อที่แสดง (Display Name)</span>
                <input type="text" name="mock_name" value="คุณสมชาย ใจดี" required>
              </div>

              <div class="sandbox-field">
                <span>อีเมล (Email)</span>
                <input type="email" name="mock_email" value="<?= e($defaultEmail) ?>" required>
              </div>

              <?php if ($provider === 'line'): ?>
                <div class="sandbox-field">
                  <span>LINE ID (สำหรับผู้สนใจติดต่อกลับ)</span>
                  <input type="text" name="mock_line_id" value="somchai_kasa" placeholder="เช่น somchai_kasa">
                </div>
              <?php endif; ?>

              <button type="submit" class="sandbox-btn-submit">✓ อนุญาตและเข้าสู่ระบบทันที</button>
            </form>
            <a href="login.php" class="sandbox-cancel">✕ ยกเลิกและกลับหน้าเข้าสู่ระบบ</a>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ── 2. เมื่อผู้ใช้คลิกปุ่ม Social Login จากหน้า login.php / register.php ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['provider'])) {
    csrf_check();
    if (!$cfg['enabled']) oauth_fail('ผู้ให้บริการนี้ยังไม่ได้เปิดใช้งาน');

    $requestedReturnTo = (string)($_POST['return_to'] ?? '');
    $adminReturnTargets = array_map(
        static fn(string $page): string => 'admin/' . $page,
        ['index.php', 'analytics.php', 'pdf-report.php', 'category-images.php', 'featured.php', 'projects.php', 'add.php', 'edit.php', 'view.php', 'guestbook.php', 'cookie_stats.php', 'dashboard.php', 'oauth.php']
    );
    $validAdminReturn = in_array($requestedReturnTo, $adminReturnTargets, true)
        || preg_match('#^admin/(?:edit|view)\.php\?id=[1-9][0-9]{0,9}$#D', $requestedReturnTo);
    $returnTo = in_array($requestedReturnTo, ['submit.php', 'submit-project.php'], true) || $validAdminReturn ? $requestedReturnTo : '';

    // ถ้าเปิดโหมด Sandbox ไว้ ให้พาไปหน้าเข้าสู่ระบบทันทีแบบง่ายๆ
    if (!empty($cfg['sandbox'])) {
        redirect('oauth.php?provider=' . rawurlencode($provider) . '&sandbox=1&return_to=' . rawurlencode($returnTo));
    }

    // ถ้าเป็นโหมดเชื่อมต่อ API จริงกับ LINE, Google หรือ Facebook
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $flow = json_encode(['state' => $state, 'nonce' => $nonce, 'return_to' => $returnTo, 'provider' => $provider, 'expires' => time() + 600]);
    $secure = request_is_https();
    setcookie('sp_oauth_flow', base64_encode($flow), ['expires' => time() + 600, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => $secure ? 'None' : 'Lax']);

    // กำหนด Scope แยกตามผู้ให้บริการอย่างชัดเจน (ตัด email เฉพาะ Facebook)
    $scope = $cfg['scope'];
    if ($provider === 'facebook') {
        $scope = trim(str_replace(['email,', ',email', 'email'], '', $scope), ',');
    } elseif ($provider === 'google') {
        $scope = 'openid email profile';
    } elseif ($provider === 'line') {
        $scope = 'openid profile email';
    }

    $params = [
        'client_id' => $cfg['client_id'],
        'redirect_uri' => oauth_callback_url($provider),
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ];

   if ($provider === 'google') {
        $params['nonce'] = $nonce;
        $params['prompt'] = 'select_account';
    } elseif ($provider === 'line') {
        $params['nonce'] = $nonce;
        $params['prompt'] = 'consent';
    }

    redirect($cfg['authorize'] . '?' . http_build_query($params));
}

// ── 3. รับ Callback จากผู้ให้บริการ API จริง ──
$flowRaw = base64_decode((string)($_COOKIE['sp_oauth_flow'] ?? ''), true);
$flow = $flowRaw !== false ? json_decode($flowRaw, true) : null;
$state = (string)($_GET['state'] ?? $_POST['state'] ?? '');
$secure = request_is_https();
setcookie('sp_oauth_flow', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => $secure ? 'None' : 'Lax']);

if (!is_array($flow) || ($flow['provider'] ?? '') !== $provider || (int)($flow['expires'] ?? 0) < time() || $state === '' || !hash_equals((string)$flow['state'], $state)) {
    oauth_fail('การยืนยันตัวตนหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่');
}
if (!empty($_GET['error']) || !empty($_POST['error'])) {
    oauth_fail('ยกเลิกหรือไม่สามารถเข้าสู่ระบบด้วย ' . $cfg['name'] . ' ได้');
}

try {
    $code = (string)($_GET['code'] ?? $_POST['code'] ?? '');
    if ($code === '') throw new RuntimeException('ไม่ได้รับรหัสยืนยันจากผู้ให้บริการ');

    $tokenParams = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => oauth_callback_url($provider),
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
    ];

    $tokens = oauth_http($cfg['token'], $tokenParams);
    $lineId = '';

    if ($provider === 'line') {
        // ดึงโปรไฟล์ LINE (userId, displayName, pictureUrl)
        $profile = oauth_http($cfg['userinfo'], null, ['Authorization: Bearer ' . (string)($tokens['access_token'] ?? '')]);
        $sub = (string)($profile['userId'] ?? '');
        $name = (string)($profile['displayName'] ?? 'ผู้ใช้ LINE');
        $email = '';
        if (!empty($tokens['id_token'])) {
            $claims = oauth_jwt_payload((string)$tokens['id_token'], 'https://access.line.me', $cfg['client_id']);
            $email = (string)($claims['email'] ?? '');
        }
        $lineId = $name;
        $identity = ['sub' => $sub, 'email' => $email, 'name' => $name];
    } elseif ($provider === 'google') {
        $claims = oauth_jwt_payload((string)($tokens['id_token'] ?? ''), 'https://accounts.google.com', $cfg['client_id']);
        $identity = ['sub' => $claims['sub'] ?? '', 'email' => $claims['email'] ?? '', 'name' => $claims['name'] ?? ''];
    } else {
        $profile = oauth_http($cfg['userinfo'], null, ['Authorization: Bearer ' . (string)($tokens['access_token'] ?? '')]);
        $identity = ['sub' => $profile['id'] ?? '', 'email' => $profile['email'] ?? '', 'name' => $profile['name'] ?? ''];
    }

    if ($identity['sub'] === '') throw new RuntimeException('ผู้ให้บริการไม่ส่งรหัสบัญชีมา');
    $user = oauth_get_or_create_user(db(), $provider, (string)$identity['sub'], (string)$identity['email'], (string)$identity['name'], true, $lineId);
    oauth_finish_login($user, (string)($flow['return_to'] ?? ''));
} catch (Throwable $exception) {
    error_log('OAuth sign-in failed (' . $provider . '): ' . $exception->getMessage());
    oauth_fail($exception instanceof RuntimeException ? $exception->getMessage() : 'ไม่สามารถเข้าสู่ระบบได้ในขณะนี้ กรุณาลองใหม่');
}