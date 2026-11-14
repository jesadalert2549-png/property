<?php
require_once __DIR__ . '/includes/mobile_nav.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();
require_once __DIR__ . '/includes/profile_schema.php';
ensure_profile_schema(db());

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT properties.id, properties.title, properties.type, properties.property_category, properties.status, properties.verification_status, properties.verification_notes, properties.price, properties.original_price, properties.location, properties.lat, properties.lng, properties.line_id,
            properties.description, properties.bedrooms, properties.bathrooms, properties.area_sqm, properties.land_area, properties.parking_spaces, properties.floor_number, properties.furnished,
            properties.amenities, properties.nearby_places, properties.contact_name, properties.phone, properties.video_file,
            u.id AS owner_user_id,
            u.full_name AS owner_full_name,
            u.avatar_path AS owner_avatar_path,
            u.agent_status AS owner_agent_status
     FROM properties
     LEFT JOIN users u ON u.id = properties.user_id
     WHERE properties.id = ? AND properties.approval = 'approved' LIMIT 1"
);
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    render_not_found();
}
$publisherName = trim((string)($p['owner_full_name'] ?? '')) ?: (trim((string)$p['contact_name']) ?: t('ผู้ประกาศ', 'Lister'));
$publisherAvatar = basename((string)($p['owner_avatar_path'] ?? ''));
$publisherAvatarExists = $publisherAvatar !== '' && is_file(__DIR__ . '/uploads/avatars/' . $publisherAvatar);

$st = db()->prepare('SELECT filename FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
$st->execute([$id]);
$files = array_values(array_filter(
    array_map(static fn($file) => basename($file), array_column($st->fetchAll(), 'filename')),
    static fn($file) => is_file(UPLOAD_DIR . $file)
));

$typeWord  = type_label($p['type']);
$title     = $p['title'] . ' | ' . $typeWord . ' ' . $p['location'] . ' | ' . SITE_NAME;
$desc      = mb_substr(trim(preg_replace('/\s+/', ' ', $p['description'] ?: $p['title'])), 0, 140);
$canonical = page_url('property.php?id=' . $id);
$catSlug   = category_slug_for($p['property_category'], $p['type']);
$listingStatusLabel = $p['status'] === 'done'
    ? t('ปิดประกาศแล้ว', 'Listing closed')
    : ($p['type'] === 'rent' ? t('พร้อมให้เช่า', 'Available for rent') : t('พร้อมขาย', 'Available for sale'));

// ประกาศแนะนำจากหมวดเดียวกัน (ไม่รวมประกาศนี้)
$st = db()->prepare(
    "SELECT p.id, p.title, p.type, p.property_category, p.price, p.original_price, p.location, p.video_file,
            (SELECT i.filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
     FROM properties p
     WHERE p.approval = 'approved' AND p.id != ? AND p.property_category = ?
     ORDER BY (p.status = 'done'), p.created_at DESC LIMIT 6"
);
$st->execute([$id, $p['property_category']]);
$related = $st->fetchAll();

$ld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'RealEstateListing',
    'name'        => $p['title'],
    'url'         => $canonical,
    'description' => $desc,
    'address'     => ['@type' => 'PostalAddress', 'streetAddress' => $p['location'], 'addressCountry' => 'TH'],
    'offers'      => [
        '@type'         => 'Offer',
        'price'         => (int)$p['price'],
        'priceCurrency' => 'THB',
        'availability'  => $p['status'] === 'done' ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
    ],
];
if ($p['bedrooms'] !== null) {
    $ld['numberOfBedrooms'] = (int)$p['bedrooms'];
}
if ($p['bathrooms'] !== null) {
    $ld['numberOfBathroomsTotal'] = (int)$p['bathrooms'];
}
if ($p['area_sqm'] !== null) {
    $ld['floorSize'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['area_sqm'], 'unitCode' => 'MTK'];
}
if ($files) {
    $ld['image'] = array_map(fn($f) => page_url('uploads/' . $f), $files);
}
json_ld($ld);
json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => t('หน้าแรก', 'Home'), 'item' => page_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => category_label($p['property_category']), 'item' => page_url('category.php?page=' . rawurlencode((string)$catSlug))],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title'], 'item' => $canonical],
    ],
]);
require_once __DIR__ . '/includes/seo_provinces.php';
$propertyAiFaq = generate_property_ai_faq_schema($p, $files);
json_ld($propertyAiFaq);
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php   seo_head($title, $desc, $canonical, true, $files ? page_url('uploads/' . $files[0]) : ''); ?>
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
  <link rel="stylesheet" href="css/theme-utilities.css?v=<?= e((string) filemtime(__DIR__ . '/css/theme-utilities.css')) ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <style>
    html { scroll-behavior: smooth; }
    body { font-family: "Fenice Std", "Noto Serif Thai", "Prompt", "Inter", -apple-system, system-ui, serif !important; background-color: #F9F9FB; }
    h1, h2, h3, h4, .text-3xl, .text-2xl, .text-xl {
      font-family: "Fenice Std", "Noto Serif Thai", "Pridi", serif !important;
      letter-spacing: -0.015em;
    }
    .map-grid {
      background:
        linear-gradient(rgba(255,255,255,.55), rgba(255,255,255,.55)),
        linear-gradient(90deg, rgba(24,50,75,.06) 1px, transparent 1px),
        linear-gradient(rgba(24,50,75,.06) 1px, transparent 1px),
        #edf4ef;
      background-size: auto, 38px 38px, 38px 38px, auto;
    }
    .property-gallery-track { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); grid-auto-rows:clamp(135px,19vw,230px); gap:10px; overflow:hidden; border-radius:24px; }
    .property-gallery-slide { position:relative; min-width:0; min-height:0; overflow:hidden; border:0; border-radius:18px; padding:0; background:#e2e8f0; cursor:zoom-in; }
    .property-gallery-slide:first-child { grid-column:span 2; grid-row:span 2; }
    .property-gallery-slide img { width:100%; height:100%; object-fit:cover; display:block; }
    .gallery-desktop-hidden { display:none; }
    .property-gallery-arrow,.property-lightbox-arrow { position:absolute; z-index:2; top:50%; display:grid; place-items:center; width:44px; height:44px; transform:translateY(-50%); border:1px solid #ffffff90; border-radius:50%; background:#111827aa; color:#fff; font-size:26px; line-height:1; cursor:pointer; }
    .property-gallery-arrow.prev,.property-lightbox-arrow.prev { left:12px; }
    .property-gallery-arrow.next,.property-lightbox-arrow.next { right:12px; }
    .property-gallery-count,.property-lightbox-count { position:absolute; z-index:2; right:14px; bottom:14px; padding:7px 11px; border-radius:999px; background:#111827b8; color:#fff; font-size:12px; font-weight:800; backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); pointer-events:none; }
    .property-gallery-dots { display:none; }
    #galleryDialog { max-width:94vw; max-height:92vh; }
    .property-lightbox-frame { position:relative; display:grid; place-items:center; background:#111827; }
    .property-lightbox-frame img { max-width:94vw; max-height:88vh; width:auto; }
    .property-lightbox-close { position:absolute; z-index:3; right:12px; top:12px; }
    .publisher-profile-card { display:flex;align-items:center;gap:12px;min-width:0;padding:12px;border:1px solid #e2e8f0;border-radius:16px;background:#fff; }
    .publisher-avatar { display:grid;flex:0 0 48px;width:48px;height:48px;place-items:center;overflow:hidden;border-radius:50%;background:#eee8fa;color:#6044a6;font-size:18px;font-weight:900; }
    .publisher-avatar img { width:100%;height:100%;object-fit:cover; }
    .publisher-profile-info { display:flex;align-items:center;flex-wrap:wrap;gap:6px 9px;min-width:0; }
    .publisher-profile-info strong { color:#25213a;font-size:15px;overflow-wrap:anywhere; }
    .publisher-profile-link { color:inherit;text-decoration:none;transition:border-color .18s,box-shadow .18s; }
    .publisher-profile-link:hover { border-color:#c8b7ed;box-shadow:0 8px 22px #38285d12; }
    .publisher-eyebrow { width:100%;margin:0;color:#728094;font-size:11px; }
    @media(max-width:640px) {
      .property-gallery-track {
        display:flex !important;
        flex-direction:row !important;
        flex-wrap:nowrap !important;
        overflow-x:auto !important;
        overflow-y:hidden !important;
        scroll-snap-type:x mandatory !important;
        -webkit-overflow-scrolling:touch !important;
        scrollbar-width:none !important;
        gap:0 !important;
        border-radius:20px !important;
        height:270px !important;
        background:#0b1c33;
      }
      .property-gallery-track::-webkit-scrollbar { display:none; }
      .property-gallery-slide {
        display:block !important;
        flex:0 0 100% !important;
        width:100% !important;
        height:100% !important;
        scroll-snap-align:start !important;
        border-radius:0 !important;
      }
      .property-gallery-slide img {
        width:100% !important;
        height:100% !important;
        object-fit:cover !important;
      }
      .property-gallery-dots {
        display:flex;
        position:absolute;
        bottom:14px;
        left:50%;
        transform:translateX(-50%);
        z-index:2;
        gap:6px;
        padding:4px 8px;
        border-radius:999px;
        background:rgba(11,28,51,0.55);
        backdrop-filter:blur(4px);
        -webkit-backdrop-filter:blur(4px);
        pointer-events:none;
      }
      .gallery-dot {
        width:6px;
        height:6px;
        border-radius:50%;
        background:rgba(255,255,255,0.45);
        transition:all .2s ease;
      }
      .gallery-dot.active {
        width:16px;
        border-radius:999px;
        background:#C5A059;
      }
      .property-gallery-count {
        right:10px !important;
        bottom:10px !important;
        padding:4px 9px !important;
        font-size:11px !important;
      }
    }

    /* Kasa Reels-style card video preview */
    .card-media-wrap {
      position: relative;
      overflow: hidden;
      border-radius: 16px;
      background: #0B1C33;
      aspect-ratio: 16 / 10;
      box-shadow: 0 4px 14px rgba(11,28,51,0.06);
    }
    .card-media-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform .45s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .group:hover .card-media-wrap img {
      transform: scale(1.05);
    }
    .card-hover-video {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      z-index: 2;
      opacity: 1; /* Directly visible like Kasa Reels */
      transition: opacity .3s ease;
      background: #0B1C33;
    }
    .card-media-wrap.video-playing .card-hover-video {
      opacity: 1;
    }
    .card-video-pill {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 11.5px;
      font-weight: 850;
      letter-spacing: .02em;
      background: linear-gradient(135deg, rgba(11,28,51,.92), rgba(21,46,77,.92));
      color: #F8D888;
      border: 1px solid rgba(197,160,89,.55);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      box-shadow: 0 4px 14px rgba(0,0,0,.35);
      transition: all .24s cubic-bezier(0.34, 1.56, 0.64, 1);
      cursor: pointer;
      user-select: none;
    }
    .card-video-pill:hover {
      background: #0B1C33;
      border-color: #F8D888;
      transform: scale(1.08);
      box-shadow: 0 6px 18px rgba(197,160,89,.45);
    }
    .card-video-pill:active {
      transform: scale(0.95);
    }
    .card-video-pill.sound-active {
      background: #15803d !important;
      border-color: #4ade80 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(21,128,61,.5) !important;
    }
    .card-video-pill.sound-active svg {
      color: #ffffff !important;
    }

    /* Kasa Reels Reels Center Pulse Play Overlay */
    .card-reels-center-btn {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      z-index: 5;
      pointer-events: none;
      transition: opacity .25s ease, transform .25s ease;
    }
    .card-reels-play-ring {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: rgba(11, 28, 51, 0.75);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 2px solid rgba(248, 216, 136, 0.85);
      display: grid;
      place-items: center;
      color: #F8D888;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
      transition: transform .25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .group:hover .card-reels-play-ring {
      transform: scale(1.15);
      background: rgba(11, 28, 51, 0.9);
      border-color: #ffffff;
      color: #ffffff;
    }
    .card-media-wrap.video-playing .card-reels-center-btn {
      opacity: 0;
      transform: scale(0.8);
      pointer-events: none;
    }
  </style>
  <?php render_mobile_nav_assets(); ?>
</head>
<body class="page-property bg-slate-50 text-slate-800 antialiased" data-back-url="category.php?page=<?= e(rawurlencode((string)$catSlug)) ?>">

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
<main class="mx-auto max-w-[1240px] px-4 pb-16 pt-6 sm:px-6">
  <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="breadcrumb">
    <a href="index.php" class="hover:text-ink"><?= e(t('หน้าแรก', 'Home')) ?></a>
    <span>›</span>
    <a href="search.php?type=<?= e($p['type']) ?>" class="hover:text-ink"><?= e($typeWord) ?></a>
    <span>›</span>
    <a href="category.php?page=<?= e(rawurlencode((string)$catSlug)) ?>" class="hover:text-ink"><?= e(category_label_bi($p['property_category'])) ?></a>
    <span>›</span>
    <span class="text-slate-700"><?= e($p['location']) ?></span>
  </nav>

  <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] grid-cols-1 items-start">
    <div class="space-y-5">
      <?php
        $propVideoFilename = basename((string)($p['video_file'] ?? ''));
        $propVideoPath = $propVideoFilename !== '' ? UPLOAD_DIR . $propVideoFilename : '';
        $hasPropVideo = $propVideoFilename !== '' && is_file($propVideoPath);
      ?>
      <?php if ($hasPropVideo): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-accent font-bold">🎬</span>
              <h3 class="text-base font-extrabold text-ink"><?= e(t('วิดีโอแนะนำทรัพย์ / คลิปพาทัวร์', 'Property Video Tour')) ?></h3>
            </div>
            <span class="text-xs font-bold text-accent bg-amber-50 px-2.5 py-1 rounded-full">HD VIDEO</span>
          </div>
          <div class="overflow-hidden rounded-2xl bg-black aspect-video relative group/mainvid">
            <video id="mainPropertyVideo" src="uploads/<?= e(rawurlencode($propVideoFilename)) ?>" autoplay controls playsinline preload="auto" class="w-full h-full object-contain"></video>
          </div>
        </div>
      <?php endif; ?>

      <div class="relative" data-property-carousel>
        <?php $gallery = $files ? array_map(fn($file) => 'uploads/' . e($file), $files) : [
          'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
          'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80'
        ]; ?>
        <div class="property-gallery-track" id="propertyGalleryTrack">
          <?php foreach ($gallery as $index => $image): ?>
            <button type="button" class="property-gallery-slide <?= $index >= 5 ? 'gallery-desktop-hidden' : '' ?>" data-gallery-image="<?= $image ?>" data-gallery-index="<?= $index ?>" aria-label="<?= e(t('เปิดดูรูปที่ ', 'Open photo ') . ($index + 1)) ?>">
              <img src="<?= $image ?>" alt="<?= e($p['title'] . ' ' . t('ภาพที่', 'photo') . ' ' . ($index + 1)) ?>" decoding="async" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            </button>
          <?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
          <span class="property-gallery-count" id="propertyGalleryCount"><?= e(t(count($gallery) . ' รูป', count($gallery) . ' photos')) ?></span>
          <div class="property-gallery-dots" id="propertyGalleryDots" aria-hidden="true">
            <?php foreach ($gallery as $dotIdx => $dotImg): ?>
              <span class="gallery-dot <?= $dotIdx === 0 ? 'active' : '' ?>" data-dot-index="<?= $dotIdx ?>"></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (($p['verification_status'] ?? 'passed') === 'flagged'): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong><?= e(t('ควรตรวจสอบประกาศนี้เพิ่มเติม', 'This listing needs further review')) ?></strong><br><?= e($p['verification_notes'] ?: t('ระบบพบข้อมูลที่คล้ายกับประกาศอื่น', 'The system found data similar to another listing')) ?> <?= e(t('ก่อนตัดสินใจหรือติดต่อ โปรดตรวจสอบรายละเอียดและนัดดูทรัพย์ด้วยตนเอง', 'Before deciding or contacting, please verify the details and arrange a viewing yourself.')) ?> <a class="font-bold underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งปัญหาประกาศนี้', 'Report this listing')) ?></a></div>
      <?php elseif (($p['verification_status'] ?? 'unchecked') === 'passed'): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"><?= e(t('ข้อมูลผ่านการตรวจความครบถ้วนเบื้องต้นของระบบ ซึ่งไม่ใช่การยืนยันกรรมสิทธิ์หรือรับรองว่าทรัพย์มีอยู่จริง โปรดตรวจสอบและนัดดูทรัพย์ก่อนชำระเงิน', 'This listing passed the system\'s basic completeness check, which does not verify ownership or confirm the property exists. Please inspect and view the property before paying anything.')) ?> <a class="font-bold text-accent underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งประกาศนี้', 'Report this listing')) ?></a></div>
      <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"><?= e(t('ประกาศนี้ยังไม่ได้ผ่านการตรวจความครบถ้วนอัตโนมัติ โปรดตรวจสอบข้อมูลและนัดดูทรัพย์ก่อนตัดสินใจ', 'This listing has not passed the automatic completeness check yet. Please verify the information and arrange a viewing before deciding.')) ?> <a class="font-bold text-accent underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งประกาศนี้', 'Report this listing')) ?></a></div>
      <?php endif; ?>
      <?php render_banner_slot('property_detail_top', 'medium'); ?>
    </div>

    <aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-xs font-bold uppercase tracking-[.18em] text-accent"><?= e($listingStatusLabel) ?></p>
          <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink"><?= e($p['title']) ?></h1>
        </div>
        <button id="shareProperty" type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm font-bold text-ink transition hover:border-ink">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.5 11 7.8-4.7M8.5 13.2l7.7 4.5"/></svg>
          <?= e(t('แชร์', 'Share')) ?>
        </button>
      </div>
      <div class="mt-5 rounded-2xl bg-gradient-to-br from-slate-50 to-amber-50/20 p-5 border border-slate-200/80 shadow-xs">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-500"><?= e(t('ราคา', 'Price')) ?></p>
          <?php
            require_once __DIR__ . '/includes/price_helpers.php';
            echo render_card_stats_badge((int)($p['views_count'] ?? 0), (int)($p['clicks_count'] ?? 0), 'detail');
          ?>
        </div>
        <div class="mt-2">
          <?php
            require_once __DIR__ . '/includes/price_helpers.php';
            echo render_price_badge((int)$p['price'], !empty($p['original_price']) ? (int)$p['original_price'] : null, $p['type'], 'large');
          ?>
        </div>
      </div>
      <div class="mt-5 space-y-3 text-sm text-slate-600">
        <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-accentSoft text-lg text-accent">⌖</span>
          <div>
            <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('ทำเล', 'Location')) ?></p>
            <p class="font-bold text-ink"><?= e($p['location']) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-accentSoft text-lg text-accent">⌂</span>
          <div>
            <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('ประเภท', 'Type')) ?></p>
            <p class="font-bold text-ink"><?= e(category_label_bi($p['property_category'])) ?></p>
          </div>
        </div>
        <?php if (!empty($p['owner_user_id'])): ?><a class="publisher-profile-card publisher-profile-link" href="member.php?id=<?= (int)$p['owner_user_id'] ?>" aria-label="ดูโปรไฟล์และประกาศของ <?= e($publisherName) ?>"><?php else: ?><div class="publisher-profile-card"><?php endif; ?><span class="publisher-avatar"><?php if ($publisherAvatarExists): ?><img src="uploads/avatars/<?= e($publisherAvatar) ?>" alt="รูปโปรไฟล์ <?= e($publisherName) ?>" loading="lazy" decoding="async"><?php else: ?><?= e(mb_strtoupper(mb_substr($publisherName, 0, 1))) ?><?php endif; ?></span><div class="publisher-profile-info"><p class="publisher-eyebrow"><?= e(t('ผู้ประกาศ · ดูโปรไฟล์', 'Lister · View profile')) ?></p><strong><?= e($publisherName) ?></strong><?php if (($p['owner_agent_status'] ?? '') === 'verified'): ?><span class="agent-verified-badge" title="<?= e(t('ผู้ดูแลตรวจสอบหลักฐาน Agent แล้ว', 'Agent credentials verified by admin')) ?>"><?= e(t('✓ Agent รับรอง', '✓ Verified agent')) ?></span><?php endif; ?></div><?= !empty($p['owner_user_id']) ? '</a>' : '</div>' ?>
      </div>
      <div class="mt-5">
        <?php if ($p['status'] !== 'done'): ?>
          <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>" class="inline-flex h-12 w-full items-center justify-center rounded-full bg-accent text-sm font-extrabold text-white transition hover:bg-[#d86c26]"><?= e(t('โทรสอบถามทันที', 'Call now')) ?></a>
          <?php if ($p['line_id'] !== ''): ?>
            <a href="https://line.me/R/ti/p/~<?= rawurlencode($p['line_id']) ?>" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex h-12 w-full items-center justify-center rounded-full bg-[#06c755] text-sm font-extrabold text-white transition hover:bg-[#05ad49]"><?= e(t('ติดต่อผ่าน LINE', 'Contact via LINE')) ?></a>
          <?php endif; ?>
        <?php else: ?>
          <span class="inline-flex h-12 w-full items-center justify-center rounded-full bg-slate-200 text-sm font-extrabold text-slate-500"><?= e(t('ประกาศนี้ปิดแล้ว', 'This listing is closed')) ?></span>
        <?php endif; ?>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="button" class="sp-btn-save-detail flex-1 rounded-full border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-ink inline-flex items-center justify-center gap-2 shadow-xs" id="saveProperty" data-fav-toggle data-fav-id="<?= (int)$p['id'] ?>">
          <span class="fav-heart-icon">
            <svg class="heart-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
          </span>
          <span class="fav-btn-text"><?= e(t('บันทึก', 'Save')) ?></span>
        </button>
        <a href="submit.php" class="flex-1 rounded-full border border-slate-200 px-3 py-2 text-center text-sm font-bold text-slate-700 transition hover:border-ink"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
      </div>
      <?php render_banner_slot('property_sidebar', 'compact'); ?>
    </aside>
  </section>

  <section class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_380px] grid-cols-1">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
      <div class="mb-6 flex items-center gap-3">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accentSoft text-lg text-accent">★</span>
        <h3 class="text-2xl font-extrabold tracking-tight text-ink"><?= e(t('รายละเอียดประกาศ', 'Listing details')) ?></h3>
      </div>
      <p class="whitespace-pre-line text-base leading-8 text-slate-600"><?= nl2br(e($p['description'] ?: t('ผู้ลงประกาศยังไม่ได้เพิ่มรายละเอียดเพิ่มเติม', 'The lister has not added a description yet'))) ?></p>

      <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('ประเภท', 'Type')) ?></p>
          <p class="mt-2 text-base font-extrabold text-ink"><?= e(category_label_bi($p['property_category'])) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('รูปแบบ', 'Listing type')) ?></p>
          <p class="mt-2 text-base font-extrabold text-ink"><?= e($typeWord) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('ราคา', 'Price')) ?></p>
          <p class="mt-2 text-base font-extrabold text-ink"><?= e(is_en() ? number_format((int)$p['price']) . ($p['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($p['type'], (int)$p['price'])) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e(t('รหัสประกาศ', 'Listing ID')) ?></p>
          <p class="mt-2 text-base font-extrabold text-ink">#<?= (int)$p['id'] ?></p>
        </div>
        <?php foreach ([
          'bedrooms' => [t('ห้องนอน', 'Bedrooms'), t(' ห้อง', ' rooms')],
          'bathrooms' => [t('ห้องน้ำ', 'Bathrooms'), t(' ห้อง', ' rooms')],
          'area_sqm' => [t('พื้นที่ใช้สอย', 'Floor area'), t(' ตร.ม.', ' sq.m.')],
          'land_area' => [t('พื้นที่ดิน', 'Land area'), t(' ตร.ว.', ' sq.wa')],
          'parking_spaces' => [t('ที่จอดรถ', 'Parking'), t(' คัน', ' spaces')],
          'floor_number' => [t('ชั้น', 'Floor'), ''],
          'furnished' => [t('เฟอร์นิเจอร์', 'Furnishing'), ''],
        ] as $field => [$label, $unit]): ?>
          <?php if ($p[$field] !== null && $p[$field] !== ''):
            $fieldVal = (string)$p[$field];
            if ($field === 'furnished') {
              $fieldVal = match (trim($fieldVal)) {
                'เฟอร์นิเจอร์ครบ', 'ตกแต่งครบ' => t('เฟอร์นิเจอร์ครบ', 'Fully furnished'),
                'ไม่มีเฟอร์นิเจอร์', 'ห้องเปล่า' => t('ไม่มีเฟอร์นิเจอร์', 'Unfurnished'),
                'มีบางส่วน' => t('มีบางส่วน', 'Partially furnished'),
                default => $fieldVal,
              };
            }
          ?>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs uppercase tracking-[.15em] text-slate-500"><?= e($label) ?></p>
              <p class="mt-2 text-base font-extrabold text-ink"><?= e($fieldVal) ?><?= e($unit) ?></p>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
      <h3 class="text-xl font-extrabold text-ink"><?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?></h3>
      <?php $amenities = array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string)$p['amenities']))); ?>
      <?php if ($amenities): ?>
        <ul class="mt-5 space-y-3 text-sm text-slate-700">
          <?php foreach ($amenities as $amenity): ?>
            <li class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-accentSoft text-accent">✓</span><span><?= e($amenity) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="mt-5 text-sm text-slate-500"><?= e(t('ผู้ลงประกาศยังไม่ได้ระบุสิ่งอำนวยความสะดวก', 'The lister has not specified amenities yet')) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-accent"><?= e(t('ทำเลที่ตั้ง', 'Location')) ?></p>
        <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-ink"><?= e(t('แผนที่และสถานที่ใกล้เคียง', 'Map & nearby places')) ?></h3>
      </div>
      <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"><?= e($p['location']) ?></span>
    </div>
    <div class="grid gap-6 grid-cols-1 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="overflow-hidden rounded-3xl border border-slate-200">
        <?php if ($p['lat'] !== null && $p['lng'] !== null): ?>
          <div id="propertyMap" class="h-[340px] w-full" data-lat="<?= e((string)$p['lat']) ?>" data-lng="<?= e((string)$p['lng']) ?>" data-title="<?= e($p['title']) ?>"></div>
          <div class="flex flex-wrap items-center justify-between gap-2 bg-white px-4 py-3 text-sm">
            <span class="text-slate-500"><?= e(t('พิกัด: ', 'Coordinates: ')) ?><?= e(number_format((float)$p['lat'], 6)) ?>, <?= e(number_format((float)$p['lng'], 6)) ?></span>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= e($p['lat']) ?>,<?= e($p['lng']) ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-accent hover:underline"><?= e(t('เปิดใน Google Maps ↗', 'Open in Google Maps ↗')) ?></a>
          </div>
        <?php else: ?>
          <iframe
            class="property-map property-map-fallback"
            title="<?= e(t('แผนที่โดยประมาณของ ', 'Approximate map of ') . $p['location']) ?>"
            src="https://maps.google.com/maps?q=<?= e(rawurlencode((string)$p['location'])) ?>&amp;z=14&amp;output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen></iframe>
          <div class="flex flex-wrap items-center justify-between gap-2 bg-white px-4 py-3 text-sm">
            <span class="text-slate-500"><?= e(t('แผนที่โดยประมาณจากข้อความทำเล · หากต้องการหมุดตรงตำแหน่ง ให้เลือกหมุดในฟอร์มลงประกาศ', 'Approximate map from the location text · For an exact pin, set it in the listing form')) ?></span>
            <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode((string)$p['location'])) ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-accent hover:underline"><?= e(t('เปิดใน Google Maps ↗', 'Open in Google Maps ↗')) ?></a>
          </div>
        <?php endif; ?>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
        <h4 class="mb-4 font-extrabold text-ink"><?= e(t('สถานที่ใกล้เคียง', 'Nearby places')) ?></h4>
        <?php $nearbyPlaces = array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string)$p['nearby_places']))); ?>
        <?php if ($nearbyPlaces): ?>
          <ul class="space-y-3 text-sm text-slate-700">
            <?php foreach ($nearbyPlaces as $place): ?>
              <?php $placeQuery = $p['lat'] !== null ? '&sll=' . e($p['lat']) . ',' . e($p['lng']) : ''; ?>
              <li class="flex items-center justify-between gap-2 rounded-2xl bg-white p-3">
                <span><?= e($place) ?></span>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($place . ' ' . $p['location']) ?><?= $placeQuery ?>" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-bold text-accent hover:underline"><?= e(t('ดูแผนที่ ↗', 'View map ↗')) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-sm text-slate-500"><?= e(t('ผู้ลงประกาศยังไม่ได้ระบุสถานที่ใกล้เคียง', 'The lister has not specified nearby places yet')) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="mt-10 rounded-2xl sm:rounded-3xl bg-[#faf7ff] p-4 sm:p-8 shadow-soft border border-slate-200/80">
    <div class="grid gap-6 grid-cols-1 lg:grid-cols-[0.85fr_1.15fr]">
      <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-accent">Financial Health & Decision Tool</p>
        <h3 class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight text-ink"><?= e(t('คำนวณเงินดาวน์ & สุขภาพการเงิน', 'Down payment & financial health')) ?></h3>
        <p class="mt-2 sm:mt-3 text-sm sm:text-base leading-relaxed text-slate-800 font-medium"><?= e(t('เครื่องมือช่วยตัดสินใจซื้ออสังหาฯ: ปรับเงินดาวน์ คำนวณค่างวด พร้อมวิเคราะห์สัดส่วนรายได้ต่อภาระผ่อน เพื่อให้คุณเห็นภาพชัดเจนว่าชีวิตหลังซื้อบ้านจะคล่องตัวหรือตึงมือเพียงใด', 'Decision assistant: adjust down payment, compute installments, and assess your income-to-debt ratio to see your lifestyle and financial comfort after buying.')) ?></p>

        <!-- กล่องคำแนะนำภาพรวมสุขภาพทางการเงินเบื้องต้น (Financial Rules of Thumb) คอนทราสต์สูง อ่านง่ายชัดเจน -->
        <div class="mt-5 rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 sm:p-5 text-slate-900 space-y-2.5 shadow-xs">
          <div class="flex items-center gap-2 font-black text-amber-950 text-sm sm:text-base">
            <span class="text-lg">💡</span>
            <span><?= e(t('เกณฑ์ประเมิน DSR (Debt Service Ratio)', 'DSR Financial Benchmark')) ?></span>
          </div>
          <p class="leading-relaxed text-xs sm:text-sm font-semibold text-slate-800"><?= e(t('สถาบันการเงินแนะนำให้ภาระผ่อนบ้านไม่ควรเกิน 30% - 40% ของรายได้ เพื่อคงสภาพคล่องสำหรับการใช้ชีวิต การออม และเหตุฉุกเฉิน', 'Banks generally recommend home installment below 30%-40% of monthly income to maintain living liquidity and savings.')) ?></p>
          <div class="grid grid-cols-2 gap-2 pt-1 font-black text-xs">
            <span class="rounded-xl border border-emerald-300 bg-emerald-100 px-2.5 py-1.5 text-emerald-950 flex items-center gap-1"><span>✅</span> <span>≤ 30%: สบาย ไร้กังวล</span></span>
            <span class="rounded-xl border border-blue-300 bg-blue-100 px-2.5 py-1.5 text-blue-950 flex items-center gap-1"><span>👌</span> <span>31-40%: กำลังดี ผ่อนไหว</span></span>
            <span class="rounded-xl border border-amber-400 bg-amber-100 px-2.5 py-1.5 text-amber-950 flex items-center gap-1"><span>⚠️</span> <span>41-50%: เริ่มตึง มีวินัย</span></span>
            <span class="rounded-xl border border-rose-300 bg-rose-100 px-2.5 py-1.5 text-rose-950 flex items-center gap-1"><span>🚨</span> <span>> 50%: ตึงมาก เสี่ยงสูง</span></span>
          </div>
        </div>
      </div>

      <div class="rounded-2xl sm:rounded-3xl bg-white p-4 sm:p-6 shadow-soft space-y-5 border border-slate-200">
        <!-- แถบค่างวดประมาณการ และสัดส่วนเงินกู้สุทธิ -->
        <div class="flex items-end justify-between gap-3 border-b-2 border-slate-100 pb-4">
          <div>
            <p class="text-xs font-black uppercase tracking-[.14em] text-slate-600"><?= e(t('ประมาณการผ่อนชำระ', 'Estimated monthly payment')) ?></p>
            <p class="mt-1.5 text-2xl sm:text-4xl font-black tracking-tight text-ink"><span id="monthlyPayment">0</span> <span class="text-sm sm:text-lg text-slate-700 font-black"><?= e(t('บาท/เดือน', 'THB/month')) ?></span></p>
          </div>
          <div class="rounded-2xl bg-slate-100 border border-slate-300 px-4 py-2.5 text-right">
            <p class="text-xs font-black uppercase tracking-[.14em] text-slate-600"><?= e(t('วงเงินกู้สุทธิ', 'Net loan ratio')) ?></p>
            <p id="loanRatio" class="mt-0.5 text-lg sm:text-xl font-black text-ink">80%</p>
          </div>
        </div>

        <div class="space-y-4 sm:space-y-5">
          <!-- 1. ราคาอสังหาริมทรัพย์ -->
          <div>
            <div class="mb-2 flex items-center justify-between gap-2">
              <label for="homePrice" class="text-xs sm:text-sm font-black text-slate-900"><?= e(t('ราคาอสังหาริมทรัพย์', 'Property price')) ?></label>
              <span class="flex items-center gap-1.5">
                <input id="priceNumber" class="h-9 sm:h-10 w-28 sm:w-36 rounded-xl border-2 border-slate-300 bg-slate-50 px-2.5 text-right text-xs sm:text-sm font-black text-ink outline-none focus:border-accent focus:bg-white" type="number" min="100000" max="50000000" step="10000" value="<?= (int)$p['price'] ?: 5890000 ?>">
                <span class="text-xs sm:text-sm font-black text-slate-800"><?= e(t('บาท', 'THB')) ?></span>
              </span>
            </div>
            <input id="homePrice" class="w-full accent-accent cursor-pointer h-2 bg-slate-200 rounded-lg" type="range" min="100000" max="20000000" step="10000" value="<?= (int)$p['price'] ?: 5890000 ?>">
          </div>

          <!-- 2. เงินดาวน์ (Down payment) สามารถกรอก % เองได้ หรือเลื่อน Range ได้ พร้อมคำนวณเป็นยอดเงินบาทจริง -->
          <div class="rounded-2xl border-2 border-slate-300 bg-slate-50 p-3.5 sm:p-4 space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <div>
                <label for="downPercent" class="text-xs sm:text-sm font-black text-slate-900 flex items-center gap-1.5">
                  <span class="text-base">💰</span>
                  <span><?= e(t('เงินดาวน์ (Down payment)', 'Down payment')) ?></span>
                </label>
                <p class="text-xs sm:text-sm text-slate-700 font-bold mt-1">
                  <?= e(t('คิดเป็นเงินดาวน์จริง:', 'Actual down payment:')) ?>
                  <span id="downAmountThb" class="font-black text-ink text-sm sm:text-base ml-1">0 บาท</span>
                </p>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-black text-slate-700"><?= e(t('สัดส่วน', 'Ratio')) ?></span>
                <div class="flex items-center rounded-xl border-2 border-slate-300 bg-white px-2.5 py-1.5 shadow-xs focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20">
                  <input id="downPercentNumber" class="w-12 text-right text-xs sm:text-sm font-black text-ink outline-none" type="number" min="0" max="90" step="1" value="20">
                  <span class="text-xs font-black text-slate-800 ml-1">%</span>
                </div>
              </div>
            </div>
            <input id="downPercent" class="w-full accent-accent cursor-pointer h-2 bg-slate-200 rounded-lg" type="range" min="0" max="90" step="1" value="20">
            <div class="flex justify-between text-xs font-black text-slate-700 px-0.5">
              <span>0% (กู้เต็ม 100%)</span>
              <span>10%</span>
              <span>20% (มาตรฐาน)</span>
              <span>30%</span>
              <span>50%+</span>
            </div>
          </div>

          <!-- 3. อัตราดอกเบี้ย, ระยะเวลากู้, เงินกู้เพิ่มเติม -->
          <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3">
            <label class="block text-xs sm:text-sm font-black text-slate-900"><?= e(t('อัตราดอกเบี้ย / ปี (%)', 'Interest rate / year (%)')) ?>
              <input id="interestRate" class="mt-1.5 h-10 sm:h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 px-3 text-sm sm:text-base font-black text-ink outline-none focus:border-accent focus:bg-white" type="number" min="0" max="20" step="0.05" value="3.25">
            </label>
            <label class="block text-xs sm:text-sm font-black text-slate-900"><?= e(t('ระยะเวลากู้ (ปี)', 'Loan term (years)')) ?>
              <input id="loanYears" class="mt-1.5 h-10 sm:h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 px-3 text-sm sm:text-base font-black text-ink outline-none focus:border-accent focus:bg-white" type="number" min="1" max="40" step="1" value="30">
            </label>
            <label class="block text-xs sm:text-sm font-black text-slate-900"><?= e(t('เงินกู้เพิ่มเติม/ตกแต่ง (บาท)', 'Extra/decor loan (THB)')) ?>
              <input id="extraLoan" class="mt-1.5 h-10 sm:h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 px-3 text-sm sm:text-base font-black text-ink outline-none focus:border-accent focus:bg-white" type="number" min="0" max="20000000" step="10000" value="0">
            </label>
          </div>

          <!-- 4. ส่วนสรุปตัวเลขสินเชื่อรวม (ตัวหนา ชัดเจน สบายตา) -->
          <div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-slate-50 p-3.5 sm:p-4 text-xs sm:text-sm text-slate-700 space-y-2 font-medium">
            <div class="flex justify-between items-center"><span class="font-bold text-slate-700"><?= e(t('ยอดเงินดาวน์ที่ต้องเตรียม', 'Down payment required')) ?></span><span id="downSummary" class="font-black text-slate-900 text-sm sm:text-base">0 <?= e(t('บาท', 'THB')) ?></span></div>
            <div class="flex justify-between items-center"><span class="font-bold text-slate-700"><?= e(t('ยอดขอกู้ธนาคารสุทธิ', 'Net bank loan')) ?></span><span id="loanSummary" class="font-black text-slate-900 text-sm sm:text-base">0 <?= e(t('บาท', 'THB')) ?></span></div>
            <div class="flex justify-between items-center"><span class="font-bold text-slate-700"><?= e(t('ดอกเบี้ยรวมตลอดสัญญา', 'Total interest')) ?></span><span id="interestSummary" class="font-black text-slate-900 text-sm sm:text-base">0 <?= e(t('บาท', 'THB')) ?></span></div>
            <div class="flex justify-between items-center border-t-2 border-slate-200 pt-2 mt-1 font-black text-slate-900"><span><?= e(t('ยอดชำระคืนรวมตลอดสัญญา', 'Total repayment')) ?></span><span id="totalSummary" class="font-black text-accentDark text-base sm:text-lg">0 <?= e(t('บาท', 'THB')) ?></span></div>
          </div>

          <!-- 5. เครื่องมือคำนวณสุขภาพทางการเงิน (Financial Health & Decision Assistant) -->
          <div class="rounded-2xl sm:rounded-3xl border-2 border-slate-300 bg-white p-4 sm:p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-accentSoft text-lg text-accentDark border border-accent/30 font-bold">🩺</span>
                <div>
                  <h4 class="text-sm sm:text-base font-black text-ink"><?= e(t('วิเคราะห์สุขภาพทางการเงินและคุณภาพชีวิต', 'Financial Health & Decision Assistant')) ?></h4>
                  <p class="text-xs font-bold text-slate-600"><?= e(t('ใส่เงินเดือนเพื่อดูภาพจำลองชีวิตและสัดส่วนภาระผ่อน (DSR)', 'Enter monthly income to see your lifestyle scenario & DSR ratio')) ?></p>
                </div>
              </div>
            </div>

            <!-- ช่องกรอกเงินเดือน / รายได้ต่อเดือน -->
            <div class="grid grid-cols-1 sm:grid-cols-[1.2fr_0.8fr] gap-3 items-end">
              <div>
                <label for="monthlyIncomeInput" class="block text-xs font-black text-slate-800 mb-1.5">
                  <?= e(t('รายได้รวมต่อเดือนของคุณ/ผู้กู้ร่วม (บาท)', 'Monthly total income (THB)')) ?>
                </label>
                <div class="relative flex items-center">
                  <span class="absolute left-3.5 text-slate-600 font-black text-sm">฿</span>
                  <input id="monthlyIncomeInput" class="h-11 sm:h-12 w-full rounded-xl border-2 border-slate-300 bg-white pl-8 pr-3 text-base sm:text-lg font-black text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20" type="number" min="10000" max="2000000" step="5000" value="45000" placeholder="เช่น 45000">
                </div>
              </div>

              <!-- ปุ่มเลือกตัวอย่างเงินเดือนด่วน -->
              <div>
                <span class="block text-xs font-bold text-slate-600 mb-1.5"><?= e(t('เลือกด่วน:', 'Quick select:')) ?></span>
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-xs">
                  <button type="button" class="quick-salary-btn rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-1.5 font-black text-slate-800 hover:border-ink hover:bg-slate-100 transition" data-val="30000">30k</button>
                  <button type="button" class="quick-salary-btn rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-1.5 font-black text-slate-800 hover:border-ink hover:bg-slate-100 transition" data-val="45000">45k</button>
                  <button type="button" class="quick-salary-btn rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-1.5 font-black text-slate-800 hover:border-ink hover:bg-slate-100 transition" data-val="60000">60k</button>
                  <button type="button" class="quick-salary-btn rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-1.5 font-black text-slate-800 hover:border-ink hover:bg-slate-100 transition" data-val="100000">100k</button>
                </div>
              </div>
            </div>

            <!-- กล่องสรุปผลการวิเคราะห์ระดับสุขภาพทางการเงิน (Dynamic Card) -->
            <div id="healthAssessmentCard" class="rounded-2xl border-2 p-4 sm:p-5 transition-all duration-300">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b-2 border-slate-200/80 pb-3.5">
                <div class="flex items-center gap-3">
                  <div id="healthBadgeIcon" class="grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl shadow-sm border">
                    🌟
                  </div>
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <span id="healthGradePill" class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-wider shadow-xs">
                        ยอดเยี่ยม
                      </span>
                      <span id="healthRatioLabel" class="text-xs font-black text-slate-800">
                        สัดส่วนค่างวด 0% ของรายได้
                      </span>
                    </div>
                    <h5 id="healthTitle" class="mt-1.5 text-base sm:text-lg font-black text-ink leading-snug">
                      กำลังคำนวณ...
                    </h5>
                  </div>
                </div>
                <div class="text-left sm:text-right bg-white p-3 sm:p-0 rounded-xl border sm:border-0 border-slate-200">
                  <p class="text-xs font-black uppercase tracking-wider text-slate-600"><?= e(t('เงินเหลือใช้หลังผ่อนบ้าน', 'Remaining after installment')) ?></p>
                  <p id="remainingMoneyText" class="text-lg sm:text-2xl font-black text-ink mt-0.5">0 บาท/เดือน</p>
                </div>
              </div>

              <!-- หลอดวัดระดับความตึงตัวทางการเงิน (Health Meter Bar) -->
              <div class="mt-4 space-y-2">
                <div class="flex justify-between text-xs sm:text-sm font-black text-slate-800">
                  <span><?= e(t('ภาระผ่อนต่อเงินเดือน (DSR)', 'Installment-to-Income (DSR)')) ?>: <strong id="healthDsrPercent" class="text-ink font-black text-sm sm:text-base">0%</strong></span>
                  <span id="healthMeterDesc" class="font-black text-emerald-800">ผ่อนสบายมาก</span>
                </div>
                <div class="h-3.5 w-full overflow-hidden rounded-full bg-slate-200 border border-slate-300 flex">
                  <div id="healthProgressBar" class="h-full rounded-full transition-all duration-500 ease-out" style="width: 30%;"></div>
                </div>
                <div class="flex justify-between text-xs font-black text-slate-700 pt-0.5">
                  <span>0% (สบาย)</span>
                  <span>30%</span>
                  <span>40% (เพดานแนะนำ)</span>
                  <span>50%+ (เสี่ยง)</span>
                </div>
              </div>

              <!-- บรรยายภาพจำลองชีวิตเมื่อตัดสินใจซื้อ (Life Scenario & Advice) คอนทราสต์สูง อ่านเข้าใจทันที -->
              <div class="mt-4 rounded-xl bg-white p-4 border-2 border-slate-200 shadow-xs space-y-2.5">
                <p class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                  <span class="text-base">🏙️</span>
                  <span><?= e(t('ภาพจำลองวิถีชีวิตจริงหลังซื้อทรัพย์นี้ (Lifestyle Scenario)', 'Post-purchase lifestyle scenario')) ?></span>
                </p>
                <p id="healthNarrative" class="text-sm sm:text-base leading-relaxed text-slate-900 font-semibold">
                  กำลังประเมินภาพรวมวิถีชีวิตและการเงินของคุณ...
                </p>
                <div class="pt-2.5 border-t border-slate-200 flex items-start gap-2 text-xs sm:text-sm text-slate-800 font-medium">
                  <span class="text-accentDark font-black shrink-0">💡 คำแนะนำ:</span>
                  <span id="healthActionTip" class="leading-relaxed font-bold text-slate-900">...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- AI Overviews & Knowledge FAQ ประจำทรัพย์ -->
  <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
    <div class="flex items-center gap-3">
      <span class="grid h-10 w-10 place-items-center rounded-full bg-accentSoft text-lg text-accent">💡</span>
      <div>
        <h3 class="text-xl font-extrabold text-ink"><?= e(t('คำถามและข้อควรรู้เกี่ยวกับประกาศนี้ (AI Knowledge FAQ)', 'Property FAQs & Buying Insights')) ?></h3>
        <p class="text-xs text-slate-500"><?= e(t('สรุปสาระสำคัญ การประเมินค่างวด และขั้นตอนติดต่อเพื่อความสะดวกของผู้ซื้อ/ผู้เช่า', 'Key insights, loan estimates, and direct inquiry steps')) ?></p>
      </div>
    </div>
    <div class="mt-6 space-y-4">
      <?php foreach ($propertyAiFaq['mainEntity'] as $faqItem): ?>
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

  <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-4 sm:p-6 shadow-soft">
    <div class="mb-5 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-accent"><?= e(t('ประกาศอื่น ๆ', 'More listings')) ?></p>
        <h3 class="mt-1 text-xl sm:text-2xl font-extrabold tracking-tight text-ink leading-snug"><?= e(t('ประกาศแนะนำที่คล้ายกัน', 'Similar recommended listings')) ?></h3>
      </div>
      <a href="search.php?type=<?= e($p['type']) ?>" class="shrink-0 whitespace-nowrap rounded-full border border-slate-300 px-3.5 py-1.5 text-xs sm:text-sm font-bold text-ink transition hover:border-ink hover:bg-slate-50"><?= e(t('ดูทั้งหมด ›', 'View all ›')) ?></a>
    </div>
    <?php if ($related): ?>
      <div class="grid gap-3.5 sm:gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($related as $r): ?>
          <?php
            $relVideoFilename = basename((string)($r['video_file'] ?? ''));
            $relVideoPath = $relVideoFilename !== '' ? UPLOAD_DIR . $relVideoFilename : '';
            $relVideoUrl = ($relVideoFilename !== '' && is_file($relVideoPath)) ? 'uploads/' . rawurlencode($relVideoFilename) : '';
            $hasRelVideo = $relVideoUrl !== '';
            $cover = $r['cover'] && is_file(UPLOAD_DIR . basename($r['cover'])) ? 'uploads/' . rawurlencode(basename($r['cover'])) : 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg';
          ?>
          <a href="property.php?id=<?= (int)$r['id'] ?>" class="group rounded-2xl sm:rounded-3xl border border-slate-200 bg-white p-3 shadow-soft transition hover:-translate-y-0.5 hover:border-accent block">
            <div class="card-media-wrap<?= $hasRelVideo ? ' has-hover-video video-playing' : '' ?>"<?= $hasRelVideo ? ' data-card-video="' . e($relVideoUrl) . '"' : '' ?>>
              <?php if ($hasRelVideo): ?>
                <video class="card-hover-video" src="<?= e($relVideoUrl) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= e($cover) ?>"></video>
              <?php endif; ?>
              <img src="<?= e($cover) ?>" alt="<?= e($r['title']) ?>" loading="lazy" decoding="async" width="640" height="220">
              <?php if ($hasRelVideo): ?>
                <button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>" title="<?= e(t('วิดีโอคลิป (แตะเปิด/ปิดเสียง)', 'Video (tap to unmute)')) ?>">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                  <span><?= e(t('วิดีโอ', 'Video')) ?></span>
                  <svg class="sound-muted" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('ปิดเสียงอยู่', 'Muted')) ?>"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                  <svg class="sound-unmuted" style="display:none;" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('เปิดเสียงแล้ว', 'Playing with sound')) ?>"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                </button>
              <?php endif; ?>
            </div>
            <p class="mt-3 font-extrabold text-ink text-sm sm:text-base leading-snug line-clamp-2"><?= e($r['title']) ?></p>
            <p class="mt-1 text-xs sm:text-sm text-slate-500 truncate">⌖ <?= e(category_label_bi($r['property_category'])) ?> · <?= e($r['location']) ?></p>
            <div class="mt-2 font-black text-accent">
              <?php
                require_once __DIR__ . '/includes/price_helpers.php';
                echo render_price_badge((int)$r['price'], !empty($r['original_price']) ? (int)$r['original_price'] : null, $r['type']);
              ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-slate-500"><?= e(t('ยังไม่มีประกาศอื่น ๆ ในหมวดนี้ ', 'No other listings in this category yet ')) ?><a href="search.php" class="font-bold text-accent hover:underline"><?= e(t('ดูประกาศทั้งหมด', 'View all listings')) ?></a></p>
    <?php endif; ?>
  </section>
</main>

<?php
render_page_cta([
    'title'   => t('ชอบทำเลแบบนี้? ดูประกาศอื่นในย่านเดียวกัน', 'Like this location? See other listings in the same area'),
    'text'    => t('เทียบราคาและทำเลเพิ่มเติมจากหมวดที่เกี่ยวข้อง หรือบันทึกทรัพย์นี้ไว้ติดต่อผู้ขายได้ทันที', 'Compare prices and locations from related categories, or save this property to contact the seller directly.'),
    'primary' => ['search.php?type=' . rawurlencode($p['type']) . '&q=' . rawurlencode($p['location']), t('ดูประกาศในย่าน ' . $p['location'], 'Listings in ' . $p['location'])],
    'ghost'   => ['category.php?page=' . rawurlencode((string)$catSlug), t('ดูหมวด ' . category_label($p['property_category']), 'Browse ' . (property_category_en_label($p['property_category']) ?? 'this category'))],
]);
render_to_top();
?>

<?php render_site_footer(); ?>

<dialog id="galleryDialog" class="overflow-hidden rounded-3xl border-0 bg-transparent p-0">
  <div class="property-lightbox-frame">
    <button id="closeGallery" type="button" class="property-lightbox-close grid h-10 w-10 place-items-center rounded-full bg-black/60 text-2xl text-white" aria-label="<?= e(t('ปิดรูป', 'Close image')) ?>">×</button>
    <button type="button" class="property-lightbox-arrow prev" data-lightbox-prev aria-label="<?= e(t('รูปก่อนหน้า', 'Previous image')) ?>">‹</button>
    <img id="galleryDialogImage" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="">
    <button type="button" class="property-lightbox-arrow next" data-lightbox-next aria-label="<?= e(t('รูปถัดไป', 'Next image')) ?>">›</button>
    <span class="property-lightbox-count" data-lightbox-count></span>
  </div>
</dialog>

<?php seo_track_script([['event' => 'property', 'property_id' => (int)$p['id']]]); ?>
<?php render_ux_scripts(); ?>
<script>
  const shareButton = document.getElementById('shareProperty');
  const saveButton = document.getElementById('saveProperty');
  const galleryDialog = document.getElementById('galleryDialog');
  const galleryImage = document.getElementById('galleryDialogImage');

  shareButton?.addEventListener('click', async () => {
    const shareData = { title: document.title, url: window.location.href };
    if (navigator.share) {
      navigator.share(shareData).catch(() => {});
      return;
    }
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(window.location.href);
      shareButton.textContent = '<?= e(t('คัดลอกแล้ว', 'Copied')) ?>';
      setTimeout(() => { shareButton.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.5 11 7.8-4.7M8.5 13.2l7.7 4.5"/></svg><?= e(t('แชร์', 'Share')) ?>'; }, 1500);
    }
  });

  const gallerySlides = Array.from(document.querySelectorAll('[data-gallery-image]'));
  const lightboxCount = document.querySelector('[data-lightbox-count]');
  const galleryTrack = document.getElementById('propertyGalleryTrack');
  const galleryCount = document.getElementById('propertyGalleryCount');
  const galleryDots = document.getElementById('propertyGalleryDots');
  let activeImage = 0;

  const showGalleryImage = (index, openDialog = false) => {
    if (!gallerySlides.length) return;
    activeImage = (index + gallerySlides.length) % gallerySlides.length;
    galleryImage.src = gallerySlides[activeImage].dataset.galleryImage;
    galleryImage.alt = gallerySlides[activeImage].querySelector('img')?.alt || '<?= e(t('ภาพประกาศ', 'Listing photo')) ?>';
    if (lightboxCount) lightboxCount.textContent = `${activeImage + 1} / ${gallerySlides.length}`;
    if (openDialog && !galleryDialog.open) galleryDialog.showModal();
  };
  gallerySlides.forEach((button, index) => button.addEventListener('click', () => showGalleryImage(index, true)));
  document.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => showGalleryImage(activeImage - 1));
  document.querySelector('[data-lightbox-next]')?.addEventListener('click', () => showGalleryImage(activeImage + 1));

  // Sync mobile swipe on track with indicator dots & counter
  if (galleryTrack && gallerySlides.length > 1) {
    let scrollTimeout;
    galleryTrack.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const slideWidth = galleryTrack.offsetWidth;
        if (!slideWidth) return;
        const currentIdx = Math.round(galleryTrack.scrollLeft / slideWidth);
        const dots = galleryDots?.querySelectorAll('.gallery-dot');
        if (dots) {
          dots.forEach((d, i) => d.classList.toggle('active', i === currentIdx));
        }
        if (galleryCount) {
          galleryCount.textContent = `${currentIdx + 1} / ${gallerySlides.length}`;
        }
      }, 50);
    }, { passive: true });
  }
  let touchStartX = 0;
  galleryDialog?.addEventListener('touchstart', event => { touchStartX = event.changedTouches[0]?.clientX ?? 0; }, { passive: true });
  galleryDialog?.addEventListener('touchend', event => {
    const delta = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
    if (Math.abs(delta) > 45) showGalleryImage(activeImage + (delta < 0 ? 1 : -1));
  }, { passive: true });
  document.getElementById('closeGallery')?.addEventListener('click', () => galleryDialog.close());
  galleryDialog?.addEventListener('click', (event) => {
    if (event.target === galleryDialog) galleryDialog.close();
  });

  const priceInput = document.getElementById('homePrice');
  if (priceInput) {
  const priceNumber = document.getElementById('priceNumber');
  const downRange = document.getElementById('downPercent');
  const downNumber = document.getElementById('downPercentNumber');
  const downAmountThb = document.getElementById('downAmountThb');
  const downSummary = document.getElementById('downSummary');
  const interestInput = document.getElementById('interestRate');
  const yearsInput = document.getElementById('loanYears');
  const extraLoanInput = document.getElementById('extraLoan');
  const monthlyEl = document.getElementById('monthlyPayment');
  const loanRatio = document.getElementById('loanRatio');
  const loanSummary = document.getElementById('loanSummary');
  const interestSummary = document.getElementById('interestSummary');
  const totalSummary = document.getElementById('totalSummary');

  // สุขภาพทางการเงิน & Decision Assistant Elements
  const incomeInput = document.getElementById('monthlyIncomeInput');
  const healthCard = document.getElementById('healthAssessmentCard');
  const healthBadgeIcon = document.getElementById('healthBadgeIcon');
  const healthGradePill = document.getElementById('healthGradePill');
  const healthRatioLabel = document.getElementById('healthRatioLabel');
  const healthTitle = document.getElementById('healthTitle');
  const remainingMoneyText = document.getElementById('remainingMoneyText');
  const healthDsrPercent = document.getElementById('healthDsrPercent');
  const healthMeterDesc = document.getElementById('healthMeterDesc');
  const healthProgressBar = document.getElementById('healthProgressBar');
  const healthNarrative = document.getElementById('healthNarrative');
  const healthActionTip = document.getElementById('healthActionTip');

  const formatTh = (n) => new Intl.NumberFormat('th-TH').format(Math.round(n));

  const updateMortgage = (source) => {
    let price = Number(priceInput.value || 0);
    if (source === 'price_number') {
      price = Math.min(50000000, Math.max(100000, Number(priceNumber.value || 0)));
      priceInput.value = Math.min(20000000, price);
    } else {
      priceNumber.value = price;
    }

    // จัดการเงินดาวน์ (Down payment %) ทั้งจาก Slider และช่องกรอกตัวเลข %
    let downPct = Number(downRange.value || 20);
    if (source === 'down_number') {
      downPct = Math.min(90, Math.max(0, Number(downNumber.value || 0)));
      downRange.value = downPct;
    } else {
      downNumber.value = downPct;
    }

    const loanPct = Math.max(0, 100 - downPct);
    const downPaymentThb = price * (downPct / 100);
    const annualRate = Number(interestInput.value || 0);
    const years = Math.min(40, Math.max(1, Number(yearsInput.value || 30)));
    const principal = Math.max(0, price * (loanPct / 100) + Number(extraLoanInput.value || 0));
    const months = years * 12;
    const monthlyRate = annualRate / 100 / 12;

    let payment = principal / months;
    if (monthlyRate > 0) {
      payment = (principal * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -months));
    }
    const totalPaid = payment * months;

    // อัปเดตข้อมูลตัวเลขสินเชื่อ
    if (downAmountThb) downAmountThb.textContent = formatTh(downPaymentThb) + ' บาท';
    if (downSummary) downSummary.textContent = formatTh(downPaymentThb) + ' <?= e(t('บาท', 'THB')) ?>';
    loanRatio.textContent = loanPct + '%';
    monthlyEl.textContent = formatTh(payment);
    loanSummary.textContent = formatTh(principal) + ' <?= e(t('บาท', 'THB')) ?>';
    interestSummary.textContent = formatTh(totalPaid - principal) + ' <?= e(t('บาท', 'THB')) ?>';
    totalSummary.textContent = formatTh(totalPaid) + ' <?= e(t('บาท', 'THB')) ?>';

    // คำนวณและประเมินสุขภาพทางการเงิน & วิเคราะห์คุณภาพชีวิต (Financial Health & Decision Assistant)
    updateFinancialHealth(payment);
  };

  const updateFinancialHealth = (monthlyInstallment) => {
    if (!incomeInput) return;
    const income = Math.max(1, Number(incomeInput.value || 0));
    const dsr = (monthlyInstallment / income) * 100;
    const remaining = income - monthlyInstallment;

    if (healthDsrPercent) healthDsrPercent.textContent = dsr.toFixed(1) + '%';
    if (remainingMoneyText) {
      if (remaining >= 0) {
        remainingMoneyText.textContent = formatTh(remaining) + ' บาท/เดือน';
        remainingMoneyText.className = 'text-base sm:text-xl font-black text-emerald-600 mt-0.5';
      } else {
        remainingMoneyText.textContent = '-' + formatTh(Math.abs(remaining)) + ' บาท (ติดลบ)';
        remainingMoneyText.className = 'text-base sm:text-xl font-black text-rose-600 mt-0.5';
      }
    }

    if (healthRatioLabel) {
      healthRatioLabel.textContent = `ภาระผ่อน ${dsr.toFixed(1)}% ของรายได้ (ยอดผ่อน ${formatTh(monthlyInstallment)} บ./ด.)`;
    }

    // ปรับเปอร์เซ็นต์หลอด Bar (Cap ที่ 100%)
    const barWidth = Math.min(100, Math.max(4, dsr));
    if (healthProgressBar) healthProgressBar.style.width = barWidth + '%';

    // กำหนดระดับสุขภาพทางการเงิน 5 ระดับ (ตัวอักษรเข้ม คอนทราสต์สูง อ่านชัดเจน)
    if (dsr <= 25) {
      // ระดับ 1: ยอดเยี่ยม สบาย ไร้กังวล
      healthCard.className = 'rounded-2xl border-2 border-emerald-500 bg-emerald-50/70 p-4 sm:p-5 transition-all duration-300 shadow-sm';
      healthBadgeIcon.className = 'grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl bg-emerald-200 text-emerald-950 shadow-xs border border-emerald-400';
      healthBadgeIcon.textContent = '🏖️';
      healthGradePill.className = 'rounded-full bg-emerald-700 text-white px-3 py-1 text-xs font-black uppercase tracking-wider';
      healthGradePill.textContent = 'ยอดเยี่ยม ผ่อนสบายมาก (DSR ≤ 25%)';
      healthTitle.textContent = 'สุขภาพการเงินแข็งแกร่งมาก — ใช้ชีวิตได้อย่างอิสระไร้กังวล';
      healthMeterDesc.textContent = 'ผ่อนชิลล์มาก ไม่กระทบชีวิตประจำวัน';
      healthMeterDesc.className = 'font-black text-emerald-800';
      healthProgressBar.className = 'h-full rounded-full bg-emerald-600 transition-all duration-500 ease-out';
      healthNarrative.innerHTML = 'หลังหักค่างวดบ้านแล้ว คุณจะยังมีเงินเหลือใช้ถึง <strong class="text-emerald-950 font-black text-base">' + formatTh(remaining) + ' บาท/เดือน</strong> ทำให้สามารถกิน เที่ยว ช้อปปิ้ง ท่องเที่ยวต่างประเทศ และยังมีเงินเหลือเก็บออมลงทุนสร้างความมั่งคั่งได้ตามปกติ ชีวิตมีความสุขและผ่อนคลายเต็มที่';
      healthActionTip.textContent = 'พร้อมกู้ซื้อได้ทันที! แบงก์อนุมัติผ่านง่ายมาก และคุณยังสามารถโปะค่างวดเพิ่มเพื่อตัดเงินต้นให้หมดหนี้เร็วขึ้นได้อีกด้วย';
    } else if (dsr <= 35) {
      // ระดับ 2: แข็งแรง กำลังดี สบายใจ
      healthCard.className = 'rounded-2xl border-2 border-teal-500 bg-teal-50/70 p-4 sm:p-5 transition-all duration-300 shadow-sm';
      healthBadgeIcon.className = 'grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl bg-teal-200 text-teal-950 shadow-xs border border-teal-400';
      healthBadgeIcon.textContent = '☕';
      healthGradePill.className = 'rounded-full bg-teal-700 text-white px-3 py-1 text-xs font-black uppercase tracking-wider';
      healthGradePill.textContent = 'สมดุลดีมาก (DSR 26% - 35%)';
      healthTitle.textContent = 'สัดส่วนมาตรฐานในอุดมคติ — มีบ้านสวยพร้อมรักษาสภาพคล่อง';
      healthMeterDesc.textContent = 'อยู่ในเกณฑ์แนะนำของธนาคาร';
      healthMeterDesc.className = 'font-black text-teal-800';
      healthProgressBar.className = 'h-full rounded-full bg-teal-600 transition-all duration-500 ease-out';
      healthNarrative.innerHTML = 'สัดส่วนนี้เป็นมาตรฐานที่ผู้เชี่ยวชาญด้านการเงินแนะนำ คุณเหลือเงินใช้จ่าย <strong class="text-teal-950 font-black text-base">' + formatTh(remaining) + ' บาท/เดือน</strong> เพียงพอสำหรับค่ากินอยู่ ค่าน้ำไฟ ประกันชีวิต และยังสังสรรค์พักผ่อนวันหยุดได้สบาย ไม่ทำให้รู้สึกอึดอัด';
      healthActionTip.textContent = 'เป็นระดับที่น่าซื้อมาก แนะนำให้เตรียมเงินสำรองฉุกเฉินเผื่อไว้ 3-6 เท่าของค่างวด เพื่อความสบายใจสูงสุดในทุกสถานการณ์';
    } else if (dsr <= 45) {
      // ระดับ 3: ปานกลาง มีวินัย เริ่มตึงมือ
      healthCard.className = 'rounded-2xl border-2 border-amber-500 bg-amber-50/70 p-4 sm:p-5 transition-all duration-300 shadow-sm';
      healthBadgeIcon.className = 'grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl bg-amber-200 text-amber-950 shadow-xs border border-amber-400';
      healthBadgeIcon.textContent = '⚖️';
      healthGradePill.className = 'rounded-full bg-amber-600 text-white px-3 py-1 text-xs font-black uppercase tracking-wider';
      healthGradePill.textContent = 'ปานกลาง ต้องมีวินัย (DSR 36% - 45%)';
      healthTitle.textContent = 'ผ่อนไหวแต่เริ่มตึงมือ — ต้องวางแผนงบประมาณอย่างรัดกุม';
      healthMeterDesc.textContent = 'แตะเพดานที่เริ่มต้องระมัดระวัง';
      healthMeterDesc.className = 'font-black text-amber-900';
      healthProgressBar.className = 'h-full rounded-full bg-amber-500 transition-all duration-500 ease-out';
      healthNarrative.innerHTML = 'หลังหักค่างวดคุณจะมีเงินเหลือ <strong class="text-amber-950 font-black text-base">' + formatTh(remaining) + ' บาท/เดือน</strong> ซึ่งยังสามารถดำรงชีวิตได้ แต่จำเป็นต้องลดค่าใช้จ่ายฟุ่มเฟือย เช่น ปาร์ตี้บ่อยๆ หรือการซื้อของชิ้นใหญ่ อาจต้องคำนวณค่าบัตรเครดิตและภาระหนี้อื่นอย่างใกล้ชิด';
      healthActionTip.textContent = 'หากต้องการผ่อนสบายขึ้น แนะนำให้เพิ่มเงินดาวน์เป็น 25%-30% หรือยืดระยะเวลากู้ให้นานขึ้น หรือมองหาผู้กู้ร่วมเพื่อช่วยเสริมฐานรายได้';
    } else if (dsr <= 60) {
      // ระดับ 4: ค่อนข้างตึง เครียด เสี่ยงสูง
      healthCard.className = 'rounded-2xl border-2 border-orange-500 bg-orange-50/70 p-4 sm:p-5 transition-all duration-300 shadow-sm';
      healthBadgeIcon.className = 'grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl bg-orange-200 text-orange-950 shadow-xs border border-orange-400';
      healthBadgeIcon.textContent = '😰';
      healthGradePill.className = 'rounded-full bg-orange-600 text-white px-3 py-1 text-xs font-black uppercase tracking-wider';
      healthGradePill.textContent = 'ตึงตัวมาก เสี่ยงสูง (DSR 46% - 60%)';
      healthTitle.textContent = 'ภาระหนักเกินไป — มีความเสี่ยงต่อสภาพคล่องและชีวิตประจำวัน';
      healthMeterDesc.textContent = 'เสี่ยงผ่อนไม่ไหวหรือแบงก์ปฏิเสธ';
      healthMeterDesc.className = 'font-black text-orange-900';
      healthProgressBar.className = 'h-full rounded-full bg-orange-500 transition-all duration-500 ease-out';
      healthNarrative.innerHTML = 'เงินเกือบครึ่งหนึ่งของเงินเดือนต้องหมดไปกับค่างวดบ้าน โดยเหลือเงินใช้จ่ายเพียง <strong class="text-orange-950 font-black text-base">' + formatTh(remaining) + ' บาท/เดือน</strong> หากมีเหตุฉุกเฉิน ค่ารักษาพยาบาล หรือค่าซ่อมแซม คุณจะตกอยู่ในภาวะชักหน้าไม่ถึงหลังทันที และอาจต้องกู้หนี้ยืมสินเพิ่ม';
      healthActionTip.textContent = 'เสี่ยงสูงมากที่ธนาคารจะไม่อนุมัติสินเชื่อ แนะนำให้เพิ่มเงินดาวน์ก้อนใหญ่ หรือหาผู้กู้ร่วม (Co-borrower) หรือพิจารณาทรัพย์ในระดับราคาที่ย่อมเยากว่านี้';
    } else {
      // ระดับ 5: วิกฤต แย่มาก อันตราย
      healthCard.className = 'rounded-2xl border-2 border-rose-500 bg-rose-50/80 p-4 sm:p-5 transition-all duration-300 shadow-sm';
      healthBadgeIcon.className = 'grid h-12 w-12 sm:h-14 sm:w-14 shrink-0 place-items-center rounded-2xl text-2xl sm:text-3xl bg-rose-200 text-rose-950 shadow-xs border border-rose-400';
      healthBadgeIcon.textContent = '🚨';
      healthGradePill.className = 'rounded-full bg-rose-700 text-white px-3 py-1 text-xs font-black uppercase tracking-wider';
      healthGradePill.textContent = 'วิกฤต ไม่แนะนำเด็ดขาด (DSR > 60%)';
      healthTitle.textContent = 'เกินกำลังความสามารถ — สภาพคล่องวิกฤตและอาจถูกยึดทรัพย์ได้';
      healthMeterDesc.textContent = 'อันตรายสูงสุด ไม่ผ่านเกณฑ์สินเชื่อ';
      healthMeterDesc.className = 'font-black text-rose-900';
      healthProgressBar.className = 'h-full rounded-full bg-rose-600 transition-all duration-500 ease-out';
      healthNarrative.innerHTML = (remaining >= 0)
        ? 'ค่างวดกินรายได้ส่วนใหญ่เกือบทั้งหมด คุณเหลือเงินเพียง <strong class="text-rose-950 font-black text-base">' + formatTh(remaining) + ' บาท/เดือน</strong> ซึ่งไม่เพียงพอต่อค่าครองชีพขั้นพื้นฐาน การตัดสินใจซื้อในจุดนี้จะสร้างความเครียดมหาศาลต่อการใช้ชีวิต'
        : 'ค่างวดบ้านสูงเกินกว่าเงินเดือนที่คุณได้รับถึง <strong class="text-rose-700 font-black text-base">' + formatTh(Math.abs(remaining)) + ' บาท</strong> ส่งผลให้ติดลบทันทีตั้งแต่เดือนแรกที่เริ่มผ่อน';
      healthActionTip.textContent = 'ชะลอการซื้อทรัพย์ราคานี้ไว้ก่อน ควรเก็บเงินดาวน์เพิ่มหรือปรับลดงบประมาณอสังหาฯ ลงมาให้อยู่ในจุดที่ผ่อนแล้วสบายใจ ปลอดภัยต่ออนาคต';
    }
  };

  // Event Listeners สำหรับการคำนวณและอัปเดตแบบเรียลไทม์
  [priceInput, interestInput, yearsInput, extraLoanInput].forEach((input) => {
    input?.addEventListener('input', () => updateMortgage());
  });
  priceNumber?.addEventListener('input', () => updateMortgage('price_number'));

  downRange?.addEventListener('input', () => updateMortgage('down_range'));
  downNumber?.addEventListener('input', () => updateMortgage('down_number'));

  incomeInput?.addEventListener('input', () => {
    const price = Number(priceInput.value || 0);
    const downPct = Number(downRange.value || 20);
    const loanPct = Math.max(0, 100 - downPct);
    const principal = Math.max(0, price * (loanPct / 100) + Number(extraLoanInput.value || 0));
    const annualRate = Number(interestInput.value || 0);
    const years = Math.min(40, Math.max(1, Number(yearsInput.value || 30)));
    const months = years * 12;
    const monthlyRate = annualRate / 100 / 12;
    let payment = principal / months;
    if (monthlyRate > 0) {
      payment = (principal * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -months));
    }
    updateFinancialHealth(payment);
  });

  // ปุ่ม Quick Salary Buttons (30k, 45k, 60k, 100k)
  document.querySelectorAll('.quick-salary-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const val = btn.getAttribute('data-val');
      if (incomeInput && val) {
        incomeInput.value = val;
        incomeInput.dispatchEvent(new Event('input'));
      }
    });
  });

  // คำนวณครั้งแรกเมื่อเปิดหน้า
  updateMortgage();
  }

  // แผนที่ Leaflet (OpenStreetMap API)
  const mapEl = document.getElementById('propertyMap');
  if (mapEl && typeof L !== 'undefined') {
    const lat = parseFloat(mapEl.dataset.lat);
    const lng = parseFloat(mapEl.dataset.lng);
    const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    L.marker([lat, lng]).addTo(map).bindPopup(mapEl.dataset.title || '<?= e(t('ตำแหน่งประกาศ', 'Listing position')) ?>').openPopup();
    L.control.scale({ imperial: false }).addTo(map);
    map.on('click', () => map.scrollWheelZoom.enable());
  } else if (mapEl) {
    const lat = Number(mapEl.dataset.lat);
    const lng = Number(mapEl.dataset.lng);
    if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
      const fallbackMap = document.createElement('iframe');
      fallbackMap.title = `<?= e(t('แผนที่ทำเล', 'Location map')) ?> ${mapEl.dataset.title || ''}`;
      fallbackMap.loading = 'lazy';
      fallbackMap.referrerPolicy = 'no-referrer-when-downgrade';
      fallbackMap.allowFullscreen = true;
      fallbackMap.src = `https://maps.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&z=15&output=embed`;
      fallbackMap.className = 'h-full w-full border-0';
      mapEl.replaceChildren(fallbackMap);
    }
  }

  // เล่นวิดีโอแนะนำทรัพย์หลักทันทีพร้อมเสียง (Autoplay with Sound & Max Volume 100%)
  const mainVideo = document.getElementById('mainPropertyVideo');
  if (mainVideo) {
    mainVideo.volume = 1.0; // เพิ่ม Volume เสียงเต็มที่ 100%
    mainVideo.muted = false; // เปิดเสียง

    const activateFullAudio = () => {
      mainVideo.muted = false;
      mainVideo.volume = 1.0;
      mainVideo.play().catch(() => {});
      document.removeEventListener('click', activateFullAudio, true);
      document.removeEventListener('touchstart', activateFullAudio, true);
      document.removeEventListener('keydown', activateFullAudio, true);
    };

    const tryPlay = mainVideo.play();
    if (tryPlay !== undefined) {
      tryPlay.catch(() => {
        // Fallback: หากเบราว์เซอร์ติด Autoplay Policy บล็อกเสียงก่อนปฏิสัมพันธ์แรก
        // ให้เล่นแบบเงียบก่อน แล้วปลดล็อกเปิดเสียงดัง 100% ทันทีที่ผู้ใช้คลิก/แตะหน้าจอครั้งแรก
        mainVideo.muted = true;
        mainVideo.play().then(() => {
          document.addEventListener('click', activateFullAudio, { capture: true, once: true });
          document.addEventListener('touchstart', activateFullAudio, { capture: true, once: true });
          document.addEventListener('keydown', activateFullAudio, { capture: true, once: true });
        }).catch(() => {});
      });
    }
  }
</script>
</body>
</html>

<script>
  // Kasa Reels-style Video Previews for Related Property Cards
  (() => {
    const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || window.matchMedia('(hover: none)').matches;
    const relatedCards = Array.from(document.querySelectorAll('.card-media-wrap[data-card-video]'));
    let activeAudioCard = null;

    relatedCards.forEach(mediaWrap => {
      const src = mediaWrap.getAttribute('data-card-video');
      if (!src) return;

      let vEl = mediaWrap.querySelector('video.card-hover-video');
      let userEngaged = false;
      const pill = mediaWrap.querySelector('.card-video-pill');
      const soundMuted = pill?.querySelector('.sound-muted');
      const soundUnmuted = pill?.querySelector('.sound-unmuted');

      const updateUI = (isMuted) => {
        if (pill) pill.classList.toggle('sound-active', !isMuted);
        if (soundMuted) soundMuted.style.display = isMuted ? 'inline-block' : 'none';
        if (soundUnmuted) soundUnmuted.style.display = isMuted ? 'none' : 'inline-block';
      };

      const getOrCreateVideo = () => {
        if (vEl) return vEl;
        vEl = document.createElement('video');
        vEl.className = 'card-hover-video';
        vEl.src = src;
        vEl.loop = true;
        vEl.playsInline = true;
        vEl.preload = 'auto';
        vEl.muted = true;
        vEl.volume = 0.85;
        mediaWrap.appendChild(vEl);
        updateUI(true);
        return vEl;
      };

      // Auto start video directly if pre-rendered
      if (vEl) {
        vEl.muted = true;
        vEl.volume = 0.85;
        vEl.play().catch(() => {});
        updateUI(true);
      }

      const start = () => {
        const v = getOrCreateVideo();
        v.play().catch(() => {});
      };

      const stop = (force = false) => {
        if (!force && userEngaged && !isTouch) return;
        if (vEl && force) {
          vEl.muted = true;
          updateUI(true);
          if (activeAudioCard === mediaWrap) activeAudioCard = null;
        }
        userEngaged = false;
      };

      const card = mediaWrap.closest('a') || mediaWrap;
      card.addEventListener('mouseenter', () => { if (!userEngaged) start(); });
      card.addEventListener('mouseleave', () => stop(false));
      card.addEventListener('focusin', () => { if (!userEngaged) start(); });
      card.addEventListener('focusout', () => stop(false));

      if (pill) {
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const v = getOrCreateVideo();

          if (userEngaged && mediaWrap.classList.contains('video-playing') && !v.muted) {
            stop(true);
            return;
          }

          if (activeAudioCard && activeAudioCard !== mediaWrap) {
            activeAudioCard._mute?.();
          }

          userEngaged = true;
          v.muted = false;
          v.volume = 0.85;
          activeAudioCard = mediaWrap;
          if (!mediaWrap.classList.contains('video-playing')) {
            v.play().then(() => mediaWrap.classList.add('video-playing')).catch(() => {});
          } else {
            v.play().catch(() => {});
          }
          updateUI(false);
        });
      }

      mediaWrap._start = start;
      mediaWrap._stop = (f = true) => stop(f);
      mediaWrap._mute = () => {
        if (vEl) {
          vEl.muted = true;
          updateUI(true);
        }
        userEngaged = false;
      };
    });

    if ('IntersectionObserver' in window && relatedCards.length > 0) {
      const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const media = entry.target;
          const v = media.querySelector('video.card-hover-video');
          if (!v) return;
          if (entry.isIntersecting) {
            v.play().catch(() => {});
          } else {
            v.pause();
          }
        });
      }, { threshold: 0.15, rootMargin: '50px 0px 50px 0px' });
      relatedCards.forEach(c => obs.observe(c));
    }
  })();
</script>
