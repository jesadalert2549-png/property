<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/oauth.php';
require_admin();

$configFile = dirname(__DIR__) . '/oauth_config.json';
$message = '';
$errors = [];

// โหลดการตั้งค่าปัจจุบัน
$config = [];
if (is_file($configFile)) {
    $decoded = json_decode((string)file_get_contents($configFile), true);
    if (is_array($decoded)) $config = $decoded;
}

// ค่าตั้งต้นถ้ายังไม่มี
$defaults = [
    'line' => ['enabled' => true, 'sandbox' => true, 'channel_id' => '', 'channel_secret' => ''],
    'google' => ['enabled' => true, 'sandbox' => false, 'client_id' => '', 'client_secret' => ''],
    'facebook' => ['enabled' => true, 'sandbox' => false, 'client_id' => '', 'client_secret' => ''],
];
foreach ($defaults as $k => $v) {
    if (!isset($config[$k]) || !is_array($config[$k])) {
        $config[$k] = $v;
    }
}

if (request_is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        // LINE
        $config['line']['enabled'] = !empty($_POST['line_enabled']);
        $config['line']['sandbox'] = !empty($_POST['line_sandbox']);
        $config['line']['channel_id'] = trim((string)($_POST['line_channel_id'] ?? ''));
        $config['line']['channel_secret'] = trim((string)($_POST['line_channel_secret'] ?? ''));

        // Google
        $config['google']['enabled'] = !empty($_POST['google_enabled']);
        $config['google']['sandbox'] = !empty($_POST['google_sandbox']);
        $config['google']['client_id'] = trim((string)($_POST['google_client_id'] ?? ''));
        $config['google']['client_secret'] = trim((string)($_POST['google_client_secret'] ?? ''));

        // Facebook
        $config['facebook']['enabled'] = !empty($_POST['facebook_enabled']);
        $config['facebook']['sandbox'] = !empty($_POST['facebook_sandbox']);
        $config['facebook']['client_id'] = trim((string)($_POST['facebook_client_id'] ?? ''));
        $config['facebook']['client_secret'] = trim((string)($_POST['facebook_client_secret'] ?? ''));

        $saved = @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($saved !== false) {
            $message = 'บันทึกการตั้งค่า Social Login (LINE / Google / Facebook) เรียบร้อยแล้ว';
        } else {
            $errors[] = 'ไม่สามารถบันทึกไฟล์ oauth_config.json ได้ กรุณาตรวจสอบสิทธิ์ของไฟล์';
        }
    }
}

// สถิติสมาชิกที่เชื่อมต่อ Social Login
$pdo = db();
$socialUsers = [];
try {
    $socialUsers = $pdo->query(
        "SELECT oi.provider, oi.provider_subject, oi.email AS oauth_email, oi.created_at AS linked_at,
                u.id AS user_id, u.username, u.full_name, u.line_id, u.last_login_at
         FROM oauth_identities oi
         JOIN users u ON u.id = oi.user_id
         ORDER BY oi.created_at DESC
         LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    // Table may be empty
}

$token = csrf_token();
$lineCallback = oauth_callback_url('line');
$googleCallback = oauth_callback_url('google');
$facebookCallback = oauth_callback_url('facebook');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการระบบ Social Login (LINE / Google / Facebook) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .oauth-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 30px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.3); margin-bottom:22px; }
    .oauth-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .oauth-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .oauth-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:28px 30px; margin-bottom:22px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .oauth-card h2 { margin:0 0 6px; color:#0B1C33; font-size:18px; }
    .oauth-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; }

    .provider-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:18px; margin-bottom:24px; }
    .provider-box { border:1px solid #e2e8f0; border-radius:16px; padding:20px; background:#fff; display:flex; flex-direction:column; gap:14px; position:relative; }
    .provider-box.active-line { border-color:#06C755; box-shadow:0 6px 18px rgba(6,199,85,.12); }
    .provider-box.active-google { border-color:#4285F4; box-shadow:0 6px 18px rgba(66,133,244,.12); }
    .provider-box.active-facebook { border-color:#1877F2; box-shadow:0 6px 18px rgba(24,119,242,.12); }

    .provider-head { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .provider-brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:16px; }
    .provider-badge-line { color:#06C755; }
    .provider-badge-google { color:#4285F4; }
    .provider-badge-facebook { color:#1877F2; }

    .provider-toggle-label { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#334155; cursor:pointer; }
    .provider-toggle-label input[type="checkbox"] { width:18px; height:18px; accent-color:#0B1C33; cursor:pointer; }

    .sandbox-tag { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:800; background:#FFF8E6; border:1px solid #F5D480; color:#9C7B39; }

    .field-group { display:grid; gap:6px; font-size:12.5px; font-weight:700; color:#475569; }
    .field-group input { width:100%; min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:9px; font:inherit; font-size:13px; font-family:"JetBrains Mono","SF Mono",monospace; color:#0B1C33; background:#F9F9FB; }
    .field-group input:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); background:#fff; }

    .callback-box { background:#F8FAFC; border:1px dashed #cbd5e1; border-radius:10px; padding:10px 12px; font-size:11.5px; }
    .callback-box strong { display:block; color:#475569; margin-bottom:4px; }
    .callback-box code { display:block; word-break:break-all; font-family:monospace; color:#0B1C33; background:#fff; padding:6px 8px; border-radius:6px; border:1px solid #e2e8f0; }

    .btn-save-oauth { display:inline-flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:48px; border:0; border-radius:12px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 6px 18px rgba(197,160,89,.35); transition:transform .18s, box-shadow .18s; }
    .btn-save-oauth:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(197,160,89,.45); }

    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:13.5px; }
    th, td { text-align:left; padding:12px 14px; border-bottom:1px solid #eef2f6; }
    th { font-weight:700; color:#475569; background:#F8FAFC; }

    .provider-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:800; }
    .provider-pill.line { background:#E8FDF0; color:#04A747; border:1px solid #06C755; }
    .provider-pill.google { background:#EBF3FE; color:#1A73E8; border:1px solid #4285F4; }
    .provider-pill.facebook { background:#EAF3FF; color:#1877F2; border:1px solid #1877F2; }

    .alert-ok { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#effaf3; border:1px solid #bbebcd; color:#187243; font-size:14px; font-weight:600; }
    .alert-err { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:14px; font-weight:600; }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1050px); margin:38px auto 70px;">
  <div class="oauth-hero">
    <div>
      <span class="eyebrow">IDENTITY & SOCIAL ACCESS</span>
      <h1>จัดการระบบ Social Login (LINE / Google / Facebook)</h1>
      <p style="margin:0;color:rgba(255,255,255,.75);font-size:13.5px;">เปิดใช้งาน เข้าสู่ระบบได้ง่ายในคลิกเดียว บันทึกข้อมูลลงฐานข้อมูลและเชื่อมโยงบัญชีอัตโนมัติ</p>
    </div>
  </div>

  <?php if ($message): ?><div class="alert-ok">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert-err">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="save">

    <section class="oauth-card">
      <h2>🔌 ตั้งค่าช่องทางเข้าสู่ระบบ (API Providers)</h2>
      <p class="intro">
        💡 <strong>โหมดทดสอบ (Sandbox Mode):</strong> ช่วยให้ผู้ใช้และแอดมินคลิกเดียวเข้าสู่ระบบได้ทันทีสำหรับทดสอบระบบ แม้จะยังไม่ได้ขอ App ID หรือ Channel จากผู้ให้บริการ<br>
        เมื่อคุณใส่ Channel ID / Secret หรือ Client ID จาก LINE Developers / Google Cloud ระบบจะเชื่อมต่อกับ API ทางการจริงอัตโนมัติ
      </p>

      <div class="provider-grid">
        <!-- LINE -->
        <div class="provider-box <?= !empty($config['line']['enabled']) ? 'active-line' : '' ?>">
          <div class="provider-head">
            <span class="provider-brand provider-badge-line">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="#06C755"><path d="M19.365 9.864c0-4.043-3.906-7.332-8.706-7.332-4.8 0-8.706 3.289-8.706 7.332 0 3.623 3.102 6.671 7.294 7.234.284.061.67.188.767.432.088.22.057.564.028.786l-.121.729c-.037.225-.173.88.77.48 1.045-.443 5.641-3.322 7.7-5.688 1.488-1.708 2.074-3.467 2.074-4.973z"/><path fill="#FFF" d="M8.532 12.016H6.965a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v2.616h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44zm1.884-.44a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.902 0a.44.44 0 0 1-.373.22.44.44 0 0 1-.362-.187l-1.57-2.18v1.927a.44.44 0 0 1-.44.44.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .373.22l1.558 2.164V8.52a.44.44 0 0 1 .44-.44.44.44 0 0 1 .44.44v3.056zm3.323-1.928h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.567a.44.44 0 0 1-.44-.44V8.52a.44.44 0 0 1 .44-.44h1.567a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44h-1.127v.648h1.127a.44.44 0 0 1 .44.44.44.44 0 0 1-.44.44z"/></svg>
              LINE Login
            </span>
            <label class="provider-toggle-label">
              <input type="checkbox" name="line_enabled" value="1" <?= !empty($config['line']['enabled']) ? 'checked' : '' ?>>
              เปิดใช้งาน
            </label>
          </div>

          <label class="provider-toggle-label" style="font-size:12px;">
            <input type="checkbox" name="line_sandbox" value="1" <?= !empty($config['line']['sandbox']) ? 'checked' : '' ?>>
            <span class="sandbox-tag">🧪 โหมดทดสอบ (คลิกเดียวเข้าสู่ระบบทันที)</span>
          </label>

          <div class="field-group">
            <span>LINE Channel ID (จาก LINE Developers)</span>
            <input type="text" name="line_channel_id" value="<?= e($config['line']['channel_id'] ?? '') ?>" placeholder="เช่น 2007894561">
          </div>

          <div class="field-group">
            <span>LINE Channel Secret</span>
            <input type="password" name="line_channel_secret" value="<?= e($config['line']['channel_secret'] ?? '') ?>" placeholder="••••••••••••••••••••••••">
          </div>

          <div class="callback-box">
            <strong>LINE Callback URL (นำไปใส่ใน LINE Developers Console):</strong>
            <code><?= e($lineCallback) ?></code>
          </div>
        </div>

        <!-- Google -->
        <div class="provider-box <?= !empty($config['google']['enabled']) ? 'active-google' : '' ?>">
          <div class="provider-head">
            <span class="provider-brand provider-badge-google">
              <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
              Google
            </span>
            <label class="provider-toggle-label">
              <input type="checkbox" name="google_enabled" value="1" <?= !empty($config['google']['enabled']) ? 'checked' : '' ?>>
              เปิดใช้งาน
            </label>
          </div>

          <label class="provider-toggle-label" style="font-size:12px;">
            <input type="checkbox" name="google_sandbox" value="1" <?= !empty($config['google']['sandbox']) ? 'checked' : '' ?>>
            <span class="sandbox-tag">🧪 โหมดทดสอบ (คลิกเดียวเข้าสู่ระบบทันที)</span>
          </label>

          <div class="field-group">
            <span>Google Client ID (จาก Google Cloud Console)</span>
            <input type="text" name="google_client_id" value="<?= e($config['google']['client_id'] ?? '') ?>" placeholder="xxx.apps.googleusercontent.com">
          </div>

          <div class="field-group">
            <span>Google Client Secret</span>
            <input type="password" name="google_client_secret" value="<?= e($config['google']['client_secret'] ?? '') ?>" placeholder="••••••••••••••••••••••••">
          </div>

          <div class="callback-box">
            <strong>Google Redirect URI (นำไปใส่ใน Credentials):</strong>
            <code><?= e($googleCallback) ?></code>
          </div>
        </div>

        <!-- Facebook -->
        <div class="provider-box <?= !empty($config['facebook']['enabled']) ? 'active-facebook' : '' ?>">
          <div class="provider-head">
            <span class="provider-brand provider-badge-facebook">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              Facebook
            </span>
            <label class="provider-toggle-label">
              <input type="checkbox" name="facebook_enabled" value="1" <?= !empty($config['facebook']['enabled']) ? 'checked' : '' ?>>
              เปิดใช้งาน
            </label>
          </div>

          <label class="provider-toggle-label" style="font-size:12px;">
            <input type="checkbox" name="facebook_sandbox" value="1" <?= !empty($config['facebook']['sandbox']) ? 'checked' : '' ?>>
            <span class="sandbox-tag">🧪 โหมดทดสอบ (คลิกเดียวเข้าสู่ระบบทันที)</span>
          </label>

          <div class="field-group">
            <span>Facebook App ID (จาก Meta Developers)</span>
            <input type="text" name="facebook_client_id" value="<?= e($config['facebook']['client_id'] ?? '') ?>" placeholder="เช่น 1828358914832406">
          </div>

          <div class="field-group">
            <span>Facebook App Secret</span>
            <input type="password" name="facebook_client_secret" value="<?= e($config['facebook']['client_secret'] ?? '') ?>" placeholder="••••••••••••••••••••••••">
          </div>

          <div class="callback-box">
            <strong>Facebook Valid OAuth Redirect URI:</strong>
            <code><?= e($facebookCallback) ?></code>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-save-oauth">💾 บันทึกการตั้งค่า Social Login ทั้งหมด</button>
    </section>
  </form>

  <!-- สถิติและรายการผู้ใช้ที่เชื่อมต่อ Social Login -->
  <section class="oauth-card">
    <h2>👥 ผู้ใช้งานที่ลงทะเบียนผ่าน Social Login (ในฐานข้อมูล)</h2>
    <p class="intro">ข้อมูลสมาชิกทุกคนที่เข้าสู่ระบบด้วย LINE, Google หรือ Facebook จะถูกจัดเก็บลงตาราง <code>users</code> และ <code>oauth_identities</code> เรียบร้อย</p>

    <?php if (empty($socialUsers)): ?>
      <p style="color:#64748b; font-style:italic;">ยังไม่มีสมาชิกเข้าสู่ระบบด้วย Social Login</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ช่องทาง</th>
              <th>ชื่อ-นามสกุล</th>
              <th>ชื่อผู้ใช้ (Username)</th>
              <th>อีเมล</th>
              <th>LINE ID</th>
              <th>เข้าสู่ระบบล่าสุด</th>
              <th>วันที่เชื่อมต่อ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($socialUsers as $u): ?>
              <tr>
                <td>
                  <span class="provider-pill <?= e($u['provider']) ?>">
                    <?= $u['provider'] === 'line' ? '🟢 LINE' : ($u['provider'] === 'google' ? '🔴 Google' : '🔵 Facebook') ?>
                  </span>
                </td>
                <td><strong><?= e($u['full_name']) ?></strong></td>
                <td><code><?= e($u['username']) ?></code></td>
                <td><a href="mailto:<?= e($u['oauth_email'] ?: '-') ?>"><?= e($u['oauth_email'] ?: '-') ?></a></td>
                <td>
                  <?php if (!empty($u['line_id'])): ?>
                    <a href="https://line.me/R/ti/p/~<?= rawurlencode($u['line_id']) ?>" target="_blank" rel="noopener" style="color:#04A747; font-weight:700;">
                      <?= e($u['line_id']) ?> ↗
                    </a>
                  <?php else: ?>
                    <span style="color:#94a3b8;">-</span>
                  <?php endif; ?>
                </td>
                <td><?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '-' ?></td>
                <td><?= date('d/m/Y H:i', strtotime($u['linked_at'])) ?></td>
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
