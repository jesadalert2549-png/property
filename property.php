<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();
require_once __DIR__ . '/includes/profile_schema.php';
ensure_profile_schema(db());

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT id, title, type, property_category, status, verification_status, verification_notes, price, location, lat, lng, line_id,
            description, bedrooms, bathrooms, area_sqm, land_area, parking_spaces, floor_number, furnished, line_id,
            amenities, nearby_places, contact_name, phone,
            (SELECT u.id FROM users u WHERE u.id = properties.user_id LIMIT 1) AS owner_user_id,
            (SELECT u.full_name FROM users u WHERE u.id = properties.user_id LIMIT 1) AS owner_full_name,
            (SELECT u.avatar_path FROM users u WHERE u.id = properties.user_id LIMIT 1) AS owner_avatar_path,
            (SELECT u.agent_status FROM users u WHERE u.id = properties.user_id LIMIT 1) AS owner_agent_status
     FROM properties WHERE properties.id = ? AND properties.approval = 'approved'"
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
    "SELECT p.id, p.title, p.type, p.property_category, p.price, p.location,
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
  <style>
    html { scroll-behavior: smooth; }
    body { font-family: "Fenice Std", "Noto Serif Thai", "Prompt", "Inter", -apple-system, system-ui, serif; background-color: #F9F9FB; }
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
    .property-gallery-slide img { width:100%; height:100%; object-fit:cover; }
    .property-gallery-arrow,.property-lightbox-arrow { position:absolute; z-index:2; top:50%; display:grid; place-items:center; width:44px; height:44px; transform:translateY(-50%); border:1px solid #ffffff90; border-radius:50%; background:#111827aa; color:#fff; font-size:26px; line-height:1; cursor:pointer; }
    .property-gallery-arrow.prev,.property-lightbox-arrow.prev { left:12px; }
    .property-gallery-arrow.next,.property-lightbox-arrow.next { right:12px; }
    .property-gallery-count,.property-lightbox-count { position:absolute; z-index:2; right:14px; bottom:14px; padding:7px 11px; border-radius:999px; background:#111827b8; color:#fff; font-size:12px; font-weight:800; }
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
    @media(max-width:600px) { .property-gallery-track { grid-template-columns:repeat(2,minmax(0,1fr)); grid-auto-rows:clamp(120px,34vw,185px); gap:8px; } .property-gallery-slide,.property-gallery-slide:first-child { grid-column:auto; grid-row:auto; border-radius:14px; } .property-gallery-slide:first-child { grid-column:span 2; } .property-gallery-arrow,.property-lightbox-arrow { width:40px; height:40px; } }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased" data-back-url="category.php?page=<?= e(rawurlencode((string)$catSlug)) ?>">

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
  <div class="mx-auto flex max-w-[1240px] items-center justify-between gap-4 px-4 py-4 sm:px-6">
    <?= render_logo_html('index.php') ?>
    <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-600 lg:flex">
      <a class="transition hover:text-ink" href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a class="transition hover:text-ink" href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a class="transition hover:text-ink" href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <a class="transition hover:text-ink" href="#footer"><?= e(t('เกี่ยวกับเรา', 'About us')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a class="transition hover:text-ink" href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a><a class="transition hover:text-ink" href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a class="transition hover:text-ink" href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
    </nav>
    <div class="flex items-center gap-3">
      <a href="search.php" class="hidden rounded-full border border-slate-300 px-4 py-2 text-sm font-bold text-ink transition hover:border-ink sm:inline-flex"><?= e(t('ค้นหา', 'Search')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>" class="hidden rounded-full border border-slate-300 px-4 py-2 text-sm font-bold text-ink sm:inline-flex"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
      <?php else: ?>
        <a href="login.php" class="hidden rounded-full border border-slate-300 px-4 py-2 text-sm font-bold text-ink sm:inline-flex"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <a href="submit.php" class="inline-flex items-center rounded-full bg-accent px-5 py-2.5 text-sm font-extrabold text-white shadow-lg shadow-orange-200 transition hover:bg-[#d86c26]"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
      <?php render_lang_toggle(); ?>
    </div>
  </div>
  <details class="sp-mobile-menu"><summary aria-label="<?= e(t('เปิดเมนูเว็บไซต์', 'Open website menu')) ?>">☰ <span><?= e(t('เมนู', 'Menu')) ?></span></summary><nav aria-label="<?= e(t('เมนูมือถือ', 'Mobile menu')) ?>"><a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a><a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search properties')) ?></a><a href="search.php?type=sale"><?= e(t('ซื้อ', 'Buy')) ?></a><a href="search.php?type=rent"><?= e(t('เช่า', 'Rent')) ?></a><a href="project.php"><?= e(t('โครงการ', 'Projects')) ?></a><a href="advertise.php"><?= e(t('ติดต่อเรา', 'Contact us')) ?></a><a href="submit.php"><?= e(t('ลงประกาศทรัพย์', 'Post a listing')) ?></a><?php if (!empty($_SESSION['user_id'])): ?><a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a><?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin/agents.php"><?= e(t('ตรวจรับรอง Agent', 'Verify agents')) ?></a><?php endif; ?><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a><?php else: ?><a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a><a href="register.php"><?= e(t('สมัครสมาชิก', 'Register')) ?></a><?php endif; ?><?php render_lang_toggle(); ?></nav></details>
</header>

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

  <?php if (($p['verification_status'] ?? 'passed') === 'flagged'): ?>
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong><?= e(t('ควรตรวจสอบประกาศนี้เพิ่มเติม', 'This listing needs further review')) ?></strong><br><?= e($p['verification_notes'] ?: t('ระบบพบข้อมูลที่คล้ายกับประกาศอื่น', 'The system found data similar to another listing')) ?> <?= e(t('ก่อนตัดสินใจหรือติดต่อ โปรดตรวจสอบรายละเอียดและนัดดูทรัพย์ด้วยตนเอง', 'Before deciding or contacting, please verify the details and arrange a viewing yourself.')) ?> <a class="font-bold underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งปัญหาประกาศนี้', 'Report this listing')) ?></a></div>
  <?php elseif (($p['verification_status'] ?? 'unchecked') === 'passed'): ?>
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"><?= e(t('ข้อมูลผ่านการตรวจความครบถ้วนเบื้องต้นของระบบ ซึ่งไม่ใช่การยืนยันกรรมสิทธิ์หรือรับรองว่าทรัพย์มีอยู่จริง โปรดตรวจสอบและนัดดูทรัพย์ก่อนชำระเงิน', 'This listing passed the system\'s basic completeness check, which does not verify ownership or confirm the property exists. Please inspect and view the property before paying anything.')) ?> <a class="font-bold text-accent underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งประกาศนี้', 'Report this listing')) ?></a></div>
  <?php else: ?>
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"><?= e(t('ประกาศนี้ยังไม่ได้ผ่านการตรวจความครบถ้วนอัตโนมัติ โปรดตรวจสอบข้อมูลและนัดดูทรัพย์ก่อนตัดสินใจ', 'This listing has not passed the automatic completeness check yet. Please verify the information and arrange a viewing before deciding.')) ?> <a class="font-bold text-accent underline" href="advertise.php?mode=report&amp;listing_id=<?= (int)$p['id'] ?>"><?= e(t('แจ้งประกาศนี้', 'Report this listing')) ?></a></div>
  <?php endif; ?>

  <section class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_380px]">
    <div class="space-y-5">
      <div class="relative" data-property-carousel>
        <?php $gallery = $files ? array_map(fn($file) => 'uploads/' . e($file), array_slice($files, 0, 5)) : [
          'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
          'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=900&q=80',
          'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80'
        ]; ?>
        <div class="property-gallery-track" id="propertyGalleryTrack">
          <?php foreach ($gallery as $index => $image): ?>
            <button type="button" class="property-gallery-slide" data-gallery-image="<?= $image ?>" aria-label="<?= e(t('เปิดดูรูปที่ ', 'Open photo ') . ($index + 1)) ?>">
              <img src="<?= $image ?>" alt="<?= e($p['title'] . ' ' . t('ภาพที่', 'photo') . ' ' . ($index + 1)) ?>" decoding="async" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            </button>
          <?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1): ?><span class="property-gallery-count"><?= e(t(count($gallery) . ' รูป', count($gallery) . ' photos')) ?></span><?php endif; ?>
      </div>
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
      <div class="mt-5 rounded-2xl bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-[.16em] text-slate-500"><?= e(t('ราคา', 'Price')) ?></p>
        <p class="mt-2 text-3xl font-black tracking-tight text-accent"><?= e(is_en() ? number_format((int)$p['price']) . ($p['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($p['type'], (int)$p['price'])) ?></p>
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
        <?php if (!empty($p['owner_user_id'])): ?><a class="publisher-profile-card publisher-profile-link" href="member.php?id=<?= (int)$p['owner_user_id'] ?>" aria-label="ดูโปรไฟล์และประกาศของ <?= e($publisherName) ?>"><?php else: ?><div class="publisher-profile-card"><?php endif; ?><span class="publisher-avatar"><?php if ($publisherAvatarExists): ?><img src="uploads/avatars/<?= e($publisherAvatar) ?>" alt="รูปโปรไฟล์ <?= e($publisherName) ?>"><?php else: ?><?= e(mb_strtoupper(mb_substr($publisherName, 0, 1))) ?><?php endif; ?></span><div class="publisher-profile-info"><p class="publisher-eyebrow"><?= e(t('ผู้ประกาศ · ดูโปรไฟล์', 'Lister · View profile')) ?></p><strong><?= e($publisherName) ?></strong><?php if (($p['owner_agent_status'] ?? '') === 'verified'): ?><span class="agent-verified-badge" title="<?= e(t('ผู้ดูแลตรวจสอบหลักฐาน Agent แล้ว', 'Agent credentials verified by admin')) ?>"><?= e(t('✓ Agent รับรอง', '✓ Verified agent')) ?></span><?php endif; ?></div><?= !empty($p['owner_user_id']) ? '</a>' : '</div>' ?>
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
        <button type="button" class="flex-1 rounded-full border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 transition hover:border-ink" id="saveProperty"><?= e(t('บันทึก', 'Save')) ?></button>
        <a href="submit.php" class="flex-1 rounded-full border border-slate-200 px-3 py-2 text-center text-sm font-bold text-slate-700 transition hover:border-ink"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
      </div>
      <?php render_banner_slot('property_sidebar', 'compact'); ?>
    </aside>
  </section>

  <section class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_380px]">
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
    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
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

  <section class="mt-10 rounded-3xl bg-[#faf7ff] p-6 shadow-soft sm:p-8">
    <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
      <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-accent">Mortgage Calculator</p>
        <h3 class="mt-2 text-3xl font-extrabold tracking-tight text-ink"><?= e(t('คำนวณผ่อนชำระต่อเดือน', 'Monthly payment calculator')) ?></h3>
        <p class="mt-3 text-sm leading-6 text-slate-600"><?= e(t('ใช้ข้อมูลเบื้องต้นเพื่อประมาณการงบผ่อน รายการนี้เป็นการคำนวณแบบสรุป ไม่ใช่ข้อเสนอจากธนาคาร', 'Use basic figures to estimate your installment budget. This is a summary calculation, not a bank offer.')) ?></p>
      </div>
      <div class="rounded-3xl bg-white p-5 shadow-soft">
        <div class="mb-5 flex items-end justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[.14em] text-slate-500"><?= e(t('ประมาณการ', 'Estimate')) ?></p>
            <p class="mt-2 text-4xl font-black tracking-tight text-ink"><span id="monthlyPayment">0</span> <span class="text-lg text-slate-500"><?= e(t('บาท/เดือน', 'THB/month')) ?></span></p>
          </div>
          <div class="rounded-2xl bg-slate-100 px-3 py-2 text-right">
            <p class="text-[10px] font-bold uppercase tracking-[.14em] text-slate-500"><?= e(t('วงเงินกู้', 'Loan ratio')) ?></p>
            <p id="loanRatio" class="mt-1 text-lg font-extrabold text-ink">80%</p>
          </div>
        </div>

        <div class="space-y-5">
          <div>
            <div class="mb-2 flex items-center justify-between"><label for="homePrice" class="text-sm font-bold text-slate-700"><?= e(t('ราคาอสังหาริมทรัพย์', 'Property price')) ?></label>
              <span class="flex items-center gap-1"><input id="priceNumber" class="h-9 w-36 rounded-lg border border-slate-200 bg-slate-50 px-2 text-right text-sm font-extrabold text-ink outline-none focus:border-accent" type="number" min="100000" max="50000000" step="10000" value="<?= (int)$p['price'] ?: 5890000 ?>"><span class="text-sm font-extrabold text-ink"><?= e(t('บาท', 'THB')) ?></span></span></div>
            <input id="homePrice" class="w-full accent-accent" type="range" min="100000" max="20000000" step="10000" value="<?= (int)$p['price'] ?: 5890000 ?>">
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between"><label for="loanPercent" class="text-sm font-bold text-slate-700"><?= e(t('วงเงินสินเชื่อ', 'Loan amount')) ?></label><span id="loanValue" class="text-sm font-extrabold text-ink">80%</span></div>
            <input id="loanPercent" class="w-full accent-accent" type="range" min="10" max="100" value="80">
          </div>
          <div class="grid gap-4 sm:grid-cols-3">
            <label class="block text-sm font-bold text-slate-700"><?= e(t('อัตราดอกเบี้ย / ปี (%)', 'Interest rate / year (%)')) ?>
              <input id="interestRate" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-bold outline-none focus:border-accent" type="number" min="0" max="20" step="0.1" value="3.25">
            </label>
            <label class="block text-sm font-bold text-slate-700"><?= e(t('ระยะเวลากู้ (ปี)', 'Loan term (years)')) ?>
              <input id="loanYears" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-bold outline-none focus:border-accent" type="number" min="1" max="40" step="1" value="30">
            </label>
            <label class="block text-sm font-bold text-slate-700"><?= e(t('เงินกู้เพิ่มเติม (บาท)', 'Extra loan (THB)')) ?>
              <input id="extraLoan" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-bold outline-none focus:border-accent" type="number" min="0" max="20000000" step="10000" value="0">
            </label>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
            <div class="flex justify-between"><span><?= e(t('เงินกู้รวม', 'Total loan')) ?></span><span id="loanSummary" class="font-extrabold text-ink">0 <?= e(t('บาท', 'THB')) ?></span></div>
            <div class="mt-1 flex justify-between"><span><?= e(t('ดอกเบี้ยรวมตลอดสัญญา', 'Total interest')) ?></span><span id="interestSummary" class="font-extrabold text-ink">0 <?= e(t('บาท', 'THB')) ?></span></div>
            <div class="mt-1 flex justify-between"><span><?= e(t('ยอดชำระรวมทั้งหมด', 'Total repayment')) ?></span><span id="totalSummary" class="font-extrabold text-ink">0 <?= e(t('บาท', 'THB')) ?></span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
    <div class="mb-5 flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-accent"><?= e(t('ประกาศอื่น ๆ', 'More listings')) ?></p>
        <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-ink"><?= e(t('ประกาศแนะนำที่คล้ายกัน', 'Similar recommended listings')) ?></h3>
      </div>
      <a href="search.php?type=<?= e($p['type']) ?>" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-bold text-ink transition hover:border-ink"><?= e(t('ดูทั้งหมด', 'View all')) ?></a>
    </div>
    <?php if ($related): ?>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($related as $r): ?>
          <a href="property.php?id=<?= (int)$r['id'] ?>" class="group rounded-3xl border border-slate-200 bg-white p-3 shadow-soft transition hover:-translate-y-0.5 hover:border-accent">
            <div class="overflow-hidden rounded-2xl bg-slate-100">
              <?php $cover = $r['cover'] && is_file(UPLOAD_DIR . basename($r['cover'])) ? 'uploads/' . rawurlencode(basename($r['cover'])) : 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg'; ?>
              <img src="<?= e($cover) ?>" alt="<?= e($r['title']) ?>" class="h-44 w-full object-cover transition duration-500 group-hover:scale-[1.04]" loading="lazy" decoding="async" width="640" height="176">
            </div>
            <p class="mt-3 truncate font-extrabold text-ink"><?= e($r['title']) ?></p>
            <p class="mt-1 text-sm text-slate-500 truncate"><?= e(category_label_bi($r['property_category'])) ?> · <?= e($r['location']) ?></p>
            <p class="mt-2 font-black text-accent"><?= e(is_en() ? number_format((int)$r['price']) . ($r['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($r['type'], (int)$r['price'])) ?></p>
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
    <img id="galleryDialogImage" src="" alt="">
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

  saveButton?.addEventListener('click', () => {
    const saved = saveButton.dataset.saved === 'true';
    saveButton.dataset.saved = String(!saved);
    saveButton.textContent = saved ? '<?= e(t('บันทึก', 'Save')) ?>' : '<?= e(t('บันทึกแล้ว', 'Saved')) ?>';
  });

  const gallerySlides = Array.from(document.querySelectorAll('[data-gallery-image]'));
  const lightboxCount = document.querySelector('[data-lightbox-count]');
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
  const priceNumber = document.getElementById('priceNumber');
  const loanInput = document.getElementById('loanPercent');
  const interestInput = document.getElementById('interestRate');
  const yearsInput = document.getElementById('loanYears');
  const extraLoanInput = document.getElementById('extraLoan');
  const monthlyEl = document.getElementById('monthlyPayment');
  const loanValue = document.getElementById('loanValue');
  const loanRatio = document.getElementById('loanRatio');
  const loanSummary = document.getElementById('loanSummary');
  const interestSummary = document.getElementById('interestSummary');
  const totalSummary = document.getElementById('totalSummary');

  const formatTh = (n) => new Intl.NumberFormat('th-TH').format(Math.round(n));
  const updateMortgage = (source) => {
    let price = Number(priceInput.value || 0);
    if (source === 'number') {
      price = Math.min(50000000, Math.max(100000, Number(priceNumber.value || 0)));
      priceInput.value = Math.min(20000000, price);
    } else {
      priceNumber.value = price;
    }
    const loanPct = Number(loanInput.value || 80);
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

    loanValue.textContent = loanPct + '%';
    loanRatio.textContent = loanPct + '%';
    monthlyEl.textContent = formatTh(payment);
    loanSummary.textContent = formatTh(principal) + ' <?= e(t('บาท', 'THB')) ?>';
    interestSummary.textContent = formatTh(totalPaid - principal) + ' <?= e(t('บาท', 'THB')) ?>';
    totalSummary.textContent = formatTh(totalPaid) + ' <?= e(t('บาท', 'THB')) ?>';
  };
  [priceInput, loanInput, interestInput, yearsInput, extraLoanInput].forEach((input) => input.addEventListener('input', () => updateMortgage(input === extraLoanInput ? '' : '')));
  priceNumber.addEventListener('input', () => updateMortgage('number'));
  updateMortgage();

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
</script>
</body>
</html>
