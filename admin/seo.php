<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/seo_provinces.php';
require_admin('marketing', 'view');

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageMarketing = $isFounder || staff_can(db(), $staffId, 'marketing', 'edit') || staff_can(db(), $staffId, 'marketing', 'manage');

$pdo = db();

    // ── ตรวจสอบและสร้างตารางฐานข้อมูล SEO อัตโนมัติ ──
    ensure_seo_tables($pdo);

// จัดการคำขอ POST
if (request_is_post()) {
    csrf_check();
    if (!$canManageMarketing) {
        set_flash('สิทธิ์ถูกจำกัด: คุณไม่ได้รับสิทธิ์จัดการ SEO & Marketing');
        redirect('seo.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. Bulk Generate Keywords ตามจังหวัดที่เลือก
    if ($action === 'bulk_generate') {
        $selectedProvinces = $_POST['provinces'] ?? [];
        $selectedCategories = $_POST['categories'] ?? ['house', 'condo', 'land'];
        $types = $_POST['intent_types'] ?? ['short_tail', 'long_tail'];

        $catLabels = [
            'house' => 'บ้านเดี่ยว',
            'condo' => 'คอนโด',
            'townhouse' => 'ทาวน์โฮม',
            'land' => 'ที่ดิน',
            'commercial' => 'อาคารพาณิชย์'
        ];

        $inserted = 0;
        $stmt = $pdo->prepare('INSERT INTO seo_keywords (keyword, keyword_type, category, province, target_intent) VALUES (?, ?, ?, ?, ?)');

        foreach ($selectedProvinces as $prov) {
            $prov = trim((string)$prov);
            if ($prov === '') continue;

            foreach ($selectedCategories as $catKey) {
                $cLabel = $catLabels[$catKey] ?? $catKey;

                // Short-tail: ค้นหาทั่วไปใน Google SERP
                if (in_array('short_tail', $types, true)) {
                    $kw1 = "ขาย{$cLabel}{$prov}";
                    $stmt->execute([$kw1, 'short_tail', $catKey, $prov, 'ขาย']);
                    $inserted++;

                    $kw2 = "{$cLabel}ให้เช่า{$prov}";
                    $stmt->execute([$kw2, 'short_tail', $catKey, $prov, 'เช่า']);
                    $inserted++;
                }

                // Long-tail: คำค้นหาละเอียดสำหรับ AI Assistant / Voice Search
                if (in_array('long_tail', $types, true)) {
                    $aiKw = "หาซื้อ{$cLabel}ใน{$prov} ทำเลดี ราคาคุ้มค่า พร้อมอยู่";
                    $stmt->execute([$aiKw, 'long_tail', $catKey, $prov, 'ซื้อเพื่ออยู่อาศัย']);
                    $inserted++;

                    $aiKw2 = "{$cLabel}ราคาไม่เกิน 3 ล้าน {$prov} ใกล้สิ่งอำนวยความสะดวก";
                    $stmt->execute([$aiKw2, 'long_tail', $catKey, $prov, 'ค้นหาตามงบประมาณ']);
                    $inserted++;
                }
            }
        }

        set_flash("สร้างคีย์เวิร์ดเจาะจงจังหวัดอัตโนมัติสำเร็จแล้ว {$inserted} รายการ");
        redirect('seo.php');
    }

    // 1.1 Bulk Generate Keywords สำหรับ Prime CBD Zones & แนวรถไฟฟ้า
    if ($action === 'bulk_generate_cbd') {
        $selectedCbd = $_POST['cbd_zones'] ?? [];
        $selectedCategories = $_POST['cbd_categories'] ?? ['condo', 'house'];
        $selectedIntents = $_POST['cbd_intents'] ?? ['near_transit', 'price_budget', 'lifestyle', 'investment'];

        $catLabels = [
            'condo'      => 'คอนโด',
            'house'      => 'บ้าน',
            'townhouse'  => 'ทาวน์โฮม',
            'land'       => 'ที่ดิน',
            'commercial' => 'อาคารพาณิชย์'
        ];

        $inserted = 0;
        $stmt = $pdo->prepare('INSERT INTO seo_keywords (keyword, keyword_type, category, province, target_intent) VALUES (?, ?, ?, ?, ?)');

        foreach ($selectedCbd as $cbdKey) {
            if (!isset(PRIME_CBD_ZONES[$cbdKey])) continue;
            $cbd = PRIME_CBD_ZONES[$cbdKey];
            $zoneName = $cbd['name_th'];
            $prov = $cbd['province'];

            foreach ($selectedCategories as $catKey) {
                $cLabel = $catLabels[$catKey] ?? $catKey;

                // คีย์หลักประจำย่าน (Short-tail)
                $kw1 = "{$cLabel}{$zoneName}";
                $stmt->execute([$kw1, 'short_tail', $catKey, $prov, 'ทำเล CBD']);
                $inserted++;

                $kw2 = "{$cLabel}ให้เช่า{$zoneName}";
                $stmt->execute([$kw2, 'short_tail', $catKey, $prov, 'เช่าทำเล CBD']);
                $inserted++;

                $kw3 = "ขาย{$cLabel}{$zoneName}";
                $stmt->execute([$kw3, 'short_tail', $catKey, $prov, 'ขายทำเล CBD']);
                $inserted++;

                // คีย์เวิร์ดย่อยตามซอย / แขวงสำคัญ
                foreach (array_slice($cbd['sub_districts'], 0, 3) as $subD) {
                    $kwSub = "{$cLabel}{$subD}";
                    $stmt->execute([$kwSub, 'short_tail', $catKey, $prov, 'ย่อย ' . $subD]);
                    $inserted++;
                }

                // คีย์เวิร์ดตามสถานีรถไฟฟ้า
                foreach (array_slice($cbd['transit'], 0, 2) as $trn) {
                    $kwTrn = "{$cLabel}ติด {$trn}";
                    $stmt->execute([$kwTrn, 'short_tail', $catKey, $prov, 'แนวรถไฟฟ้า']);
                    $inserted++;
                }

                // คีย์เวิร์ดพฤติกรรม Long-tail AI สำหรับคำถามยอดฮิต
                if (in_array('near_transit', $selectedIntents, true)) {
                    $ai1 = "{$cLabel}{$zoneName} ติดรถไฟฟ้า BTS เดินทางสะดวก ไม่ต้องใช้รถ";
                    $stmt->execute([$ai1, 'long_tail', $catKey, $prov, 'AI Search รถไฟฟ้า']);
                    $inserted++;
                }
                if (in_array('price_budget', $selectedIntents, true)) {
                    $ai2 = "{$cLabel}{$zoneName} ราคาคุ้มค่า เหมาะสำหรับคนทำงานและลงทุน";
                    $stmt->execute([$ai2, 'long_tail', $catKey, $prov, 'AI Search งบประมาณ']);
                    $inserted++;
                }
                if (in_array('lifestyle', $selectedIntents, true)) {
                    $ai3 = "{$cLabel}{$zoneName} เลี้ยงสัตว์ได้ Pet Friendly พร้อมอยู่";
                    $stmt->execute([$ai3, 'long_tail', $catKey, $prov, 'AI Search Pet Friendly']);
                    $inserted++;
                }
                if (in_array('investment', $selectedIntents, true)) {
                    $ai4 = "{$cLabel}{$zoneName} ปล่อยเช่าง่าย Yield สูง ผู้เช่าต่างชาตินิยม";
                    $stmt->execute([$ai4, 'long_tail', $catKey, $prov, 'AI Search ลงทุน Yield']);
                    $inserted++;
                }
            }
        }

        set_flash("สร้างคีย์เวิร์ดเจาะจงย่านเศรษฐกิจ CBD & รถไฟฟ้า สำเร็จแล้ว {$inserted} รายการ");
        redirect('seo.php');
    }

    // 2. เพิ่มคีย์เวิร์ดแบบกำหนดเอง 1 รายการ
    if ($action === 'add_custom') {
        $keyword = trim((string)($_POST['keyword'] ?? ''));
        $keywordType = in_array($_POST['keyword_type'] ?? '', ['short_tail', 'long_tail'], true) ? $_POST['keyword_type'] : 'short_tail';
        $category = trim((string)($_POST['category'] ?? ''));
        $province = trim((string)($_POST['province'] ?? ''));
        $intent = trim((string)($_POST['target_intent'] ?? ''));

        if (mb_strlen($keyword) >= 2) {
            $stmt = $pdo->prepare('INSERT INTO seo_keywords (keyword, keyword_type, category, province, target_intent) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$keyword, $keywordType, $category, $province, $intent]);
            set_flash("เพิ่มคีย์เวิร์ด \"{$keyword}\" เรียบร้อยแล้ว");
        } else {
            set_flash("กรุณากรอกคีย์เวิร์ดให้ถูกต้อง");
        }
        redirect('seo.php');
    }

    // 3. สลับสถานะเปิด/ปิด
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE seo_keywords SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        set_flash("เปลี่ยนสถานะคีย์เวิร์ดเรียบร้อยแล้ว");
        redirect('seo.php');
    }

    // 4. ลบคีย์เวิร์ด
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM seo_keywords WHERE id = ?')->execute([$id]);
        set_flash("ลบคีย์เวิร์ดเรียบร้อยแล้ว");
        redirect('seo.php');
    }

    // 4.1 แปลงคำค้นหาจากผู้ใช้ (Search Query) เป็นคีย์เวิร์ดเป้าหมาย SEO ทันที
    if ($action === 'promote_search_query') {
        $sqId = (int)($_POST['query_id'] ?? 0);
        $sqRow = $pdo->prepare('SELECT * FROM search_queries WHERE id = ? LIMIT 1');
        $sqRow->execute([$sqId]);
        $queryData = $sqRow->fetch(PDO::FETCH_ASSOC);

        if ($queryData) {
            $kw = trim((string)$queryData['query']);
            $cat = trim((string)$queryData['category']);
            $kType = mb_strlen($kw) > 20 ? 'long_tail' : 'short_tail';

            $stmt = $pdo->prepare('INSERT INTO seo_keywords (keyword, keyword_type, category, province, target_intent) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$kw, $kType, $cat, '', 'จากผู้ใช้งานจริง']);

            $pdo->prepare('UPDATE search_queries SET is_promoted_seo = 1 WHERE id = ?')->execute([$sqId]);
            set_flash("บรรจุคำค้น \"{$kw}\" เข้าสู่ระบบ SEO เป้าหมายเรียบร้อยแล้ว");
        }
        redirect('seo.php#search-insights');
    }

    // 4.2 ลบประวัติคำค้นหา
    if ($action === 'delete_search_query') {
        $sqId = (int)($_POST['query_id'] ?? 0);
        $pdo->prepare('DELETE FROM search_queries WHERE id = ?')->execute([$sqId]);
        set_flash("ลบประวัติคำค้นหาเรียบร้อยแล้ว");
        redirect('seo.php#search-insights');
    }

    // 4.3 ล้างประวัติคำค้นหาทั้งหมด
    if ($action === 'clear_all_queries') {
        $pdo->exec('TRUNCATE TABLE search_queries');
        set_flash("ล้างประวัติคำค้นหาทั้งหมดเรียบร้อยแล้ว");
        redirect('seo.php#search-insights');
    }

    // 5. ล้างคีย์เวิร์ดทั้งหมด
    if ($action === 'clear_all') {
        $pdo->exec('TRUNCATE TABLE seo_keywords');
        set_flash("ล้างข้อมูลคีย์เวิร์ดทั้งหมดเรียบร้อยแล้ว");
        redirect('seo.php');
    }
}

// ข้อมูลสำหรับแสดงผล
$totalKeywords = (int)$pdo->query('SELECT COUNT(*) FROM seo_keywords')->fetchColumn();
$activeKeywords = (int)$pdo->query('SELECT COUNT(*) FROM seo_keywords WHERE is_active = 1')->fetchColumn();

// สถิติระบบบันทึกและวิเคราะห์คำค้นหาเรียลไทม์ (Search Queries Intelligence)
$totalSearchQueries = (int)$pdo->query('SELECT COUNT(*) FROM search_queries')->fetchColumn();
$totalSearchHits    = (int)$pdo->query('SELECT COALESCE(SUM(search_count), 0) FROM search_queries')->fetchColumn();
$recentSearchQueries = $pdo->query('SELECT * FROM search_queries ORDER BY last_searched_at DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
$topSearchQueries    = $pdo->query('SELECT * FROM search_queries ORDER BY search_count DESC, last_searched_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายการคีย์เวิร์ดล่าสุด 60 รายการ
$filterProv = trim((string)($_GET['filter_prov'] ?? ''));
$filterType = trim((string)($_GET['filter_type'] ?? ''));

$sql = 'SELECT * FROM seo_keywords WHERE 1=1';
$params = [];
if ($filterProv !== '') {
    $sql .= ' AND province = ?';
    $params[] = $filterProv;
}
if ($filterType !== '') {
    $sql .= ' AND keyword_type = ?';
    $params[] = $filterType;
}
$sql .= ' ORDER BY id DESC LIMIT 60';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keywords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่ม 77 จังหวัดตามภูมิภาค
$provincesByRegion = [
    'bangkok' => ['title' => t('กรุงเทพฯ และปริมณฑล', 'Bangkok & Greater Bangkok'), 'list' => []],
    'central' => ['title' => t('ภาคกลาง', 'Central Region'), 'list' => []],
    'north'   => ['title' => t('ภาคเหนือ', 'Northern Region'), 'list' => []],
    'northeast' => ['title' => t('ภาคตะวันออกเฉียงเหนือ (อีสาน)', 'Northeastern Region (Isan)'), 'list' => []],
    'east'    => ['title' => t('ภาคตะวันออก', 'Eastern Region'), 'list' => []],
    'west'    => ['title' => t('ภาคตะวันตก', 'Western Region'), 'list' => []],
    'south'   => ['title' => t('ภาคใต้', 'Southern Region'), 'list' => []],
];

foreach (THAI_PROVINCES as $provName => $p) {
    $reg = $p['region'] ?? 'central';
    if (!isset($provincesByRegion[$reg])) {
        $reg = 'central';
    }
    $provincesByRegion[$reg]['list'][] = $provName;
}

$token = csrf_token();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('ระบบ SEO & GEO AI เจาะ 77 จังหวัด', 'SEO & GEO AI 77 Provinces Engine')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .seo-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; padding:28px 32px; border-radius:24px; background:linear-gradient(135deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.35); margin-bottom:26px; box-shadow:0 14px 34px rgba(11,28,51,.12); }
    .seo-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .seo-hero h1 { margin:8px 0 4px; color:#fff; font-size:clamp(22px,3vw,30px); }
    .seo-hero .stat-badge { display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:999px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); font-size:12px; color:#E5CB96; }

    .seo-card { background:#fff; border:1px solid #e3e9ef; border-radius:22px; padding:26px 30px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.05); }
    .seo-card h2 { margin:0 0 6px; color:#0B1C33; font-size:19px; display:flex; align-items:center; gap:8px; }
    .seo-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; line-height:1.6; }

    .btn-gold { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 22px; border:0; border-radius:10px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-size:14px; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); text-decoration:none; transition:transform .16s, box-shadow .16s; }
    .btn-gold:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(197,160,89,.45); }

    .region-box { border:1px solid #e2e8f0; border-radius:14px; padding:16px; margin-bottom:14px; background:#fafbfc; }
    .region-box header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-weight:800; color:#0B1C33; font-size:13.5px; }
    .prov-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:8px; }
    .prov-label { display:flex; align-items:center; gap:6px; font-size:12.5px; color:#334155; cursor:pointer; padding:4px 6px; border-radius:6px; transition:background .12s; }
    .prov-label:hover { background:#f1f5f9; }
    .prov-label input { accent-color:#0B1C33; }

    .serp-preview { border:1px solid #dadce0; border-radius:14px; padding:18px; background:#fff; max-width:680px; font-family:Arial,sans-serif; margin-top:14px; }
    .serp-url { font-size:12px; color:#202124; display:flex; align-items:center; gap:6px; margin-bottom:4px; }
    .serp-title { font-size:18px; color:#1a0dab; line-height:1.3; font-weight:500; cursor:pointer; }
    .serp-title:hover { text-decoration:underline; }
    .serp-desc { font-size:13px; color:#4d5156; line-height:1.5; margin-top:4px; }

    .ai-preview { border:1px solid rgba(197,160,89,.4); border-radius:14px; padding:18px; background:linear-gradient(145deg, #faf9f6, #fff); max-width:680px; margin-top:14px; position:relative; }
    .ai-tag { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; color:#8C6B32; background:rgba(197,160,89,.15); padding:3px 10px; border-radius:999px; margin-bottom:8px; }

    .flash { background:#effaf3; color:#187243; border:1px solid #bbebcd; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:600; font-size:14px; }
    .mini { border:0; border-radius:8px; padding:6px 12px; font:inherit; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
    .mini.del { background:#FFF1F1; color:#ae3333; }
    .mini.live { background:rgba(197,160,89,.15); color:#8C6B32; border:1px solid rgba(197,160,89,.4); }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">

  <!-- Hero Header -->
  <div class="seo-hero">
    <div>
      <span class="eyebrow"><?= e(t('SEARCH ENGINE & AI GEO OPTIMIZATION', 'SEARCH ENGINE & AI GEO OPTIMIZATION')) ?></span>
      <h1><?= e(t('ศูนย์ควบคุม SEO & AI เจาะจง 77 จังหวัด', 'SEO & AI Geo Command Center (77 Provinces & CBD)')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;"><?= e(t('เพิ่มพลังติดหน้าแรก Google SERP และให้ผู้ช่วย AI (SearchGPT, Claude, Perplexity, Gemini) ดึงข้อมูลอสังหาฯ ของเราไปตอบทันที', 'Boost Google SERP ranking and empower AI Search Engines (SearchGPT, Claude, Perplexity, Gemini) to cite our property data.')) ?></p>
    </div>
    <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
      <span class="stat-badge">📍 <?= e(t('77 จังหวัดครอบคลุมทั้งระบบ', '77 Thai Provinces Covered')) ?></span>
      <span class="stat-badge">⚡ <?= number_format($totalKeywords) ?> <?= e(t('คีย์เวิร์ดเป้าหมาย (เปิดใช้งาน ' . number_format($activeKeywords) . ')', 'Target Keywords (Active: ' . number_format($activeKeywords) . ')')) ?></span>
      <span class="stat-badge" style="background:rgba(197,160,89,.22); color:#FFF; font-weight:700;">🔍 <?= number_format($totalSearchQueries) ?> <?= e(t('คำค้นพบใหม่ (ถูกค้นรวม ' . number_format($totalSearchHits) . ' ครั้ง)', 'New Queries Found (' . number_format($totalSearchHits) . ' hits)')) ?></span>
      <div style="display:flex; gap:8px; margin-top:4px;">
        <a href="#search-insights" style="font-size:12px; color:#E5CB96; text-decoration:underline;"><?= e(t('ดูคำค้นหาจากผู้ใช้จริง ↓', 'View User Search Insights ↓')) ?></a>
        <span style="color:rgba(255,255,255,.3);">|</span>
        <a href="../sitemap.xml" target="_blank" rel="noopener" style="font-size:12px; color:#E5CB96; text-decoration:underline;"><?= e(t('เปิด XML Sitemap ↗', 'Open XML Sitemap ↗')) ?></a>
        <span style="color:rgba(255,255,255,.3);">|</span>
        <a href="../robots.txt" target="_blank" rel="noopener" style="font-size:12px; color:#E5CB96; text-decoration:underline;"><?= e(t('ตรวจสอบ robots.txt ↗', 'Check robots.txt ↗')) ?></a>
      </div>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash">✅ <?= e($flash) ?></div><?php endif; ?>

  <!-- 1. ตัวจำลอง Google SERP & AI Overviews Snippet -->
  <section class="seo-card">
    <h2>👁️ <?= e(t('ตัวอย่างผลลัพธ์บน Google SERP & คำตอบ AI', 'Google SERP & AI Overviews Simulation')) ?></h2>
    <p class="intro"><?= e(t('เมื่อระบบเจาะคีย์เวิร์ดและสร้างหน้าหมวดหมู่อัตโนมัติ ผู้ใช้งานและบอทค้นหาจะเห็นโครงสร้างข้อมูลที่ถูกต้องตามมาตรฐาน Google Schema.org', 'Demonstrates how search crawlers and AI assistants interpret schema-structured landing pages.')) ?></p>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
      <!-- Google SERP Card -->
      <div>
        <strong style="font-size:13px; color:#475569;">1. รูปแบบบน Google Search (Desktop &amp; Mobile)</strong>
        <div class="serp-preview">
          <div class="serp-url">
            <span style="font-weight:bold; color:#0B1C33;">PropertyGO</span>
            <span>https://standardproperty.cloud › category › ขายบ้านเชียงใหม่</span>
          </div>
          <div class="serp-title">ขายบ้านเชียงใหม่ | รวมบ้านเดี่ยว บ้านมือสอง ทำเลดี อัปเดตล่าสุด</div>
          <div class="serp-desc">
            รวมประกาศขายบ้านเชียงใหม่ บ้านเดี่ยว ทาวน์โฮม พร้อมอยู่ ราคาคุ้มค่า เจาะทำเลเมืองเชียงใหม่ หางดง สันทราย แม่ริม สารภี ค้นหาและติดต่อเจ้าของประกาศได้ทันที...
          </div>
        </div>
      </div>

      <!-- AI Assistant Card -->
      <div>
        <strong style="font-size:13px; color:#475569;">2. รูปแบบที่ AI Overviews / SearchGPT ดึงไปตอบ</strong>
        <div class="ai-preview">
          <span class="ai-tag">✨ AI Overview / SGE Cited Source</span>
          <p style="margin:0; font-size:13px; line-height:1.6; color:#1e293b;">
            "จากการค้นหาบน <strong>PropertyGO</strong> สำหรับทำเลเชียงใหม่ พบประกาศบ้านเดี่ยวและอสังหาริมทรัพย์หลากหลายระดับราคา ตั้งแต่ไม่เกิน 2-5 ล้านบาท โดยมีทำเลยอดนิยม เช่น สันทราย และหางดง มีฟังก์ชันตรวจสอบโฉนดและติดต่อเจ้าของทรัพย์โดยตรง"
          </p>
          <div style="margin-top:10px; font-size:11.5px; color:#8C6B32; font-weight:700;">
            ✓ อ้างอิงจาก Schema: RealEstateAgent + FAQPage + GeoCoordinates
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 1.1 เครื่องมือสร้างคีย์เวิร์ดย่านเศรษฐกิจ CBD & แนวรถไฟฟ้า BTS/MRT (Top Prime Zones) -->
  <section class="seo-card" style="border:1.5px solid #C5A059; background:linear-gradient(180deg, #fff, #fdfbf7);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <h2>🏢 เจาะทำเลเศรษฐกิจ Prime CBD, รถไฟฟ้า &amp; แหล่งลงทุนยอดนิยม</h2>
      <span style="font-size:11.5px; font-weight:800; color:#8C6B32; background:rgba(197,160,89,.15); padding:4px 12px; border-radius:999px;">★ ย่านที่คนและ AI ค้นหามากที่สุด</span>
    </div>
    <p class="intro">เจาะจงย่านธุรกิจหลัก (สุขุมวิท, สาทร, สีลม, พระราม 9, อารีย์, บางนา ฯลฯ) พร้อม Intent การค้นหาจริง เช่น ติดรถไฟฟ้า BTS, เลี้ยงสัตว์ได้ Pet Friendly, งบประมาณ, หรือการลงทุน Yield สูง</p>

    <?php if ($canManageMarketing): ?>
      <form method="post" id="cbdBulkForm">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bulk_generate_cbd">

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; margin-bottom:18px; box-shadow:0 2px 8px rgba(11,28,51,.04);">
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px;">
            <div>
              <strong style="display:block; font-size:13px; margin-bottom:8px; color:#0B1C33;">1. ประเภทอสังหาฯ ในย่าน CBD:</strong>
              <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_categories[]" value="condo" checked> 🏢 คอนโด</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_categories[]" value="house" checked> 🏠 บ้าน / วิลล่า</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_categories[]" value="townhouse"> 🏘️ ทาวน์โฮม / โฮมออฟฟิศ</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_categories[]" value="commercial"> 🏬 อาคารพาณิชย์</label>
              </div>
            </div>

            <div>
              <strong style="display:block; font-size:13px; margin-bottom:8px; color:#0B1C33;">2. รูปแบบพฤติกรรมคำค้นหา (Search Intent):</strong>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <label style="font-size:12.5px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_intents[]" value="near_transit" checked> 🚇 แนวรถไฟฟ้า BTS/MRT</label>
                <label style="font-size:12.5px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_intents[]" value="price_budget" checked> 💰 งบประมาณคุ้มค่า</label>
                <label style="font-size:12.5px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_intents[]" value="lifestyle" checked> 🐾 เลี้ยงสัตว์ได้ / วิวสวย</label>
                <label style="font-size:12.5px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="cbd_intents[]" value="investment" checked> 📈 เหมาะลงทุน Yield สูง</label>
              </div>
            </div>
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <strong style="font-size:13.5px; color:#0B1C33;">3. เลือกย่าน CBD &amp; ทำเลทองเป้าหมาย:</strong>
          <div style="display:flex; gap:8px;">
            <button type="button" onclick="document.querySelectorAll('.cbd-cb').forEach(c=>c.checked=true)" style="border:1px solid #C5A059; background:rgba(197,160,89,.1); color:#8C6B32; padding:4px 12px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:700;">✓ เลือกทุกย่าน CBD</button>
            <button type="button" onclick="document.querySelectorAll('.cbd-cb').forEach(c=>c.checked=false)" style="border:1px solid #cbd5e1; background:#fff; padding:4px 12px; border-radius:6px; font-size:12px; cursor:pointer; color:#64748b;">✕ ยกเลิก</button>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px; margin-bottom:18px;">
          <?php foreach (PRIME_CBD_ZONES as $cbdKey => $cbd): ?>
            <div style="border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#fff; transition:border-color .15s;" onmouseover="this.style.borderColor='#C5A059'" onmouseout="this.style.borderColor='#e2e8f0'">
              <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                <input type="checkbox" name="cbd_zones[]" value="<?= e($cbdKey) ?>" class="cbd-cb" checked style="margin-top:3px; accent-color:#0B1C33;">
                <div>
                  <strong style="font-size:13.5px; color:#0B1C33;"><?= e($cbd['name_th']) ?></strong>
                  <span style="display:block; font-size:11.5px; color:#8C6B32; font-weight:600;"><?= e($cbd['province']) ?> · <?= e($cbd['name_en']) ?></span>
                  <p style="margin:4px 0 0; font-size:11.5px; color:#64748B; line-height:1.4;"><?= e($cbd['highlights']) ?></p>
                </div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex; gap:12px; align-items:center;">
          <button type="submit" class="btn-gold">⚡ เริ่มสร้างคีย์เวิร์ดเจาะจงย่าน CBD ทันที</button>
          <span style="font-size:12.5px; color:#64748B;">สร้างทั้งคำค้นสั้น คำค้นสถานีรถไฟฟ้า และประโยควิเคราะห์ AI ตอบตรงเป๊ะ</span>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <!-- 2. เครื่องมือสร้างคีย์เวิร์ดเจาะจงทีละหลายจังหวัด (Multi-Select Bulk Generator) -->
  <section class="seo-card">
    <h2>⚡ สร้างคีย์เวิร์ดเจาะจง 77 จังหวัด (Bulk Generator)</h2>
    <p class="intro">เลือกจังหวัดและประเภทอสังหาฯ ที่ต้องการเจาะจง ระบบจะสร้างคีย์เวิร์ดทั้งแบบคำค้นหาหลัก (Short-tail) และคำค้นหาสำหรับ AI (Long-tail) เข้าสู่ฐานข้อมูลทันที</p>

    <?php if ($canManageMarketing): ?>
      <form method="post" id="bulkForm">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="bulk_generate">

        <!-- ตัวเลือกประเภทและ Intent -->
        <div style="background:#F8FAFC; border:1px solid #e2e8f0; border-radius:14px; padding:18px; margin-bottom:18px;">
          <div style="display:flex; flex-wrap:wrap; gap:24px;">
            <div>
              <strong style="display:block; font-size:13px; margin-bottom:8px; color:#0B1C33;">ประเภทอสังหาฯ ที่ต้องการเจาะ:</strong>
              <div style="display:flex; flex-wrap:wrap; gap:12px;">
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="categories[]" value="house" checked> 🏠 บ้านเดี่ยว</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="categories[]" value="condo" checked> 🏢 คอนโด</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="categories[]" value="townhouse" checked> 🏘️ ทาวน์โฮม</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="categories[]" value="land" checked> 🌳 ที่ดิน</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="categories[]" value="commercial"> 🏬 อาคารพาณิชย์</label>
              </div>
            </div>

            <div>
              <strong style="display:block; font-size:13px; margin-bottom:8px; color:#0B1C33;">รูปแบบเป้าหมายคีย์เวิร์ด:</strong>
              <div style="display:flex; flex-wrap:wrap; gap:12px;">
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="intent_types[]" value="short_tail" checked> 🎯 Short-tail (ติดหน้าแรก Google SERP)</label>
                <label style="font-size:13px; display:flex; align-items:center; gap:5px;"><input type="checkbox" name="intent_types[]" value="long_tail" checked> 🤖 Long-tail (คำถามผู้ช่วย AI &amp; Voice)</label>
              </div>
            </div>
          </div>
        </div>

        <!-- รายการ 77 จังหวัดแยกตามภูมิภาค -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
          <strong style="font-size:14px; color:#0B1C33;">เลือกจังหวัดที่ต้องการสร้าง:</strong>
          <div style="display:flex; gap:8px;">
            <button type="button" onclick="toggleAllProvinces(true)" style="border:1px solid #cbd5e1; background:#fff; padding:5px 12px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:700;">✓ เลือกทั้งหมด 77 จังหวัด</button>
            <button type="button" onclick="toggleAllProvinces(false)" style="border:1px solid #cbd5e1; background:#fff; padding:5px 12px; border-radius:6px; font-size:12px; cursor:pointer; color:#64748b;">✕ ยกเลิกทั้งหมด</button>
          </div>
        </div>

        <div style="max-height:480px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:14px; padding:14px;">
          <?php foreach ($provincesByRegion as $regKey => $regInfo): ?>
            <div class="region-box">
              <header>
                <span>📍 <?= e($regInfo['title']) ?> (<?= count($regInfo['list']) ?> จังหวัด)</span>
                <button type="button" onclick="toggleRegion('<?= e($regKey) ?>', true)" style="border:0; background:none; color:#C5A059; font-weight:700; font-size:12px; cursor:pointer;">เลือกทั้งภาค</button>
              </header>
              <div class="prov-grid" id="region-<?= e($regKey) ?>">
                <?php foreach ($regInfo['list'] as $provName): ?>
                  <label class="prov-label">
                    <input type="checkbox" name="provinces[]" value="<?= e($provName) ?>" class="prov-cb cb-<?= e($regKey) ?>">
                    <span><?= e($provName) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:18px; display:flex; gap:12px; align-items:center;">
          <button type="submit" class="btn-gold">⚡ เริ่มสร้างคีย์เวิร์ดเจาะจงจังหวัดทันที</button>
          <span style="font-size:12.5px; color:#64748B;">ระบบจะบันทึกเข้าฐานข้อมูลและพร้อมให้บอท Google / AI ตรวจจับ</span>
        </div>
      </form>

      <script>
        function toggleAllProvinces(checked) {
          document.querySelectorAll('.prov-cb').forEach(cb => cb.checked = checked);
        }
        function toggleRegion(regKey, checked) {
          document.querySelectorAll('.cb-' + regKey).forEach(cb => cb.checked = checked);
        }
      </script>
    <?php else: ?>
      <div style="font-size:13px;color:#64748B;font-style:italic;padding:14px;background:#F9F9FB;border-radius:12px;">
        🔒 ดูข้อมูลได้อย่างเดียว (ต้องได้รับสิทธิ์จาก Founder &amp; CEO เพื่อสร้างหรือจัดการ SEO)
      </div>
    <?php endif; ?>
  </section>

  <!-- 2.5 ระบบตรวจจับและวิเคราะห์คำค้นหาเรียลไทม์ (Live Search Queries Intelligence) -->
  <section class="seo-card" id="search-insights" style="border:1.5px solid #2563eb; background:linear-gradient(180deg, #fff, #f8faff);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
      <div>
        <h2 style="color:#1e40af;">📡 <?= e(t('คำค้นหาและพฤติกรรมจริงจากผู้เข้าชม (Search Intelligence)', 'Live Search Queries & User Behavior Intelligence')) ?></h2>
        <span style="font-size:13px; color:#64748B;"><?= e(t('บันทึกทุกคำค้นหาใหม่ทันทีเมื่อมีคนค้นหาในเว็บ หรือคลิกเข้ามาจาก Google เพื่อให้คุณแปลงเป็นคีย์เวิร์ด SEO เพิ่มโอกาสติดหน้าแรกเรื่อย ๆ', 'Logs incoming user queries in real-time from site searches and Google referrals — convert them to active SEO landing pages in one click.')) ?></span>
      </div>
      <?php if ($canManageMarketing && $totalSearchQueries > 0): ?>
        <form method="post" onsubmit="return confirm('<?= e(t('คุณแน่ใจหรือไม่ว่าต้องการล้างประวัติคำค้นหาทั้งหมด?', 'Clear all recorded search queries?')) ?>');" style="margin:0;">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="clear_all_queries">
          <button type="submit" class="mini del" style="padding:6px 12px;">🗑️ <?= e(t('ล้างประวัติคำค้นหา', 'Clear query logs')) ?></button>
        </form>
      <?php endif; ?>
    </div>

    <!-- สรุปสถิติคำค้น -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:18px;">
      <div style="padding:14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px;">
        <span style="font-size:11px; font-weight:800; color:#1d4ed8; text-transform:uppercase;"><?= e(t('คำค้นไม่ซ้ำทั้งหมด', 'Unique Queries')) ?></span>
        <div style="font-size:24px; font-weight:800; color:#1e3a8a; margin-top:2px;"><?= number_format($totalSearchQueries) ?> <span style="font-size:13px; font-weight:normal;"><?= e(t('คำ', 'keywords')) ?></span></div>
      </div>
      <div style="padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
        <span style="font-size:11px; font-weight:800; color:#15803d; text-transform:uppercase;"><?= e(t('ยอดคลิกค้นหารวม', 'Total Search Hits')) ?></span>
        <div style="font-size:24px; font-weight:800; color:#14532d; margin-top:2px;"><?= number_format($totalSearchHits) ?> <span style="font-size:13px; font-weight:normal;"><?= e(t('ครั้ง', 'hits')) ?></span></div>
      </div>
      <div style="padding:14px; background:#fefce8; border:1px solid #fef08a; border-radius:12px;">
        <span style="font-size:11px; font-weight:800; color:#a16207; text-transform:uppercase;"><?= e(t('ระบบทำงานอัตโนมัติ', 'Automation Engine')) ?></span>
        <div style="font-size:13.5px; font-weight:700; color:#713f12; margin-top:6px;">✓ <?= e(t('บันทึกเรียลไทม์ 24 ชม.', 'Real-time 24/7 logging')) ?></div>
      </div>
    </div>

    <?php if (!$recentSearchQueries): ?>
      <div style="text-align:center; padding:32px 20px; background:#f8fafc; border-radius:14px; color:#64748B;">
        <div style="font-size:28px; margin-bottom:6px;">🔍</div>
        <p style="margin:0; font-size:14px; font-weight:600;"><?= e(t('ระบบกำลังเฝ้ารอคำค้นหาใหม่อยู่ครับ', 'Awaiting new search queries')) ?></p>
        <span style="font-size:12.5px;"><?= e(t('เมื่อผู้ใช้พิมพ์คำค้นหาในหน้าแรก หรือหน้าค้นหา (`search.php`) ระบบจะบันทึกคำและความถี่ลงในตารางนี้ทันที', 'When visitors search properties or locations, queries and frequency counts will automatically appear here.')) ?></span>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#f1f5f9; text-align:left; border-bottom:2px solid #cbd5e1;">
              <th style="padding:10px 12px;"><?= e(t('คำที่ผู้ใช้ค้นหา', 'User Query')) ?></th>
              <th style="padding:10px 12px; text-align:center;"><?= e(t('จำนวนครั้ง', 'Frequency')) ?></th>
              <th style="padding:10px 12px; text-align:center;"><?= e(t('ผลลัพธ์ในเว็บ', 'Listings Found')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('แหล่งที่มา', 'Source')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('ค้นหาล่าสุด', 'Last Searched')) ?></th>
              <th style="padding:10px 12px; text-align:right;"><?= e(t('การนำไปใช้', 'Actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSearchQueries as $sq): ?>
              <tr style="border-bottom:1px solid #f1f5f9; background:#fff;">
                <td style="padding:10px 12px; font-weight:700; color:#0f172a;">
                  <span style="font-size:13.5px;"><?= e($sq['query']) ?></span>
                  <?php if (!empty($sq['category'])): ?>
                    <span style="display:inline-block; font-size:11px; padding:1px 6px; border-radius:4px; background:#f1f5f9; color:#475569; margin-left:4px; font-weight:normal;">
                      <?= e(category_label_bi($sq['category'])) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 12px; text-align:center;">
                  <span style="display:inline-block; padding:2px 10px; border-radius:999px; font-weight:800; font-size:12px; background:#eff6ff; color:#1d4ed8;">
                    <?= number_format($sq['search_count']) ?>
                  </span>
                </td>
                <td style="padding:10px 12px; text-align:center;">
                  <span style="color:<?= $sq['results_count'] > 0 ? '#15803d' : '#b91c1c' ?>; font-weight:700;">
                    <?= $sq['results_count'] > 0 ? ("{$sq['results_count']} " . e(t('รายการ', 'listings'))) : ("0 " . e(t('รายการ', 'listings'))) ?>
                  </span>
                </td>
                <td style="padding:10px 12px; font-size:12px; color:#475569;">
                  <?= $sq['source'] === 'external' ? '🌐 Google / External' : '🔍 ' . e(t('ในเว็บ', 'On-site Search')) ?>
                </td>
                <td style="padding:10px 12px; font-size:11.5px; color:#64748b;">
                  <?= e(date('d/m/Y H:i', strtotime($sq['last_searched_at']))) ?>
                </td>
                <td style="padding:10px 12px; text-align:right;">
                  <div style="display:inline-flex; gap:6px; align-items:center;">
                    <a href="../search.php?q=<?= urlencode($sq['query']) ?>" target="_blank" rel="noopener" class="mini" style="background:#f8fafc; border:1px solid #cbd5e1; color:#334155;">⌕ <?= e(t('ผลค้นหา', 'Results')) ?></a>
                    <?php if ($canManageMarketing): ?>
                      <?php if (!empty($sq['is_promoted_seo'])): ?>
                        <span class="mini" style="background:#dcfce7; color:#15803d; border:1px solid #86efac;">✓ <?= e(t('บรรจุเป็น SEO แล้ว', 'Promoted to SEO')) ?></span>
                      <?php else: ?>
                        <form method="post" style="display:inline; margin:0;">
                          <input type="hidden" name="csrf" value="<?= e($token) ?>">
                          <input type="hidden" name="action" value="promote_search_query">
                          <input type="hidden" name="query_id" value="<?= (int)$sq['id'] ?>">
                          <button type="submit" class="mini live" style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;" title="<?= e(t('เพิ่มคำนี้เข้าเป็นคีย์เวิร์ด SEO ของเว็บทันที', 'Promote this search query to active SEO landing page')) ?>">
                            ⚡ <?= e(t('ทำเป็น SEO', 'Promote to SEO')) ?>
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('<?= e(t('ลบคำค้นหานี้?', 'Delete this search query?')) ?>');" style="display:inline; margin:0;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete_search_query">
                        <input type="hidden" name="query_id" value="<?= (int)$sq['id'] ?>">
                        <button type="submit" class="mini del" title="<?= e(t('ลบคำค้นหา', 'Delete query')) ?>">✕</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- 3. เพิ่มคีย์เวิร์ดแบบกำหนดเอง (Custom Keyword / Long-tail AI Query) -->
  <section class="seo-card">
    <h2>➕ เพิ่มคีย์เวิร์ดแบบกำหนดเอง (Custom AI Query)</h2>
    <p class="intro">ใส่คีย์เวิร์ดยอดฮิตหรือประโยคที่คาดว่าผู้ค้นหาจะถามใน ChatGPT / Perplexity เช่น "คอนโดทองหล่อ เลี้ยงสัตว์ได้ ไม่เกิน 5 ล้าน"</p>

    <?php if ($canManageMarketing): ?>
      <form method="post" style="display:grid; grid-template-columns:2fr 1.2fr 1fr 1fr auto; gap:10px; align-items:end;">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="add_custom">

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#334155;">คำค้นหา / ประโยคเป้าหมาย</label>
          <input type="text" name="keyword" placeholder="เช่น คอนโดสุขุมวิท เลี้ยงสัตว์ได้ หรือ บ้านเดี่ยวหัวหิน" required style="width:100%; min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#334155;">รูปแบบคำค้นหา</label>
          <select name="keyword_type" style="width:100%; min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
            <option value="short_tail">🎯 Short-tail (ทั่วไป)</option>
            <option value="long_tail">🤖 Long-tail (ประโยคค้นหา AI)</option>
          </select>
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#334155;">หมวดหมู่</label>
          <select name="category" style="width:100%; min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
            <option value="">ทุกหมวด</option>
            <option value="condo">คอนโด</option>
            <option value="house">บ้านเดี่ยว</option>
            <option value="townhouse">ทาวน์โฮม</option>
            <option value="land">ที่ดิน</option>
            <option value="commercial">อาคารพาณิชย์</option>
          </select>
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px; color:#334155;">จังหวัด</label>
          <input type="text" name="province" placeholder="เช่น เชียงใหม่, ภูเก็ต" style="width:100%; min-height:42px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
        </div>

        <button type="submit" class="btn-gold" style="min-height:42px; white-space:nowrap;">➕ เพิ่มคีย์เวิร์ด</button>
      </form>
    <?php endif; ?>
  </section>

  <!-- 4. ตารางรายการคีย์เวิร์ดในระบบ -->
  <section class="seo-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
      <div>
        <h2>📋 รายการคีย์เวิร์ดในระบบ (แสดงล่าสุด <?= count($keywords) ?> รายการ)</h2>
        <span style="font-size:12.5px; color:#64748B;">คลิกที่ "ทดสอบลิงก์หน้าเว็บ" เพื่อดูหน้าหมวดหมู่ที่บอท Google และ AI เห็นจริง</span>
      </div>
      <?php if ($canManageMarketing && $totalKeywords > 0): ?>
        <form method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการล้างคีย์เวิร์ดทั้งหมด?');" style="margin:0;">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="clear_all">
          <button type="submit" class="mini del" style="padding:8px 14px;">🗑️ ล้างคีย์เวิร์ดทั้งหมด</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- ฟิลเตอร์ตัวกรอง -->
    <form method="get" style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
      <input type="text" name="filter_prov" value="<?= e($filterProv) ?>" placeholder="กรองตามจังหวัด..." style="min-height:38px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px;">
      <select name="filter_type" style="min-height:38px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px;">
        <option value="">ทุกรูปแบบ</option>
        <option value="short_tail" <?= $filterType==='short_tail'?'selected':'' ?>>Short-tail</option>
        <option value="long_tail" <?= $filterType==='long_tail'?'selected':'' ?>>Long-tail AI</option>
      </select>
      <button type="submit" style="min-height:38px; padding:0 14px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; font-weight:700; cursor:pointer;">⌕ กรอง</button>
      <?php if ($filterProv !== '' || $filterType !== ''): ?>
        <a href="seo.php" class="mini" style="background:#F1F5F9; color:#475569; padding:0 12px; align-self:center; height:38px;">ล้างตัวกรอง</a>
      <?php endif; ?>
    </form>

    <?php if (!$keywords): ?>
      <p style="text-align:center; padding:30px; background:#F8FAFC; border-radius:12px; color:#64748B; font-size:13.5px;">
        ยังไม่มีคีย์เวิร์ดในระบบ — สามารถใช้เครื่องมือ Bulk Generator ด้านบนเพื่อสร้างอัตโนมัติสำหรับ 77 จังหวัดได้เลยครับ
      </p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#F8FAFC; text-align:left; border-bottom:2px solid #e2e8f0;">
              <th style="padding:10px 12px;">ID</th>
              <th style="padding:10px 12px;">คีย์เวิร์ดเป้าหมาย</th>
              <th style="padding:10px 12px;">ประเภท</th>
              <th style="padding:10px 12px;">จังหวัด</th>
              <th style="padding:10px 12px;">สถานะ</th>
              <th style="padding:10px 12px; text-align:right;">การทำงาน</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($keywords as $kw): ?>
              <?php
                $targetUrl = '../category.php?page=' . urlencode($kw['keyword']);
              ?>
              <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px 12px; color:#64748B;">#<?= (int)$kw['id'] ?></td>
                <td style="padding:10px 12px; font-weight:bold; color:#0B1C33;">
                  <?= e($kw['keyword']) ?>
                  <?php if (!empty($kw['target_intent'])): ?>
                    <span style="display:block; font-size:11px; color:#64748B; font-weight:normal;"><?= e($kw['target_intent']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 12px;">
                  <span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; background:<?= $kw['keyword_type']==='long_tail'?'rgba(197,160,89,.15)':'#EBF3FE' ?>; color:<?= $kw['keyword_type']==='long_tail'?'#8C6B32':'#1A73E8' ?>;">
                    <?= $kw['keyword_type']==='long_tail' ? '🤖 Long-tail AI' : '🎯 Short-tail' ?>
                  </span>
                </td>
                <td style="padding:10px 12px; color:#475569;"><?= e($kw['province'] ?: 'ทั่วไทย') ?></td>
                <td style="padding:10px 12px;">
                  <span style="color:<?= $kw['is_active'] ? '#15803d' : '#94a3b8' ?>; font-weight:700;">
                    <?= $kw['is_active'] ? '● เปิดใช้งาน' : '○ ปิด' ?>
                  </span>
                </td>
                <td style="padding:10px 12px; text-align:right;">
                  <div style="display:inline-flex; gap:6px; align-items:center;">
                    <a href="<?= e($targetUrl) ?>" target="_blank" rel="noopener" class="mini live" title="เปิดหน้าหมวดหมู่นี้เพื่อดูผล SEO จริง">👁️ หน้าเว็บ ↗</a>
                    <?php if ($canManageMarketing): ?>
                      <form method="post" style="display:inline; margin:0;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$kw['id'] ?>">
                        <button type="submit" class="mini" style="background:#F1F5F9; color:#475569;" title="เปิด/ปิดการใช้งาน">
                          <?= $kw['is_active'] ? 'ปิด' : 'เปิด' ?>
                        </button>
                      </form>
                      <form method="post" onsubmit="return confirm('ต้องการลบคีย์เวิร์ดนี้ใช่หรือไม่?');" style="display:inline; margin:0;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$kw['id'] ?>">
                        <button type="submit" class="mini del" title="ลบคีย์เวิร์ด">✕</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
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
