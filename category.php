<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();

$pdo = db();
$slug = trim((string)($_GET['page'] ?? ''), '/ ');
$slug = urldecode($slug);

// สารบัญแมป Slug เป็นหมวดหมู่หลัก (เผื่อกรณีไม่มีใน SEO_CATEGORIES)
$cat_map = [
    'condo' => 'condo', 'คอนโด' => 'condo',
    'house' => 'house', 'บ้านเดี่ยว' => 'house',
    'townhouse' => 'townhouse', 'ทาวน์เฮ้าส์' => 'townhouse', 'ทาวน์โฮม' => 'townhouse',
    'land' => 'land', 'ที่ดิน' => 'land',
    'commercial' => 'commercial', 'อาคารพาณิชย์' => 'commercial',
    'warehouse' => 'warehouse', 'โกดัง' => 'warehouse',
    'other' => 'other'
];

$cfg = SEO_CATEGORIES[$slug] ?? null;
$target_category = null;
$target_type = null;
$target_q = '';
$target_max_price = 0;

if ($cfg) {
    $title_h1 = $cfg['h1'];
    $page_title = $cfg['title'];
    $page_desc = $cfg['desc'];
    $target_category = $cfg['category'] ?? null;
    $target_type = $cfg['type'] ?? null;
    $target_q = $cfg['q'] ?? '';
    $target_max_price = (int)($cfg['max_price'] ?? 0);
} else {
    // ค้นหาคีย์ที่ตรงกัน
    $cat_key = $cat_map[$slug] ?? (isset(PROPERTY_CATEGORIES[$slug]) ? $slug : 'condo');
    $category_label = PROPERTY_CATEGORIES[$cat_key] ?? 'อสังหาริมทรัพย์';
    
    $title_h1 = "ขาย - ให้เช่า " . $category_label;
    $page_title = "รวมประกาศ " . $category_label;
    $page_desc = "รวมประกาศซื้อ ขาย ให้เช่า " . $category_label . " รายละเอียดครบถ้วน";
    $target_category = $cat_key;
}

// ดึงรายการประกาศจากฐานข้อมูลตาม Category และ Type (ถ้ากำหนด)
$sql = "SELECT p.*,
        (SELECT u.full_name FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_name,
        (SELECT u.avatar_path FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_avatar,
        (SELECT u.agent_status FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_agent_status,
        (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover 
        FROM properties p 
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
    $sql .= " AND (p.location LIKE ? OR p.nearby_places LIKE ? OR p.description LIKE ?)";
    $like = '%' . addcslashes($target_q, '%_\\') . '%';
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
  <?php seo_head($page_title . ' | ' . SITE_NAME, $page_desc, $canonical, true, $categoryImage ? page_url($categoryImage) : ''); ?>
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
</head>
<body class="bg-[#F9F9FB] text-slate-800 antialiased" style="font-family:'Prompt','Inter',system-ui,sans-serif;">

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
  <div class="mx-auto flex max-w-[1240px] items-center justify-between gap-4 px-4 py-4 sm:px-6">
    <?= render_logo_html('index.php') ?>
    <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-600 lg:flex">
      <a class="transition hover:text-ink" href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a class="transition hover:text-ink" href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a class="transition hover:text-ink" href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <a class="transition hover:text-ink" href="submit.php"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
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

<main class="mx-auto max-w-[1240px] px-4 pb-16 pt-8 sm:px-6">
  <?php render_banner_slot('category_top', 'compact'); ?>
  <div class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-gradient-to-r from-[#fdfbf8] via-white to-[#f4f8fb] p-6 shadow-soft sm:p-8">
    <?php if ($categoryImage): ?>
      <img src="<?= e($categoryImage) ?>" alt="ภาพหมวด<?= e($title_h1) ?>" class="absolute inset-0 h-full w-full object-cover opacity-25" loading="eager">
    <?php endif; ?>
    <div class="relative">
    <nav class="mb-5 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="breadcrumb">
      <a href="index.php" class="hover:text-ink"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <span>›</span>
      <span class="text-slate-700"><?= e($title_h1) ?></span>
    </nav>

    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-accent">Property listing</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl"><?= e($title_h1) ?></h1>
      </div>
      <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600">
        <?= e(t('พบ ' . count($items) . ' ประกาศที่ตรงกับเงื่อนไข', count($items) . ' listings match your criteria')) ?>
      </div>
      </div>
    </div>
  </div>

  <section class="mt-8">
    <div class="flex flex-wrap gap-2 pb-4">
      <?php foreach (PROPERTY_CATEGORIES as $cat_slug => $cat_label): if ($cat_slug === 'other') continue; ?>
        <?php $chipIcon = $categoryIconsAll[$cat_slug] ?? ''; ?>
        <a href="category.php?page=<?= e($cat_slug) ?>" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition <?= $target_category === $cat_slug ? 'bg-ink text-white shadow-lg shadow-slate-200' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-ink' ?>">
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
      <p class="text-lg font-bold text-slate-600"><?= e(t('ยังไม่มีประกาศในหมวดหมู่ ' . $title_h1 . ' ในขณะนี้', 'No listings in ' . $title_h1 . ' yet')) ?></p>
      <a href="submit.php" class="mt-5 inline-flex items-center rounded-full bg-accent px-6 py-3 text-sm font-extrabold text-white transition hover:bg-[#d86c26]"><?= e(t('ลงประกาศเป็นคนแรก', 'Be the first to post')) ?></a>
    </div>
  <?php else: ?>
    <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($items as $row): ?>
        <?php $cover = basename((string)($row['cover'] ?? '')); $coverExists = $cover !== '' && is_file(UPLOAD_DIR . $cover); $img = $coverExists ? 'uploads/' . e($cover) : 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80'; ?>
        <article class="group overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-soft transition hover:-translate-y-1 hover:shadow-xl">
          <a href="property.php?id=<?= (int)$row['id'] ?>" class="block no-underline text-inherit">
            <div class="relative overflow-hidden">
              <img src="<?= $img ?>" alt="<?= e($row['title']) ?>" class="h-60 w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" decoding="async" width="640" height="240">
              <span class="absolute left-4 top-4 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-ink shadow-sm">
                <?= e(is_en() ? ($row['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($row['type'])) ?>
              </span>
              <?php if ($row['status'] === 'done'): ?>
                <span class="absolute bottom-4 left-4 rounded-full bg-slate-900/80 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.12em] text-white">
                  <?= e(t($row['type'] === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว', $row['type'] === 'rent' ? 'Rented' : 'Sold')) ?>
                </span>
              <?php else: ?><?php render_publisher_mini($row); ?><?php endif; ?>
            </div>
            <div class="p-5">
              <div class="flex items-center justify-between gap-3">
                <span class="rounded-full bg-accentSoft px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-accent"><?= e(category_label_bi($row['property_category'])) ?></span>
                <span class="text-xs font-bold text-slate-500">#<?= (int)$row['id'] ?></span>
              </div>
              <h2 class="mt-4 line-clamp-2 text-xl font-extrabold leading-7 text-ink"><?= e($row['title']) ?></h2>
              <p class="mt-3 flex items-center gap-2 text-sm text-slate-500"><span>⌖</span><span><?= e($row['location']) ?></span></p>
              
              <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div>
                  <p class="text-[10px] font-bold uppercase tracking-[.14em] text-slate-500"><?= e(t('ราคา', 'Price')) ?></p>
                  <p class="mt-1 text-lg font-black text-accent"><?= e(is_en() ? number_format((int)$row['price']) . ($row['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($row['type'], (int)$row['price'])) ?></p>
                </div>
                <span class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700"><?= e(t('ดูรายละเอียด', 'View details')) ?></span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

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
