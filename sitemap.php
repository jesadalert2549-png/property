<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/seo_provinces.php';
current_lang();

$listings = db()->query(
    "SELECT id, title, type, property_category, location, updated_at
     FROM properties
     WHERE approval = 'approved'
     ORDER BY updated_at DESC, id DESC"
)->fetchAll();
$pageTitle = (is_en() ? 'Sitemap' : 'แผนผังเว็บไซต์') . ' | ' . SITE_NAME;
$pageDescription = is_en() ? 'Links to main pages, property categories and published listings on ' . SITE_NAME : 'รวมลิงก์หน้าหลัก หมวดหมู่อสังหาริมทรัพย์ และประกาศที่เผยแพร่บน ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head($pageTitle, $pageDescription, page_url('sitemap.php')); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    .sitemap-page { padding: 48px 0 64px; }
    .sitemap-section { margin-top: 28px; padding: 24px; border: 1px solid #e5e9ee; border-radius: 14px; background: #fff; }
    .sitemap-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px 24px; padding: 0; margin: 16px 0 0; list-style: none; }
    .sitemap-links a { color: #2b2150; text-decoration: none; }
    .sitemap-links a:hover { color: #583caa; text-decoration: none; }
    .sitemap-listing-meta { display: block; color: #697586; font-size: 13px; }
  </style>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <a href="submit.php"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="container sitemap-page">
  <h1 class="section-title"><?= e(t('แผนผังเว็บไซต์', 'Sitemap')) ?></h1>
  <p class="muted"><?= e(t('เลือกหมวดหรือประกาศเพื่อไปยังหน้าที่ต้องการ', 'Choose a category or listing to navigate')) ?></p>

  <section class="sitemap-section">
    <h2><?= e(t('หน้าหลัก', 'Main pages')) ?></h2>
    <ul class="sitemap-links">
      <li><a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a></li>
      <li><a href="search.php"><?= e(t('ค้นหาอสังหาริมทรัพย์', 'Search properties')) ?></a></li>
      <li><a href="search.php?type=sale"><?= e(t('ประกาศขาย', 'Listings for sale')) ?></a></li>
      <li><a href="search.php?type=rent"><?= e(t('ประกาศให้เช่า', 'Listings for rent')) ?></a></li>
      <li><a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a></li>
      <li><a href="dashboard.php"><?= e(t('แดชบอร์ดสมาชิก', 'Member dashboard')) ?></a></li>
      <li><a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a></li>
      <li><a href="register.php"><?= e(t('สมัครสมาชิก', 'Register')) ?></a></li>
      <li><a href="submit.php"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a></li>
      <li><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a></li>
      <li><a href="sitemap.xml"><?= e(t('XML Sitemap สำหรับเครื่องมือค้นหา', 'XML Sitemap for search engines')) ?></a></li>
      <li><a href="sitemap_xml.php"><?= e(t('ไฟล์ XML Sitemap (ต้นทาง)', 'XML Sitemap file (source)')) ?></a></li>
    </ul>
  </section>

  <section class="sitemap-section">
    <h2><?= e(t('หมวดหมู่อสังหาริมทรัพย์', 'Property categories')) ?></h2>
    <ul class="sitemap-links">
      <?php foreach (PROPERTY_CATEGORIES as $category => $label): ?>
        <?php $saleSlug = category_slug_for($category, 'sale') ?: $category;
              $rentSlug = category_slug_for($category, 'rent') ?: $category; ?>
        <li><a href="category.php?page=<?= e(rawurlencode((string)$category)) ?>"><?= e(category_label_bi($category)) ?> (<?= e(t('ทั้งหมด', 'All')) ?>)</a></li>
        <li><a href="category.php?page=<?= e(rawurlencode((string)$saleSlug)) ?>"><?= e(t('ขาย ', 'For sale: ') . category_label_bi($category)) ?></a></li>
        <li><a href="category.php?page=<?= e(rawurlencode((string)$rentSlug)) ?>"><?= e(t('เช่า ', 'For rent: ') . category_label_bi($category)) ?></a></li>
      <?php endforeach; ?>
      <?php
      $zoneEnMap = [
          'สุขุมวิท' => 'Sukhumvit',
          'ลาดพร้าว' => 'Lat Phrao',
          'รามอินทรา' => 'Ram Inthra',
          'รัชดา' => 'Ratchada',
          'เชียงใหม่' => 'Chiang Mai',
      ];
      foreach (SEO_CATEGORIES as $slug => $config):
        $catEn = property_category_en_label($config['category'] ?? '') ?? 'Properties';
        $typeEn = ($config['type'] ?? '') === 'rent' ? 'for Rent' : 'for Sale';
        $extraEn = !empty($config['max_price']) ? ' under ' . number_format((int)$config['max_price'] / 1000000) . 'M' : '';
        if (!empty($config['q'])) {
          $qEn = $zoneEnMap[$config['q']] ?? $config['q'];
          $extraEn .= ' in ' . $qEn;
        }
        $seoTitle = is_en() ? ($catEn . ' ' . $typeEn . $extraEn) : $config['h1'];
      ?>
        <li><a href="<?= e(category_url($slug)) ?>"><?= e($seoTitle) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- หมวดหมู่เจาะจงทำเลทอง ย่าน CBD & แนวรถไฟฟ้า BTS/MRT สำหรับ Google & AI -->
  <section class="sitemap-section">
    <h2>🏢 <?= e(t('ค้นหาตามย่านเศรษฐกิจ Prime CBD & แนวรถไฟฟ้า BTS/MRT', 'Search by Prime CBD & Transit Zones (BTS/MRT)')) ?></h2>
    <p class="muted" style="margin-top:4px; font-size:13px;"><?= e(t('เจาะลึกคอนโดและบ้านเดี่ยวในทำเลที่มีความต้องการซื้อ-เช่าสูงสุด', 'In-depth condo and house searches across prime urban transit hubs')) ?></p>
    <ul class="sitemap-links">
      <?php
      if (defined('PRIME_CBD_ZONES')) {
          foreach (PRIME_CBD_ZONES as $cbdKey => $cbd) {
              $cbdName = $cbd['name_th'];
              echo '<li><a href="category.php?page=' . urlencode('คอนโด' . $cbdName) . '">⚡ ' . e($cbdName) . ' - คอนโดขายและเช่า</a></li>';
              echo '<li><a href="category.php?page=' . urlencode('คอนโดติดรถไฟฟ้า' . $cbdName) . '">🚇 คอนโดติดรถไฟฟ้า ' . e($cbdName) . '</a></li>';
              echo '<li><a href="category.php?page=' . urlencode('บ้าน' . $cbdName) . '">🏠 บ้านเดี่ยวและทาวน์โฮม ' . e($cbdName) . '</a></li>';
              foreach (array_slice($cbd['sub_districts'], 0, 2) as $subD) {
                  echo '<li><a href="category.php?page=' . urlencode('คอนโด' . $subD) . '">📍 คอนโดย่าน ' . e($subD) . '</a></li>';
              }
          }
      }
      ?>
    </ul>
  </section>

  <!-- หมวดหมู่ค้นหาเจาะจงตามงบประมาณยอดนิยม -->
  <section class="sitemap-section">
    <h2>💰 <?= e(t('ค้นหาตามช่วงงบประมาณยอดนิยม (Budget Search)', 'Search by Popular Budget Brackets')) ?></h2>
    <p class="muted" style="margin-top:4px; font-size:13px;"><?= e(t('ดักคำค้นหายอดฮิตสำหรับผู้ซื้อที่ค้นหาตามงบประมาณผ่อนได้จริง', 'Targeted searches for buyers filtering by realistic purchasing budget')) ?></p>
    <ul class="sitemap-links">
      <li><a href="category.php?page=<?= urlencode('คอนโดราคาไม่เกิน1ล้าน') ?>">💵 คอนโดราคาไม่เกิน 1 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('คอนโดราคาไม่เกิน2ล้าน') ?>">💵 คอนโดราคาไม่เกิน 2 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('คอนโดราคาไม่เกิน3ล้าน') ?>">💵 คอนโดราคาไม่เกิน 3 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('คอนโดราคาไม่เกิน5ล้าน') ?>">💵 คอนโดราคาไม่เกิน 5 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('บ้านราคาไม่เกิน2ล้าน') ?>">🏡 บ้านเดี่ยวราคาไม่เกิน 2 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('บ้านราคาไม่เกิน3ล้าน') ?>">🏡 บ้านเดี่ยวราคาไม่เกิน 3 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('บ้านราคาไม่เกิน5ล้าน') ?>">🏡 บ้านเดี่ยวราคาไม่เกิน 5 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('บ้านราคาไม่เกิน10ล้าน') ?>">🏡 บ้านหรูราคาไม่เกิน 10 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('ทาวน์โฮมราคาไม่เกิน2ล้าน') ?>">🏘️ ทาวน์โฮมราคาไม่เกิน 2 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('ทาวน์โฮมราคาไม่เกิน3ล้าน') ?>">🏘️ ทาวน์โฮมราคาไม่เกิน 3 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('ที่ดินราคาถูกไม่เกิน1ล้าน') ?>">🌳 ที่ดินราคาถูก งบไม่เกิน 1 ล้านบาท</a></li>
      <li><a href="category.php?page=<?= urlencode('คอนโดให้เช่าราคาไม่เกิน10000') ?>">🔑 คอนโดให้เช่า ค่าเช่าไม่เกิน 10,000 บาท/เดือน</a></li>
      <li><a href="category.php?page=<?= urlencode('คอนโดให้เช่าราคาไม่เกิน15000') ?>">🔑 คอนโดให้เช่า ค่าเช่าไม่เกิน 15,000 บาท/เดือน</a></li>
      <li><a href="category.php?page=<?= urlencode('บ้านให้เช่าราคาไม่เกิน20000') ?>">🔑 บ้านให้เช่า ค่าเช่าไม่เกิน 20,000 บาท/เดือน</a></li>
    </ul>
  </section>

  <!-- หมวดหมู่เจาะจง 77 จังหวัดทั่วไทย -->
  <section class="sitemap-section">
    <h2>📍 <?= e(t('ค้นหาอสังหาริมทรัพย์เจาะจง 77 จังหวัดทั่วไทย', 'Properties across all 77 Thai Provinces')) ?></h2>
    <p class="muted" style="margin-top:4px; font-size:13px;"><?= e(t('แผนผังครอบคลุมทุกภูมิภาค สำหรับ Googlebot และเครื่องมือค้นหาทั่วโลก', 'Complete nationwide provincial coverage for search engine crawlers')) ?></p>
    <ul class="sitemap-links">
      <?php
      if (defined('THAI_PROVINCES')) {
          foreach (THAI_PROVINCES as $pName => $pData) {
              if ($pName === '') continue;
              echo '<li><a href="category.php?page=' . urlencode('ขายบ้าน' . $pName) . '">🏠 บ้าน' . e($pName) . '</a></li>';
              echo '<li><a href="category.php?page=' . urlencode('ขายคอนโด' . $pName) . '">🏢 คอนโด' . e($pName) . '</a></li>';
              echo '<li><a href="category.php?page=' . urlencode('ขายที่ดิน' . $pName) . '">🌳 ที่ดิน' . e($pName) . '</a></li>';
          }
      }
      ?>
    </ul>
  </section>

  <section class="sitemap-section">
    <h2><?= e(t('ประกาศที่เผยแพร่ ', 'Published listings (') . count($listings) . ')') ?></h2>
    <?php if ($listings): ?>
      <ul class="sitemap-links">
        <?php foreach ($listings as $listing): ?>
          <li>
            <a href="property.php?id=<?= (int)$listing['id'] ?>"><?= e($listing['title']) ?></a>
            <span class="sitemap-listing-meta"><?= e(category_label_bi($listing['property_category'])) ?> · <?= e(is_en() ? ($listing['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($listing['type'])) ?> · <?= e($listing['location']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted"><?= e(t('ยังไม่มีประกาศที่เผยแพร่', 'No published listings yet')) ?></p>
    <?php endif; ?>
  </section>

  <section class="sitemap-section">
    <h2><?= e(t('เริ่มต้นใช้งาน', 'Get started')) ?></h2>
    <ul class="sitemap-links">
      <li><a href="register.php"><?= e(t('1. สมัครสมาชิกฟรี', '1. Register for free')) ?></a></li>
      <li><a href="submit.php"><?= e(t('2. ลงประกาศอสังหาริมทรัพย์', '2. Post a property listing')) ?></a></li>
      <li><a href="dashboard.php"><?= e(t('3. จัดการประกาศของคุณ', '3. Manage your listings')) ?></a></li>
      <li><a href="search.php"><?= e(t('4. ค้นหาและติดต่อผู้ขาย/ผู้ให้เช่า', '4. Search and contact sellers/landlords')) ?></a></li>
    </ul>
  </section>

  <section class="sitemap-section">
    <h2><?= e(t('ติดต่อเรา', 'Contact us')) ?></h2>
    <p style="margin-top:10px"><?= e(t('สอบถามทุกเรื่องได้ที่ ', 'For any enquiries, reach us at ')) ?><a href="mailto:<?= e(CONTACT_EMAIL) ?>" style="color:#2b2150;font-weight:bold"><?= e(CONTACT_EMAIL) ?></a></p>
  </section>
</main>

<?php
render_page_cta([
    'title'   => t('หาลิงก์ไม่เจอ? แผนผังนี้มีครบทุกหน้า', "Can't find a link? This sitemap covers all pages"),
    'text'    => t('รวมทุกหมวด ทุกประกาศ และทุกบริการของ ' . SITE_NAME . ' ไว้ในหน้าเดียว', 'All categories, listings and services of ' . SITE_NAME . ' in one place.'),
    'primary' => ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')],
    'ghost'   => ['index.php', t('กลับหน้าแรก', 'Back to home')],
]);
render_to_top();
render_ux_scripts();
render_site_footer(); ?>
</body>
</html>
