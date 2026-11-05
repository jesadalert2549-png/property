<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = db();
$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, [1, 7, 30], true)) $range = 30;
$since = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));
$rangeLabel = [1 => 'วันนี้', 7 => '7 วันย้อนหลัง', 30 => '30 วันย้อนหลัง'][$range];

// ===== สถิติประกาศ =====
$totalProps   = (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$activeProps  = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available' AND approval = 'approved'")->fetchColumn();
$doneProps    = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'done'")->fetchColumn();
$saleProps    = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'sale'")->fetchColumn();
$rentProps    = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'rent'")->fetchColumn();
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$approvalCounts = array_merge(
    ['pending' => 0, 'approved' => 0, 'rejected' => 0],
    $pdo->query('SELECT approval, COUNT(*) FROM properties GROUP BY approval')->fetchAll(PDO::FETCH_KEY_PAIR)
);

// ===== สถิติผู้เข้าชมทั้งหมด =====
$allTime = $pdo->query('SELECT COUNT(*) AS pv, COUNT(DISTINCT visitor_hash) AS uv FROM visits')->fetch()
    ?: ['pv' => 0, 'uv' => 0];
$periodStmt = $pdo->prepare("SELECT COUNT(*) AS pv, COUNT(DISTINCT visitor_hash) AS uv,
                                    SUM(event_type = 'page') AS page_views,
                                    SUM(event_type = 'property') AS property_views
                             FROM visits WHERE created_at >= ?");
$periodStmt->execute([$since]);
$periodStats = $periodStmt->fetch() ?: ['pv' => 0, 'uv' => 0, 'page_views' => 0, 'property_views' => 0];
$periodStats = array_map('intval', $periodStats);
$detailRate = $periodStats['page_views'] > 0 ? round($periodStats['property_views'] / $periodStats['page_views'] * 100, 1) : 0;
$visitorStmt = $pdo->prepare("SELECT COUNT(*) AS visitors,
                                     COALESCE(SUM(first_seen >= ?),0) AS new_visitors,
                                     COALESCE(SUM(first_seen < ?),0) AS returning_visitors
                              FROM (SELECT visitor_hash, MIN(created_at) AS first_seen
                                    FROM visits GROUP BY visitor_hash HAVING MAX(created_at) >= ?) visitor_summary");
$visitorStmt->execute([$since, $since, $since]);
$visitorStats = $visitorStmt->fetch() ?: ['new_visitors' => 0, 'returning_visitors' => 0];

// ===== ยอดเข้าชมรายวัน 14 วันล่าสุด =====
$chartDays = min($range, 14);
$chartSince = date('Y-m-d 00:00:00', strtotime('-' . ($chartDays - 1) . ' days'));
$st = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS pv, COUNT(DISTINCT visitor_hash) AS uv
                     FROM visits WHERE created_at >= ? GROUP BY DATE(created_at)");
$st->execute([$chartSince]);
$byDay = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $byDay[$r['d']] = $r;
}
$daily = [];
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily[$d] = ['pv' => (int)($byDay[$d]['pv'] ?? 0), 'uv' => (int)($byDay[$d]['uv'] ?? 0)];
}
$maxPv = max(1, ...array_column($daily, 'pv'));

// ===== ประกาศยอดนิยมตลอดกาล =====
$topStmt = $pdo->prepare("SELECT p.title, p.type, COUNT(*) AS views, COUNT(DISTINCT v.visitor_hash) AS visitors
                         FROM visits v JOIN properties p ON p.id = v.property_id
                         WHERE v.event_type = 'property' AND v.created_at >= ?
                         GROUP BY p.id, p.title, p.type
                         ORDER BY views DESC LIMIT 10");
$topStmt->execute([$since]);
$topProps = $topStmt->fetchAll();

// ===== ประกาศแยกตามประเภท =====
$categoryStats = $pdo->query("SELECT property_category, COUNT(*) AS listings
                              FROM properties WHERE approval = 'approved'
                              GROUP BY property_category ORDER BY listings DESC")->fetchAll();

$generatedAt = date('d/m/Y H:i:s');
$periodLabel = date('d/m/Y', strtotime($since)) . ' – ' . date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รายงานสรุปสถิติเว็บไซต์ (PDF) | ระบบหลังบ้าน</title>
  <link rel="icon" type="image/svg+xml" href="../images/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
  <link rel="shortcut icon" href="../favicon.ico">
  <style>
    @page { size: A4; margin: 14mm; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Sarabun", "Noto Sans Thai", Tahoma, sans-serif;
      color: #1e293b;
      background: #f1f5f9;
      font-size: 13px;
    }

    /* ===== แถบเครื่องมือ (ไม่ปริ้น) ===== */
    .toolbar {
      background: #2b2150;
      color: #fff;
      padding: 14px 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      position: sticky;
      top: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .toolbar strong { font-size: 16px; }
    .toolbar a {
      color: #fff;
      background: #7955d9;
      padding: 9px 18px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      border: none;
      font-family: inherit;
    }
    .toolbar a.ghost { background: rgba(255,255,255,.15); }
    .print-hint { font-size: 12px; color: #cbd5e1; margin: 6px 22px 14px; }

    /* ===== กระดาษ A4 ===== */
    .paper {
      background: #fff;
      max-width: 210mm;
      margin: 0 auto 30px;
      padding: 16mm 15mm;
      box-shadow: 0 4px 20px rgba(0,0,0,.12);
    }

    .rep-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2b2150; padding-bottom: 12px; margin-bottom: 6px; }
    .rep-head h1 { margin: 0; font-size: 21px; color: #2b2150; }
    .rep-head .sub { color: #64748b; font-size: 12px; margin-top: 4px; }
    .brand { text-align: right; }
    .brand .bm { background: #2b2150; color: #fff; display: inline-block; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 16px; }
    .brand .bn { font-size: 12px; color: #64748b; margin-top: 5px; }
    .rep-meta { display: flex; gap: 18px; flex-wrap: wrap; font-size: 11.5px; color: #475569; padding: 9px 0 3px; }
    .rep-meta span { background: #f1f5f9; border-radius: 6px; padding: 4px 10px; }

    h2.rep-sec { font-size: 15px; color: #2b2150; border-left: 5px solid #7955d9; padding-left: 10px; margin: 26px 0 12px; }

    /* ===== การ์ดสรุป ===== */
    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .kcard { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; background: #fbfdff; }
    .kcard .n { font-size: 21px; font-weight: 800; color: #2b2150; line-height: 1.15; }
    .kcard.acc .n { color: #059669; }
    .kcard.wrn .n { color: #d97706; }
    .kcard .l { color: #64748b; font-size: 11px; margin-top: 3px; }

    /* ===== ตาราง ===== */
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #e2e8f0; padding: 7px 9px; text-align: left; }
    th { background: #2b2150; color: #fff; font-weight: 600; }
    tr:nth-child(even) td { background: #f8fafc; }
    .r { text-align: right; }

    /* ===== กราฟแท่ง ===== */
    .chart { display: flex; align-items: flex-end; gap: 5px; height: 120px; border-bottom: 2px solid #cbd5e1; padding-top: 6px; }
    .cw { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; min-width: 0; }
    .cb { width: 70%; background: linear-gradient(180deg, #7955d9, #583caa); border-radius: 3px 3px 0 0; min-height: 2px; }
    .cv { font-size: 9px; color: #475569; margin-bottom: 2px; }
    .cl { font-size: 9px; color: #64748b; margin-top: 4px; white-space: nowrap; }

    .foot { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 10.5px; color: #94a3b8; display: flex; justify-content: space-between; }
    .summary-note { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 12px 14px; margin-top: 20px; font-size: 12px; color: #7c2d12; }
    .toolbar-actions { display:flex; flex-wrap:wrap; gap:8px; }

    /* ===== โหมดปริ้น/บันทึก PDF ===== */
    @media print {
      body { background: #fff; }
      .toolbar, .print-hint { display: none !important; }
      .paper { box-shadow: none; margin: 0; max-width: none; padding: 0; }
      .rep-head, .kcard, table, .chart { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      h2.rep-sec { page-break-after: avoid; }
      table, .chart { page-break-inside: avoid; }
    }
  </style>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
</head>
<body>
<?php require __DIR__ . '/_sidebar.php'; ?>

<div class="toolbar">
  <strong>🧾 รายงานสรุปสถิติเว็บไซต์ทั้งหมด</strong>
  <span class="toolbar-actions">
    <a href="#" onclick="window.print(); return false;">🖨️ ปริ้น / บันทึกเป็น PDF</a>
    <a class="ghost" href="analytics.php?range=<?= $range ?>">← กลับหน้าสถิติ</a>
  </span>
</div>
<p class="print-hint">💡 กด “ปริ้น / บันทึกเป็น PDF” แล้วเลือก “บันทึกเป็น PDF” ในหน้าต่างพิมพ์ แนะนำกระดาษ A4</p>

<div class="paper">
  <div class="rep-head">
    <div>
      <h1>รายงานสรุปสถิติเว็บไซต์</h1>
      <div class="sub">รายงานภาพรวมประกาศอสังหาริมทรัพย์และการเข้าชมเว็บไซต์ สำหรับผู้บริหาร</div>
    </div>
    <div class="brand">
      <div class="bm">SP</div>
      <div class="bn"><?= e(SITE_NAME) ?><br><?= e(SITE_URL) ?></div>
    </div>
  </div>
  <div class="rep-meta">
    <span>🗓️ สร้างรายงาน: <?= e($generatedAt) ?></span>
    <span>👤 ผู้สร้าง: <?= e($_SESSION['username'] ?? 'admin') ?></span>
    <span>📈 ช่วงรายงาน: <?= e($rangeLabel) ?> (<?= e($periodLabel) ?>)</span>
  </div>

  <!-- ส่วนที่ 1: ภาพรวม -->
  <h2 class="rep-sec">1. ภาพรวมประกาศในระบบ</h2>
  <div class="cards">
    <div class="kcard"><div class="n"><?= number_format($totalProps) ?></div><div class="l">ประกาศทั้งหมดในระบบ</div></div>
    <div class="kcard acc"><div class="n"><?= number_format($activeProps) ?></div><div class="l">เผยแพร่และพร้อมขาย/เช่า</div></div>
    <div class="kcard wrn"><div class="n"><?= number_format($doneProps) ?></div><div class="l">ขาย/ปล่อยเช่าแล้ว</div></div>
    <div class="kcard"><div class="n"><?= number_format($saleProps) ?></div><div class="l">ประเภทขาย (Sale)</div></div>
    <div class="kcard"><div class="n"><?= number_format($rentProps) ?></div><div class="l">ประเภทให้เช่า (Rent)</div></div>
    <div class="kcard"><div class="n"><?= number_format($totalUsers) ?></div><div class="l">สมาชิกลงทะเบียน</div></div>
  </div>

  <h2 class="rep-sec">2. สถานะการอนุมัติประกาศ</h2>
  <table>
    <tr><th>สถานะ</th><th class="r">จำนวน (รายการ)</th><th class="r">สัดส่วน</th></tr>
    <?php foreach (['approved' => 'เผยแพร่แล้ว', 'pending' => 'รอตรวจสอบ', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?>
      <tr>
        <td><?= e($lbl) ?></td>
        <td class="r"><?= number_format((int)$approvalCounts[$k]) ?></td>
        <td class="r"><?= $totalProps > 0 ? round($approvalCounts[$k] / $totalProps * 100, 1) : 0 ?>%</td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- ส่วนที่ 3: สถิติผู้เข้าชม -->
  <h2 class="rep-sec">3. สถิติการเข้าชมเว็บไซต์</h2>
  <div class="cards">
    <div class="kcard"><div class="n"><?= number_format((int)$allTime['pv']) ?></div><div class="l">ยอดเข้าชมสะสมทั้งหมด</div></div>
    <div class="kcard"><div class="n"><?= number_format((int)$allTime['uv']) ?></div><div class="l">ผู้เข้าชมไม่ซ้ำสะสม</div></div>
    <div class="kcard"><div class="n"><?= number_format($periodStats['page_views']) ?></div><div class="l">เปิดหน้าเว็บ (PV) · <?= e($rangeLabel) ?></div></div>
    <div class="kcard acc"><div class="n"><?= number_format($periodStats['uv']) ?></div><div class="l">ผู้เข้าชมไม่ซ้ำ (UV) · <?= e($rangeLabel) ?></div></div>
    <div class="kcard"><div class="n"><?= number_format((int)$visitorStats['new_visitors']) ?></div><div class="l">ผู้เข้าชมใหม่ · <?= e($rangeLabel) ?></div></div>
    <div class="kcard"><div class="n"><?= number_format((int)$visitorStats['returning_visitors']) ?></div><div class="l">ผู้เข้าชมกลับมา · <?= e($rangeLabel) ?></div></div>
    <div class="kcard"><div class="n"><?= number_format($periodStats['property_views']) ?></div><div class="l">ดูรายละเอียดประกาศ</div></div>
    <div class="kcard"><div class="n"><?= number_format($detailRate, 1) ?>%</div><div class="l">สัดส่วนดูรายละเอียดต่อการเปิดหน้า</div></div>
  </div>

  <h2 class="rep-sec">4. ยอดเข้าชมรายวัน (<?= $chartDays ?> วันล่าสุด)</h2>
  <div class="chart">
    <?php foreach ($daily as $d => $v): ?>
      <div class="cw">
        <span class="cv"><?= $v['pv'] ?></span>
        <div class="cb" style="height: <?= max(2, round($v['pv'] / $maxPv * 88)) ?>px;"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="chart" style="height:auto; border:none; padding:0;">
    <?php foreach ($daily as $d => $v): ?>
      <div class="cw"><span class="cl"><?= date('d/m', strtotime($d)) ?></span></div>
    <?php endforeach; ?>
  </div>

  <h2 class="rep-sec">5. ประกาศยอดนิยม (Top 10)</h2>
  <?php if (!$topProps): ?>
    <p style="color:#94a3b8;">ยังไม่มีข้อมูลการเข้าชมประกาศ</p>
  <?php else: ?>
    <table>
      <tr><th>#</th><th>หัวข้อประกาศ</th><th>ประเภท</th><th class="r">ยอดดู</th></tr>
      <?php foreach ($topProps as $i => $p): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($p['title']) ?></td>
          <td><?= e(type_label($p['type'])) ?></td>
          <td class="r"><?= number_format((int)$p['views']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2 class="rep-sec">6. ประกาศแยกตามประเภทอสังหาริมทรัพย์</h2>
  <?php if (!$categoryStats): ?>
    <p style="color:#94a3b8;">ยังไม่มีประกาศที่เผยแพร่ในระบบ</p>
  <?php else: ?>
    <table>
      <tr><th>ประเภท</th><th class="r">จำนวนประกาศที่เผยแพร่</th></tr>
      <?php foreach ($categoryStats as $c): ?>
        <tr>
          <td><?= e(category_label($c['property_category'])) ?></td>
          <td class="r"><?= number_format((int)$c['listings']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <div class="summary-note">
    📌 <strong>สรุปสำหรับผู้บริหาร:</strong> ช่วง <?= e($rangeLabel) ?> มีผู้เข้าชมไม่ซ้ำ <?= number_format($periodStats['uv']) ?> คน เปิดหน้าเว็บ <?= number_format($periodStats['page_views']) ?> ครั้ง และดูรายละเอียดประกาศ <?= number_format($periodStats['property_views']) ?> ครั้ง
    เว็บไซต์มีประกาศรวม <?= number_format($totalProps) ?> รายการ
    เผยแพร่แล้ว <?= number_format((int)$approvalCounts['approved']) ?> รายการ
    มีผู้เข้าชมไม่ซ้ำสะสม <?= number_format((int)$allTime['uv']) ?> คน
    จากยอดเข้าชมรวม <?= number_format((int)$allTime['pv']) ?> ครั้ง
  </div>

  <div class="foot">
    <span>รายงานนี้สร้างโดยระบบหลังบ้านของ <?= e(SITE_NAME) ?> — ใช้เพื่อการภายในเท่านั้น</span>
    <span>พิมพ์เมื่อ <?= e($generatedAt) ?> · <?= e(SITE_VERSION) ?></span>
  </div>
</div>

</body>
</html>

