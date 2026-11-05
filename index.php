<?php
/**
 * หน้าหลักเว็บไซต์ (Index Page) - ออกแบบ UI/UX Responsive + Hamburger Drawer Menu สไตล์ DDproperty
 * พัฒนาสำหรับโครงการ EasyHome v.3.0
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
start_session_if_available:
current_lang();

$pdo = db();
$heroBg = get_hero_background();
$featuredSections = [];

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS featured_sections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(30) NOT NULL,
            title VARCHAR(120) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_featured_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $featuredSections = $pdo->query(
        'SELECT id, category, title
         FROM featured_sections
         WHERE enabled = 1
         ORDER BY sort_order, id
         LIMIT 4'
    )->fetchAll();

    if (!$featuredSections) {
        $featuredSections = $pdo->query(
            "SELECT DISTINCT p.property_category AS category,
                    CASE p.property_category
                      WHEN 'condo' THEN 'คอนโดแนะนำ'
                      WHEN 'house' THEN 'บ้านแนะนำ'
                      WHEN 'townhouse' THEN 'ทาวน์เฮ้าส์แนะนำ'
                      WHEN 'land' THEN 'ที่ดินแนะนำ'
                      WHEN 'commercial' THEN 'อาคารพาณิชย์แนะนำ'
                      WHEN 'warehouse' THEN 'โกดังและโรงงาน'
                      ELSE 'อสังหาริมทรัพย์แนะนำ'
                    END AS title
             FROM properties p
             WHERE p.approval = 'approved' AND p.status = 'available'
             ORDER BY p.property_category
             LIMIT 4"
        )->fetchAll();
         foreach ($featuredSections as $index => &$fallbackSection) {
            $fallbackSection['id'] = 0;
            $fallbackSection['listings'] = [];
            $fallbackSection['title'] = match ($fallbackSection['category']) {
                'condo' => t('คอนโดแนะนำ', 'Featured condos'),
                'house' => t('บ้านแนะนำ', 'Featured houses'),
                'townhouse' => t('ทาวน์เฮ้าส์แนะนำ', 'Featured townhouses'),
                'land' => t('ที่ดินแนะนำ', 'Featured land'),
                'commercial' => t('อาคารพาณิชย์แนะนำ', 'Featured commercial buildings'),
                'warehouse' => t('โกดังและโรงงาน', 'Warehouses & factories'),
                default => t('อสังหาริมทรัพย์แนะนำ', 'Featured properties'),
            };
        }
        unset($fallbackSection);
    }
} catch (PDOException $e) {
    $featuredSections = [];
}

$featuredPropertiesEnabled = false;
try {
    $pdo->query('SELECT property_id FROM featured_properties LIMIT 0');
    $featuredPropertiesEnabled = true;
} catch (PDOException $e) {
    // Existing installations without the promotion table continue showing newest listings.
}

$featuredImages = [];
foreach ($featuredSections as &$featuredSection) {
    $listingSql = $featuredPropertiesEnabled
        ? 'SELECT p.id, p.title, p.type, p.price, p.location, p.contact_name,
                  (SELECT u.full_name FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_name,
                  (SELECT u.avatar_path FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_avatar,
                  (SELECT u.agent_status FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_agent_status,
                  (fp.property_id IS NOT NULL) AS is_featured
           FROM properties p LEFT JOIN featured_properties fp ON fp.property_id = p.id
           WHERE p.approval = ? AND p.status = ? AND p.property_category = ?
           ORDER BY (fp.property_id IS NULL), fp.sort_order, p.created_at DESC LIMIT 4'
        : 'SELECT p.id, p.title, p.type, p.price, p.location, p.contact_name,
                  (SELECT u.full_name FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_name,
                  (SELECT u.avatar_path FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_avatar,
                  (SELECT u.agent_status FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_agent_status,
                  0 AS is_featured
           FROM properties p
           WHERE p.approval = ? AND p.status = ? AND p.property_category = ?
           ORDER BY p.created_at DESC LIMIT 4';
    $listingStatement = $pdo->prepare($listingSql);
    $listingStatement->execute(['approved', 'available', $featuredSection['category']]);
    $featuredSection['listings'] = $listingStatement->fetchAll();

    $listingIds = array_column($featuredSection['listings'], 'id');
    if ($listingIds) {
        $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
        $imageStatement = $pdo->prepare(
            "SELECT pi.property_id, pi.filename
             FROM property_images pi
             JOIN (
                 SELECT property_id, MIN(id) AS first_image_id
                 FROM property_images
                 WHERE property_id IN ($placeholders)
                 GROUP BY property_id
             ) first_image ON first_image.first_image_id = pi.id"
        );
        $imageStatement->execute($listingIds);
        foreach ($imageStatement as $image) {
            $featuredImages[(int)$image['property_id']] = $image['filename'];
        }
    }
}
unset($featuredSection);

// ── ไอคอนภาพประจำหมวดหมู่ (อัปโหลดได้ที่ หลังบ้าน › ภาพหมวดหมู่) ──
$categoryIcons = [];
$categoryIconsTable = true;
try {
    foreach ($pdo->query('SELECT category, filename FROM category_images') as $categoryRow) {
        $iconName = basename((string)$categoryRow['filename']);
        if ($iconName !== '' && is_file(UPLOAD_DIR . 'categories/' . $iconName)) {
            $categoryIcons[(string)$categoryRow['category']] = 'uploads/categories/' . $iconName;
        }
    }
} catch (PDOException $e) {
    $categoryIconsTable = false;
}
$categoryIconFallback = [
    'condo' => '🏢', 'house' => '🏠', 'townhouse' => '🏘️',
    'land' => '🌳', 'commercial' => '🏬', 'warehouse' => '🏭', 'other' => '🏡',
];

// ── เมนูหมวดหมู่หลักยกไปไว้บนสุดของหน้าแรก ──
$homeCategories = [];
foreach (PROPERTY_CATEGORIES as $categorySlug => $categoryLabel) {
    if ($categorySlug === 'other') { continue; }
    $homeCategories[] = [
        'slug'  => $categorySlug,
        'label' => category_label_bi($categorySlug),
        'icon'  => $categoryIcons[$categorySlug] ?? '',
        'emoji' => $categoryIconFallback[$categorySlug] ?? '🏡',
        'href'  => 'category.php?page=' . rawurlencode($categorySlug),
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php seo_head(SITE_NAME . (is_en() ? ' | Property Listings for Sale & Rent' : ' | ประกาศขาย-เช่าอสังหาริมทรัพย์'), is_en() ? 'List houses, condos, townhouses, land and commercial property for sale or rent across Thailand. Free listings, contact owners directly.' : 'ประกาศขายและให้เช่าบ้าน คอนโด ทาวน์เฮ้าส์ ที่ดิน และอสังหาริมทรัพย์ ลงประกาศฟรี ติดต่อเจ้าของได้โดยตรง', site_url() . '/'); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    /* ==========================================================================
       Global & Mobile-First Reset
       ========================================================================== */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      overflow-x: hidden;
      width: 100%;
      background: #F9F9FB;
      font-family: "Prompt", "Inter", -apple-system, system-ui, sans-serif;
    }
    img {
      max-width: 100%;
      height: auto;
      display: block;
    }

    /* ==========================================================================
       1. ส่วน Navigation Bar แบบ Responsive & Hamburger Menu
       ========================================================================== */
    .dd-navbar {
      background: #0B1C33;
      padding: 12px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      width: 100%;
    }
    .dd-nav-left, .dd-nav-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .dd-logo {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-weight: 800;
      color: #ffffff;
      font-size: 18px;
      white-space: nowrap;
    }
    .dd-logo span.brand-red {
      color: #C5A059;
    }
    .dd-desktop-menu {
      display: flex;
      gap: 20px;
      margin-left: 20px;
      align-items: center;
    }
    .dd-desktop-menu .nav-dropdown-toggle {
      color: #ffffff !important;
      text-decoration: none;
    }
    .dd-menu-item {
      color: #ffffff;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 4px;
      transition: color 0.2s;
      white-space: nowrap;
    }
    .dd-menu-item:hover,
    .dd-desktop-menu .nav-dropdown-toggle:hover {
      color: #C5A059 !important;
    }
    .dd-icon-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.28);
      padding: 6px 12px;
      border-radius: 9999px;
      font-size: 13px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      color: #ffffff;
      text-decoration: none;
      transition: background 0.2s, color 0.2s, border-color 0.2s;
      white-space: nowrap;
    }
    .dd-icon-btn:hover {
      background: rgba(197, 160, 89, 0.16);
      border-color: #C5A059;
      color: #E4C688;
    }
    .dd-btn-login {
      background: #7955d9;
      color: #fff;
      padding: 8px 20px;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 14px;
      text-decoration: none;
      box-shadow: 0 2px 4px rgba(114,87,245,0.2);
      transition: background 0.2s;
      white-space: nowrap;
    }
    .dd-btn-login:hover {
      background: #583caa;
    }
    .dd-admin-stats-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border: 1px solid #7955d9;
      border-radius: 9999px;
      color: #7955d9;
      background: #fff;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      white-space: nowrap;
    }
    .dd-admin-stats-btn:hover {
      background: #fffaf5;
    }

    /* ปุ่ม Hamburger Toggle (แสดงเฉพาะมือถือ) */
    .mobile-hamburger-btn {
      display: none;
      background: none;
      border: none;
      font-size: 24px;
      color: #ffffff;
      cursor: pointer;
      padding: 4px;
      line-height: 1;
    }

    /* ==========================================================================
       2. Mobile Drawer Menu (เมนูด้านข้างสไตล์ DDproperty)
       ========================================================================== */
    .mobile-drawer-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .mobile-drawer-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .mobile-drawer {
      position: fixed;
      top: 0;
      right: -100%;
      width: 310px;
      max-width: 85vw;
      height: 100vh;
      background: #ffffff;
      z-index: 2001;
      box-shadow: -4px 0 20px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .mobile-drawer.active {
      right: 0;
    }
    .drawer-header {
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .drawer-close-btn {
      background: none;
      border: none;
      font-size: 22px;
      color: #64748b;
      cursor: pointer;
      padding: 4px;
    }
    .drawer-menu-list {
      list-style: none;
      padding: 0;
      margin: 0;
      overflow-y: auto;
      flex: 1;
    }
    .drawer-menu-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      color: #261d3b;
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
    }
    .drawer-menu-item:hover {
      background: #f8fafc;
      color: #7955d9;
    }
    .drawer-menu-item span.arrow {
      color: #94a3b8;
      font-size: 14px;
    }
    .drawer-footer {
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      text-align: center;
      background: #ffffff;
    }
    .drawer-login-btn {
      background: #7955d9;
      color: #ffffff;
      text-decoration: none;
      padding: 12px;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 15px;
      display: block;
      box-shadow: 0 4px 10px rgba(114,87,245,0.25);
    }
    .drawer-download-link {
      color: #261d3b;
      font-size: 13px;
      font-weight: 700;
    }

    /* ==========================================================================
       3. ส่วน Hero & Search Box (ดีไซน์ค้นหาหลัก)
       ========================================================================== */
    .dd-hero {
      position: relative;
      min-height: 460px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 56px 16px 48px;
      width: 100%;
      isolation: isolate;
    }
    .dd-search-container {
      background: #ffffff;
      width: 100%;
      max-width: 760px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      padding: 16px;
      margin-top: 20px;
    }
    .dd-tabs {
      display: flex;
      gap: 20px;
      margin-bottom: 16px;
    }
    .dd-tab {
      font-weight: 700;
      font-size: 15px;
      color: #64748b;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0 0 4px 0;
      position: relative;
    }
    .dd-tab.active {
      color: #7955d9;
    }
    .dd-search-bar {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      width: 100%;
    }
    .dd-search-input-wrap {
      flex: 1;
      min-width: 200px;
      display: flex;
      align-items: center;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 0 12px;
    }
    .dd-search-input-wrap input {
      border: none;
      background: transparent;
      padding: 12px 8px;
      width: 100%;
      outline: none;
      font-size: 14px;
      font-family: inherit;
    }
    .dd-search-bar select {
      padding: 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      background: #f8fafc;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      cursor: pointer;
    }
    .dd-btn-search {
      background: #7955d9;
      color: #fff;
      border: none;
      padding: 12px 28px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .dd-btn-search:hover {
      background: #583caa;
    }

    /* ==========================================================================
       4. ส่วน Banner Cards & Showcase
       ========================================================================== */
      .featured-nav {
      display: grid;
      grid-auto-flow: column;
      grid-auto-columns: calc((100% - 42px) / 4);
      gap: 14px;
      overflow-x: auto;
      padding: 22px 0 14px;
      scrollbar-width: thin;
      scroll-snap-type: x mandatory;
    }
    .featured-nav-item {
      position: relative;
      min-height: 104px;
      display: flex;
      align-items: flex-end;
      overflow: hidden;
      border-radius: 8px;
      text-decoration: none;
      background: #e2e8f0;
      isolation: isolate;
    }
    .featured-nav-item img,
    .featured-nav-placeholder {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .25s ease;
    }
    .featured-nav-item:hover img {
      transform: scale(1.035);
    }
    .featured-nav-title {
      position: relative;
      z-index: 2;
      width: 100%;
      padding: 12px 14px;
      color: #fff;
      font-size: 15px;
      line-height: 1.25;
      font-weight: 800;
      text-shadow: 0 1px 3px rgba(0,0,0,.35);
    }
    .featured-nav-placeholder {
      background: linear-gradient(135deg, #94a3b8, #475569);
    }

    .showcase-section {
      padding: 34px 0;
      background: #fff;
      width: 100%;
    }
    .showcase-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
      flex-wrap: nowrap;
      gap: 12px;
    }
    .showcase-title-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .showcase-title {
      font-size: 22px;
      font-weight: 800;
      color: #111827;
      margin: 0;
    }
    .badge-new {
      display: none;
    }
    .showcase-more {
      font-size: 13px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      white-space: nowrap;
    }
    .showcase-more:hover {
      text-decoration: none;
      color: var(--accent-dark, #583caa);
    }
    .showcase-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 18px;
      width: 100%;
    }
    .showcase-card {
      min-width: 0;
      background: #fff;
      border: 1px solid #dfe3e8;
      border-radius: 10px;
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      transition: transform 0.2s, box-shadow 0.2s;
      display: block;
      position: relative;
    }
    .showcase-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.06);
    }
    .showcase-media {
      position: relative;
      aspect-ratio: 4 / 3;
      overflow: hidden;
      background: #e2e8f0;
    }
    .showcase-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .showcase-tag {
      position: absolute;
      left: 10px;
      bottom: 10px;
      padding: 3px 8px;
      border-radius: 3px;
      background: #7955d9;
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      box-shadow: 0 1px 4px rgba(0,0,0,.18);
    }
    .showcase-body {
      min-height: 86px;
      padding: 10px 12px 12px;
    }
    .showcase-card-title {
      min-height: 38px;
      font-size: 14px;
      line-height: 1.35;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 3px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .showcase-card-loc {
      font-size: 12px;
      line-height: 1.35;
      color: #64748b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .showcase-price {
      display: none;
    }

    /* ==========================================================================
       5. Media Queries: สำหรับมือถือและแท็บเล็ต
       ========================================================================== */
    @media (max-width: 992px) {
      .dd-desktop-menu, .dd-nav-right {
        display: none !important;
      }
      .mobile-hamburger-btn {
        display: block !important;
      }
      .dd-navbar {
        padding: 12px 16px;
      }
    }

    @media (max-width: 768px) {
      .dd-hero {
        padding: 30px 16px;
        min-height: auto;
      }
      .dd-hero h1 {
        font-size: 22px !important;
      }
      .dd-hero p {
        font-size: 13px !important;
      }
      .dd-search-container {
        padding: 14px;
        margin-top: 16px;
      }
      .dd-search-bar {
        flex-direction: column;
        align-items: stretch;
      }
      .dd-search-input-wrap, 
      .dd-search-bar select, 
      .dd-btn-search {
        width: 100% !important;
        min-width: 100% !important;
      }
      .featured-nav {
        grid-template-columns: none;
        grid-auto-flow: column;
        grid-auto-columns: 74%;
        gap: 12px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: thin;
      }
      .featured-nav-item {
        min-height: 112px;
        scroll-snap-align: start;
      }
      .showcase-section {
        padding: 28px 0;
      }
      .showcase-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
      }
      .showcase-title {
        font-size: 18px;
      }
      .showcase-grid {
        display: grid;
        grid-template-columns: none;
        grid-auto-flow: column;
        grid-auto-columns: 78%;
        gap: 12px;
        overflow-x: auto;
        padding: 2px 2px 14px;
        scroll-snap-type: x mandatory;
        scrollbar-width: thin;
      }
      .showcase-card {
        scroll-snap-align: start;
      }
    }
  </style>
  <link rel="stylesheet" href="css/home.css?v=<?= e((string) filemtime(__DIR__ . '/css/home.css')) ?>">
</head>
<body class="home-page">

<?php render_banner_slot('home_hero_top', 'compact'); ?>

<!-- Navigation Bar -->
<header class="dd-navbar">
  <div class="dd-nav-left">
    <?= render_logo_html('index.php') ?>
    
    <!-- เมนู Desktop -->
    <nav class="dd-desktop-menu">
      <?php render_nav_dropdowns(); ?>
      <a href="project.php" class="dd-menu-item"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="advertise.php" class="dd-menu-item"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <a href="submit.php" class="dd-menu-item"><?= e(t('ลงประกาศกับเรา', 'List with us')) ?></a>
    </nav>
  </div>

  <!-- ปุ่มขวา Desktop -->
  <div class="dd-nav-right">
    <a href="submit.php" class="dd-icon-btn"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
    <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>" class="dd-btn-login"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
      <a href="logout.php" class="dd-icon-btn"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
    <?php else: ?>
      <a href="login.php" class="dd-btn-login"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
    <?php endif; ?>
    <?php render_lang_toggle(); ?>
  </div>

  <!-- ปุ่ม Hamburger สำหรับ Mobile -->
  <button type="button" class="mobile-hamburger-btn" id="openDrawerBtn" aria-label="<?= e(t('เปิดเมนู', 'Open menu')) ?>">
    ☰
  </button>
</header>

<!-- Mobile Drawer Component (สไตล์ DDproperty) -->
<div class="mobile-drawer-overlay" id="drawerOverlay"></div>
<aside class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
  <div>
    <!-- Header เมนู Drawer -->
    <div class="drawer-header">
      <?= render_logo_html('index.php') ?>
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="drawer-close-btn" id="closeDrawerBtn" aria-label="<?= e(t('ปิดเมนู', 'Close menu')) ?>">✕</button>
      </div>
    </div>

    <!-- รายการเมนู Dropdown -->
    <ul class="drawer-menu-list">
      <li>
        <a href="search.php?type=sale" class="drawer-menu-item">
          <span><?= e(t('ซื้อ', 'Buy')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <li>
        <a href="search.php?type=rent" class="drawer-menu-item">
          <span><?= e(t('เช่า', 'Rent')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <li>
        <a href="category.php?page=house" class="drawer-menu-item">
          <span><?= e(t('บ้านเดี่ยว', 'Houses')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="search.php?category=commercial" class="drawer-menu-item">
          <span><?= e(t('พาณิชย์', 'Commercial')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="project.php" class="drawer-menu-item">
          <span><?= e(t('โครงการใหม่', 'New Projects')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="advertise.php" class="drawer-menu-item">
          <span><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="submit.php" class="drawer-menu-item">
          <span><?= e(t('ลงประกาศ', 'Post a listing')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <li><?php render_lang_toggle(); ?></li>
    </ul>
  </div>

  <!-- Footer ด้านล่างสุดของ Drawer -->
  <div class="drawer-footer">
    <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>" class="drawer-login-btn"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
      <a href="logout.php" class="drawer-download-link"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
    <?php else: ?>
      <a href="login.php" class="drawer-login-btn"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
    <?php endif; ?>
    <a href="submit.php" class="drawer-download-link"><?= e(t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')) ?></a>
  </div>
</aside>

<!-- Hero Section & Search -->
<section class="dd-hero">
  <!-- Dynamic Hero Media Layer (Auto-scaled for desktop & mobile) -->
  <div class="dd-hero-media-wrap" aria-hidden="true">
    <?php if ($heroBg['type'] === 'video'): ?>
      <video class="dd-hero-media" src="<?= e($heroBg['url']) ?>" autoplay muted loop playsinline preload="auto"></video>
    <?php else: ?>
      <img class="dd-hero-media" src="<?= e($heroBg['url']) ?>" alt="KASA Hero Background" fetchpriority="high">
    <?php endif; ?>
    <div class="dd-hero-overlay" style="background: rgba(11, 28, 51, <?= (float)($heroBg['opacity'] ?? 0.45) ?>);"></div>
  </div>

  <div class="dd-hero-heading-wrap" style="text-align: center; color: #fff; margin-bottom: 8px;">
    <span class="hero-eyebrow"><?= e(t('ค้นหาบ้านในแบบที่เป็นคุณ', 'Find a home that feels like you')) ?></span>
    <h1><?= e(t('บ้านที่ใช่ เริ่มต้นจากทำเลที่ชอบ', 'The right home starts with the right location')) ?></h1>
    <p><?= e(t('ค้นหาบ้าน คอนโด และอสังหาริมทรัพย์ทั่วไทย พร้อมติดต่อเจ้าของได้โดยตรง', 'Search houses, condos and properties across Thailand — contact owners directly.')) ?></p>
  </div>

  <div class="dd-search-container">
    <div class="dd-tabs">
      <button type="button" class="dd-tab active" aria-pressed="true" onclick="setTypeFilter('', this)"><?= e(t('ทั้งหมด', 'All')) ?></button>
      <button type="button" class="dd-tab" aria-pressed="false" onclick="setTypeFilter('sale', this)"><?= e(t('ซื้อ', 'Buy')) ?></button>
      <button type="button" class="dd-tab" aria-pressed="false" onclick="setTypeFilter('rent', this)"><?= e(t('เช่า', 'Rent')) ?></button>
    </div>

    <form method="get" action="search.php" class="dd-search-bar">
      <input type="hidden" name="type" id="formTypeInput" value="">
      
      <div class="dd-search-input-wrap">
        <span style="color: #94a3b8; font-size: 16px;">🔍</span>
        <input type="text" name="q" placeholder="<?= e(t('ค้นหาทำเล เช่น สุขุมวิท, ภูเก็ต, ลาดพร้าว', 'Search a location, e.g. Sukhumvit, Phuket, Lat Phrao')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
      </div>

      <select name="category">
        <option value=""><?= e(t('ทุกประเภทอสังหาฯ', 'All property types')) ?></option>
        <?php foreach (PROPERTY_CATEGORIES as $key => $label): if ($key === 'other') continue; ?>
          <option value="<?= e($key) ?>"><?= e(category_label_bi($key)) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="dd-btn-search"><?= e(t('ค้นหา', 'Search')) ?></button>
    </form>
  </div>
  <nav class="home-category-icons" aria-label="<?= e(t('หมวดหมู่อสังหาริมทรัพย์', 'Property categories')) ?>">
    <?php foreach ($homeCategories as $homeCategory): ?>
      <a class="home-category-item" href="<?= e($homeCategory['href']) ?>" title="<?= e($homeCategory['label']) ?>">
        <span class="home-category-icon" aria-hidden="true">
          <?php if ($homeCategory['icon'] !== ''): ?>
            <img src="<?= e($homeCategory['icon']) ?>" alt="" loading="lazy" decoding="async">
          <?php else: ?>
            <span class="home-category-emoji"><?= e($homeCategory['emoji']) ?></span>
          <?php endif; ?>
        </span>
        <span class="home-category-label"><?= e($homeCategory['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <nav class="hero-shortcuts" aria-label="<?= e(t('ค้นหาตามประเภทอสังหาริมทรัพย์', 'Search by property type')) ?>">
    <span><?= e(t('ค้นหายอดนิยม', 'Popular searches')) ?></span>
    <a href="search.php?category=condo"><?= e(t('คอนโด', 'Condos')) ?></a>
    <a href="search.php?category=house"><?= e(t('บ้านเดี่ยว', 'Houses')) ?></a>
    <a href="search.php?category=townhouse"><?= e(t('ทาวน์โฮม', 'Townhouses')) ?></a>
    <a href="search.php?category=land"><?= e(t('ที่ดิน', 'Land')) ?></a>
  </nav>
  <?php render_banner_slot('home_below_hero', 'medium'); ?>
</section>

<?php render_banner_slot('home_category_gap', 'medium'); ?>

<!-- เมนูรูปภาพตามหมวดแนะนำที่ผู้ดูแลตั้งค่า -->
<?php if ($featuredSections): ?>
<nav class="container featured-nav" aria-label="<?= e(t('หมวดอสังหาริมทรัพย์แนะนำ', 'Featured property categories')) ?>">
  <?php foreach ($featuredSections as $featuredSection): ?>
    <?php
      // ใช้ภาพหมวดหมู่ที่แอดมินเลือกเอง ไม่ใช้รูปปกของประกาศสมาชิก
      $navCategory = (string)$featuredSection['category'];
      $navCategoryImage = $categoryIcons[$navCategory] ?? '';
      $navCategoryImageExists = $navCategoryImage !== '' && is_file(__DIR__ . '/' . $navCategoryImage);
  ?>
  <a class="featured-nav-item" href="search.php?category=<?= e(rawurlencode($featuredSection['category'])) ?>">
      <?php if ($navCategoryImageExists): ?>
        <img src="<?= e($navCategoryImage) ?>" alt="<?= e($featuredSection['title']) ?>" loading="lazy" decoding="async">
      <?php else: ?>
        <span class="featured-nav-placeholder" aria-hidden="true"><span><?= e($categoryIconFallback[$navCategory] ?? '🏡') ?></span></span>
      <?php endif; ?>
      <span class="featured-nav-title"><?= e($featuredSection['title']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- หมวดประกาศแนะนำที่ผู้ดูแลตั้งค่า -->
<?php foreach ($featuredSections as $featuredSection): ?>
<section class="section showcase-section">
  <div class="container">
    <div class="showcase-header">
      <div class="showcase-title-wrap">
        <h2 class="showcase-title"><?= e($featuredSection['title']) ?></h2>
      </div>
      <a href="search.php?category=<?= e(rawurlencode($featuredSection['category'])) ?>" class="showcase-more"><?= e(t('ดูประกาศทั้งหมด ›', 'View all ›')) ?></a>
    </div>

    <?php if ($featuredSection['listings']): ?>
      <div class="showcase-grid">
        <?php foreach ($featuredSection['listings'] as $listing): ?>
          <a href="property.php?id=<?= (int)$listing['id'] ?>" class="showcase-card">
            <?php
              $featuredFilename = $featuredImages[(int)$listing['id']] ?? '';
              $featuredImagePath = $featuredFilename ? __DIR__ . '/uploads/' . $featuredFilename : '';
              $featuredImageUrl = $featuredFilename ? 'uploads/' . $featuredFilename : '';
              $featuredImageExists = $featuredFilename !== '' && is_file($featuredImagePath);
            ?>
            <div class="showcase-media">
              <?php if ($featuredImageExists): ?>
                <img src="<?= e($featuredImageUrl) ?>" class="showcase-img" alt="<?= e($listing['title']) ?>" loading="lazy" decoding="async" width="640" height="441">
              <?php else: ?>
                <div class="showcase-img noimg" aria-hidden="true"></div>
              <?php endif; ?>
              <span class="showcase-tag"><?= e(is_en() ? (property_category_en_label($featuredSection['category']) ?? (PROPERTY_CATEGORIES[$featuredSection['category']] ?? $featuredSection['category'])) : (PROPERTY_CATEGORIES[$featuredSection['category']] ?? $featuredSection['category'])) ?></span>
              <?php if (!empty($listing['is_featured'])): ?><span class="showcase-featured-tag"><?= e(t('แนะนำ', 'Featured')) ?></span><?php endif; ?>
              <?php render_publisher_mini($listing); ?>
            </div>
            <div class="showcase-body">
              <div class="showcase-card-title"><?= e($listing['title']) ?></div>
              <div class="showcase-card-loc"><?= e($listing['location'] ?: '-') ?></div>
              
              <div class="showcase-price">฿<?= number_format((int)$listing['price']) ?><?= $listing['type'] === 'rent' ? '/เดือน' : '' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty"><?= e(t('ยังไม่มีประกาศในหมวดนี้ที่อนุมัติแล้ว', 'No approved listings in this category yet')) ?></p>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<!-- ส่วนติดต่อ -->

<!-- Footer -->
<?php
render_page_cta([
    'title'   => 'พร้อมประกาศขายหรือให้เช่าแล้วหรือยัง?',
    'text'    => 'ลงประกาศฟรีบน ' . SITE_NAME . ' เข้าถึงผู้ซื้อและผู้เช่าทั่วไทย พร้อมเครื่องมือคำนวณเงินผ่อนและแผนที่ครบมือ',
    'primary' => ['submit.php', 'ลงประกาศฟรี'],
    'ghost'   => ['search.php', 'เริ่มค้นหาทรัพย์'],
]);
render_to_top(); render_ux_scripts();
render_banner_slot('home_footer_above', 'compact');
render_site_footer(); ?>

<script>
  function setTypeFilter(val, el) {
    document.getElementById('formTypeInput').value = val;
    document.querySelectorAll('.dd-tab').forEach(tab => {
      tab.classList.remove('active');
      tab.setAttribute('aria-pressed', 'false');
    });
    el.classList.add('active');
    el.setAttribute('aria-pressed', 'true');
  }

  // ระบบเปิด-ปิด Mobile Drawer Menu
  const openDrawerBtn = document.getElementById('openDrawerBtn');
  const closeDrawerBtn = document.getElementById('closeDrawerBtn');
  const drawerOverlay = document.getElementById('drawerOverlay');
  const mobileDrawer = document.getElementById('mobileDrawer');

  function openDrawer() {
    mobileDrawer.classList.add('active');
    drawerOverlay.classList.add('active');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    openDrawerBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    mobileDrawer.classList.remove('active');
    drawerOverlay.classList.remove('active');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    openDrawerBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  openDrawerBtn.addEventListener('click', openDrawer);
  closeDrawerBtn.addEventListener('click', closeDrawer);
  drawerOverlay.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeDrawer();
  });
</script>
<script src="js/app.js?v=<?= time() ?>"></script>
</body>
</html>
