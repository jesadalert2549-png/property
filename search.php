<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
require_once __DIR__ . '/includes/mobile_nav.php';
current_lang();

$q        = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) && is_string($_GET['category']) ? $_GET['category'] : '';
$type     = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';

$where  = ["approval = 'approved'"];
$params = [];

if ($q !== '') {
    $where[] = '(title LIKE ? OR location LIKE ?)';
    $term = '%' . addcslashes($q, '%_\\') . '%';
    $params[] = $term;
    $params[] = $term;
}
if (array_key_exists($category, PROPERTY_CATEGORIES)) {
    $where[]  = 'property_category = ?';
    $params[] = $category;
}
if (in_array($type, ['sale', 'rent'], true)) {
    $where[]  = 'type = ?';
    $params[] = $type;
}

$st = db()->prepare(
    'SELECT properties.id, properties.title, properties.type, properties.status, properties.price, properties.original_price, properties.location, properties.property_category, properties.contact_name, properties.video_file,
            u.full_name AS publisher_name,
            u.avatar_path AS publisher_avatar,
            u.agent_status AS publisher_agent_status,
            (SELECT i.filename FROM property_images i
             WHERE i.property_id = properties.id
             ORDER BY i.sort_order, i.id LIMIT 1) AS cover
     FROM properties
     LEFT JOIN users u ON u.id = properties.user_id
     WHERE ' . implode(' AND ', $where) . "
     ORDER BY (properties.status = 'done'), properties.created_at DESC
     LIMIT 100"
);
$st->execute($params);
$items = $st->fetchAll();

// ── บันทึกคำค้นหาลงระบบวิเคราะห์ SEO เมื่อมีการค้นหาจริง ──
if ($q !== '' && empty($_GET['no_log'])) {
    require_once __DIR__ . '/includes/seo_provinces.php';
    $searchSource = 'internal';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (preg_match('/google\.|bing\.|yahoo\.|duckduckgo\./i', $ref)) {
        $searchSource = 'external';
    }
    record_search_query($q, count($items), $category, $type, $searchSource);
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head((is_en() ? 'Search Properties | ' : 'ค้นหาอสังหาริมทรัพย์ | ') . SITE_NAME, is_en() ? 'Search property listings for sale and rent by location and type' : 'ค้นหาประกาศขายและให้เช่าอสังหาริมทรัพย์ตามทำเลและประเภท', page_url('search.php'), false); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    .card .thumb {
      position: relative;
      overflow: hidden;
      border-radius: 14px;
      background: #0B1C33;
    }
    .thumb.has-hover-video { position: relative; overflow: hidden; background: #0B1C33; }
    .thumb .card-video-stream {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      z-index: 2;
      opacity: 1;
      background: #0B1C33;
    }
    .thumb-badges-left {
      position: absolute;
      top: 10px;
      left: 10px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      z-index: 10;
      pointer-events: none;
    }
    .thumb-badges-left .badge {
      position: static !important;
      display: inline-flex !important;
      align-items: center !important;
      padding: 3px 8px !important;
      border-radius: 6px !important;
      font-size: 10px !important;
      font-weight: 700 !important;
      margin: 0 !important;
      border: 1px solid rgba(255, 255, 255, 0.2) !important;
      backdrop-filter: blur(4px) !important;
      -webkit-backdrop-filter: blur(4px) !important;
    }
    .thumb-actions-right {
      position: absolute;
      top: 10px;
      right: 10px;
      display: flex;
      align-items: center;
      gap: 6px;
      z-index: 10;
    }
    .thumb-actions-right .card-video-pill {
      position: static !important;
      top: auto !important;
      right: auto !important;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3.5px 9px;
      border-radius: 999px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: .02em;
      background: rgba(11,28,51,.85);
      color: #F8D888;
      border: 1px solid rgba(197,160,89,.4);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      box-shadow: 0 3px 8px rgba(0,0,0,.22);
      transition: all .22s cubic-bezier(0.34, 1.56, 0.64, 1);
      cursor: pointer;
      user-select: none;
      margin: 0 !important;
    }
    .thumb-actions-right .card-video-pill:hover { background: #0B1C33; border-color: #F8D888; transform: scale(1.08); box-shadow: 0 4px 12px rgba(197,160,89,.35); }
    .thumb-actions-right .card-video-pill:active { transform: scale(0.95); }
    .thumb-actions-right .card-video-pill.sound-active { background: #15803d !important; border-color: #4ade80 !important; color: #ffffff !important; box-shadow: 0 4px 14px rgba(21,128,61,.5) !important; }
    .thumb-actions-right .card-video-pill.sound-active svg { color: #ffffff !important; }
    .thumb-actions-right .card-fav-heart-btn {
      position: static !important;
      top: auto !important;
      right: auto !important;
      width: 30px !important;
      height: 30px !important;
      border-radius: 50% !important;
      background: rgba(255, 255, 255, 0.88) !important;
      backdrop-filter: blur(6px) !important;
      -webkit-backdrop-filter: blur(6px) !important;
      border: 1px solid rgba(255, 255, 255, 0.8) !important;
      display: grid !important;
      place-items: center !important;
      font-size: 14px !important;
      cursor: pointer !important;
      box-shadow: 0 2px 6px rgba(0,0,0,0.14) !important;
      transition: transform 0.2s, background 0.2s !important;
      margin: 0 !important;
    }
    .thumb-actions-right .card-fav-heart-btn:hover {
      transform: scale(1.12) !important;
      background: #fff !important;
    }
    .thumb-actions-right .card-fav-heart-btn svg {
      width: 16px !important;
      height: 16px !important;
    }
    .card .info {
      padding: 14px 16px 16px;
    }
    .card-publisher-line {
      margin-bottom: 6px;
    }
    .card-publisher-line .publisher-mini {
      position: static !important;
      left: auto !important;
      bottom: auto !important;
      display: inline-flex !important;
      padding: 2px 8px 2px 3px !important;
      background: #f8fafc !important;
      border: 1px solid #e2e8f0 !important;
      box-shadow: none !important;
      max-width: 100% !important;
    }
    .card-publisher-line .publisher-mini-avatar {
      width: 20px !important;
      height: 20px !important;
      flex: 0 0 20px !important;
      font-size: 10px !important;
    }
    .card-publisher-line .publisher-mini-name {
      font-size: 11px !important;
      color: #475569 !important;
      font-weight: 700 !important;
    }
    .card-publisher-line .publisher-mini-loc {
      font-size: 10.5px !important;
      color: #94a3b8 !important;
    }
    .card-price-stats-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 6px;
    }
    .card-stats-slot .property-card-stats-badge {
      position: static !important;
      bottom: auto !important;
      right: auto !important;
      display: inline-flex !important;
      padding: 2px 7px !important;
      font-size: 10px !important;
      background: #f1f5f9 !important;
      color: #64748b !important;
      border: 1px solid #e2e8f0 !important;
      box-shadow: none !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
    }
    .card-stats-slot .property-card-stats-badge svg {
      stroke: #64748b !important;
    }
    .card-stats-slot .stat-badge-views,
    .card-stats-slot .stat-badge-clicks {
      color: #64748b !important;
    }
  </style>
  <?php render_mobile_nav_assets(); ?>
</head>
<body class="search-page-body">

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <?php render_mobile_nav(); ?>
    <nav class="site-nav search-site-nav" id="searchSiteNav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?> <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="notifications.php">🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?> <span class="sp-notif-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <a href="submit.php" class="nav-cta"><?= e(t('ลงประกาศฟรี', 'Post a free listing')) ?></a>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="section">
  <div class="container search-page">
    <?php render_banner_slot('search_top', 'compact'); ?>
    <div class="search-page-heading">
      <div>
        <p class="search-page-eyebrow"><?= e(t('ค้นหาบ้านและอสังหาริมทรัพย์', 'Search homes and properties')) ?></p>
        <h1><?= e(t('ค้นหาทรัพย์ที่ใช่', 'Find the right property')) ?></h1>
        <p><?= e(t('ค้นจากชื่อประกาศ ทำเล และเลือกประเภทที่ต้องการได้เลย', 'Search by listing title, location, and choose the type you want.')) ?></p>
      </div>
      <span class="search-result-count"><strong><?= count($items) ?></strong><span><?= e(t('ประกาศที่แสดง', 'listings shown')) ?></span></span>
    </div>

    <form method="get" action="search.php" class="search-bar search-page-form" role="search" aria-label="<?= e(t('ค้นหาอสังหาริมทรัพย์', 'Search properties')) ?>">
      <label class="search-field search-keyword">
        <span><?= e(t('คำค้นหรือทำเล', 'Keyword or location')) ?></span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('เช่น คอนโดสุขุมวิท, บ้านลาดพร้าว', 'e.g. Sukhumvit condo, Lat Phrao house')) ?>" autocomplete="off">
      </label>
      <label class="search-field">
        <span><?= e(t('ประเภททรัพย์', 'Property type')) ?></span>
        <select name="category">
        <option value=""><?= e(t('ทุกประเภท', 'All types')) ?></option>
        <?php foreach (PROPERTY_CATEGORIES as $key => $label):
          if ($key === 'other') continue; ?>
          <option value="<?= e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= e(category_label_bi($key)) ?></option>
        <?php endforeach; ?>
        </select>
      </label>
      <label class="search-field">
        <span><?= e(t('ต้องการ', 'Looking to')) ?></span>
        <select name="type">
        <option value="" <?= $type === '' ? 'selected' : '' ?>><?= e(t('ขายและเช่า', 'For sale & rent')) ?></option>
        <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>><?= e(t('ขาย', 'For sale')) ?></option>
        <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>><?= e(t('ให้เช่า', 'For rent')) ?></option>
        </select>
      </label>
      <div class="search-actions">
        <button type="submit" class="btn"><span aria-hidden="true">⌕</span> <?= e(t('ค้นหา', 'Search')) ?></button>
        <?php if ($q !== '' || $category !== '' || $type !== ''): ?><a href="search.php" class="search-reset"><?= e(t('ล้างตัวกรอง', 'Clear filters')) ?></a><?php endif; ?>
      </div>
    </form>

    <div class="search-hot-provinces" style="display:flex; flex-wrap:wrap; gap:6px; margin:14px 0 24px; align-items:center;">
      <span style="font-size:12px; font-weight:700; color:#C5A059;">🏢 <?= e(t('ย่าน CBD & รถไฟฟ้า:', 'Prime CBD & Transit:')) ?></span>
      <?php
      $cbdFastTags = [
          'สุขุมวิท' => t('สุขุมวิท', 'Sukhumvit'),
          'ทองหล่อ'  => t('ทองหล่อ', 'Thong Lo'),
          'เอกมัย'   => t('เอกมัย', 'Ekkamai'),
          'สาทร'     => t('สาทร', 'Sathorn'),
          'สีลม'     => t('สีลม', 'Silom'),
          'พระราม 9' => t('พระราม 9', 'Rama 9'),
          'อารีย์'    => t('อารีย์', 'Ari'),
          'ลาดพร้าว' => t('ลาดพร้าว', 'Ladprao'),
          'บางนา'    => t('บางนา', 'Bangna'),
          'นิมมาน'   => t('นิมมานฯ (เชียงใหม่)', 'Nimman (Chiang Mai)'),
          'พัทยา'    => t('พัทยา', 'Pattaya'),
          'หัวหิน'    => t('หัวหิน', 'Hua Hin')
      ];
      foreach ($cbdFastTags as $kw => $lbl):
      ?>
        <a href="search.php?q=<?= urlencode($kw) ?>" style="font-size:12px; font-weight:700; padding:4px 10px; border-radius:999px; background:#0B1C33; color:#fff; text-decoration:none; transition:all .15s;" onmouseover="this.style.background='#C5A059'" onmouseout="this.style.background='#0B1C33'"><?= e($lbl) ?></a>
      <?php endforeach; ?>
      <span style="font-size:12px; font-weight:700; color:#64748B; margin-left:8px;">📍 <?= e(t('จังหวัดยอดนิยม:', 'Popular Provinces:')) ?></span>
      <?php
      $hotProvs = [
          'กรุงเทพมหานคร' => t('กรุงเทพฯ', 'Bangkok'),
          'เชียงใหม่'     => t('เชียงใหม่', 'Chiang Mai'),
          'ภูเก็ต'        => t('ภูเก็ต', 'Phuket'),
          'ชลบุรี'        => t('ชลบุรี (พัทยา)', 'Chonburi (Pattaya)'),
          'นนทบุรี'       => t('นนทบุรี', 'Nonthaburi'),
          'สมุทรปราการ'   => t('สมุทรปราการ', 'Samut Prakan'),
          'ปทุมธานี'       => t('ปทุมธานี', 'Pathum Thani'),
          'ระยอง'         => t('ระยอง', 'Rayong'),
      ];
      foreach ($hotProvs as $hpKw => $hpLbl):
      ?>
        <a href="search.php?q=<?= urlencode($hpKw) ?>" style="font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px; background:#fff; border:1px solid #e2e8f0; color:#0B1C33; text-decoration:none;" onmouseover="this.style.borderColor='#C5A059';this.style.color='#C5A059'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#0B1C33'"><?= e($hpLbl) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="search-results-heading">
      <h2><?= e(t('ประกาศอสังหาริมทรัพย์', 'Property listings')) ?></h2>
      <p><?= count($items) >= 100 ? t('แสดง 100 ประกาศล่าสุด', 'Showing the latest 100 listings') : t('พบ ' . count($items) . ' ประกาศ', count($items) . ' listings found') ?></p>
    </div>

    <?php if (!$items): ?>
      <div class="search-empty">
        <span class="search-empty-icon" aria-hidden="true">⌕</span>
        <h2><?= e(t('ยังไม่พบประกาศที่ตรงกับการค้นหา', 'No listings match your search')) ?></h2>
        <p><?= e(t('ลองเปลี่ยนคำค้นหรือเลือกตัวกรองให้น้อยลง', 'Try different keywords or fewer filters')) ?></p>
        <a href="search.php" class="btn"><?= e(t('ดูประกาศทั้งหมด', 'View all listings')) ?></a>
      </div>
    <?php else: ?>
    <div class="search-results-grid">
      <?php foreach ($items as $row): ?>
        <a class="card<?= $row['status'] === 'done' ? ' done' : '' ?>"
           href="property.php?id=<?= (int)$row['id'] ?>" style="display:block;color:inherit;text-decoration:none">
          <?php
            $cover = basename((string)($row['cover'] ?? ''));
            $coverExists = $cover !== '' && is_file(UPLOAD_DIR . $cover);
            $videoFilename = basename((string)($row['video_file'] ?? ''));
            $videoPath = $videoFilename !== '' ? UPLOAD_DIR . $videoFilename : '';
            $videoUrl = ($videoFilename !== '' && is_file($videoPath)) ? 'uploads/' . rawurlencode($videoFilename) : '';
            $hasVideo = $videoUrl !== '';
          ?>
          <div class="thumb<?= $hasVideo ? ' has-hover-video' : '' ?>"<?= $hasVideo ? ' data-card-video="' . e($videoUrl) . '"' : '' ?>>
            <?php if ($hasVideo): ?>
              <video class="card-video-stream" src="<?= e($videoUrl) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= $coverExists ? 'uploads/' . e($cover) : '' ?>"></video>
            <?php endif; ?>
            <?php if ($coverExists): ?>
              <img src="uploads/<?= e($cover) ?>" alt="<?= e($row['title']) ?>" loading="lazy" decoding="async" width="640" height="441">
            <?php else: ?>
              <div class="noimg" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="thumb-badges-left">
              <span class="badge <?= e($row['type']) ?>"><?= e(is_en() ? ($row['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($row['type'])) ?></span>
              <?php if ($row['status'] === 'done'): ?>
                <span class="badge" style="background:#b91c1c;color:#fff;"><?= e(t($row['type'] === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว', $row['type'] === 'rent' ? 'Rented' : 'Sold')) ?></span>
              <?php endif; ?>
            </div>
            <div class="thumb-actions-right">
              <?php if ($hasVideo): ?>
                <button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>" title="<?= e(t('วิดีโอคลิป (แตะเปิด/ปิดเสียง)', 'Video (tap to unmute)')) ?>">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                  <span><?= e(t('วิดีโอ', 'Video')) ?></span>
                  <svg class="sound-muted" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('ปิดเสียงอยู่', 'Muted')) ?>"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                  <svg class="sound-unmuted" style="display:none;" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('เปิดเสียงแล้ว', 'Playing with sound')) ?>"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                </button>
              <?php endif; ?>
              <button type="button" class="sp-fav-card-btn card-fav-heart-btn" data-fav-toggle data-fav-id="<?= (int)$row['id'] ?>" aria-label="<?= e(t('บันทึกในรายการที่ชอบ', 'Save to favorites')) ?>">
                <span class="fav-heart-icon">
                  <svg class="heart-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                </span>
              </button>
            </div>
          </div>
          <div class="info">
            <?php if ($row['status'] !== 'done'): ?>
              <div class="card-publisher-line">
                <?php render_publisher_mini($row); ?>
              </div>
            <?php endif; ?>
            <h2><?= e($row['title']) ?></h2>
            <p class="loc"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg> <?= e($row['location']) ?></p>
            <div class="card-price-stats-row">
              <div class="price">
                <?php
                  require_once __DIR__ . '/includes/price_helpers.php';
                  echo render_price_badge((int)$row['price'], !empty($row['original_price']) ? (int)$row['original_price'] : null, $row['type']);
                ?>
              </div>
              <div class="card-stats-slot">
                <?php
                  require_once __DIR__ . '/includes/price_helpers.php';
                  echo render_card_stats_badge((int)($row['views_count'] ?? 0), (int)($row['clicks_count'] ?? 0));
                ?>
              </div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    render_page_cta([
        'title'   => t('ไม่เจอทรัพย์ที่ถูกใจ? ลองดูหมวดยอดนิยม', "Can't find the right property? Browse popular categories"),
        'text'    => t('เลือกดูตามประเภทหรือทำเลที่สนใจ หรือลงประกาศฟรีเพื่อให้ผู้ซื้อมาหาคุณเอง', 'Browse by type or location, or post a free listing and let buyers find you.'),
        'primary' => ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')],
        'ghost'   => ['category.php?page=' . rawurlencode('คอนโดให้เช่าราคาถูก'), t('ดูคอนโดเช่าราคาถูก', 'See budget condo rentals')],
    ]);
    ?>
  </div>
</main>

<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>
</body>
</html>

<script>
  // Kasa Reels-style Feed Video Stream & Sound Toggle for Search Results
  (() => {
    const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || window.matchMedia('(hover: none)').matches;
    const cards = Array.from(document.querySelectorAll('.thumb[data-card-video]'));
    let activeCard = null;

    cards.forEach(media => {
      const src = media.getAttribute('data-card-video');
      if (!src) return;

      let vEl = media.querySelector('video.card-video-stream');
      const pill = media.querySelector('.card-video-pill');
      const soundMuted = pill?.querySelector('.sound-muted');
      const soundUnmuted = pill?.querySelector('.sound-unmuted');
      let userEngaged = false;

      const updateUI = (isMuted) => {
        if (pill) pill.classList.toggle('sound-active', !isMuted);
        if (soundMuted) soundMuted.style.display = isMuted ? 'inline-block' : 'none';
        if (soundUnmuted) soundUnmuted.style.display = isMuted ? 'none' : 'inline-block';
      };

      if (vEl) {
        vEl.muted = true;
        vEl.volume = 1.0;
        vEl.play().catch(() => {});
        updateUI(true);
      }

      if (pill) {
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          if (!vEl) return;

          if (userEngaged && !vEl.muted) {
            vEl.muted = true;
            userEngaged = false;
            if (activeCard === media) activeCard = null;
            updateUI(true);
            return;
          }

          if (activeCard && activeCard !== media) {
            activeCard._mute?.();
          }

          userEngaged = true;
          vEl.muted = false;
          vEl.volume = 1.0;
          activeCard = media;
          vEl.play().catch(() => {});
          updateUI(false);
        });
      }

      media._mute = () => {
        if (vEl) {
          vEl.muted = true;
          updateUI(true);
        }
        userEngaged = false;
      };
    });

    if ('IntersectionObserver' in window && cards.length > 0) {
      const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const v = entry.target.querySelector('video.card-video-stream');
          if (!v) return;
          if (entry.isIntersecting) v.play().catch(() => {});
          else v.pause();
        });
      }, { threshold: 0.15, rootMargin: '50px 0px 50px 0px' });
      cards.forEach(c => obs.observe(c));
    }
  })();
</script>
