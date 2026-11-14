<?php
require_once __DIR__ . '/includes/mobile_nav.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
require_once __DIR__ . '/includes/seo_provinces.php';
current_lang();

$pdo = db();
$slug = trim((string)($_GET['page'] ?? ''), '/ ');
$slug = urldecode($slug);

$cfg = SEO_CATEGORIES[$slug] ?? null;
$intent = resolve_seo_intent($slug, $_GET);

if ($cfg) {
    $title_h1 = $cfg['h1'];
    $page_title = $cfg['title'];
    $page_desc = $cfg['desc'];
    $page_intro = $cfg['intro'] ?? '';
    $target_category = $cfg['category'] ?? null;
    $target_type = $cfg['type'] ?? null;
    $target_q = $cfg['q'] ?? '';
    $target_max_price = (int)($cfg['max_price'] ?? 0);
    $intent['title'] = $page_title;
    $intent['desc'] = $page_desc;
    $intent['h1'] = $title_h1;
    $intent['intro'] = $page_intro;
} else {
    $title_h1 = $intent['h1'];
    $page_title = $intent['title'];
    $page_desc = $intent['desc'];
    $page_intro = $intent['intro'];
    $target_category = $intent['category'];
    $target_type = $intent['type'];
    $target_q = $intent['search_q'];
    $target_max_price = (int)($intent['max_price'] ?? 0);
}

// ดึงรายการประกาศจากฐานข้อมูลตาม Category และ Type (ถ้ากำหนด)
$sql = "SELECT p.id, p.title, p.type, p.status, p.price, p.original_price, p.location, p.property_category, p.views_count, p.clicks_count, p.video_file, p.created_at,
        u.full_name AS publisher_name,
        u.avatar_path AS publisher_avatar,
        u.agent_status AS publisher_agent_status,
        (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover 
        FROM properties p 
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.approval = 'approved'";

$params = [];

if (!empty($target_category)) {
    $sql .= " AND (p.category = ? OR p.category LIKE ?)";
    $params[] = $target_category;
    $params[] = '%' . $target_category . '%';
}

if (!empty($target_type)) {
    $sql .= " AND p.type = ?";
    $params[] = $target_type;
}

if ($target_q !== '') {
    $sql .= " AND (p.location LIKE ? OR p.nearby_places LIKE ? OR p.description LIKE ? OR p.title LIKE ?)";
    $like = '%' . addcslashes($target_q, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($target_max_price > 0) {
    $sql .= " AND p.price <= ?";
    $params[] = $target_max_price;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$categoryImage = '';
if ($target_category !== null) {
    $imageStatement = $pdo->prepare('SELECT filename FROM category_images WHERE category = ?');
    $imageStatement->execute([$target_category]);
    $candidateImage = $imageStatement->fetchColumn();
    if ($candidateImage && is_file(UPLOAD_DIR . 'categories/' . basename($candidateImage))) {
        $categoryImage = 'uploads/categories/' . basename($candidateImage);
    }
}

// ไอคอนทุกหมวด (ใช้เป็น chip ด้านบน)
$categoryIconsAll = [];
try {
    foreach ($pdo->query('SELECT category, filename FROM category_images') as $iconRow) {
        $iconFile = basename((string)$iconRow['filename']);
        if ($iconFile !== '' && is_file(UPLOAD_DIR . 'categories/' . $iconFile)) {
            $categoryIconsAll[(string)$iconRow['category']] = 'uploads/categories/' . $iconFile;
        }
    }
} catch (PDOException $e) {
    $categoryIconsAll = [];
}

$canonical = site_url() . '/category.php?page=' . urlencode($slug);
$all_slugs = available_category_slugs();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php
    $display_h1 = is_en() ? (property_category_en_label($target_category ?? '') ? (property_category_en_label($target_category ?? '') . ' for ' . ($target_type === 'rent' ? 'Rent' : ($target_type === 'sale' ? 'Sale' : 'Sale & Rent'))) : $title_h1) : $title_h1;
    $seo_title = is_en()
        ? ($display_h1 . ' | ' . SITE_NAME)
        : (str_contains($page_title, SITE_NAME) ? $page_title : $page_title . ' | ' . SITE_NAME);
    seo_head($seo_title, $page_desc, $canonical, true, $categoryImage ? page_url($categoryImage) : '');
    json_ld([
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => t('หน้าแรก', 'Home'), 'item' => page_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $title_h1, 'item' => $canonical],
      ],
    ]);
    json_ld(generate_item_list_schema($items, $page_title, $canonical));
    json_ld(generate_geo_faq_schema($intent));
    json_ld(generate_national_agency_schema());
  ?>
  <?php lang_toggle_head(); ?>
  <meta name="theme-color" content="#0B1C33">
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink: '#0B1C33',
            accent: '#C5A059',
            accentDark: '#A8843F',
            accentSoft: '#F9F5EC',
            muted: '#64748B',
            line: '#E2E8F0',
            canvas: '#F9F9FB'
          },
          boxShadow: {
            soft: '0 16px 36px rgba(11, 28, 51, .08)'
          }
        }
      }
    };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/theme-utilities.css?v=<?= e((string) filemtime(__DIR__ . '/css/theme-utilities.css')) ?>">
  <style>
    .cat-media-wrap { position: relative; overflow: hidden; background: #0B1C33; border-radius: 20px; }
    .cat-media-wrap .card-video-stream { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; z-index: 2; opacity: 1; transition: opacity .25s ease; background: #0B1C33; }
    .cat-badges-left { position: absolute; top: 12px; left: 12px; z-index: 10; display: flex; align-items: center; gap: 6px; pointer-events: none; }
    .cat-actions-right { position: absolute; top: 12px; right: 12px; z-index: 10; display: flex; align-items: center; gap: 6px; }
    .cat-media-wrap .card-video-pill { position: static !important; top: auto !important; right: auto !important; display: inline-flex; align-items: center; gap: 4px; padding: 3.5px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; letter-spacing: .02em; background: rgba(11,28,51,.85); color: #F8D888; border: 1px solid rgba(197,160,89,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); box-shadow: 0 3px 8px rgba(0,0,0,.22); transition: all .22s cubic-bezier(0.34, 1.56, 0.64, 1); cursor: pointer; user-select: none; margin: 0 !important; }
    .cat-media-wrap .card-video-pill:hover { background: #0B1C33; border-color: #F8D888; transform: scale(1.08); box-shadow: 0 4px 12px rgba(197,160,89,.35); }
    .cat-media-wrap .card-video-pill:active { transform: scale(0.95); }
    .cat-media-wrap .card-video-pill.sound-active { background: #15803d !important; border-color: #4ade80 !important; color: #ffffff !important; box-shadow: 0 4px 14px rgba(21,128,61,.5) !important; }
    .cat-media-wrap .card-video-pill.sound-active svg { color: #ffffff !important; }
    .cat-fav-btn {
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
    .cat-fav-btn:hover { transform: scale(1.12) !important; background: #fff !important; }
    .cat-fav-btn svg { width: 16px !important; height: 16px !important; }
    .cat-publisher-line { margin-bottom: 8px; }
    .cat-publisher-line .publisher-mini {
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
    .cat-publisher-line .publisher-mini-avatar { width: 20px !important; height: 20px !important; flex: 0 0 20px !important; font-size: 10px !important; }
    .cat-publisher-line .publisher-mini-name { font-size: 11px !important; color: #475569 !important; font-weight: 700 !important; }
    .cat-publisher-line .publisher-mini-loc { font-size: 10.5px !important; color: #94a3b8 !important; }
    .cat-stats-slot .property-card-stats-badge {
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
    .cat-stats-slot .property-card-stats-badge svg { stroke: #64748b !important; }
    .cat-stats-slot .stat-badge-views, .cat-stats-slot .stat-badge-clicks { color: #64748b !important; }
  </style>
  <?php render_mobile_nav_assets(); ?>
</head>
<body class="page-category bg-[#F9F9FB] text-slate-800 antialiased" style="font-family:'Fenice Std','Noto Serif Thai','Prompt','Inter',system-ui,serif;">

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?> <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="notifications.php">🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?> <span class="sp-notif-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'Projects')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
        <a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <a href="submit.php" class="nav-cta"><?= e(t('ลงประกาศฟรี', 'Post free listing')) ?></a>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<?php render_mobile_nav(); ?>
<main class="mx-auto max-w-[1240px] px-4 pb-16 pt-8 sm:px-6">
  <?php render_banner_slot('category_top', 'compact'); ?>
  <div class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-gradient-to-r from-[#fdfbf8] via-white to-[#f4f8fb] p-6 shadow-soft sm:p-8">
    <?php if ($categoryImage): ?>
      <img src="<?= e($categoryImage) ?>" alt="ภาพหมวด<?= e($title_h1) ?>" class="absolute inset-0 h-full w-full object-cover opacity-25" loading="eager">
    <?php endif; ?>
    <div class="relative">
    <?php
      $display_h1 = is_en() ? (property_category_en_label($target_category ?? '') ? (property_category_en_label($target_category ?? '') . ' for ' . ($target_type === 'rent' ? 'Rent' : ($target_type === 'sale' ? 'Sale' : 'Sale & Rent'))) : $title_h1) : $title_h1;
    ?>
    <nav class="mb-5 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="breadcrumb">
      <a href="index.php" class="hover:text-ink"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <span>›</span>
      <span class="text-slate-700"><?= e($display_h1) ?></span>
    </nav>

    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-accent">Property listing</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl"><?= e($display_h1) ?></h1>
        <?php
          $display_intro = is_en() ? ('Discover verified ' . strtolower($display_h1) . ' with photos, detailed specs, maps and direct owner contacts.') : $page_intro;
        ?>
        <?php if (!empty($display_intro)): ?>
          <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600"><?= e($display_intro) ?></p>
        <?php endif; ?>
      </div>
      <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600">
        <?= e(t('พบ ' . count($items) . ' ประกาศที่ตรงกับเงื่อนไข', count($items) . ' listings match your criteria')) ?>
      </div>
      </div>
    </div>
  </div>

  <section class="mt-6 sm:mt-8">
    <div class="flex flex-nowrap overflow-x-auto gap-2 pb-3 pt-1 -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap scrollbar-none" style="-webkit-overflow-scrolling:touch;">
      <?php foreach (PROPERTY_CATEGORIES as $cat_slug => $cat_label): if ($cat_slug === 'other') continue; ?>
        <?php $chipIcon = $categoryIconsAll[$cat_slug] ?? ''; ?>
        <a href="category.php?page=<?= e($cat_slug) ?>" class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition <?= $target_category === $cat_slug ? 'bg-ink text-white shadow-lg shadow-slate-200' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-ink' ?>">
          <?php if ($chipIcon !== ''): ?>
            <img src="<?= e($chipIcon) ?>" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover ring-1 ring-slate-200" loading="lazy">
          <?php endif; ?>
          <?= e(category_label_bi($cat_slug)) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (empty($items)): ?>
    <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-soft">
      <p class="text-lg font-bold text-slate-600"><?= e(t('ยังไม่มีประกาศในหมวดหมู่ ' . $title_h1 . ' ในขณะนี้', 'No listings in ' . $display_h1 . ' yet')) ?></p>
      <a href="submit.php" class="mt-5 inline-flex items-center rounded-full bg-accent px-6 py-3 text-sm font-extrabold text-white transition hover:bg-[#d86c26]"><?= e(t('ลงประกาศเป็นคนแรก', 'Be the first to post')) ?></a>
    </div>
  <?php else: ?>
    <section class="category-listings-grid mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($items as $row): ?>
        <?php
          $cover = basename((string)($row['cover'] ?? ''));
          $coverExists = $cover !== '' && is_file(UPLOAD_DIR . $cover);
          $img = $coverExists ? 'uploads/' . e($cover) : 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80';
          $videoFilename = basename((string)($row['video_file'] ?? ''));
          $videoPath = $videoFilename !== '' ? UPLOAD_DIR . $videoFilename : '';
          $videoUrl = ($videoFilename !== '' && is_file($videoPath)) ? 'uploads/' . rawurlencode($videoFilename) : '';
          $hasVideo = $videoUrl !== '';
        ?>
        <article class="group overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-soft transition hover:-translate-y-1 hover:shadow-xl sm:rounded-[28px]">
          <a href="property.php?id=<?= (int)$row['id'] ?>" class="block no-underline text-inherit">
            <div class="cat-media-wrap relative overflow-hidden"<?= $hasVideo ? ' data-card-video="' . e($videoUrl) . '"' : '' ?>>
              <?php if ($hasVideo): ?>
                <video class="card-video-stream" src="<?= e($videoUrl) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= $img ?>"></video>
              <?php endif; ?>
              <img src="<?= $img ?>" alt="<?= e($row['title']) ?>" class="h-52 w-full object-cover transition duration-500 group-hover:scale-105 sm:h-60" loading="lazy" decoding="async" width="640" height="240">
              <div class="cat-badges-left">
                <span class="rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-ink shadow-sm">
                  <?= e(is_en() ? ($row['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($row['type'])) ?>
                </span>
                <?php if ($row['status'] === 'done'): ?>
                  <span class="rounded-full bg-slate-900/80 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.12em] text-white">
                    <?= e(t($row['type'] === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว', $row['type'] === 'rent' ? 'Rented' : 'Sold')) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="cat-actions-right">
                <?php if ($hasVideo): ?>
                  <button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>" title="<?= e(t('วิดีโอคลิป (แตะเปิด/ปิดเสียง)', 'Video (tap to unmute)')) ?>">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    <span><?= e(t('วิดีโอ', 'Video')) ?></span>
                    <svg class="sound-muted" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('ปิดเสียงอยู่', 'Muted')) ?>"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                    <svg class="sound-unmuted" style="display:none;" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('เปิดเสียงแล้ว', 'Playing with sound')) ?>"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                  </button>
                <?php endif; ?>
                <button type="button" class="sp-fav-card-btn cat-fav-btn" data-fav-toggle data-fav-id="<?= (int)$row['id'] ?>" aria-label="<?= e(t('บันทึกในรายการที่ชอบ', 'Save to favorites')) ?>">
                  <span class="fav-heart-icon">
                    <svg class="heart-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                  </span>
                </button>
              </div>
            </div>
            <div class="p-4 sm:p-5">
              <?php if ($row['status'] !== 'done'): ?>
                <div class="cat-publisher-line">
                  <?php render_publisher_mini($row); ?>
                </div>
              <?php endif; ?>
              <div class="flex items-center justify-between gap-3">
                <span class="rounded-full bg-accentSoft px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-accent"><?= e(category_label_bi($row['property_category'])) ?></span>
                <span class="text-xs font-bold text-slate-500">#<?= (int)$row['id'] ?></span>
              </div>
              <h2 class="mt-3 line-clamp-2 text-lg font-extrabold leading-6 text-ink sm:text-xl sm:leading-7 break-words"><?= e($row['title']) ?></h2>
              <p class="mt-2 flex items-center gap-1.5 text-sm text-slate-500"><svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg><span><?= e($row['location']) ?></span></p>
              
              <div class="mt-3.5 flex items-center justify-between gap-3 border-t border-slate-200 pt-3.5 sm:mt-4 sm:pt-4">
                <div>
                  <p class="text-[10px] font-bold uppercase tracking-[.14em] text-slate-500"><?= e(t('ราคา', 'Price')) ?></p>
                  <div class="mt-1 font-black text-accent">
                    <?php
                      require_once __DIR__ . '/includes/price_helpers.php';
                      echo render_price_badge((int)$row['price'], !empty($row['original_price']) ? (int)$row['original_price'] : null, $row['type']);
                    ?>
                  </div>
                </div>
                <div class="cat-stats-slot">
                  <?php
                    require_once __DIR__ . '/includes/price_helpers.php';
                    echo render_card_stats_badge((int)($row['views_count'] ?? 0), (int)($row['clicks_count'] ?? 0));
                  ?>
                </div>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <!-- SEO & AI Overviews Knowledge FAQ Section -->
  <?php $geoFaq = generate_geo_faq_schema($intent); ?>
  <section class="mt-12 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
    <div class="flex items-center gap-3">
      <span class="grid h-10 w-10 place-items-center rounded-full bg-accentSoft text-lg text-accent">💡</span>
      <div>
        <h2 class="text-xl font-extrabold text-ink"><?= e(t('คำถามที่พบบ่อย (FAQ) เกี่ยวกับ ' . $title_h1, 'Frequently Asked Questions')) ?></h2>
        <p class="text-xs text-slate-500"><?= e(t('ข้อมูลเชิงลึกสำหรับผู้ซื้อ ผู้เช่า และนักลงทุนอสังหาริมทรัพย์', 'In-depth insights for buyers, tenants and investors')) ?></p>
      </div>
    </div>

    <div class="mt-6 space-y-4">
      <?php foreach ($geoFaq['mainEntity'] as $faqItem): ?>
        <details class="group rounded-2xl border border-slate-200 bg-slate-50/60 p-4 transition open:bg-white open:shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-ink">
            <span><?= e($faqItem['name']) ?></span>
            <span class="text-xs text-accent transition group-open:rotate-180">▼</span>
          </summary>
          <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= e($faqItem['acceptedAnswer']['text']) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Prime CBD & Transit Zones SEO Linking Cloud -->
  <section class="mt-8 rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-50 via-white to-amber-50/20 p-6 shadow-soft sm:p-8">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-extrabold uppercase tracking-wider text-accentDark">🏢 <?= e(t('ทำเลเศรษฐกิจ Prime CBD & แนวรถไฟฟ้า BTS/MRT', 'Prime CBD & Transit Zones (BTS/MRT)')) ?></h3>
        <p class="mt-1 text-xs text-slate-500"><?= e(t('ย่านศูนย์กลางธุรกิจและการอยู่อาศัยระดับพรีเมียมที่มีการค้นหาสูงสุด', 'Top-searched commercial and high-end residential hubs')) ?></p>
      </div>
      <a href="search.php" class="text-xs font-bold text-accent hover:underline"><?= e(t('ค้นหาตามทำเล ›', 'Search by zone ›')) ?></a>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <?php
      if (defined('PRIME_CBD_ZONES')) {
          foreach (PRIME_CBD_ZONES as $cbdKey => $cbd) {
              $cbdTitle = is_en() ? ($cbd['name_en'] ?? $cbd['name_th']) : $cbd['name_th'];
              $cbdTarget = 'category.php?page=' . urlencode('คอนโด' . $cbd['name_th']);
              echo '<a href="' . e($cbdTarget) . '" class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-ink shadow-xs transition hover:border-accent hover:bg-accentSoft hover:text-accentDark">⚡ ' . e($cbdTitle) . '</a>';
              $subEnMap = [
                  'ทองหล่อ' => 'Thong Lo', 'เอกมัย' => 'Ekkamai', 'พร้อมพงษ์' => 'Phrom Phong',
                  'สาทร' => 'Sathorn', 'สีลม' => 'Silom', 'ช่องนนทรี' => 'Chong Nonsi',
                  'พระราม 9' => 'Rama 9', 'รัชดาภิเษก' => 'Ratchada', 'ห้วยขวาง' => 'Huai Khwang',
                  'อารีย์' => 'Ari', 'สะพานควาย' => 'Saphan Khwai', 'พญาไท' => 'Phayathai',
                  'ห้าแยกลาดพร้าว' => 'Ladprao Junction', 'รัชโยธิน' => 'Ratchayothin',
                  'บางนา' => 'Bangna', 'เมกาบางนา' => 'Mega Bangna',
                  'เจริญกรุง' => 'Charoen Krung', 'คลองสาน' => 'Khlong San',
                  'นิมมาน' => 'Nimman', 'นิมมานเหมินท์' => 'Nimmanhaemin',
                  'พัทยากลาง' => 'Central Pattaya', 'พัทยาเหนือ' => 'North Pattaya',
                  'หัวหิน' => 'Hua Hin', 'เขาเต่า' => 'Khao Tao',
                  'บางเทา' => 'Bang Tao', 'ลากูน่า' => 'Laguna',
              ];
              foreach (array_slice($cbd['sub_districts'], 0, 2) as $subD) {
                  $subTarget = 'category.php?page=' . urlencode('คอนโด' . $subD);
                  $subLabel = is_en() ? ($subEnMap[$subD] ?? $subD) : $subD;
                  echo '<a href="' . e($subTarget) . '" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-accent hover:text-white">' . e($subLabel) . '</a>';
              }
          }
      }
      ?>
    </div>
  </section>

  <!-- Nationwide Province SEO Linking Cloud -->
  <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-600">📍 <?= e(t('เจาะทำเลอสังหาริมทรัพย์ยอดนิยมทั่วไทย', 'Popular Property Locations Across Thailand')) ?></h3>
      <a href="search.php" class="text-xs font-bold text-accent hover:underline"><?= e(t('ดูทั้งหมด 77 จังหวัด ›', 'View all 77 provinces ›')) ?></a>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <?php
      $topProvList = [
          'กรุงเทพมหานคร' => t('กรุงเทพมหานคร', 'Bangkok'),
          'เชียงใหม่'     => t('เชียงใหม่', 'Chiang Mai'),
          'ภูเก็ต'        => t('ภูเก็ต', 'Phuket'),
          'ชลบุรี'        => t('ชลบุรี', 'Chonburi'),
          'นนทบุรี'       => t('นนทบุรี', 'Nonthaburi'),
          'สมุทรปราการ'   => t('สมุทรปราการ', 'Samut Prakan'),
          'ปทุมธานี'       => t('ปทุมธานี', 'Pathum Thani'),
          'ระยอง'         => t('ระยอง', 'Rayong'),
          'ประจวบคีรีขันธ์' => t('ประจวบฯ', 'Prachuap Khiri Khan'),
          'นครราชสีมา'     => t('นครราชสีมา', 'Nakhon Ratchasima'),
          'ขอนแก่น'       => t('ขอนแก่น', 'Khon Kaen'),
          'สุราษฎร์ธานี'    => t('สุราษฎร์ธานี', 'Surat Thani'),
          'สงขลา'         => t('สงขลา', 'Songkhla'),
          'เชียงราย'       => t('เชียงราย', 'Chiang Rai'),
          'พระนครศรีอยุธยา'=> t('อยุธยา', 'Ayutthaya'),
          'นครปฐม'        => t('นครปฐม', 'Nakhon Pathom'),
          'กระบี่'         => t('กระบี่', 'Krabi'),
          'เพชรบุรี'       => t('เพชรบุรี', 'Phetchaburi'),
          'กาญจนบุรี'      => t('กาญจนบุรี', 'Kanchanaburi'),
          'อุดรธานี'       => t('อุดรธานี', 'Udon Thani'),
          'อุบลราชธานี'    => t('อุบลราชธานี', 'Ubon Ratchathani'),
          'พิษณุโลก'      => t('พิษณุโลก', 'Phitsanulok'),
          'ฉะเชิงเทรา'     => t('ฉะเชิงเทรา', 'Chachoengsao'),
          'สระบุรี'        => t('สระบุรี', 'Saraburi')
      ];
      $activeCatLabel = is_en() ? (property_category_en_label($intent['category'] ?? '') ?? 'Properties') : ($intent['category_label'] ?: 'บ้าน');
      foreach ($topProvList as $pKey => $pLabel):
      ?>
        <a href="category.php?page=<?= urlencode('ขาย' . ($intent['category_label'] ?: 'บ้าน') . $pKey) ?>" class="rounded-full bg-slate-100 px-3.5 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-accent hover:text-white">
          <?= e($activeCatLabel . ' ' . $pLabel) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php
  render_page_cta([
      'title'   => t('ไม่พบทรัพย์ที่ใช่? เปลี่ยนไปดูหมวดหรือย่านอื่น', "Can't find the right property? Try other categories or areas"),
      'text'    => t('ลองคอนโดติด BTS, คอนโดราคาไม่เกิน 1 ล้าน หรือบ้านราคาไม่เกิน 2 ล้าน ก็มีประกาศใหม่เข้ามาทุกสัปดาห์', 'Try condos near BTS, condos under 1M, or houses under 2M — new listings arrive every week.'),
      'primary' => ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')],
      'ghost'   => ['search.php', t('ค้นหาแบบละเอียด', 'Advanced search')],
  ]);
  ?>
</main>

<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>
</body>
</html>

<script>
  // Kasa Reels-style Feed Video Stream & Sound Toggle for Category Page
  (() => {
    const cards = Array.from(document.querySelectorAll('.cat-media-wrap[data-card-video]'));
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
