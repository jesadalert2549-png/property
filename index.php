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
            KEY idx_featured_order (enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $featuredSections = $pdo->query(
        'SELECT id, category, title
         FROM featured_sections
         WHERE enabled = 1
         ORDER BY sort_order, id
         LIMIT 20'
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
        ? 'SELECT p.id, p.title, p.type, p.price, p.original_price, p.views_count, p.clicks_count, p.location, p.contact_name,
                  (SELECT u.full_name FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_name,
                  (SELECT u.avatar_path FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_avatar,
                  (SELECT u.agent_status FROM users u WHERE u.id = p.user_id LIMIT 1) AS publisher_agent_status,
                  (fp.property_id IS NOT NULL) AS is_featured
           FROM properties p LEFT JOIN featured_properties fp ON fp.property_id = p.id
           WHERE p.approval = ? AND p.status = ? AND p.property_category = ?
           ORDER BY (fp.property_id IS NULL), fp.sort_order, p.created_at DESC LIMIT 4'
        : 'SELECT p.id, p.title, p.type, p.price, p.original_price, p.views_count, p.clicks_count, p.location, p.contact_name,
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
  <?php 
    $homeTitle = is_en()
        ? 'Buy & Rent Houses, Condos, Land & Properties in Thailand | ' . SITE_NAME
        : 'ซื้อขายบ้าน บ้านเดี่ยว คอนโด ที่ดิน ทาวน์เฮ้าส์ อาคารพาณิชย์ | ' . SITE_NAME;
    $homeDesc = is_en()
        ? 'Find verified houses, condos, townhouses and land for sale or rent across Thailand. Compare prices, view locations, post free listings, or contact owners directly.'
        : 'ศูนย์รวมประกาศซื้อ ขาย เช่า อสังหาริมทรัพย์ครบวงจร ทั้งบ้าน คอนโด ทาวน์โฮม ที่ดิน และอาคารพาณิชย์ ทั่วไทย ดูรูป ราคา และทำเล ติดต่อผู้ขายได้ทันที';
    seo_head($homeTitle, $homeDesc, site_url() . '/');
    require_once __DIR__ . '/includes/seo_provinces.php';
    json_ld(generate_website_schema());
    json_ld(generate_national_agency_schema());
    json_ld(generate_site_navigation_schema());
    json_ld(generate_homepage_ai_faq_schema());
  ?>
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
      width: 100%;
      background: #F9F9FB;
      font-family: "Fenice Std", "Noto Serif Thai", "Prompt", "Inter", -apple-system, system-ui, serif !important;
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
      background: transparent;
      padding: 14px 28px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      width: 100%;
      border-bottom: 1px solid rgba(255, 255, 255, 0.15);
      transition: background 0.32s cubic-bezier(.22, 1, .36, 1),
                  border-color 0.32s ease,
                  box-shadow 0.32s ease;
    }
    .dd-navbar.scrolled {
      position: fixed;
      top: 0;
      background: #0B1C33 !important;
      border-bottom: 1px solid rgba(197, 160, 89, 0.35) !important;
      box-shadow: 0 8px 26px rgba(6, 15, 28, 0.45) !important;
    }
    .dd-navbar:not(.scrolled) .dd-menu-item,
    .dd-navbar:not(.scrolled) .dd-desktop-menu .nav-dropdown-toggle {
      color: #0B1C33 !important;
      font-weight: 800 !important;
      text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5) !important;
    }
    .dd-navbar:not(.scrolled) .dd-menu-item:hover,
    .dd-navbar:not(.scrolled) .dd-desktop-menu .nav-dropdown-toggle:hover {
      color: #8C6B32 !important;
    }
    .dd-navbar:not(.scrolled) .nav-caret {
      border-color: #0B1C33 !important;
    }
    .dd-navbar:not(.scrolled) .dd-icon-btn {
      background: rgba(255, 255, 255, 0.65) !important;
      border: 1px solid rgba(11, 28, 51, 0.25) !important;
      color: #0B1C33 !important;
      font-weight: 800 !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
    }
    .dd-navbar:not(.scrolled) .dd-icon-btn:hover {
      background: #ffffff !important;
      border-color: #C5A059 !important;
      color: #A8843F !important;
    }
    .dd-navbar:not(.scrolled) .mobile-hamburger-btn {
      color: #0B1C33 !important;
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
      right: 0;
      transform: translateX(105%);
      width: 310px;
      max-width: 85vw;
      height: 100vh;
      background: #ffffff;
      z-index: 2001;
      box-shadow: -4px 0 20px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      visibility: hidden;
    }
    .mobile-drawer.active {
      transform: translateX(0);
      visibility: visible;
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
      min-height: 520px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 120px 16px 48px;
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
      top: 10px;
      bottom: auto;
      padding: 4px 10px;
      border-radius: 999px;
      background: #0B1C33;
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      border: 1px solid rgba(197, 160, 89, 0.4);
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
      z-index: 3;
    }
    .showcase-featured-tag {
      position: absolute;
      top: 10px;
      left: auto;
      right: 50px;
      padding: 4px 10px;
      border-radius: 999px;
      background: linear-gradient(135deg, #C5A059, #A8843F);
      color: #0B1C33;
      font-size: 11px;
      font-weight: 800;
      z-index: 3;
      box-shadow: 0 2px 8px rgba(197, 160, 89, 0.35);
    }
    .showcase-fav-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(0, 0, 0, 0.08);
      display: grid;
      place-items: center;
      font-size: 16px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transition: transform 0.2s, background 0.2s;
      z-index: 3;
    }
    .showcase-fav-btn:hover {
      transform: scale(1.15);
      background: #fff;
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
      display: block;
      margin-top: 6px;
      font-size: 16px;
      font-weight: 800;
      color: #A8843F;
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
        padding: 96px 16px 36px;
        min-height: auto;
      }
      .dd-hero h1 {
        font-size: 24px !important;
      }
      .dd-hero p {
        font-size: 13.5px !important;
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
      <a href="favorites.php" class="dd-menu-item" title="<?= e(t('ทรัพย์ที่บันทึกไว้', 'Saved Properties')) ?>">
        ❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?>
        <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:999px; margin-left:2px;"></span>
      </a>
      <a href="notifications.php" class="dd-menu-item" title="<?= e(t('การแจ้งเตือน', 'Notifications')) ?>">
        🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?>
        <span class="sp-notif-badge" style="display:none; background:#ef4444; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:999px; margin-left:2px;"></span>
      </a>
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
      <img class="dd-hero-media" src="<?= e($heroBg['url']) ?>" alt="PropertyGO Hero Background" fetchpriority="high">
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
      $navCategory = (string)$featuredSection['category'];
      $navCategoryImage = $categoryIcons[$navCategory] ?? '';
      $navCategoryImageExists = $navCategoryImage !== '' && is_file(__DIR__ . '/' . $navCategoryImage);
      $navTitle = is_en() ? (property_category_en_label($navCategory) ? 'Featured ' . property_category_en_label($navCategory) : $featuredSection['title']) : $featuredSection['title'];
  ?>
  <a class="featured-nav-item" href="search.php?category=<?= e(rawurlencode($featuredSection['category'])) ?>">
      <?php if ($navCategoryImageExists): ?>
        <img src="<?= e($navCategoryImage) ?>" alt="<?= e($navTitle) ?>" loading="lazy" decoding="async">
      <?php else: ?>
        <span class="featured-nav-placeholder" aria-hidden="true"><span><?= e($categoryIconFallback[$navCategory] ?? '🏡') ?></span></span>
      <?php endif; ?>
      <span class="featured-nav-title"><?= e($navTitle) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- หมวดประกาศแนะนำที่ผู้ดูแลตั้งค่า -->
<?php foreach ($featuredSections as $featuredSection):
  $sectionTitle = is_en() ? (property_category_en_label((string)$featuredSection['category']) ? 'Featured ' . property_category_en_label((string)$featuredSection['category']) : $featuredSection['title']) : $featuredSection['title'];
?>
<section class="section showcase-section">
  <div class="container">
    <div class="showcase-header">
      <div class="showcase-title-wrap">
        <h2 class="showcase-title"><?= e($sectionTitle) ?></h2>
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
              <button type="button" class="sp-fav-card-btn showcase-fav-btn" data-fav-toggle data-fav-id="<?= (int)$listing['id'] ?>" aria-label="<?= e(t('บันทึกในรายการที่ชอบ', 'Save to favorites')) ?>">
                <span class="fav-heart-icon">
                  <svg class="heart-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                </span>
              </button>
              <?php
                require_once __DIR__ . '/includes/price_helpers.php';
                echo render_card_stats_badge((int)($listing['views_count'] ?? 0), (int)($listing['clicks_count'] ?? 0));
              ?>
              <?php render_publisher_mini($listing); ?>
            </div>
            <div class="showcase-body">
              <div class="showcase-card-title"><?= e($listing['title']) ?></div>
              <div class="showcase-card-loc"><?= e($listing['location'] ?: '-') ?></div>
              
              <div class="showcase-price">
                <?php
                  require_once __DIR__ . '/includes/price_helpers.php';
                  echo render_price_badge((int)$listing['price'], !empty($listing['original_price']) ? (int)$listing['original_price'] : null, $listing['type']);
                ?>
              </div>
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

<!-- ==========================================================================
     Interactive Sticky Tools & Retention Engine (เครื่องมือคำนวณและลูกเล่นช่วยให้อยู่ในเว็บนานขึ้น)
     ========================================================================== -->
<section class="section" style="padding:48px 0; background:linear-gradient(180deg,#ffffff,#f8fafc); border-top:1px solid #eef2f6;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 16px;">
    
    <div style="text-align:center; max-width:680px; margin:0 auto 36px;">
      <span style="font-size:11px; font-weight:800; color:#8C6B32; background:rgba(197,160,89,.14); padding:4px 14px; border-radius:999px; letter-spacing:0.12em; text-transform:uppercase;">Interactive Property Suite</span>
      <h2 style="font-size:clamp(22px, 3.2vw, 32px); font-weight:800; color:#0B1C33; margin:10px 0 8px;"><?= e(t('เครื่องมือวิเคราะห์และตัวช่วยประเมินอสังหาฯ', 'Property Analytics & Valuation Tools')) ?></h2>
      <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0;"><?= e(t('คำนวณค่างวดผ่อนบ้าน คอนโด หรือสำรวจผลตอบแทนการลงทุน (Rental Yield) ได้แบบเรียลไทม์ทันที', 'Estimate home or condo monthly installments and explore Rental Yield in real-time.')) ?></p>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap:24px;">
      
      <!-- Card 1: เครื่องมือคำนวณเงินผ่อนบ้าน-คอนโดแบบเรียลไทม์ -->
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06); position:relative; overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(197,160,89,.15); color:#8C6B32; font-size:20px;">🧮</span>
            <div>
              <strong style="font-size:16px; color:#0B1C33; display:block;"><?= e(t('คำนวณยอดผ่อนต่อเดือน', 'Monthly Payment Calculator')) ?></strong>
              <small style="color:#64748B; font-size:11.5px;"><?= e(t('ประเมินวงเงินและค่างวดอัตโนมัติ', 'Automated loan amount and monthly estimates')) ?></small>
            </div>
          </div>
          <span style="font-size:11px; font-weight:800; color:#15803d; background:#ecfdf5; padding:3px 8px; border-radius:6px;">LIVE CALC</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
          <div>
            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px;">
              <span><?= e(t('ราคาอสังหาริมทรัพย์:', 'Property price:')) ?></span>
              <strong style="color:#0B1C33; font-size:15px;" id="calcPriceDisplay">3,000,000 <?= e(t('บาท', 'THB')) ?></strong>
            </div>
            <input type="range" id="calcPriceRange" min="500000" max="25000000" step="100000" value="3000000" style="width:100%; accent-color:#C5A059; cursor:pointer;">
            <div style="display:flex; justify-content:space-between; font-size:11px; color:#94a3b8; margin-top:2px;">
              <span><?= e(t('5 แสน', '500K')) ?></span>
              <span><?= e(t('10 ล้าน', '10M')) ?></span>
              <span><?= e(t('25 ล้าน', '25M')) ?></span>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div>
              <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?= e(t('เงินดาวน์ (%)', 'Down payment (%)')) ?></label>
              <select id="calcDownPercent" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; background:#fff;">
                <option value="0"><?= e(t('0% (กู้เต็ม 100%)', '0% (100% Loan)')) ?></option>
                <option value="10" selected><?= e(t('10% (300,000 บ.)', '10% (300K THB)')) ?></option>
                <option value="15"><?= e(t('15% (450,000 บ.)', '15% (450K THB)')) ?></option>
                <option value="20"><?= e(t('20% (600,000 บ.)', '20% (600K THB)')) ?></option>
              </select>
            </div>
            <div>
              <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?= e(t('ระยะเวลากู้ (ปี)', 'Loan term (years)')) ?></label>
              <select id="calcYears" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; background:#fff;">
                <option value="15">15 <?= e(t('ปี', 'Years')) ?></option>
                <option value="20">20 <?= e(t('ปี', 'Years')) ?></option>
                <option value="25">25 <?= e(t('ปี', 'Years')) ?></option>
                <option value="30" selected>30 <?= e(t('ปี', 'Years')) ?></option>
                <option value="35">35 <?= e(t('ปี', 'Years')) ?></option>
              </select>
            </div>
          </div>

          <div style="background:linear-gradient(135deg, #0B1C33, #152c4d); border-radius:14px; padding:18px; color:#fff; text-align:center;">
            <span style="font-size:12px; color:#cbd5e1; letter-spacing:0.04em;"><?= e(t('ประมาณการยอดผ่อนต่อเดือน', 'Estimated monthly payment')) ?></span>
            <div style="font-size:28px; font-weight:900; color:#C5A059; margin:4px 0 2px;" id="calcMonthlyResult"><?= e(t('฿', 'THB ')) ?>14,489</div>
            <small style="font-size:11px; color:#94a3b8;"><?= e(t('*คำนวณที่อัตราดอกเบี้ยเฉลี่ย 5.0% ต่อปี (ยอดจริงขึ้นกับสถาบันการเงิน)', '*Calculated at an estimated 5.0% interest rate per year')) ?></small>
          </div>
          
          <a href="search.php?type=sale" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px; border-radius:10px; background:#f1f5f9; color:#0B1C33; font-weight:700; font-size:13px; text-decoration:none; transition:all .15s;" onmouseover="this.style.background='#0B1C33';this.style.color='#fff'" onmouseout="this.style.background='#f1f5f9';this.style.color='#0B1C33'">
            <span><?= e(t('ค้นหาทรัพย์ในงบนี้', 'Find properties in this budget')) ?></span> <span>›</span>
          </a>
        </div>
      </div>

      <!-- Card 2: เครื่องมือจำลองผลตอบแทนการปล่อยเช่า (Rental Yield & Investment) -->
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(21,128,61,.12); color:#15803d; font-size:20px;">📈</span>
            <div>
              <strong style="font-size:16px; color:#0B1C33; display:block;"><?= e(t('เครื่องมือประเมินผลตอบแทน (Rental Yield)', 'Rental Yield Calculator')) ?></strong>
              <small style="color:#64748B; font-size:11.5px;"><?= e(t('วิเคราะห์ความคุ้มค่าก่อนซื้อลงทุน', 'Analyze investment return before buying')) ?></small>
            </div>
          </div>
          <span style="font-size:11px; font-weight:800; color:#8C6B32; background:rgba(197,160,89,.15); padding:3px 8px; border-radius:6px;">INVESTOR</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#475569; margin-bottom:4px;"><?= e(t('ราคาซื้ออสังหาริมทรัพย์ (บาท)', 'Property purchase price (THB)')) ?></label>
            <input type="number" id="yieldBuyPrice" value="2800000" step="50000" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:700; color:#0B1C33; box-sizing:border-box;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#475569; margin-bottom:4px;"><?= e(t('ค่าเช่าที่คาดว่าจะได้รับ (บาท/เดือน)', 'Expected rental income (THB/month)')) ?></label>
            <input type="number" id="yieldRentPrice" value="14000" step="500" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:700; color:#0B1C33; box-sizing:border-box;">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:2px;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; text-align:center;">
              <small style="color:#64748B; font-size:11px; display:block;"><?= e(t('รายรับค่าเช่าต่อปี', 'Annual rental income')) ?></small>
              <strong style="font-size:18px; color:#0B1C33; display:block; margin-top:3px;" id="yieldAnnualRent"><?= e(t('฿', 'THB ')) ?>168,000</strong>
            </div>
            <div style="background:rgba(197,160,89,.1); border:1px solid rgba(197,160,89,.3); border-radius:12px; padding:14px; text-align:center;">
              <small style="color:#8C6B32; font-size:11px; font-weight:700; display:block;">Gross Rental Yield</small>
              <strong style="font-size:22px; color:#A8843F; display:block; margin-top:1px;" id="yieldPercentResult">6.00%</strong>
            </div>
          </div>

          <div style="font-size:12px; color:#475569; line-height:1.5; background:#fafafa; border-radius:8px; padding:8px 12px;">
            💡 <strong><?= e(t('คำแนะนำนักลงทุน:', 'Investor Tip:')) ?></strong> <?= e(t('Yield ระดับ 5% - 7% ถือเป็นเกณฑ์ที่ดีมากสำหรับคอนโดย่าน CBD และแนวรถไฟฟ้า', 'A 5% - 7% yield is considered excellent for CBD and transit-oriented condos.')) ?>
          </div>

          <a href="category.php?page=condo" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px; border-radius:10px; background:#0B1C33; color:#fff; font-weight:700; font-size:13px; text-decoration:none; transition:all .15s;" onmouseover="this.style.background='#C5A059';this.style.color='#0B1C33'" onmouseout="this.style.background='#0B1C33';this.style.color='#fff'">
            <span><?= e(t('ดูคอนโดทำเลน่าลงทุน Yield สูง', 'Explore high-yield investment condos')) ?></span> <span>›</span>
          </a>
        </div>
      </div>

      <!-- Card 3: ควิซด่วนค้นหาบ้าน/คอนโดที่ใช่คุณ (Property Matchmaker) -->
      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(121,85,217,.12); color:#7955d9; font-size:20px;">🧭</span>
            <div>
              <strong style="font-size:16px; color:#0B1C33; display:block;"><?= e(t('ค้นหาอสังหาฯ ใน 10 วินาที', 'Find Properties in 10 Seconds')) ?></strong>
              <small style="color:#64748B; font-size:11.5px;"><?= e(t('ระบบจับคู่ทำเลและประเภทที่ตรงใจคุณ', 'Smart matching for locations and property types')) ?></small>
            </div>
          </div>
          <span style="font-size:11px; font-weight:800; color:#7955d9; background:#f5f3ff; padding:3px 8px; border-radius:6px;">SMART MATCH</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;" id="quizContainer">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px;"><?= e(t('1. จุดประสงค์หลักของคุณ:', '1. Primary goal:')) ?></label>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
              <button type="button" class="quiz-btn active" data-type="sale" onclick="selectQuizOption(this, 'type')"><?= e(t('ซื้อเพื่ออยู่อาศัย', 'Buy for living')) ?></button>
              <button type="button" class="quiz-btn" data-type="rent" onclick="selectQuizOption(this, 'type')"><?= e(t('เช่าระยะยาว', 'Long-term rent')) ?></button>
            </div>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px;"><?= e(t('2. ประเภทที่ต้องการ:', '2. Preferred type:')) ?></label>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px;">
              <button type="button" class="quiz-btn active" data-cat="condo" onclick="selectQuizOption(this, 'cat')">🏢 <?= e(t('คอนโด', 'Condo')) ?></button>
              <button type="button" class="quiz-btn" data-cat="house" onclick="selectQuizOption(this, 'cat')">🏠 <?= e(t('บ้าน', 'House')) ?></button>
              <button type="button" class="quiz-btn" data-cat="townhouse" onclick="selectQuizOption(this, 'cat')">🏘️ <?= e(t('ทาวน์โฮม', 'Townhouse')) ?></button>
            </div>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px;"><?= e(t('3. สไตล์ทำเลที่ชอบ:', '3. Location style:')) ?></label>
            <select id="quizZoneSelect" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; background:#fff; font-weight:600;">
              <option value="สุขุมวิท">🚇 <?= e(t('ติดรถไฟฟ้า BTS/MRT ใจกลางเมือง (สุขุมวิท/อโศก)', 'Near BTS/MRT City Center (Sukhumvit/Asoke)')) ?></option>
              <option value="สาทร">💼 <?= e(t('ย่านธุรกิจและการเงิน (สาทร/สีลม)', 'Business & Financial District (Sathorn/Silom)')) ?></option>
              <option value="อารีย์">☕ <?= e(t('ย่านคาเฟ่ ไลฟ์สไตล์ชิคๆ (อารีย์/พหลโยธิน)', 'Chic Cafe & Lifestyle Hub (Ari/Phaholyothin)')) ?></option>
              <option value="บางนา">🏡 <?= e(t('บ้านเดี่ยวบรรยากาศเงียบสงบ (บางนา/ศรีนครินทร์)', 'Quiet Residential Houses (Bangna/Srinakarin)')) ?></option>
              <option value="เชียงใหม่">🌄 <?= e(t('ต่างจังหวัดบรรยากาศร่มรื่น (เชียงใหม่)', 'Scenic Northern Living (Chiang Mai)')) ?></option>
              <option value="หัวหิน">🏖️ <?= e(t('ตากอากาศติดชายหาด (หัวหิน/ชะอำ)', 'Beachfront Getaways (Hua Hin/Cha-am)')) ?></option>
            </select>
          </div>

          <button type="button" onclick="goToMatchedProperties()" style="margin-top:4px; width:100%; padding:12px; border:0; border-radius:10px; background:linear-gradient(135deg, #C5A059, #A8843F); color:#0B1C33; font-weight:800; font-size:14px; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); transition:transform .15s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
            ⚡ <?= e(t('แสดงทรัพย์ที่ตรงกับคุณทันที', 'Show Matching Properties Instantly')) ?>
          </button>
        </div>
      </div>

    </div>
  </div>
</section>

<style>
  .quiz-btn {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all .15s ease;
  }
  .quiz-btn:hover {
    border-color: #C5A059;
    color: #0B1C33;
  }
  .quiz-btn.active {
    background: #0B1C33;
    border-color: #0B1C33;
    color: #fff;
    box-shadow: 0 2px 6px rgba(11,28,51,.2);
  }
</style>

<script>
  // 1. Script คำนวณค่างวดผ่อนบ้าน
  (function initMortgageCalc() {
    const range = document.getElementById('calcPriceRange');
    const display = document.getElementById('calcPriceDisplay');
    const downSelect = document.getElementById('calcDownPercent');
    const yearsSelect = document.getElementById('calcYears');
    const result = document.getElementById('calcMonthlyResult');

    function calculate() {
      const price = parseFloat(range.value) || 3000000;
      const downPct = parseFloat(downSelect.value) || 0;
      const years = parseFloat(yearsSelect.value) || 30;
      display.textContent = Number(price).toLocaleString('th-TH') + ' บาท';

      const loanAmount = price * (1 - (downPct / 100));
      const annualRate = 0.05; // 5% ต่อปี
      const monthlyRate = annualRate / 12;
      const totalMonths = years * 12;

      // สูตรคำนวณ PMT: P * [ r(1+r)^n ] / [ (1+r)^n - 1 ]
      const factor = Math.pow(1 + monthlyRate, totalMonths);
      const monthlyPayment = loanAmount * (monthlyRate * factor) / (factor - 1);
      const currSym = <?= json_encode(t('฿', 'THB ')) ?>;
      result.textContent = currSym + Math.round(monthlyPayment).toLocaleString('th-TH');
    }

    if (range) {
      range.addEventListener('input', calculate);
      downSelect.addEventListener('change', calculate);
      yearsSelect.addEventListener('change', calculate);
      calculate();
    }
  })();

  // 2. Script คำนวณ Rental Yield
  (function initYieldCalc() {
    const buyInput = document.getElementById('yieldBuyPrice');
    const rentInput = document.getElementById('yieldRentPrice');
    const annualDisplay = document.getElementById('yieldAnnualRent');
    const yieldDisplay = document.getElementById('yieldPercentResult');

    function calculateYield() {
      const buyPrice = parseFloat(buyInput.value) || 1;
      const rentPrice = parseFloat(rentInput.value) || 0;
      const annualRent = rentPrice * 12;
      const yieldPct = (annualRent / buyPrice) * 100;

      const currSym = <?= json_encode(t('฿', 'THB ')) ?>;
      annualDisplay.textContent = currSym + Math.round(annualRent).toLocaleString('th-TH');
      yieldDisplay.textContent = yieldPct.toFixed(2) + '%';
    }

    if (buyInput && rentInput) {
      buyInput.addEventListener('input', calculateYield);
      rentInput.addEventListener('input', calculateYield);
      calculateYield();
    }
  })();

  // 3. Script Smart Matchmaker
  let matchQuizState = { type: 'sale', cat: 'condo' };
  function selectQuizOption(btn, field) {
    const group = btn.closest('div');
    group.querySelectorAll('.quiz-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    if (field === 'type') matchQuizState.type = btn.getAttribute('data-type');
    if (field === 'cat') matchQuizState.cat = btn.getAttribute('data-cat');
  }

  function goToMatchedProperties() {
    const zone = document.getElementById('quizZoneSelect').value;
    const url = 'search.php?type=' + encodeURIComponent(matchQuizState.type) + '&category=' + encodeURIComponent(matchQuizState.cat) + '&q=' + encodeURIComponent(zone);
    window.location.href = url;
  }
</script>

<!-- AI Overviews & Knowledge FAQ Section สไตล์ DDproperty เพื่อให้ Google และ AI ดึงข้อมูลไปตอบ -->
<?php $homeAiFaq = generate_homepage_ai_faq_schema(); ?>
<section class="container" style="max-width:1200px; margin:40px auto 20px; padding:0 16px;">
  <div style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:28px 32px; box-shadow:0 8px 24px rgba(11,28,51,0.04);">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
      <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(197,160,89,0.14); color:#8C6B32; font-size:20px;">💡</span>
      <div>
        <h2 style="font-size:clamp(18px, 2.5vw, 22px); font-weight:800; color:#0B1C33; margin:0 0 2px;">
          <?= e(t('คำถามที่พบบ่อยเกี่ยวกับการซื้อ ขาย และเช่าอสังหาริมทรัพย์', 'Frequently Asked Questions & Real Estate Insights')) ?>
        </h2>
        <p style="font-size:12.5px; color:#64748B; margin:0;">
          <?= e(t('ข้อมูลสรุปและคู่มือเบื้องต้นสำหรับผู้ซื้อ ผู้เช่า และผู้ลงประกาศบน ' . SITE_NAME, 'Comprehensive property guides and market insights powered by ' . SITE_NAME)) ?>
        </p>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php foreach ($homeAiFaq['mainEntity'] as $faqItem): ?>
        <details class="dd-home-faq" style="border:1px solid #e2e8f0; border-radius:14px; background:#fafbfc; padding:14px 18px; transition:background 0.2s;">
          <summary style="font-size:14px; font-weight:700; color:#0B1C33; cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <span><?= e($faqItem['name']) ?></span>
            <span style="color:#C5A059; font-size:12px;">▼</span>
          </summary>
          <p style="margin:10px 0 0; font-size:13px; color:#475569; line-height:1.65; border-top:1px solid #edf2f7; padding-top:10px;">
            <?= e($faqItem['acceptedAnswer']['text']) ?>
          </p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ส่วนติดต่อ -->

<!-- Footer -->
<?php
render_page_cta([
    'title'   => t('พร้อมประกาศขายหรือให้เช่าแล้วหรือยัง?', 'Ready to sell or rent out your property?'),
    'text'    => t('ลงประกาศฟรีบน ' . SITE_NAME . ' เข้าถึงผู้ซื้อและผู้เช่าทั่วไทย พร้อมเครื่องมือคำนวณเงินผ่อนและแผนที่ครบมือ', 'Post for free on ' . SITE_NAME . ' and reach buyers and renters across Thailand, with mortgage calculator and maps included.'),
    'primary' => ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')],
    'ghost'   => ['search.php', t('เริ่มค้นหาทรัพย์', 'Start searching properties')],
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

  // Dynamic navbar scroll state (transparent at top, dark navy when scrolled)
  const ddNavbar = document.querySelector('.dd-navbar');
  if (ddNavbar) {
    const onScroll = () => {
      ddNavbar.classList.toggle('scrolled', window.scrollY > 20);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
</script>
<script src="js/app.js?v=<?= time() ?>"></script>
</body>
</html>
