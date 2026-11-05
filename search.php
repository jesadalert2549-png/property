<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
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
    'SELECT id, title, type, status, price, location, property_category, contact_name,
            (SELECT u.full_name FROM users u WHERE u.id = properties.user_id LIMIT 1) AS publisher_name,
            (SELECT u.avatar_path FROM users u WHERE u.id = properties.user_id LIMIT 1) AS publisher_avatar,
            (SELECT u.agent_status FROM users u WHERE u.id = properties.user_id LIMIT 1) AS publisher_agent_status,
            (SELECT i.filename FROM property_images i
             WHERE i.property_id = properties.id
             ORDER BY i.sort_order, i.id LIMIT 1) AS cover
     FROM properties
     WHERE ' . implode(' AND ', $where) . "
     ORDER BY (status = 'done'), created_at DESC
     LIMIT 100"
);
$st->execute($params);
$items = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head((is_en() ? 'Search Properties | ' : 'ค้นหาอสังหาริมทรัพย์ | ') . SITE_NAME, is_en() ? 'Search property listings for sale and rent by location and type' : 'ค้นหาประกาศขายและให้เช่าอสังหาริมทรัพย์ตามทำเลและประเภท', page_url('search.php'), false); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
</head>
<body class="search-page-body">

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <button class="search-mobile-menu" type="button" aria-controls="searchSiteNav" aria-expanded="false"><span aria-hidden="true">☰</span><span><?= e(t('เมนู', 'Menu')) ?></span></button>
    <nav class="site-nav search-site-nav" id="searchSiteNav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
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
    <div class="grid search-results-grid">
      <?php foreach ($items as $row): ?>
        <a class="card<?= $row['status'] === 'done' ? ' done' : '' ?>"
           href="property.php?id=<?= (int)$row['id'] ?>" style="display:block;color:inherit;text-decoration:none">
          <?php
            $cover = basename((string)($row['cover'] ?? ''));
            $coverExists = $cover !== '' && is_file(UPLOAD_DIR . $cover);
          ?>
          <div class="thumb">
            <?php if ($coverExists): ?>
              <img src="uploads/<?= e($cover) ?>" alt="<?= e($row['title']) ?>" loading="lazy" decoding="async" width="640" height="441">
            <?php else: ?>
              <div class="noimg" aria-hidden="true"></div>
            <?php endif; ?>
            <span class="badge <?= e($row['type']) ?>"><?= e(is_en() ? ($row['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($row['type'])) ?></span>
            <?php if ($row['status'] === 'done'): ?>
              <div class="ribbon"><?= e(t($row['type'] === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว', $row['type'] === 'rent' ? 'Rented' : 'Sold')) ?></div>
            <?php else: ?><?php render_publisher_mini($row); ?><?php endif; ?>
          </div>
          <div class="info">
            <h2><?= e($row['title']) ?></h2>
            <p class="price"><?= e(is_en() ? number_format((int)$row['price']) . ($row['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($row['type'], (int)$row['price'])) ?></p>
            <p class="loc"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg> <?= e($row['location']) ?></p>
            <?php render_publisher_mini($row); ?>
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
<script>
(() => {
  const button = document.querySelector('.search-mobile-menu');
  const menu = document.getElementById('searchSiteNav');
  if (!button || !menu) return;
  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    menu.classList.toggle('is-open', open);
  });
  menu.addEventListener('click', event => {
    if (event.target.closest('a')) {
      menu.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
</body>
</html>
