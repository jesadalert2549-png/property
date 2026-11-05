<?php
require_once __DIR__ . '/helpers.php';

function oauth_settings(): array
{
    static $settings = null;
    if ($settings !== null) return $settings;
    $path = dirname(__DIR__) . '/oauth_config.json';
    if (!is_file($path)) return $settings = [];
    $decoded = json_decode((string)file_get_contents($path), true);
    return $settings = is_array($decoded) ? $decoded : [];
}

function oauth_provider_config(string $provider): array
{
    $settings = oauth_settings();
    $setting = static function (string $provider, string $key) use ($settings): string {
        $environment = trim((string)getenv(strtoupper($provider . '_' . $key)));
        return $environment !== '' ? $environment : trim((string)($settings[$provider][$key] ?? ''));
    };
    $isSandbox = !empty($settings[$provider]['sandbox']);
    $isEnabled = isset($settings[$provider]['enabled']) ? !empty($settings[$provider]['enabled']) : true;

    $providers = [
        'google' => [
            'name' => 'Google',
            'client_id' => $setting('google', 'client_id'),
            'client_secret' => $setting('google', 'client_secret'),
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
            'oidc' => true,
            'sandbox' => $isSandbox,
            'color' => '#ffffff',
            'text_color' => '#1e293b',
        ],
        'line' => [
            'name' => 'LINE',
            'client_id' => $setting('line', 'channel_id'),
            'client_secret' => $setting('line', 'channel_secret'),
            'authorize' => 'https://access.line.me/oauth2/v2.1/authorize',
            'token' => 'https://api.line.me/oauth2/v2.1/token',
            'userinfo' => 'https://api.line.me/v2/profile',
            'scope' => 'profile openid email',
            'oidc' => true,
            'sandbox' => $isSandbox,
            'color' => '#06C755',
            'text_color' => '#ffffff',
        ],
        'facebook' => [
            'name' => 'Facebook',
            'client_id' => $setting('facebook', 'client_id'),
            'client_secret' => $setting('facebook', 'client_secret'),
            'authorize' => 'https://www.facebook.com/' . ($setting('facebook', 'graph_version') ?: 'v23.0') . '/dialog/oauth',
            'token' => 'https://graph.facebook.com/' . ($setting('facebook', 'graph_version') ?: 'v23.0') . '/oauth/access_token',
            'userinfo' => 'https://graph.facebook.com/' . ($setting('facebook', 'graph_version') ?: 'v23.0') . '/me?fields=id,name,email',
            'scope' => 'email,public_profile',
            'oidc' => false,
            'sandbox' => $isSandbox,
            'color' => '#1877F2',
            'text_color' => '#ffffff',
        ],
    ];

    if (!isset($providers[$provider])) return [];
    $cfg = $providers[$provider];

    if (!$isEnabled) {
        $cfg['enabled'] = false;
        return $cfg;
    }

    // เมื่อเปิดโหมดทดสอบ (Sandbox) จะเปิดให้ล็อกอินทันทีเพื่อให้ทดสอบระบบได้อย่างราบรื่น
    if ($cfg['sandbox']) {
        $cfg['enabled'] = true;
        return $cfg;
    }

    $cfg['enabled'] = ($cfg['client_id'] !== '' && $cfg['client_secret'] !== '');
    return $cfg;
}

function oauth_callback_url(string $provider): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = rtrim(dirname($script), '/');
    foreach (['/admin', '/api'] as $suffix) {
        if (str_ends_with($dir, $suffix)) {
            $dir = substr($dir, 0, -strlen($suffix));
            break;
        }
    }
    return $protocol . $host . $dir . '/oauth.php?provider=' . rawurlencode($provider);
}

function oauth_buttons(string $returnTo = ''): void
{
    $providers = ['google', 'line', 'facebook'];
    $items = [];
    foreach ($providers as $p) {
        $cfg = oauth_provider_config($p);
        $items[$p] = [
            'name' => $cfg['name'],
            'enabled' => $cfg['enabled'],
            'sandbox' => !empty($cfg['sandbox']),
        ];
    }
    ?>
    <div class="social-auth">
      <div class="social-auth-divider"><span><?= e(t('หรือดำเนินการต่อด้วย', 'or continue with')) ?></span></div>
      <div class="social-auth-buttons">
        <?php foreach ($items as $provider => $item): ?>
          <form method="post" action="oauth.php" class="social-auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="provider" value="<?= e($provider) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button type="submit" class="social-auth-button social-<?= e($provider) ?>" <?= $item['enabled'] ? '' : 'disabled title="ต้องตั้งค่าข้อมูลเชื่อมต่อก่อนใช้งาน"' ?>>
              <span class="social-auth-icon" aria-hidden="true">
                <?php if ($provider === 'google'): ?>
                  <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                <?php elseif ($provider === 'line'): ?>
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="#06C755"><path d="M19.365 9.864c0-4.043-3.906-7.332-8.706-7.332-4.8 0-8.706 3.289-8.706 7.332 0 3.623 3.102 6.671 7.294 7.234.284.061.67.188.767.432.088.22.057.564.028.786l-.121.729c-.037.225-.173.88.77.48 1.045-.443 5.641-3.322 7.7-5.688 1.488-1.708 2.074-3.467 2.074-4.973z"/><path fill="#FFF" d="M8.532 12.016H6.965a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v2.616h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44zm1.884-.44a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.902 0a.44.44 0 0 1-.373.22.44.44 0 0 1-.362-.187l-1.57-2.18v1.927a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .373.22l1.558 2.164V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.323-1.928h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.567a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44h1.567a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44z"/></svg>
                <?php else: ?>
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                <?php endif; ?>
              </span>
              <span><?= e(t('ดำเนินการต่อด้วย ', 'Continue with ')) ?><?= e($item['name']) ?></span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
      <p class="social-auth-privacy"><?= e(t('ระบบจะใช้ข้อมูลบัญชีพื้นฐานเพื่อสร้างหรือเข้าสู่บัญชีของคุณ โปรดอ่าน', 'We use basic profile data to create or access your account. Please see our ')) ?><a href="privacy.php"><?= e(t('นโยบายความเป็นส่วนตัว', 'Privacy Policy')) ?></a></p>
    </div>
    <?php
}

function oauth_http(string $url, ?array $form = null, array $headers = []): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('เซิร์ฟเวอร์ยังไม่ได้เปิดใช้งาน cURL');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException('ไม่สามารถยืนยันตัวตนกับผู้ให้บริการได้');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('ได้รับข้อมูลยืนยันตัวตนไม่ถูกต้อง');
    return $decoded;
}

function oauth_der_length(int $length): string
{
    if ($length < 128) return chr($length);
    $bytes = '';
    while ($length > 0) { $bytes = chr($length & 0xff) . $bytes; $length >>= 8; }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function oauth_der_integer(string $value): string
{
    $value = ltrim($value, "\0");
    if ($value === '' || (ord($value[0]) & 0x80)) $value = "\0" . $value;
    return "\x02" . oauth_der_length(strlen($value)) . $value;
}

function oauth_jwt_payload(string $jwt, string $issuer, string $audience, ?string $nonce = null): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new RuntimeException('Token จากผู้ให้บริการไม่ถูกต้อง');
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($header) || !is_array($claims)) throw new RuntimeException('รูปแบบ Token ไม่รองรับ');
    return $claims;
}

function oauth_fail(string $message): never
{
    start_session();
    $_SESSION['oauth_error'] = $message;
    redirect('login.php');
}

function oauth_finish_login(array $user, string $returnTo): never
{
    db()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')
        ->execute([(int)$user['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    if ($user['role'] === 'admin') {
        setcookie('no_track', '1', ['expires' => time() + 2592000, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    } else {
        setcookie('no_track', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
    $adminReturnTargets = array_map(
        static fn(string $page): string => 'admin/' . $page,
        ['index.php', 'analytics.php', 'pdf-report.php', 'category-images.php', 'featured.php', 'projects.php', 'add.php', 'edit.php', 'view.php', 'guestbook.php', 'cookie_stats.php', 'dashboard.php', 'oauth.php']
    );
    $isAdminReturn = in_array($returnTo, $adminReturnTargets, true)
        || preg_match('#^admin/(?:edit|view)\.php\?id=[1-9][0-9]{0,9}$#D', $returnTo);
    $destination = in_array($returnTo, ['submit.php', 'submit-project.php'], true)
        ? $returnTo
        : ($user['role'] === 'admin'
            ? ($isAdminReturn ? $returnTo : 'admin/index.php')
            : 'dashboard.php');
    redirect($destination);
}

function oauth_unique_username(PDO $pdo, string $email, string $provider): string
{
    $base = strtolower((string)strtok($email, '@'));
    $base = preg_replace('/[^a-z0-9_.-]/', '', $base) ?: ($provider . '_user');
    $base = substr($base, 0, 32);
    do {
        $username = $base . '_' . bin2hex(random_bytes(3));
        $check = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $check->execute([$username]);
    } while ($check->fetchColumn());
    return $username;
}

function oauth_get_or_create_user(PDO $pdo, string $provider, string $subject, string $email, string $name, bool $emailVerified, string $lineId = ''): array
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_identities (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        provider VARCHAR(20) NOT NULL,
        provider_subject VARCHAR(255) NOT NULL,
        email VARCHAR(190) NOT NULL DEFAULT '',
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        avatar_url VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_provider_subject (provider, provider_subject),
        KEY idx_oauth_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ถ้าไม่มีอีเมล (เช่น LINE บางบัญชีไม่ได้ผูกอีเมล) ให้สร้างเป็น unique identifier
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower($provider) . '_' . substr(md5($subject), 0, 12) . '@kasa.local';
    }

    $find = $pdo->prepare('SELECT u.id, u.username, u.role, u.full_name, u.line_id FROM oauth_identities oi JOIN users u ON u.id = oi.user_id WHERE oi.provider = ? AND oi.provider_subject = ? LIMIT 1');
    $find->execute([$provider, $subject]);
    $user = $find->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        if ($lineId !== '' && empty($user['line_id'])) {
            $pdo->prepare('UPDATE users SET line_id = ? WHERE id = ?')->execute([$lineId, $user['id']]);
        }
        return $user;
    }

    $existing = $pdo->prepare('SELECT id, username, role, line_id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    $existingUser = $existing->fetch(PDO::FETCH_ASSOC);
    if ($existingUser) {
        $link = $pdo->prepare('INSERT INTO oauth_identities (user_id, provider, provider_subject, email, display_name) VALUES (?, ?, ?, ?, ?)');
        $link->execute([(int)$existingUser['id'], $provider, $subject, $email, mb_substr($name, 0, 120)]);
        if ($lineId !== '' && empty($existingUser['line_id'])) {
            $pdo->prepare('UPDATE users SET line_id = ? WHERE id = ?')->execute([$lineId, $existingUser['id']]);
        }
        return $existingUser;
    }

    $username = oauth_unique_username($pdo, $email, $provider);
    $fullName = trim($name) ?: $username;
    $fakePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $finalLineId = $lineId !== '' ? $lineId : ($provider === 'line' ? $username : '');

    $create = $pdo->prepare('INSERT INTO users (email, username, full_name, line_id, privacy_accepted_at, password_hash, role) VALUES (?, ?, ?, ?, NOW(), ?, "user")');
    $create->execute([$email, $username, mb_substr($fullName, 0, 120), $finalLineId, $fakePassword]);
    $userId = (int)$pdo->lastInsertId();

    $link = $pdo->prepare('INSERT INTO oauth_identities (user_id, provider, provider_subject, email, display_name) VALUES (?, ?, ?, ?, ?)');
    $link->execute([$userId, $provider, $subject, $email, mb_substr($fullName, 0, 120)]);

    return ['id' => $userId, 'username' => $username, 'role' => 'user'];
}
