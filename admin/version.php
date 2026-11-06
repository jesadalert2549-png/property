<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin('settings', 'manage');

$versionFile = dirname(__DIR__) . '/version.json';
$message = '';
$errors = [];

// ฟังก์ชันบันทึกเวอร์ชันและเคลียร์แคชในหน่วยความจำทันที
function save_system_version(string $versionFile, string $newVersion): bool
{
    $payload = json_encode([
        'version'    => $newVersion,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['username'] ?? 'admin',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $ok = @file_put_contents($versionFile, $payload) !== false;
    if ($ok && function_exists('current_site_version')) {
        current_site_version(true);
    }
    return $ok;
}

if (request_is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    // 1) บันทึกผ่าน 3 ช่องตัวเลข (Major, Minor, Patch) อิสระ
    if ($action === 'set_parts') {
        $maj = max(0, (int)($_POST['major'] ?? 0));
        $min = max(0, (int)($_POST['minor'] ?? 0));
        $pat = max(0, (int)($_POST['patch'] ?? 0));
        $new = sprintf('v.%d.%d.%d', $maj, $min, $pat);

        if (save_system_version($versionFile, $new)) {
            $message = 'บันทึกเวอร์ชันเป็น ' . $new . ' เรียบร้อยแล้ว (อัปเดตทุกหน้าอัตโนมัติ)';
        } else {
            $errors[] = 'ไม่สามารถเขียนไฟล์ version.json ได้ กรุณาตรวจสอบสิทธิ์ของไฟล์';
        }
    }
    // 2) บันทึกผ่านช่องพิมพ์ (รองรับทั้ง 3.10.10 และ v.3.10.10)
    elseif ($action === 'set') {
        $raw = trim((string)($_POST['version'] ?? ''));
        if (preg_match('/^v?\.?\s*(\d+)\.(\d+)\.(\d+)$/i', $raw, $m)) {
            $new = sprintf('v.%d.%d.%d', (int)$m[1], (int)$m[2], (int)$m[3]);
            if (save_system_version($versionFile, $new)) {
                $message = 'บันทึกเวอร์ชันเป็น ' . $new . ' เรียบร้อยแล้ว (อัปเดตทุกหน้าอัตโนมัติ)';
            } else {
                $errors[] = 'ไม่สามารถเขียนไฟล์ version.json ได้ กรุณาตรวจสอบสิทธิ์ของไฟล์';
            }
        } else {
            $errors[] = 'กรุณาใส่ตัวเลขเวอร์ชัน เช่น 3.10.10 หรือ v.3.10.10';
        }
    }
    // 3) เพิ่ม/ลด ทีละ 1 แบบอิสระ ไม่รีค่าตัวเลขอื่นเป็น 0
    elseif ($action === 'bump') {
        $which = (string)($_POST['which'] ?? 'patch');
        $direction = ($_POST['direction'] ?? 'up') === 'down' ? 'down' : 'up';
        $currentVer = current_site_version(true);

        if (!preg_match('/^v\.(\d+)\.(\d+)\.(\d+)$/', $currentVer, $vm)) {
            $vm = [0, 3, 10, 10];
        }
        [$major, $minor, $patch] = [(int)$vm[1], (int)$vm[2], (int)$vm[3]];

        if ($which === 'major') {
            $major = $direction === 'up' ? ($major + 1) : max(0, $major - 1);
        } elseif ($which === 'minor') {
            $minor = $direction === 'up' ? ($minor + 1) : max(0, $minor - 1);
        } elseif ($which === 'patch') {
            $patch = $direction === 'up' ? ($patch + 1) : max(0, $patch - 1);
        }

        $new = sprintf('v.%d.%d.%d', $major, $minor, $patch);
        if (save_system_version($versionFile, $new)) {
            $message = ($direction === 'up' ? 'เพิ่ม' : 'ลด') . ' ' . ucfirst($which) . ' เป็น ' . $new . ' เรียบร้อยแล้ว';
        } else {
            $errors[] = 'ไม่สามารถบันทึกไฟล์ version.json ได้';
        }
    } else {
        $errors[] = 'คำสั่งไม่ถูกต้อง';
    }
}

$current = current_site_version(true);
$parts = [3, 10, 10];
if (preg_match('/^v\.(\d+)\.(\d+)\.(\d+)$/', $current, $vm)) {
    $parts = [(int)$vm[1], (int)$vm[2], (int)$vm[3]];
}
[$vMajor, $vMinor, $vPatch] = $parts;
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการเวอร์ชันระบบ | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .version-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 30px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.3); margin-bottom:22px; }
    .version-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .version-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }
    .version-badge { display:inline-flex; align-items:center; gap:10px; padding:12px 22px; border-radius:999px; background:rgba(197,160,89,.15); border:1px solid rgba(197,160,89,.45); }
    .version-badge .dot { width:9px; height:9px; border-radius:50%; background:#C5A059; box-shadow:0 0 12px #C5A059; flex:0 0 9px; }
    .version-badge strong { font-family:"JetBrains Mono","SF Mono",Consolas,monospace; font-size:22px; font-weight:700; letter-spacing:.05em; color:#E5CB96; }

    /* Number Editor Section */
    .v-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:28px 30px; margin-bottom:22px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .v-card h2 { margin:0 0 6px; color:#0B1C33; font-size:18px; }
    .v-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; }

    .v-editor-grid { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:12px; margin:20px 0 24px; padding:20px; background:#F9F9FB; border:1px solid #E2E8F0; border-radius:16px; }
    .v-segment-box { display:flex; flex-direction:column; align-items:center; gap:8px; }
    .v-segment-label { font-size:11.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
    .v-stepper-control { display:inline-flex; align-items:center; gap:6px; background:#fff; padding:4px; border:1px solid #cbd5e1; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
    .v-btn-step { width:38px; height:38px; border-radius:9px; border:1px solid #e2e8f0; background:#F9F5EC; color:#A8843F; font-size:18px; font-weight:800; cursor:pointer; display:grid; place-items:center; transition:all .18s ease; }
    .v-btn-step:hover { background:#C5A059; color:#0B1C33; transform:scale(1.05); }
    .v-btn-step:active { transform:scale(0.95); }
    .v-num-input { width:68px; height:38px; border:none; background:transparent; font-family:"JetBrains Mono","SF Mono",Consolas,monospace; font-size:22px; font-weight:800; text-align:center; color:#0B1C33; outline:none; -moz-appearance:textfield; }
    .v-num-input::-webkit-outer-spin-button, .v-num-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .v-dot-separator { font-family:"JetBrains Mono","SF Mono",monospace; font-size:28px; font-weight:900; color:#94A3B8; margin-top:22px; user-select:none; }

    .btn-save-main { display:inline-flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:48px; border:0; border-radius:12px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 6px 18px rgba(197,160,89,.35); transition:transform .18s, box-shadow .18s; }
    .btn-save-main:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(197,160,89,.45); }

    /* Quick One-Click Bumps */
    .quick-bumps-title { margin:24px 0 12px; font-size:14px; font-weight:700; color:#334155; }
    .quick-bumps-row { display:flex; flex-wrap:wrap; gap:10px; }
    .quick-bump-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 15px; border-radius:10px; border:1px solid #cbd5e1; background:#fff; color:#0B1C33; font:inherit; font-size:13px; font-weight:700; cursor:pointer; transition:all .18s ease; }
    .quick-bump-btn:hover { border-color:#C5A059; background:#F9F5EC; color:#A8843F; transform:translateY(-1px); }

    /* Direct Text Row */
    .set-row { display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-top:24px; padding-top:22px; border-top:1px dashed #dfe6ec; }
    .set-row label { display:grid; gap:6px; font-size:13px; font-weight:700; color:#34495d; flex:1 1 240px; }
    .set-row input { min-height:46px; padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-family:"JetBrains Mono","SF Mono",Consolas,monospace; letter-spacing:.04em; }
    .set-row input:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); }
    .set-row .btn-set { border:0; border-radius:10px; min-height:46px; padding:0 22px; background:#0B1C33; color:#fff; font:inherit; font-weight:800; cursor:pointer; transition:background .18s; }
    .set-row .btn-set:hover { background:#182C48; }

    .alert-ok { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#effaf3; border:1px solid #bbebcd; color:#187243; font-size:14px; font-weight:600; }
    .alert-err { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:14px; font-weight:600; }

    .stamp-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
    .stamp-card { background:#fff; border:1px solid #e3e9ef; border-radius:14px; padding:15px 17px; font-size:13px; }
    .stamp-card strong { display:block; color:#0B1C33; font-size:13.5px; margin-bottom:5px; }
    .stamp-card span { color:#64748b; line-height:1.55; }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 860px); margin:38px auto 70px;">
  <div class="version-hero">
    <div>
      <span class="eyebrow">SYSTEM BUILD CONTROL</span>
      <h1>จัดการเวอร์ชันระบบ</h1>
      <p style="margin:0;color:rgba(255,255,255,.75);font-size:13.5px;">เปลี่ยนเวอร์ชันแล้ว footer ทุกหน้าของเว็บไซต์ + แดชบอร์ดสมาชิก + หลังบ้านจะอัปเดตทันที</p>
    </div>
    <div class="version-badge"><span class="dot"></span><strong><?= e($current) ?></strong></div>
  </div>

  <?php if ($message): ?><div class="alert-ok">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert-err">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <section class="v-card">
    <h2>🎛️ ปรับตัวเลขเวอร์ชัน (อิสระทุกช่อง)</h2>
    <p class="intro">ใส่แค่ตัวเลขลงในแต่ละช่องได้โดยตรง หรือกดปุ่ม ＋ / − เพื่อปรับทีละช่องได้อย่างอิสระโดยไม่กระทบช่องอื่น</p>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="set_parts">

      <div class="v-editor-grid">
        <!-- Major -->
        <div class="v-segment-box">
          <span class="v-segment-label" style="color:#c64e42;">Major</span>
          <div class="v-stepper-control">
            <button type="button" class="v-btn-step" onclick="stepNumber('vMaj', -1)" title="ลด Major">−</button>
            <input type="number" id="vMaj" name="major" min="0" value="<?= $vMajor ?>" class="v-num-input" aria-label="Major version">
            <button type="button" class="v-btn-step" onclick="stepNumber('vMaj', 1)" title="เพิ่ม Major">＋</button>
          </div>
        </div>

        <span class="v-dot-separator">.</span>

        <!-- Minor -->
        <div class="v-segment-box">
          <span class="v-segment-label" style="color:#0B1C33;">Minor</span>
          <div class="v-stepper-control">
            <button type="button" class="v-btn-step" onclick="stepNumber('vMin', -1)" title="ลด Minor">−</button>
            <input type="number" id="vMin" name="minor" min="0" value="<?= $vMinor ?>" class="v-num-input" aria-label="Minor version">
            <button type="button" class="v-btn-step" onclick="stepNumber('vMin', 1)" title="เพิ่ม Minor">＋</button>
          </div>
        </div>

        <span class="v-dot-separator">.</span>

        <!-- Patch -->
        <div class="v-segment-box">
          <span class="v-segment-label" style="color:#A8843F;">Patch</span>
          <div class="v-stepper-control">
            <button type="button" class="v-btn-step" onclick="stepNumber('vPat', -1)" title="ลด Patch">−</button>
            <input type="number" id="vPat" name="patch" min="0" value="<?= $vPatch ?>" class="v-num-input" aria-label="Patch version">
            <button type="button" class="v-btn-step" onclick="stepNumber('vPat', 1)" title="เพิ่ม Patch">＋</button>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-save-main">💾 บันทึกเวอร์ชันนี้</button>
    </form>

    <!-- Quick 1-Click Bumps -->
    <div class="quick-bumps-title">⚡ หรือกดปุ่มลัด (เพิ่ม / ลด ทันทีในคลิกเดียว):</div>
    <div class="quick-bumps-row">
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="patch">
        <button type="submit" class="quick-bump-btn" title="Patch +1">＋ Patch (+1)</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="patch">
        <input type="hidden" name="direction" value="down">
        <button type="submit" class="quick-bump-btn" title="Patch -1">− Patch (-1)</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="minor">
        <button type="submit" class="quick-bump-btn" title="Minor +1">＋ Minor (+1)</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="minor">
        <input type="hidden" name="direction" value="down">
        <button type="submit" class="quick-bump-btn" title="Minor -1">− Minor (-1)</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="major">
        <button type="submit" class="quick-bump-btn" title="Major +1">＋ Major (+1)</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bump">
        <input type="hidden" name="which" value="major">
        <input type="hidden" name="direction" value="down">
        <button type="submit" class="quick-bump-btn" title="Major -1">− Major (-1)</button>
      </form>
    </div>

    <!-- Freeform Text Input -->
    <form method="post" class="set-row">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="set">
      <label>หรือพิมพ์ทั้งชุดรวดเดียว (ใส่แค่ตัวเลขได้ เช่น 3.10.10 หรือ v.3.10.10)
        <input type="text" name="version" placeholder="3.10.10 หรือ v.3.10.10" required autocomplete="off">
      </label>
      <button type="submit" class="btn-set">บันทึก</button>
    </form>
  </section>

  <section class="v-card">
    <h2>🌐 จุดที่เวอร์ชันแสดงอัตโนมัติ</h2>
    <p class="intro">เปลี่ยนตัวเลขที่นี่ครั้งเดียว ทุกจุดต่อไปนี้จะเชื่อมโยงและแสดงเวอร์ชันใหม่ทันที</p>
    <div class="stamp-list">
      <div class="stamp-card"><strong>🌐 Footer เว็บไซต์</strong><span>มุมขวาล่างของทุกหน้าสาธารณะ (ถัดจากลิขสิทธิ์)</span></div>
      <div class="stamp-card"><strong>👤 แดชบอร์ดสมาชิก</strong><span>การ์ดสถานะท้ายแดชบอร์ด dashboard.php</span></div>
      <div class="stamp-card"><strong>🛠️ Sidebar หลังบ้าน</strong><span>แคปซูลทองมุมล่างของเมนู admin (_sidebar.php)</span></div>
      <div class="stamp-card"><strong>📄 หน้าติดตั้ง / โครงการ</strong><span>install.php และ submit-project.php</span></div>
    </div>
  </section>
</main>

<script>
function stepNumber(id, delta) {
  var el = document.getElementById(id);
  if (!el) return;
  var current = parseInt(el.value, 10);
  if (isNaN(current)) current = 0;
  var next = Math.max(0, current + delta);
  el.value = next;
}
</script>
</body>
</html>
