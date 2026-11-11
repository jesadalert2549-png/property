<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/profile_schema.php';
require_admin();

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageMembers = $isFounder || staff_can(db(), $staffId, 'founder_only', 'manage');

$pdo = db();
ensure_profile_schema($pdo);
$message = '';
$errors = [];

// ดำเนินการ Action ต่างๆ
if (request_is_post()) {
    csrf_check();
    if (!$canManageMembers) {
        $errors[] = 'สิทธิ์ถูกจำกัด: เฉพาะ Founder & CEO เท่านั้นที่สามารถบริหารจัดการสิทธิ์สมาชิกได้';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        // 1. บันทึกปรับสิทธิ์สมาชิกเดี่ยว (Edit Member Permissions & Quota)
        if ($action === 'update_member') {
            $tier = in_array($_POST['tier'] ?? '', ['free', 'pro', 'vip', 'unlimited'], true) ? $_POST['tier'] : 'free';
            $maxListings = (int)($_POST['max_listings'] ?? 5);
            $canFeatured = !empty($_POST['can_post_featured']) ? 1 : 0;
            $canProject  = !empty($_POST['can_post_project']) ? 1 : 0;
            $isBanned    = !empty($_POST['is_banned']) ? 1 : 0;
            $banReason   = trim((string)($_POST['ban_reason'] ?? ''));
            $adminNotes  = trim((string)($_POST['admin_notes'] ?? ''));

            $stmt = $pdo->prepare(
                'UPDATE users SET tier = ?, max_listings = ?, can_post_featured = ?, can_post_project = ?, is_banned = ?, ban_reason = ?, admin_notes = ? WHERE id = ?'
            );
            $stmt->execute([$tier, $maxListings, $canFeatured, $canProject, $isBanned, $banReason, $adminNotes, $targetUserId]);

            $newPw = trim((string)($_POST['reset_password'] ?? ''));
            if ($newPw !== '') {
                if (strlen($newPw) < 6) {
                    $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
                } else {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($newPw, PASSWORD_DEFAULT), $targetUserId]);
                    $message = "อัปเดตสิทธิ์และรีเซ็ตรหัสผ่านใหม่ให้สมาชิก #{$targetUserId} สำเร็จแล้ว";
                }
            } else {
                $message = "อัปเดตสิทธิ์และโควตาสมาชิก #{$targetUserId} เรียบร้อยแล้ว";
            }
        }

        // 2. ปลดแบน / แบน สมาชิกด่วน (Quick Ban / Unban)
        if ($action === 'toggle_ban') {
            $cur = (int)$pdo->query("SELECT is_banned FROM users WHERE id = {$targetUserId}")->fetchColumn();
            $newBan = $cur ? 0 : 1;
            $banMsg = $newBan ? 'ระงับการใช้งานโดยผู้ดูแลระบบ' : null;
            $pdo->prepare('UPDATE users SET is_banned = ?, ban_reason = ? WHERE id = ?')->execute([$newBan, $banMsg, $targetUserId]);
            $message = $newBan ? "ระงับการใช้งานบัญชีสมาชิก #{$targetUserId} แล้ว" : "ปลดระงับบัญชี #{$targetUserId} เรียบร้อยแล้ว";
        }

        // 3. ปรับระดับแบบกลุ่ม (Bulk Apply Tier / Quota)
        if ($action === 'bulk_apply') {
            $selectedUsers = $_POST['selected_users'] ?? [];
            $bulkTier = $_POST['bulk_tier'] ?? '';
            $bulkQuota = (int)($_POST['bulk_quota'] ?? 5);

            if (!empty($selectedUsers) && is_array($selectedUsers)) {
                $placeholders = implode(',', array_fill(0, count($selectedUsers), '?'));
                $params = [$bulkTier, $bulkQuota, ...array_map('intval', $selectedUsers)];
                $pdo->prepare("UPDATE users SET tier = ?, max_listings = ? WHERE id IN ($placeholders)")->execute($params);
                $message = "ปรับโควตาและระดับสมาชิกแบบกลุ่มสำเร็จ (" . count($selectedUsers) . " บัญชี)";
            }
        }
    }
}

// ฟิลเตอร์และการแบ่งหน้า (รองรับสมาชิกเป็นหมื่นคนอย่างลื่นไหล Pagination)
$search = trim((string)($_GET['q'] ?? ''));
$filterTier = trim((string)($_GET['tier'] ?? ''));
$filterBanned = trim((string)($_GET['banned'] ?? ''));
$filterAgent = trim((string)($_GET['agent'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ["role = 'user'"];
$params = [];

if ($search !== '') {
    $where[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR phone LIKE ?)";
    $term = '%' . addcslashes($search, '%_\\') . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if ($filterTier !== '' && in_array($filterTier, ['free', 'pro', 'vip', 'unlimited'], true)) {
    $where[] = "tier = ?";
    $params[] = $filterTier;
}
if ($filterBanned === '1') {
    $where[] = "is_banned = 1";
} elseif ($filterBanned === '0') {
    $where[] = "is_banned = 0";
}
if ($filterAgent !== '') {
    $where[] = "agent_status = ?";
    $params[] = $filterAgent;
}

$whereSql = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
$countStmt->execute($params);
$totalMembers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalMembers / $perPage));

// ดึงข้อมูลสมาชิกพร้อมนับจำนวนประกาศจริงที่ลงไว้
$listSql = "SELECT u.*,
            (SELECT COUNT(*) FROM properties p WHERE p.user_id = u.id) AS property_count,
            (SELECT COUNT(*) FROM development_projects dp WHERE dp.owner_user_id = u.id) AS project_count
            FROM users u
            WHERE $whereSql
            ORDER BY u.id DESC
            LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สถิติรวมทั้งระบบ
$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$statFree  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND tier = 'free'")->fetchColumn();
$statPro   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND tier = 'pro'")->fetchColumn();
$statVip   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND tier IN ('vip', 'unlimited')")->fetchColumn();
$statBanned = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND is_banned = 1")->fetchColumn();

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ศูนย์บริหารจัดการสมาชิก & สิทธิ์โควตา (Member Command Center) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .member-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
      padding: 28px 32px;
      border-radius: 24px;
      background: linear-gradient(135deg,#0B1C33,#1a365d);
      color: #fff;
      border: 1px solid rgba(197,160,89,.35);
      margin-bottom: 26px;
      box-shadow: 0 14px 34px rgba(11,28,51,.12);
    }
    .member-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .member-hero h1 { margin:8px 0 4px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .stat-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 4px 14px rgba(11,28,51,.04); }
    .stat-box small { display: block; font-size: 11.5px; color: #64748B; font-weight: 700; text-transform: uppercase; }
    .stat-box strong { font-size: 26px; color: #0B1C33; font-weight: 800; margin-top: 2px; display: block; }

    .dash-card { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:24px 28px; margin-bottom:24px; box-shadow:0 8px 24px rgba(11,28,51,.04); }
    .tier-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    .tier-free { background:#f1f5f9; color:#475569; }
    .tier-pro  { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .tier-vip  { background:rgba(197,160,89,.15); color:#8C6B32; border:1px solid rgba(197,160,89,.4); }
    .tier-unlimited { background:#0B1C33; color:#E5CB96; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(11,28,51,.6); z-index:9999; place-items:center; backdrop-filter:blur(4px); padding:20px; }
    .modal-overlay.active { display:grid; }
    .modal-card { background:#fff; border-radius:20px; max-width:540px; width:100%; padding:26px 30px; box-shadow:0 20px 50px rgba(0,0,0,.2); max-height:90vh; overflow-y:auto; }

    .btn-gold {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 18px; border: 0; border-radius: 9px;
      background: linear-gradient(135deg,#C5A059,#A8843F); color: #0B1C33; font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; text-decoration: none;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1200px); margin:38px auto 70px;">

  <!-- Hero Header -->
  <div class="member-hero">
    <div>
      <span class="eyebrow"><?= e(t('MEMBER GOVERNANCE & QUOTA MANAGEMENT', 'MEMBER GOVERNANCE & QUOTA MANAGEMENT')) ?></span>
      <h1>👥 <?= e(t('ศูนย์บริหารจัดการสมาชิก & สิทธิ์โควตา', 'Member Command Center & Quota Governance')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">
        <?= e(t('ออกแบบเพื่อรองรับสมาชิกปริมาณมาก (Scalable) ควบคุมโควตาจำนวนประกาศสูงสุด กำหนดระดับสิทธิ์ (Free, Pro, VIP) และจัดการระงับ/เปิดใช้งานบัญชีได้แบบเบ็ดเสร็จ', 'Engineered to scale for large member volumes — govern maximum listing quotas, membership tiers (Free, Pro, VIP), and moderate account status.')) ?>
      </p>
    </div>
    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
      <span style="font-size:12px; color:#E5CB96; background:rgba(255,255,255,.08); padding:5px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.14);">
        👑 <?= e(t('เอกสิทธิ์การปรับสิทธิ์เฉพาะ Founder & CEO', 'Founder & CEO Exclusive Authority')) ?>
      </span>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success" style="margin-bottom:20px;">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error" style="margin-bottom:20px;">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- แถบสถิติสมาชิก -->
  <div class="stat-row">
    <div class="stat-box">
      <small><?= e(t('สมาชิกทั้งหมด', 'Total Members')) ?></small>
      <strong><?= number_format($statTotal) ?> <span style="font-size:14px; font-weight:normal;"><?= e(t('คน', 'users')) ?></span></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('Free Members (สมาชิกทั่วไป)', 'Free Members')) ?></small>
      <strong style="color:#475569;"><?= number_format($statFree) ?></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('Pro Members (โควตาสูง)', 'Pro Members')) ?></small>
      <strong style="color:#1d4ed8;"><?= number_format($statPro) ?></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('VIP / Unlimited', 'VIP / Unlimited')) ?></small>
      <strong style="color:#A8843F;"><?= number_format($statVip) ?></strong>
    </div>
    <div class="stat-box">
      <small><?= e(t('บัญชีถูกระงับ (Banned)', 'Suspended Accounts')) ?></small>
      <strong style="color:#b91c1c;"><?= number_format($statBanned) ?></strong>
    </div>
  </div>

  <!-- กล่องตารางสมาชิก พร้อมตัวกรองและการค้นหาความเร็วสูง -->
  <section class="dash-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <div>
        <h2 style="margin:0; font-size:18px; color:#0B1C33; font-weight:800;">
          <?= e(t('รายชื่อสมาชิกในระบบ (พบ ' . number_format($totalMembers) . ' รายการ)', 'System Member Directory (' . number_format($totalMembers) . ' users found)')) ?>
        </h2>
        <span style="font-size:12.5px; color:#64748B;">
          <?= e(t('คลิก "⚙️ ปรับสิทธิ์" เพื่อปรับโควตาประกาศ ระดับสมาชิก หรือระงับการใช้งาน', 'Click "⚙️ Permissions" to adjust listing quotas, tiers, or moderate account status.')) ?>
        </span>
      </div>
      <div style="font-size:12px; color:#64748B;">
        <?= e(t('หน้า', 'Page')) ?> <?= $page ?> <?= e(t('จาก', 'of')) ?> <?= $totalPages ?>
      </div>
    </div>

    <!-- ฟอร์มตัวกรองและค้นหา -->
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('ค้นหาชื่อ, อีเมล, เบอร์โทร หรือ username...', 'Search name, email, phone or username...')) ?>" style="min-height:38px; padding:6px 14px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px; min-width:260px;">
      <select name="tier" style="min-height:38px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px;">
        <option value=""><?= e(t('ทุกระดับ (Tier)', 'All Tiers')) ?></option>
        <option value="free" <?= $filterTier==='free'?'selected':'' ?>>Free Member</option>
        <option value="pro" <?= $filterTier==='pro'?'selected':'' ?>>Pro Member</option>
        <option value="vip" <?= $filterTier==='vip'?'selected':'' ?>>VIP Agent</option>
        <option value="unlimited" <?= $filterTier==='unlimited'?'selected':'' ?>>Unlimited</option>
      </select>
      <select name="banned" style="min-height:38px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px;">
        <option value=""><?= e(t('ทุกสถานะบัญชี', 'All Account Statuses')) ?></option>
        <option value="0" <?= $filterBanned==='0'?'selected':'' ?>><?= e(t('ปกติ (Active)', 'Active')) ?></option>
        <option value="1" <?= $filterBanned==='1'?'selected':'' ?>><?= e(t('ถูกระงับ (Banned)', 'Suspended (Banned)')) ?></option>
      </select>
      <button type="submit" style="min-height:38px; padding:0 16px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; font-weight:700; cursor:pointer;">
        ⌕ <?= e(t('ค้นหา', 'Search')) ?>
      </button>
      <?php if ($search !== '' || $filterTier !== '' || $filterBanned !== ''): ?>
        <a href="members.php" class="btn" style="min-height:38px; padding:8px 14px; font-size:12.5px; background:#f1f5f9; border:1px solid #cbd5e1; text-decoration:none; color:#475569;">
          <?= e(t('ล้างตัวกรอง', 'Clear filters')) ?>
        </a>
      <?php endif; ?>
    </form>

    <!-- ตารางรายชื่อสมาชิก -->
    <?php if (!$members): ?>
      <p style="text-align:center; padding:36px; background:#f8fafc; border-radius:14px; color:#64748B;">
        <?= e(t('ไม่พบรายชื่อสมาชิกที่ตรงกับเงื่อนไขการค้นหา', 'No members matched your search criteria.')) ?>
      </p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#f8fafc; text-align:left; border-bottom:2px solid #e2e8f0;">
              <th style="padding:10px 12px;"><?= e(t('สมาชิก', 'Member')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('ระดับ (Tier)', 'Tier')) ?></th>
              <th style="padding:10px 12px; text-align:center;"><?= e(t('โควตาประกาศ', 'Quota')) ?></th>
              <th style="padding:10px 12px; text-align:center;"><?= e(t('ลงจริง (ทรัพย์/โครงการ)', 'Posted (Props/Projects)')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('สถานะบัญชี', 'Status')) ?></th>
              <th style="padding:10px 12px; text-align:right;"><?= e(t('การบริหารสิทธิ์', 'Actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <?php
                $isBan = !empty($m['is_banned']);
                $quota = (int)$m['max_listings'];
                $used = (int)$m['property_count'];
                $tierClass = 'tier-' . ($m['tier'] ?? 'free');
              ?>
              <tr style="border-bottom:1px solid #f1f5f9; background:<?= $isBan ? '#fff5f5' : '#fff' ?>;">
                <td style="padding:12px;">
                  <strong style="color:#0B1C33; font-size:13.5px;"><?= e($m['full_name'] ?: $m['username']) ?></strong>
                  <span style="display:block; font-size:11.5px; color:#64748B;">@<?= e($m['username']) ?> · <?= e($m['email']) ?></span>
                  <?php if (!empty($m['phone'])): ?>
                    <span style="display:block; font-size:11px; color:#94a3b8;">📞 <?= e($m['phone']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px;">
                  <span class="tier-badge <?= $tierClass ?>">
                    <?= e(strtoupper($m['tier'])) ?>
                  </span>
                  <?php if ($m['agent_status'] === 'verified'): ?>
                    <span style="display:block; font-size:10.5px; color:#15803d; font-weight:700; margin-top:3px;">
                      <?= e(t('✓ Agent รับรอง', '✓ Verified Agent')) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px; text-align:center;">
                  <strong style="font-size:14px; color:#0B1C33;">
                    <?= $quota < 0 ? e(t('∞ ไม่จำกัด', '∞ Unlimited')) : (number_format($quota) . ' ' . e(t('ประกาศ', 'listings'))) ?>
                  </strong>
                </td>
                <td style="padding:12px; text-align:center;">
                  <span style="font-weight:700; color:<?= ($quota >= 0 && $used >= $quota) ? '#b91c1c' : '#15803d' ?>;">
                    <?= number_format($used) ?>
                  </span>
                  <span style="font-size:11px; color:#64748B;">/ <?= $quota < 0 ? '∞' : $quota ?></span>
                  <?php if ($m['project_count'] > 0): ?>
                    <span style="display:block; font-size:10px; color:#6366f1;">(<?= $m['project_count'] ?> <?= e(t('โครงการ', 'projects')) ?>)</span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px;">
                  <?php if ($isBan): ?>
                    <span style="display:inline-block; padding:2px 8px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:800;">
                      🚫 <?= e(t('ถูกระงับ', 'Suspended')) ?>
                    </span>
                    <?php if (!empty($m['ban_reason'])): ?>
                      <span style="display:block; font-size:10.5px; color:#991b1b;"><?= e($m['ban_reason']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color:#15803d; font-weight:700; font-size:12px;">● <?= e(t('ปกติ', 'Active')) ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px; text-align:right;">
                  <div style="display:inline-flex; gap:6px; align-items:center;">
                    <button type="button" onclick="openMemberModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8') ?>)" class="btn" style="padding:6px 12px; font-size:12px; background:#f8fafc; border:1px solid #cbd5e1; cursor:pointer;">
                      ⚙️ <?= e(t('ปรับสิทธิ์', 'Permissions')) ?>
                    </button>
                    <?php if ($canManageMembers): ?>
                      <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('<?= $isBan ? e(t('ต้องการปลดระงับบัญชีนี้ใช่หรือไม่?', 'Unsuspend this account?')) : e(t('ต้องการระงับการใช้งานบัญชีนี้ใช่หรือไม่?', 'Suspend this account?')) ?>');">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="toggle_ban">
                        <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn" style="padding:6px 10px; font-size:12px; background:<?= $isBan ? '#dcfce7' : '#fee2e2' ?>; color:<?= $isBan ? '#15803d' : '#b91c1c' ?>; border:1px solid #fca5a5; cursor:pointer;">
                          <?= $isBan ? e(t('ปลดแบน', 'Unban')) : e(t('ระงับ', 'Suspend')) ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- ปุ่มเลื่อนหน้า (Pagination) -->
      <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; margin-top:24px;">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?p=<?= $p ?>&q=<?= urlencode($search) ?>&tier=<?= urlencode($filterTier) ?>&banned=<?= urlencode($filterBanned) ?>"
               style="padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12.5px; font-weight:700; <?= $p === $page ? 'background:#0B1C33; color:#fff;' : 'background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Modal หน้าต่างปรับสิทธิ์สมาชิกเฉพาะบุคคล -->
  <div class="modal-overlay" id="memberModal" onclick="if(event.target===this)closeMemberModal()">
    <div class="modal-card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0; font-size:17px; color:#0B1C33;" id="modalUserTitle">⚙️ <?= e(t('ปรับสิทธิ์ & โควตาสมาชิก', 'Member Quota & Permissions')) ?></h3>
        <button type="button" onclick="closeMemberModal()" style="border:0; background:none; font-size:20px; cursor:pointer; color:#64748B;">✕</button>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="update_member">
        <input type="hidden" name="user_id" id="modalUserId" value="0">

        <div style="display:flex; flex-direction:column; gap:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ระดับสมาชิก (Member Tier)', 'Member Tier')) ?></label>
            <select name="tier" id="modalTier" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
              <option value="free">Free Member (<?= e(t('สมาชิกทั่วไป', 'Standard user')) ?>)</option>
              <option value="pro">Pro Member (<?= e(t('ตัวแทนมืออาชีพ', 'Professional agent')) ?>)</option>
              <option value="vip">VIP Agent (<?= e(t('โควตาสูงพิเศษ', 'High quota')) ?>)</option>
              <option value="unlimited">Unlimited (<?= e(t('ไม่จำกัดจำนวน', 'Unlimited')) ?>)</option>
            </select>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;">
              <?= e(t('โควตาลงประกาศสูงสุด (Max Listings)', 'Maximum Listing Quota')) ?>
            </label>
            <input type="number" name="max_listings" id="modalMaxListings" min="-1" max="10000" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
            <small style="color:#64748B; font-size:11px; margin-top:3px; display:block;">💡 <?= e(t('ใส่ตัวเลขที่ต้องการ เช่น 5, 20, 50 (หรือใส่ -1 หากต้องการให้ลงได้ไม่จำกัด)', 'Enter number e.g. 5, 20, 50 (or enter -1 for unlimited)')) ?></small>
          </div>

          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#0B1C33; cursor:pointer;">
              <input type="checkbox" name="can_post_project" id="modalCanProject" value="1" style="accent-color:#0B1C33;">
              <?= e(t('อนุญาตให้ลง "โครงการใหม่" (Submit Development Project)', 'Authorize to submit Development Projects')) ?>
            </label>
          </div>

          <div style="border-top:1px solid #e2e8f0; padding-top:12px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#b91c1c; cursor:pointer;">
              <input type="checkbox" name="is_banned" id="modalIsBanned" value="1" style="accent-color:#b91c1c;" onchange="document.getElementById('banReasonBox').style.display=this.checked?'block':'none'">
              <?= e(t('ระงับสิทธิ์การใช้งานบัญชีนี้ (Ban User)', 'Suspend this user account (Ban)')) ?>
            </label>
            <div id="banReasonBox" style="display:none; margin-top:8px;">
              <input type="text" name="ban_reason" id="modalBanReason" placeholder="<?= e(t('ระบุเหตุผล เช่น โพสต์ประกาศขยะ, ข้อมูลเท็จ...', 'State reason e.g. spam listings, fraudulent info...')) ?>" style="width:100%; min-height:38px; padding:6px 12px; border:1px solid #fca5a5; border-radius:8px; font:inherit; font-size:12.5px; box-sizing:border-box;">
            </div>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('รีเซ็ตรหัสผ่านใหม่ (Reset Password)', 'Reset Password')) ?></label>
            <input type="text" name="reset_password" id="modalResetPassword" placeholder="<?= e(t('เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน', 'Leave blank to keep unchanged')) ?>" autocomplete="new-password" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
            <small style="color:#64748B; font-size:11px; margin-top:3px; display:block;">🔑 <?= e(t('ระบุรหัสผ่านใหม่ (อย่างน้อย 6 หรือ 8 ตัวอักษร) เพื่อตั้งค่าให้สมาชิกทันที', 'Enter new password to reset for this user immediately.')) ?></small>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('บันทึกช่วยจำของผู้ดูแล (Admin Internal Notes)', 'Admin Internal Notes')) ?></label>
            <textarea name="admin_notes" id="modalAdminNotes" rows="2" placeholder="<?= e(t('บันทึกข้อความเฉพาะ Founder...', 'Private notes for Founder...')) ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:12.5px; box-sizing:border-box;"></textarea>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
            <button type="button" onclick="closeMemberModal()" style="padding:8px 16px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; cursor:pointer;"><?= e(t('ยกเลิก', 'Cancel')) ?></button>
            <button type="submit" class="btn-gold">💾 <?= e(t('บันทึกสิทธิ์', 'Save Permissions')) ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>

</main>

<script>
  function openMemberModal(u) {
    document.getElementById('modalUserId').value = u.id;
    document.getElementById('modalUserTitle').textContent = '⚙️ ปรับสิทธิ์: ' + (u.full_name || u.username) + ' (#' + u.id + ')';
    document.getElementById('modalTier').value = u.tier || 'free';
    document.getElementById('modalMaxListings').value = u.max_listings !== undefined ? u.max_listings : 5;
    document.getElementById('modalCanProject').checked = parseInt(u.can_post_project) === 1;
    document.getElementById('modalIsBanned').checked = parseInt(u.is_banned) === 1;
    document.getElementById('modalBanReason').value = u.ban_reason || '';
    document.getElementById('banReasonBox').style.display = parseInt(u.is_banned) === 1 ? 'block' : 'none';
    document.getElementById('modalAdminNotes').value = u.admin_notes || '';
    document.getElementById('modalResetPassword').value = '';
    document.getElementById('memberModal').classList.add('active');
  }
  function closeMemberModal() {
    document.getElementById('memberModal').classList.remove('active');
  }
</script>
</body>
</html>
