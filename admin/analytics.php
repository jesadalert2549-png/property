<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin('analytics', 'view');
$pdo = db();

// ช่วงเวลาที่เลือก: 1 / 7 / 30 วัน
$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [1, 7, 30], true)) {
    $range = 7;
}
$since = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));

// Listing totals use the actual enum values and separate publication status.
$totalProps = (int)$pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$activeProps = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available' AND approval = 'approved'")->fetchColumn();
$saleProps = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'sale'")->fetchColumn();
$rentProps = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'rent'")->fetchColumn();
$approvalCounts = array_merge(
    ['pending' => 0, 'approved' => 0, 'rejected' => 0],
    $pdo->query('SELECT approval, COUNT(*) FROM properties GROUP BY approval')->fetchAll(PDO::FETCH_KEY_PAIR)
);

$registeredUserQuery = $pdo->prepare(
    "SELECT u.id, u.full_name, u.username, u.email, u.phone, u.line_id, u.created_at, u.last_login_at,
            (SELECT oi.provider FROM oauth_identities oi WHERE oi.user_id = u.id ORDER BY oi.id DESC LIMIT 1) AS oauth_provider,
            (SELECT COUNT(*) FROM properties p WHERE p.user_id = u.id) AS listing_count,
            (SELECT COUNT(*) FROM visits v WHERE v.user_id = u.id AND v.event_type = 'page' AND v.created_at >= ?) AS recent_page_views,
            (SELECT MAX(v.created_at) FROM visits v WHERE v.user_id = u.id AND v.event_type = 'page') AS last_page_view,
            (SELECT v.page_path FROM visits v WHERE v.user_id = u.id AND v.event_type = 'page' ORDER BY v.created_at DESC LIMIT 1) AS last_page_path
     FROM users u
     WHERE u.role = 'user'
     ORDER BY u.created_at DESC
     LIMIT 100"
);
$registeredUserQuery->execute([$since]);
$registeredUsers = $registeredUserQuery->fetchAll();
$userListings = [];
$approvalLabels = [
    'pending' => 'รออนุมัติ',
    'approved' => 'เผยแพร่แล้ว',
    'rejected' => 'ไม่อนุมัติ',
];
$registeredUserIds = array_map('intval', array_column($registeredUsers, 'id'));
if ($registeredUserIds) {
    $placeholders = implode(',', array_fill(0, count($registeredUserIds), '?'));
    $listingStatement = $pdo->prepare(
        "SELECT user_id, id, title, approval
         FROM properties
         WHERE user_id IN ($placeholders)
         ORDER BY created_at DESC"
    );
    $listingStatement->execute($registeredUserIds);
    foreach ($listingStatement->fetchAll() as $userListing) {
        $userListings[(int)$userListing['user_id']][] = $userListing;
    }
}

// UV is counted from a random first-party token, not from a stored IP address.
$st = $pdo->prepare("SELECT COUNT(*) AS pv,
                            COUNT(DISTINCT visitor_hash) AS uv,
                            SUM(event_type = 'page') AS page_views,
                            SUM(event_type = 'property') AS property_views
                     FROM visits WHERE created_at >= ?");
$st->execute([$since]);
$totals = $st->fetch() ?: ['pv' => 0, 'uv' => 0, 'page_views' => 0, 'property_views' => 0];
$propViews = (int)$totals['property_views'];
$detailRate = (int)$totals['page_views'] > 0
    ? round($propViews / (int)$totals['page_views'] * 100, 1)
    : 0;

$st = $pdo->prepare("SELECT COUNT(*) AS visitors,
                            COALESCE(SUM(first_seen >= ?), 0) AS new_visitors,
                            COALESCE(SUM(first_seen < ?), 0) AS returning_visitors
                     FROM (
                         SELECT visitor_hash, MIN(created_at) AS first_seen
                         FROM visits
                         GROUP BY visitor_hash
                         HAVING MAX(created_at) >= ?
                     ) visitor_summary");
$st->execute([$since, $since, $since]);
$visitorStats = $st->fetch() ?: ['visitors' => 0, 'new_visitors' => 0, 'returning_visitors' => 0];

$st = $pdo->prepare("SELECT recent.visitor_hash, recent.first_seen, recent.last_seen, recent.visit_count,
                            recent.device, recent.user_agent, first_visit.first_seen AS first_visit
                     FROM (
                         SELECT visitor_hash, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen,
                                COUNT(*) AS visit_count, MAX(device) AS device, MAX(user_agent) AS user_agent
                         FROM visits
                         WHERE created_at >= ?
                         GROUP BY visitor_hash
                     ) recent
                     JOIN (
                         SELECT visitor_hash, MIN(created_at) AS first_seen
                         FROM visits
                         GROUP BY visitor_hash
                     ) first_visit ON first_visit.visitor_hash = recent.visitor_hash
                     ORDER BY recent.last_seen DESC
                     LIMIT 50");
$st->execute([$since]);
$visitorSessions = $st->fetchAll();

// Daily unique visitors are measured consistently over the selected date range.
$chartDays = min($range, 14);
$chartSince = date('Y-m-d 00:00:00', strtotime('-' . ($chartDays - 1) . ' days'));
$st = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(DISTINCT visitor_hash) AS uv
                     FROM visits WHERE created_at >= ? GROUP BY d");
$st->execute([$chartSince]);
$byDay = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$days = [];
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = (int)($byDay[$d] ?? 0);
}
$maxDay = max(1, ...array_values($days));

// Most viewed property details in the selected period.
$st = $pdo->prepare("SELECT p.id, p.title, p.type, p.status, COUNT(*) AS views, COUNT(DISTINCT v.visitor_hash) AS uv
                     FROM visits v JOIN properties p ON p.id = v.property_id
                     WHERE v.event_type = 'property' AND v.created_at >= ?
                     GROUP BY p.id, p.title, p.type, p.status
                     ORDER BY views DESC LIMIT 10");
$st->execute([$since]);
$topProps = $st->fetchAll();

// Recent activity shows anonymized visitor IDs and coarse browser/device data only.
$st = $pdo->prepare("SELECT v.visitor_hash, v.device, v.user_agent, v.event_type, v.page_path, v.created_at, p.title
                     FROM visits v 
                     LEFT JOIN properties p ON v.property_id = p.id 
                     WHERE v.created_at >= ? 
                     ORDER BY v.id DESC LIMIT 25");
$st->execute([$since]);
$recentVisitors = $st->fetchAll();

// Referrers are stored as host names only; no query strings or full URLs are retained.
$st = $pdo->prepare("SELECT CASE
                            WHEN referrer = '' THEN 'Direct / no referrer'
                            WHEN referrer LIKE 'http://%' OR referrer LIKE 'https://%'
                                THEN SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '/', -1)
                            ELSE referrer
                            END AS src,
                            COUNT(*) AS c
                     FROM visits
                     WHERE event_type = 'page' AND created_at >= ?
                     GROUP BY src ORDER BY c DESC LIMIT 8");
$st->execute([$since]);
$sources = $st->fetchAll();

$st = $pdo->prepare("SELECT page_path, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
                     FROM visits
                     WHERE event_type = 'page' AND created_at >= ? AND page_path <> ''
                     GROUP BY page_path ORDER BY views DESC LIMIT 10");
$st->execute([$since]);
$topPages = $st->fetchAll();

$st = $pdo->prepare("SELECT property_category, COUNT(*) AS listings
                     FROM properties
                     WHERE approval = 'approved'
                     GROUP BY property_category ORDER BY listings DESC");
$categoryStats = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('วิเคราะห์ข้อมูลและสถิติเชิงลึก', 'Analytics & Deep Insights')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat { background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .stat .num { font-size: 26px; font-weight: 700; color: #2b2150; }
    .stat .lbl { color: #64748b; font-size: 13px; margin-top: 4px; }
    .box { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .box h2 { font-size: 18px; margin-bottom: 16px; color: #2b2150; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .range { display: flex; gap: 8px; margin-bottom: 20px; }
    .range a { padding: 6px 16px; border-radius: 6px; background: #e2e8f0; color: #334155; text-decoration: none; font-weight: 600; font-size: 14px; }
    .range a.on { background: #2b2150; color: #fff; }
    .bars { display: flex; align-items: flex-end; gap: 8px; height: 180px; padding-top: 20px; overflow-x: auto; }
    .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 35px; height: 100%; justify-content: flex-end; }
    .bar { width: 100%; background: #7955d9; border-radius: 4px 4px 0 0; min-height: 4px; }
    .bar-val { font-size: 11px; color: #64748b; margin-bottom: 4px; }
    .bar-lbl { font-size: 11px; color: #64748b; margin-top: 6px; text-align: center; white-space: nowrap; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    th { color: #475569; font-weight: 600; background: #f8fafc; }
    .tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    .tag.mobile { background: #e0e7ff; color: #3730a3; }
    .tag.desktop { background: #f1f5f9; color: #475569; }
    .tag.sale { background: #d1fae5; color: #065f46; }
    .tag.rent { background: #fee2e2; color: #991b1b; }
    .topbar { background: #34254f; color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
    .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 16px; font-size: 14px; }
    .topbar nav a:hover { color: #fff; }
    .wrap { max-width: 1200px; margin: 24px auto; padding: 0 20px; }
    .muted { color: #94a3b8; font-style: italic; font-size: 13px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    /* Analytics dashboard presentation */
    .wrap { max-width:1320px; margin:0 auto; padding:28px 24px 64px; }
    .analytics-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:24px; margin:0 0 24px; padding:30px 34px; border-radius:22px; color:#fff; background:radial-gradient(circle at 85% 15%,rgba(255,255,255,.15),transparent 32%),linear-gradient(120deg,#2b2150,#634c89); box-shadow:0 16px 36px rgba(23,27,61,.15); }
    .analytics-hero .eyebrow { color:#f2b17d; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .analytics-hero h1 { margin:8px 0; color:#fff; font-size:clamp(25px,3vw,34px); }
    .analytics-hero p { margin:0; color:rgba(255,255,255,.78); }
    .analytics-hero .hero-note { flex:0 0 auto; padding:10px 14px; border:1px solid rgba(255,255,255,.25); border-radius:14px; color:#fff; font-size:12px; }
    .analytics-section-title { display:flex; align-items:center; gap:10px; margin:28px 0 14px!important; color:#2b2150!important; font-size:18px!important; font-weight:800; }
    .analytics-section-title:before { content:""; width:4px; height:20px; border-radius:4px; background:#7955d9; }
    .stats { grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; }
    .stat { position:relative; padding:19px 20px; border-radius:16px; border:1px solid #e6ecf1; box-shadow:0 5px 16px rgba(20,43,66,.045); }
    .stat:before { content:""; position:absolute; top:0; left:18px; width:34px; height:3px; border-radius:0 0 4px 4px; background:#7955d9; }
    .stat .num { font-size:29px; font-weight:800; letter-spacing:-.03em; }
    .stat .lbl { color:#64748b; font-size:12px; margin-top:6px; }
    .box { border-radius:18px; border-color:#e6ecf1; box-shadow:0 5px 18px rgba(20,43,66,.04); }
    .box h2 { font-size:17px; margin:0 0 16px; border-bottom:1px solid #edf1f5; padding-bottom:12px; }
    .range { gap:4px; width:max-content; padding:5px; border:1px solid #e7edf2; border-radius:999px; background:#fff; }
    .range a { padding:8px 17px; border-radius:999px; background:transparent; color:#64748b; font-size:13px; }
    .range a.on { background:#2b2150; color:#fff; box-shadow:0 3px 8px rgba(23,27,61,.2); }
    .analytics-controls { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:20px; }
    .analytics-controls .range { margin:0; }
    .pdf-report-link { display:inline-flex; align-items:center; gap:8px; padding:11px 17px; border-radius:999px; background:#7955d9; color:#fff; font-size:13px; font-weight:800; text-decoration:none; box-shadow:0 5px 13px rgba(114,87,245,.2); transition:transform .2s,background .2s; }
    .pdf-report-link:hover { transform:translateY(-1px); background:#ce6425; }
    .bars { gap:8px; height:190px; padding:18px 4px 0; border-bottom:1px solid #e7edf2; }
    .bar { background:linear-gradient(180deg,#d0bdfa,#7955d9); border-radius:6px 6px 2px 2px; box-shadow:0 3px 8px rgba(114,87,245,.15); }
    table { font-size:13px; }
    th, td { vertical-align:top; border-bottom-color:#edf1f5; }
    th { color:#52657a; font-weight:700; background:#f7f9fb; white-space:nowrap; }
    tbody tr:hover td { background:#fbfcfd; }
    .topbar { position:sticky; top:0; z-index:10; padding:14px max(24px,calc((100vw - 1320px)/2)); gap:16px; box-shadow:0 4px 18px rgba(14,30,46,.16); }
    .topbar nav { display:flex; flex-wrap:wrap; gap:4px; }
    .topbar nav a { margin:0; padding:7px 9px; border-radius:8px; font-size:12px; }
    .topbar nav a:hover { background:rgba(255,255,255,.1); text-decoration:none; }
    @media (max-width:900px) { .topbar { position:static; align-items:flex-start; flex-direction:column; padding:14px 20px; } }
    @media (max-width:600px) {
      .wrap { padding:14px 12px 36px; }
      .analytics-hero {
        align-items:flex-start;
        flex-direction:column;
        gap:14px;
        padding:20px 16px;
        border-radius:18px;
        margin-top:14px;
      }
      .analytics-hero h1 { font-size:22px; line-height:1.3; }
      .analytics-hero p { font-size:13px; line-height:1.5; }
      .analytics-hero .hero-note { font-size:11.5px; padding:8px 12px; width:100%; text-align:center; }
      .analytics-controls {
        align-items:stretch;
        flex-direction:column;
        gap:10px;
      }
      .analytics-controls .range {
        width:100%;
        display:grid;
        grid-template-columns:repeat(3, 1fr);
        padding:4px;
      }
      .analytics-controls .range a {
        text-align:center;
        padding:8px 4px;
        font-size:12px;
      }
      .pdf-report-link {
        width:100%;
        justify-content:center;
        padding:10px 14px;
      }
      .analytics-section-title { font-size:16px !important; margin:22px 0 12px !important; }
      .stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
      .stat { padding:14px 12px; border-radius:14px; }
      .stat .num { font-size:24px; font-family:"Prompt","Inter",system-ui,sans-serif !important; }
      .stat .lbl { font-size:11.5px; line-height:1.4; }
      .box { padding:16px 14px; border-radius:16px; margin-bottom:18px; }
      .box h2 { font-size:15px; margin-bottom:12px; padding-bottom:8px; line-height:1.4; }
      .bars { gap:5px; height:160px; padding-top:14px; }
      .bar-wrap { min-width:24px; }
      .bar-val, .bar-lbl { font-size:10px; }
      table { font-size:12px; }
      th, td { padding:9px 10px; }
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>

<div class="wrap">

  <section class="analytics-hero">
    <div><span class="eyebrow">PROPERTY INSIGHTS</span><h1><?= e(t('วิเคราะห์ข้อมูลเว็บไซต์', 'Website Analytics & Insights')) ?></h1><p><?= e(t('ภาพรวมประกาศ สมาชิก และพฤติกรรมการเข้าชมในที่เดียว', 'Overview of listings, members, and traffic behavior in one place.')) ?></p></div>
    <div class="hero-note"><?= e(t('ข้อมูลตามช่วงเวลาที่เลือก · อัปเดตเมื่อเปิดหน้านี้', 'Data based on selected period · Updated in real-time')) ?></div>
  </section>

  <!-- ตัวเลือกช่วงเวลา -->
  <div class="analytics-controls">
  <div class="range">
    <?php foreach ([1 => t('วันนี้', 'Today'), 7 => t('7 วันย้อนหลัง', 'Last 7 Days'), 30 => t('30 วันย้อนหลัง', 'Last 30 Days')] as $r => $label): ?>
      <a href="?range=<?= $r ?>" class="<?= $range === $r ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <a class="pdf-report-link" href="pdf-report.php?range=<?= $range ?>" target="_blank" rel="noopener">▣ <?= e(t('พิมพ์ / บันทึก PDF', 'Print / Export PDF')) ?></a>
  </div>

  <!-- ส่วนที่ 1: ภาพรวมฐานข้อมูลทรัพย์สินทั้งหมด -->
  <h2 class="analytics-section-title">🏠 <?= e(t('สรุปภาพรวมทรัพย์สินในระบบ', 'Property Inventory Summary')) ?></h2>
  <div class="stats">
    <div class="stat"><div class="num"><?= number_format($totalProps) ?></div><div class="lbl"><?= e(t('ประกาศทั้งหมดในระบบ', 'Total Listings in System')) ?></div></div>
    <div class="stat"><div class="num" style="color: #059669;"><?= number_format($activeProps) ?></div><div class="lbl"><?= e(t('ประกาศพร้อมขาย/เช่าที่เผยแพร่แล้ว', 'Active Published Listings')) ?></div></div>
    <div class="stat"><div class="num" style="color: #2563eb;"><?= number_format($saleProps) ?></div><div class="lbl"><?= e(t('ประเภทขาย (Sale)', 'For Sale (Sale)')) ?></div></div>
    <div class="stat"><div class="num" style="color: #7955d9;"><?= number_format($rentProps) ?></div><div class="lbl"><?= e(t('ประเภทให้เช่า (Rent)', 'For Rent (Rent)')) ?></div></div>
    <div class="stat"><div class="num"><?= number_format((int)$approvalCounts['pending']) ?></div><div class="lbl"><?= e(t('รอตรวจสอบ', 'Pending Review')) ?></div></div>
    <div class="stat"><div class="num"><?= number_format((int)$approvalCounts['rejected']) ?></div><div class="lbl"><?= e(t('ไม่อนุมัติ', 'Rejected')) ?></div></div>
  </div>

  <!-- ส่วนที่ 2: สถิติผู้เข้าชมเว็บไซต์ -->
  <h2 class="analytics-section-title">📈 <?= e(t('สถิติการเข้าชมเว็บไซต์ (ในช่วงเวลาที่เลือก)', 'Traffic & Visitor Statistics (Selected Period)')) ?></h2>
  <div class="stats">
    <div class="stat"><div class="num"><?= number_format((int)$totals['page_views']) ?></div><div class="lbl"><?= e(t('ยอดเปิดหน้าเว็บทั้งหมด (PV)', 'Total Page Views (PV)')) ?></div></div>
    <div class="stat"><div class="num"><?= number_format((int)$totals['uv']) ?></div><div class="lbl"><?= e(t('ผู้เข้าชมไม่ซ้ำกัน (UV)', 'Unique Visitors (UV)')) ?></div></div>
    <div class="stat"><div class="num" style="color:#2563eb;"><?= number_format((int)$visitorStats['new_visitors']) ?></div><div class="lbl"><?= e(t('ผู้เข้าชมใหม่ในช่วงที่เลือก', 'New Visitors')) ?></div></div>
    <div class="stat"><div class="num" style="color:#7c3aed;"><?= number_format((int)$visitorStats['returning_visitors']) ?></div><div class="lbl"><?= e(t('ผู้เข้าชมกลับมาอีก (เคยเข้าก่อนช่วงนี้)', 'Returning Visitors')) ?></div></div>
    <div class="stat"><div class="num"><?= number_format($propViews) ?></div><div class="lbl"><?= e(t('ยอดคลิกดูรายละเอียดประกาศ', 'Property Detail Clicks')) ?></div></div>
    <div class="stat"><div class="num"><?= number_format($detailRate, 1) ?>%</div><div class="lbl"><?= e(t('สัดส่วนดูรายละเอียดต่อการเปิดหน้า', 'Detail View Conversion Rate')) ?></div></div>
  </div>

  <div class="box">
    <h2>🧾 บัญชีสมาชิกและผู้ลงประกาศ</h2>
    <p class="small" style="margin-bottom:12px;">แสดงข้อมูลที่สมาชิกกรอกไว้ตอนสมัครและจำนวนประกาศที่ผูกกับบัญชีนี้ เฉพาะเจ้าของเว็บไซต์เข้าถึงได้ ไม่แสดงรหัสผ่าน</p>
    <?php if (!$registeredUsers): ?>
      <p class="muted">ยังไม่มีบัญชีสมาชิก</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <tr><th>ชื่อ-นามสกุล</th><th>ช่องทางสมัคร</th><th>ชื่อผู้ใช้ / อีเมล</th><th>ช่องทางติดต่อที่สมัคร</th><th>สมัครเมื่อ</th><th>เข้าสู่ระบบล่าสุด</th><th>ประกาศที่ลง</th><th>Page views</th></tr>
        <?php foreach ($registeredUsers as $registeredUser): ?>
          <tr>
            <td><strong><?= e($registeredUser['full_name'] ?: 'ยังไม่ได้ระบุชื่อ') ?></strong></td>
            <td>
              <?php
                $p = $registeredUser['oauth_provider'] ?? '';
                if ($p === 'line') echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#E8FDF0;color:#04A747;border:1px solid #06C755;">🟢 LINE</span>';
                elseif ($p === 'google') echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#EBF3FE;color:#1A73E8;border:1px solid #4285F4;">🔴 Google</span>';
                elseif ($p === 'facebook') echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#EAF3FF;color:#1877F2;border:1px solid #1877F2;">🔵 Facebook</span>';
                else echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#F1F5F9;color:#64748B;">🔑 ทั่วไป</span>';
              ?>
            </td>
            <td><?= e($registeredUser['username']) ?><br><a href="mailto:<?= e($registeredUser['email']) ?>"><?= e($registeredUser['email']) ?></a></td>
            <td>
              <?= $registeredUser['phone'] !== '' ? '<a href="tel:' . e($registeredUser['phone']) . '">' . e($registeredUser['phone']) . '</a>' : 'ไม่ได้ระบุเบอร์' ?><br>
              <?php if ($registeredUser['line_id'] !== ''): ?>
                <a href="https://line.me/R/ti/p/~<?= rawurlencode($registeredUser['line_id']) ?>" target="_blank" rel="noopener noreferrer">LINE: <?= e($registeredUser['line_id']) ?></a>
              <?php else: ?>
                ไม่ได้ระบุ LINE
              <?php endif; ?>
            </td>
            <td><?= date('d/m/Y H:i:s', strtotime($registeredUser['created_at'])) ?></td>
            <td><?= !empty($registeredUser['last_login_at']) ? date('d/m/Y H:i:s', strtotime($registeredUser['last_login_at'])) : 'ยังไม่เคยเข้าสู่ระบบ' ?></td>
            <td>
              <?= number_format((int)$registeredUser['listing_count']) ?> รายการ
              <?php if (!empty($userListings[(int)$registeredUser['id']])): ?>
                <ul style="margin:6px 0 0;padding-left:18px">
                  <?php foreach ($userListings[(int)$registeredUser['id']] as $userListing): ?>
                    <li><a href="../property.php?id=<?= (int)$userListing['id'] ?>"><?= e($userListing['title']) ?></a> (<?= e($approvalLabels[$userListing['approval']] ?? 'ไม่ทราบสถานะ') ?>)</li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </td>
            <td><?= number_format((int)$registeredUser['recent_page_views']) ?><?php if (!empty($registeredUser['last_page_path'])): ?><br><span class="small" title="Last page visited"> <?= e($registeredUser['last_page_path']) ?> </span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="box">
    <h2>🕒 เวลาเข้าใช้งานและสถานะผู้เข้าชม</h2>
    <p class="small" style="margin-bottom:12px;">แสดงเฉพาะสถิติจากผู้ที่ยินยอมคุกกี้วิเคราะห์ แยกใหม่/กลับมาจาก first-party cookie แบบสุ่ม อาจนับเป็นผู้เข้าชมใหม่เมื่อเปลี่ยนอุปกรณ์หรือลบ cookie และไม่มีการเก็บ IP address</p>
    <?php if (!$visitorSessions): ?>
      <p class="muted">ยังไม่มีผู้เข้าชมในช่วงเวลานี้</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <tr><th>สถานะ</th><th>รหัสไม่ระบุตัวตน</th><th>เข้าครั้งแรก</th><th>เข้าล่าสุด</th><th>กิจกรรมในช่วงนี้</th><th>อุปกรณ์ / เบราว์เซอร์</th></tr>
        <?php foreach ($visitorSessions as $visitor): ?>
          <?php $isNew = $visitor['first_visit'] >= $since; ?>
          <tr>
            <td><span class="tag <?= $isNew ? 'sale' : 'rent' ?>"><?= $isNew ? 'ผู้เข้าชมใหม่' : 'กลับมาอีก' ?></span></td>
            <td><code><?= e(substr($visitor['visitor_hash'], 0, 12)) ?>…</code></td>
            <td><?= date('d/m/Y H:i:s', strtotime($visitor['first_visit'])) ?></td>
            <td><?= date('d/m/Y H:i:s', strtotime($visitor['last_seen'])) ?></td>
            <td><?= number_format((int)$visitor['visit_count']) ?> ครั้ง</td>
            <td><?= $visitor['device'] === 'mobile' ? 'มือถือ' : 'คอมพิวเตอร์' ?> / <?= e($visitor['user_agent']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </div>

  <!-- กราฟรายวัน -->
  <div class="box">
    <h2>📈 กราฟแนวโน้มผู้เข้าชมไม่ซ้ำรายวัน (<?= $chartDays ?> วันล่าสุด)</h2>
    <p class="small">แสดงจำนวนผู้เข้าชมที่ไม่ซ้ำในแต่ละวัน รวมการเปิดหน้าเว็บและดูรายละเอียดประกาศ</p>
    <div class="bars">
      <?php foreach ($days as $d => $n): ?>
        <div class="bar-wrap">
          <span class="bar-val"><?= $n ?></span>
          <div class="bar" style="height: <?= max(6, round($n / max(1, $maxDay) * 140)) ?>px;"></div>
          <span class="bar-lbl"><?= date('d/m', strtotime($d)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="grid-2">
    <div class="box">
      <h2>📄 หน้าที่ได้รับการเข้าชมสูงสุด</h2>
      <?php if (!$topPages): ?>
        <p class="muted">ยังไม่มีข้อมูลหน้าที่เข้าชมในช่วงเวลานี้</p>
      <?php else: ?>
        <div class="table-wrap"><table>
          <tr><th>หน้า</th><th>ยอดเข้าชม</th><th>ผู้เข้าชมไม่ซ้ำ</th></tr>
          <?php foreach ($topPages as $page): ?>
            <tr>
              <td><code><?= e($page['page_path']) ?></code></td>
              <td><?= number_format((int)$page['views']) ?></td>
              <td><?= number_format((int)$page['visitors']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h2>🏘️ ประกาศแยกตามประเภทอสังหาริมทรัพย์</h2>
      <?php if (!$categoryStats): ?>
        <p class="muted">ยังไม่มีประกาศในระบบ</p>
      <?php else: ?>
        <div class="table-wrap"><table>
          <tr><th>ประเภท</th><th>จำนวนประกาศที่เผยแพร่</th></tr>
          <?php foreach ($categoryStats as $category): ?>
            <tr>
              <td><?= e(category_label($category['property_category'])) ?></td>
              <td><?= number_format((int)$category['listings']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-2">
    <!-- แหล่งที่มาของทราฟฟิก -->
    <div class="box" style="margin-bottom: 24px;">
      <h2>🌐 แหล่งที่มาของผู้เข้าชม (Traffic Sources)</h2>
      <?php if (!$sources): ?>
        <p class="muted">ยังไม่มีข้อมูลแหล่งที่มาในช่วงเวลานี้</p>
      <?php else: ?>
        <div class="table-wrap"><table>
          <tr><th>แหล่งที่มา / เว็บไซต์อ้างอิง</th><th>จำนวนครั้ง</th></tr>
          <?php foreach ($sources as $s): ?>
            <tr>
              <td><code><?= e($s['src']) ?></code></td>
              <td><strong><?= number_format((int)$s['c']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
    </div>

    <!-- สรุปสัดส่วนอุปกรณ์ -->
    <div class="box" style="margin-bottom: 24px;">
      <h2>📱 ประเภทอุปกรณ์ที่ผู้ใช้เข้าชม</h2>
      <?php
        $stDev = $pdo->prepare("SELECT device, COUNT(*) as c FROM visits WHERE created_at >= ? GROUP BY device");
        $stDev->execute([$since]);
        $devices = $stDev->fetchAll(PDO::FETCH_KEY_PAIR);
        $totalDev = array_sum($devices) ?: 1;
      ?>
      <div class="table-wrap"><table>
        <tr><th>อุปกรณ์</th><th>จำนวนครั้ง</th><th>สัดส่วน</th></tr>
        <tr>
          <td>📱 มือถือ (Mobile)</td>
          <td><?= number_format($devices['mobile'] ?? 0) ?></td>
          <td><?= round(($devices['mobile'] ?? 0) / $totalDev * 100, 1) ?>%</td>
        </tr>
        <tr>
          <td>💻 คอมพิวเตอร์ (Desktop)</td>
          <td><?= number_format($devices['desktop'] ?? 0) ?></td>
          <td><?= round(($devices['desktop'] ?? 0) / $totalDev * 100, 1) ?>%</td>
        </tr>
      </table></div>
    </div>
  </div>

  <!-- ประกาศยอดนิยม -->
  <div class="box">
    <h2>🔥 ประกาศที่ได้รับความสนใจสูงสุด (Top 10 Listings)</h2>
    <?php if (!$topProps): ?>
      <p class="muted">ยังไม่มีข้อมูลการคลิกดูประกาศในช่วงเวลานี้</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <tr><th>หัวข้อประกาศ</th><th>ประเภท</th><th>สถานะ</th><th>ยอดดู (Views)</th><th>ผู้เข้าชม (UV)</th></tr>
        <?php foreach ($topProps as $p): ?>
          <tr>
            <td><strong><?= e($p['title']) ?></strong></td>
            <td><span class="tag <?= e($p['type']) ?>"><?= e(type_label($p['type'])) ?></span></td>
            <td><span class="tag" style="background:#e2e8f0;"><?= e($p['status']) ?></span></td>
            <td><?= number_format((int)$p['views']) ?></td>
            <td><?= number_format((int)$p['uv']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </div>

  <!-- ตารางแสดงประวัติผู้เข้าชมล่าสุด -->
  <div class="box">
    <h2>👥 ประวัติการเข้าชมแบบเรียลไทม์ (Real-time Visitor Log)</h2>
    <p class="small" style="margin-bottom: 12px;">แสดงรหัสผู้เข้าชมแบบสุ่ม อุปกรณ์ เบราว์เซอร์ และหน้าที่เข้าชม โดยไม่แสดงหรือเก็บ IP address</p>
    <?php if (!$recentVisitors): ?>
      <p class="muted">ยังไม่มีประวัติการเข้าชมในช่วงเวลานี้</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <tr>
          <th>เวลา</th>
          <th>รหัสผู้เข้าชม (Visitor Hash)</th>
          <th>อุปกรณ์</th>
          <th>เบราว์เซอร์</th>
          <th>หน้า</th>
          <th>กิจกรรม / หน้าที่เข้าชม</th>
        </tr>
        <?php foreach ($recentVisitors as $rv): ?>
          <tr>
            <td><?= date('d/m/Y H:i:s', strtotime($rv['created_at'])) ?></td>
            <td><code><?= e(substr($rv['visitor_hash'], 0, 12)) ?>...</code></td>
            <td>
              <span class="tag <?= e($rv['device']) ?>">
                <?= $rv['device'] === 'mobile' ? '📱 มือถือ' : '💻 คอมพิวเตอร์' ?>
              </span>
            </td>
            <td><?= e(in_array($rv['user_agent'], ['Edge', 'Firefox', 'Chrome', 'Safari', 'Other'], true) ? $rv['user_agent'] : 'Other') ?></td>
            <td><code><?= e($rv['page_path'] ?: 'property.php') ?></code></td>
            <td>
              <?php if ($rv['event_type'] === 'property' && $rv['title']): ?>
                ดูประกาศ: <strong><?= e($rv['title']) ?></strong>
              <?php else: ?>
                เข้าชมหน้าแรก / หน้าทั่วไป
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>

