<?php
/**
 * หน้าหลักเว็บไซต์ (Index Page) - ออกแบบ UI/UX Responsive + Hamburger Drawer Menu สไตล์ DDproperty
 * พัฒนาสำหรับโครงการ EasyHome v.3.0
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
require_once __DIR__ . '/includes/mobile_nav.php';
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
        ? 'SELECT p.id, p.title, p.type, p.price, p.original_price, p.views_count, p.clicks_count, p.location, p.contact_name, p.video_file,
                  u.full_name AS publisher_name,
                  u.avatar_path AS publisher_avatar,
                  u.agent_status AS publisher_agent_status,
                  (fp.property_id IS NOT NULL) AS is_featured
           FROM properties p
           LEFT JOIN users u ON u.id = p.user_id
           LEFT JOIN featured_properties fp ON fp.property_id = p.id
           WHERE p.approval = ? AND p.status = ? AND p.property_category = ?
           ORDER BY (fp.property_id IS NULL), fp.sort_order, p.created_at DESC LIMIT 4'
        : 'SELECT p.id, p.title, p.type, p.price, p.original_price, p.views_count, p.clicks_count, p.location, p.contact_name, p.video_file,
                  u.full_name AS publisher_name,
                  u.avatar_path AS publisher_avatar,
                  u.agent_status AS publisher_agent_status,
                  0 AS is_featured
           FROM properties p
           LEFT JOIN users u ON u.id = p.user_id
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    html {
      width: 100%;
      max-width: 100%;
      overflow-x: clip;
    }
    body {
      width: 100%;
      max-width: 100%;
      overflow-x: clip;
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
    /* ⚠ เดิมตั้ง color:#0B1C33 (น้ำเงินเข้ม) บนพื้น #0B1C33 ที่บรรทัด 231
       ทำให้เมนูทุกตัวกลายเป็นน้ำเงินบนน้ำเงิน อ่านไม่ออก (contrast ratio 1.08)
       และ text-shadow ขาวยิ่งกลบให้จางลง — ต้องเป็นตัวอักษรสว่างบนพื้นเข้ม */
    .dd-navbar:not(.scrolled) .dd-menu-item,
    .dd-navbar:not(.scrolled) .dd-desktop-menu .nav-dropdown-toggle {
      color: #E8EDF4 !important;
      font-weight: 700 !important;
      text-shadow: none !important;
    }
    .dd-navbar:not(.scrolled) .dd-menu-item:hover,
    .dd-navbar:not(.scrolled) .dd-desktop-menu .nav-dropdown-toggle:hover {
      color: #E3C078 !important;
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
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      padding: 8px 20px;
      border-radius: 9999px;
      font-weight: 800;
      font-size: 14px;
      text-decoration: none;
      box-shadow: 0 4px 14px rgba(197, 160, 89, 0.35);
      transition: all 0.25s cubic-bezier(.22, 1, .36, 1);
      white-space: nowrap;
    }
    .dd-btn-login:hover {
      background: linear-gradient(135deg, #D4AF67 0%, #B58F48 100%) !important;
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(197, 160, 89, 0.45);
    }
    .dd-admin-stats-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border: 1px solid rgba(197, 160, 89, 0.6);
      border-radius: 9999px;
      color: #0B1C33;
      background: #fff;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      white-space: nowrap;
      transition: all 0.2s ease;
    }
    .dd-admin-stats-btn:hover {
      background: #FFFDF9;
      border-color: #C5A059;
    }

    /* ปุ่ม Hamburger Toggle — ขนาด/สไตล์มาจาก includes/mobile_nav.php
       (หน้าแรกใช้ navbar พื้นเข้ม จึงกำหนดเฉพาะสีให้เป็นขาว) */
    .dd-navbar .mobile-hamburger-btn {
      background: none !important;
      border-color: rgba(255,255,255,.45) !important;
      color: #ffffff !important;
    }
    /* ==========================================================================
       2. Mobile Drawer Menu
       ── CSS อยู่ใน includes/mobile_nav.php (render_mobile_nav_assets) ──
       ========================================================================== */
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
      color: #A8843F !important;
    }
    .dd-search-bar {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      width: 100%;
    }
    .dd-search-input-wrap {
      flex: 1 1 180px;
      min-width: 0;
      display: flex;
      align-items: center;
      background: #ffffff;
      border: 1.5px solid #dce2e9;
      border-radius: 10px;
      padding: 0 14px;
      transition: all 0.25s ease;
    }
    .dd-search-input-wrap input { min-width: 0; }
    .dd-search-input-wrap:focus-within {
      border-color: #C5A059 !important;
      box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.18) !important;
    }
    .dd-search-input-wrap input {
      border: none;
      background: transparent;
      padding: 12px 10px;
      width: 100%;
      outline: none;
      font-size: 14.5px;
      font-family: inherit;
    }
    .dd-search-bar select {
      padding: 12px 14px;
      border: 1.5px solid #dce2e9;
      border-radius: 10px;
      background: #ffffff;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      cursor: pointer;
      transition: all 0.25s ease;
    }
    .dd-search-bar select:focus {
      border-color: #C5A059 !important;
      box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.18) !important;
    }
    .dd-search-bar select,
    .dd-btn-search { flex: 0 0 auto; }
    .dd-btn-search {
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      border: none;
      padding: 12px 20px;
      border-radius: 10px;
      font-weight: 800;
      font-size: 14.5px;
      cursor: pointer;
      white-space: nowrap;
      box-shadow: 0 4px 14px rgba(197, 160, 89, 0.35);
      transition: all 0.25s cubic-bezier(.22, 1, .36, 1);
    }
    .dd-btn-search:hover {
      background: linear-gradient(135deg, #D4AF67 0%, #B58F48 100%) !important;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(197, 160, 89, 0.45);
    }
    /* ==========================================================================
       Quick AI Search — แถบค้นหาหลัก + dropdown สิ่งอำนวยความสะดวก
       ========================================================================== */
    .qs-form {
      position: relative;
      flex-wrap: nowrap;
      align-items: stretch;
      gap: 8px;
    }
    .qs-form .qs-input-wrap {
      flex: 1 1 auto;
      min-width: 0;
      padding-right: 6px;
    }
    .qs-search-icon { flex: 0 0 auto; }
    .qs-form input[type="text"] { min-width: 0; }

    /* ปุ่มเปิด dropdown */
    .qs-filter-btn {
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      height: 38px;
      margin-left: 4px;
      padding: 0 12px;
      border: 1px solid #dce2e9;
      border-radius: 8px;
      background: #f8fafc;
      color: #334155;
      font-family: inherit;
      font-size: 12.5px;
      font-weight: 700;
      white-space: nowrap;
      cursor: pointer;
      transition: all .2s ease;
    }
    .qs-filter-btn > svg:first-child { width: 15px; height: 15px; color: #64748b; }
    .qs-filter-btn .qs-caret { width: 14px; height: 14px; color: #94a3b8; transition: transform .24s ease; }
    .qs-filter-btn:hover { border-color: #C5A059; background: #fffdf7; color: #8C6B32; }
    .qs-filter-btn[aria-expanded="true"] {
      border-color: #C5A059;
      background: #fff;
      color: #8C6B32;
      box-shadow: 0 0 0 3px rgba(197,160,89,.15);
    }
    .qs-filter-btn[aria-expanded="true"] .qs-caret { transform: rotate(180deg); }
    .qs-filter-count {
      display: inline-grid;
      place-items: center;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background: #0B1C33;
      color: #F8D888;
      font-size: 10.5px;
      font-weight: 900;
    }

    /* ปุ่มค้นหา AI */
    .qs-submit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      flex: 0 0 auto;
      padding: 12px 18px !important;
      white-space: nowrap;
    }
    .qs-submit svg { width: 15px; height: 15px; }

    /* ── Dropdown panel ── */
    .qs-panel {
      position: absolute;
      top: calc(100% + 10px);
      left: 0;
      right: 0;
      z-index: 60;
      background: #fff;
      border: 1px solid #E6EBF2;
      border-radius: 16px;
      box-shadow: 0 24px 60px rgba(6, 15, 28, .22), 0 2px 8px rgba(6,15,28,.08);
      padding: 16px;
      opacity: 1;
      animation: qsPop .22s cubic-bezier(.22, 1, .36, 1) both;
    }
    .qs-panel[hidden] { display: none; }
    @keyframes qsPop {
      from { opacity: 0; transform: translateY(-6px) scale(.99); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    @media (prefers-reduced-motion: reduce) {
      .qs-panel { animation: none; opacity: 1; }
      .qs-option-box,
      .qs-option-box svg { transition: none; }
    }
    .qs-panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 12px;
    }
    .qs-panel-head strong { font-size: 14px; color: #0B1C33; font-weight: 800; }
    .qs-clear {
      border: 0;
      background: none;
      color: #94a3b8;
      font-family: inherit;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      padding: 4px 6px;
      border-radius: 6px;
      transition: color .2s ease, background .2s ease;
    }
    .qs-clear:hover { color: #B91C1C; background: #FEF2F2; }

    .qs-options {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 8px;
    }
    .qs-option {
      position: relative;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 11px;
      border: 1.5px solid #E6EBF2;
      border-radius: 11px;
      background: #fbfcfe;
      cursor: pointer;
      transition: all .2s ease;
      min-width: 0;
    }
    .qs-option:hover { border-color: rgba(197,160,89,.7); background: #fffdf7; }
    .qs-option input { position: absolute; opacity: 0; width: 0; height: 0; }
    .qs-option-box {
      flex: 0 0 auto;
      display: grid;
      place-items: center;
      width: 19px;
      height: 19px;
      border-radius: 6px;
      border: 1.8px solid #cbd5e1;
      background: #fff;
      transition: all .18s ease;
    }
    .qs-option-box svg {
      width: 12px; height: 12px;
      opacity: 0;
      transform: scale(.5);
      transition: all .18s cubic-bezier(.34,1.56,.64,1);
      color: #fff;
    }
    .qs-option-icon { flex: 0 0 auto; display: grid; place-items: center; width: 17px; height: 17px; color: #64748b; }
    .qs-option-icon svg { width: 17px; height: 17px; }
    .qs-option-text {
      font-size: 12.5px;
      font-weight: 700;
      color: #334155;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .qs-option input:checked ~ .qs-option-box {
      background: #15803D;
      border-color: #15803D;
      box-shadow: 0 2px 8px rgba(21,128,61,.3);
    }
    .qs-option input:checked ~ .qs-option-box svg { opacity: 1; transform: scale(1); }
    .qs-option input:checked ~ .qs-option-icon { color: #15803D; }
    .qs-option:has(input:checked) {
      border-color: #86EFAC;
      background: #F0FDF4;
    }
    .qs-option input:focus-visible ~ .qs-option-box {
      outline: 3px solid rgba(21,128,61,.3);
      outline-offset: 2px;
    }

    .qs-panel-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px solid #F1F5F9;
    }
    .qs-panel-foot span { font-size: 11.5px; color: #94a3b8; line-height: 1.4; }
    .qs-apply {
      flex: 0 0 auto;
      padding: 9px 20px;
      border: 0;
      border-radius: 9px;
      background: #0B1C33;
      color: #fff;
      font-family: inherit;
      font-size: 13px;
      font-weight: 800;
      cursor: pointer;
      transition: all .2s ease;
    }
    .qs-apply:hover { background: #C5A059; color: #0B1C33; transform: translateY(-1px); }

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
      position: relative;
      padding-left: 16px;
    }
    .showcase-title::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 5px;
      height: 70%;
      border-radius: 999px;
      background: linear-gradient(180deg, #C5A059, #A8843F);
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
      color: #A8843F !important;
    }
    .showcase-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 20px;
      width: 100%;
    }
    /* reveal ต้องใช้ transform/opacity เท่านั้น เพื่อให้ hover -6px ยังทำงานได้ */
    .showcase-card.reveal {
      opacity: 0;
      transform: translate3d(0, 22px, 0);
      transition:
        opacity .68s cubic-bezier(.22, 1, .36, 1),
        transform .68s cubic-bezier(.22, 1, .36, 1);
      transition-delay: var(--reveal-delay, 0ms);
    }
    .showcase-card.reveal.is-visible {
      opacity: 1;
      transform: none;
      transition-delay: var(--reveal-delay, 0ms);
    }
    .showcase-card.reveal.is-visible {
      transition:
        opacity .68s cubic-bezier(.22, 1, .36, 1) var(--reveal-delay, 0ms),
        transform .68s cubic-bezier(.22, 1, .36, 1) var(--reveal-delay, 0ms),
        box-shadow .32s cubic-bezier(.22, 1, .36, 1),
        border-color .32s ease;
    }
    .showcase-card.reveal.is-visible:hover {
      transform: translateY(-6px);
    }
    @media (prefers-reduced-motion: reduce) {
      .showcase-card.reveal {
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
      }
    }
    .showcase-card {
      min-width: 0;
      background: #ffffff;
      border: 1px solid #E8ECF2;
      border-radius: 16px;
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      transition: transform 0.32s cubic-bezier(.22, 1, .36, 1), box-shadow 0.32s cubic-bezier(.22, 1, .36, 1), border-color 0.32s ease;
      display: block;
      position: relative;
      box-shadow: 0 4px 16px rgba(11, 28, 51, 0.05);
    }
    .showcase-card:hover {
      transform: translateY(-6px);
      border-color: rgba(197, 160, 89, 0.55);
      box-shadow: 0 16px 36px rgba(11, 28, 51, 0.12), 0 2px 10px rgba(197, 160, 89, 0.15);
    }
    @media (prefers-reduced-motion: reduce) {
      .showcase-card,
      .showcase-card .showcase-img,
      .showcase-more,
      .home-category-item,
      .home-category-icon {
        transition-duration: .01ms !important;
        animation-duration: .01ms !important;
      }
    }

    /* ==========================================================================
       Scroll reveal — fade + slide ขึ้นเบา ๆ เมื่อเลื่อนถึง
       ========================================================================== */
    .reveal {
      opacity: 0;
      transform: translate3d(0, 26px, 0);
      transition:
        opacity .72s cubic-bezier(.22, 1, .36, 1),
        transform .72s cubic-bezier(.22, 1, .36, 1);
      transition-delay: var(--reveal-delay, 0ms);
      will-change: opacity, transform;
    }
    .reveal.is-visible {
      opacity: 1;
      transform: translate3d(0, 0, 0);
    }
    /* หลังเลื่อนครั้งแรก ไม่ต้องใช้ will-change แล้ว (ประหยัด GPU) */
    .reveal.is-done { will-change: auto; }

    /* ย้อนกลับ: ใช้ curve/ระยะเดียวกับตอนเข้า (แค่สั้นกว่าเล็กน้อย) จะได้รู้สึกเป็น animation
       อันเดียวกันที่เล่นถอยหลัง ไม่ใช่คนละเอฟเฟกต์ที่มาทับ
       สำคัญ: ต้องชนะ specificity ของ .reveal.is-visible (0,2,0) จึงเขียน selector คู่ให้เท่ากัน
       และวางไว้ทีหลัง — JS จะถอด is-visible ออกอยู่แล้ว แต่กันเหนียวไว้ */
    .reveal.is-visible.is-hidden-again,
    .reveal.is-hidden-again,
    .showcase-card.reveal.is-hidden-again {
      opacity: 0;
      transform: translate3d(0, 26px, 0);
      transition:
        opacity .55s cubic-bezier(.22, 1, .36, 1),
        transform .55s cubic-bezier(.22, 1, .36, 1);
      transition-delay: 0ms;               /* ย้อนกลับไม่ต้องรอ stagger → ไม่ดูกระตุก */
      will-change: opacity, transform;
    }

    /* Hero ใช้จังหวะเฉพาะ: เนื้อหาหลักโผล่ทันทีแบบนุ่มนวล */
    .reveal-hero {
      opacity: 0;
      transform: translate3d(0, 18px, 0);
      transition:
        opacity .8s cubic-bezier(.22, 1, .36, 1),
        transform .8s cubic-bezier(.22, 1, .36, 1);
      transition-delay: var(--reveal-delay, 0ms);
    }
    .reveal-hero.is-visible {
      opacity: 1;
      transform: translate3d(0, 0, 0);
    }
    /* Hero ย้อนกลับ: ถอยกลับทิศเดิม (ลงล่าง) ให้เหมือนเทปเล่นถอยหลัง */
    .reveal-hero.is-visible.is-hidden-again,
    .reveal-hero.is-hidden-again {
      opacity: 0;
      transform: translate3d(0, 18px, 0);
      transition:
        opacity .55s cubic-bezier(.22, 1, .36, 1),
        transform .55s cubic-bezier(.22, 1, .36, 1);
      transition-delay: 0ms;
    }

    @media (prefers-reduced-motion: reduce) {
      .reveal,
      .reveal-hero {
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
      }
    }

    /* เครื่องมือ 3 การ์ด: เข้ามาทีละใบ */
    .interactive-tool-card.reveal:nth-of-type(1) { --reveal-delay: 0ms; }
    .interactive-tool-card.reveal:nth-of-type(2) { --reveal-delay: 90ms; }
    .interactive-tool-card.reveal:nth-of-type(3) { --reveal-delay: 180ms; }

    /* Hero: บล็อก submbar + หมวดแนะนำ เข้ามาต่อกันเป็นจังหวะ */
    .reveal-hero[data-reveal-step="1"] { --reveal-delay: 60ms; }
    .reveal-hero[data-reveal-step="2"] { --reveal-delay: 140ms; }
    .reveal-hero[data-reveal-step="3"] { --reveal-delay: 220ms; }

    /* กันภาพกระพริบระหว่างรอ observer ทำงานในจอที่แคบ (carousel แนวนอน) */
    @media (max-width: 767px) {
      .featured-nav.reveal { transition-duration: .55s; }
    }

    .showcase-media {
      position: relative;
      aspect-ratio: 16 / 10;
      overflow: hidden;
      background: #0B1C33;
    }
    .showcase-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.55s cubic-bezier(.22, 1, .36, 1);
    }
    .showcase-card:hover .showcase-img {
      transform: scale(1.055);
    }
    .showcase-tag {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      border-radius: 6px;
      background: rgba(11, 28, 51, 0.78);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      white-space: nowrap;
      pointer-events: none;
      letter-spacing: 0.02em;
    }
    .showcase-featured-tag {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      border-radius: 6px;
      background: linear-gradient(135deg, rgba(197, 160, 89, 0.9), rgba(168, 132, 63, 0.9));
      color: #0B1C33;
      font-size: 10px;
      font-weight: 800;
      white-space: nowrap;
      pointer-events: none;
      letter-spacing: 0.02em;
    }
    .showcase-badges-left {
      position: absolute;
      top: 8px;
      left: 8px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 5px;
      max-width: calc(100% - 110px);
      z-index: 4;
      pointer-events: none;
    }
    .showcase-actions-right {
      position: absolute;
      top: 8px;
      right: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
      z-index: 10;
    }
    .showcase-actions-right .sp-fav-card-btn,
    .showcase-fav-btn {
      position: static !important;
      top: auto !important;
      right: auto !important;
      width: 28px !important;
      height: 28px !important;
      border-radius: 50% !important;
      background: rgba(255, 255, 255, 0.85) !important;
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
    .showcase-actions-right .sp-fav-card-btn:hover,
    .showcase-fav-btn:hover {
      transform: scale(1.12) !important;
      background: #fff !important;
    }
    .card-video-pill {
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
      pointer-events: auto;
      margin: 0;
      user-select: none;
    }
    .card-video-pill:hover {
      background: #0B1C33;
      border-color: #F8D888;
      transform: scale(1.08);
      box-shadow: 0 4px 12px rgba(197,160,89,.35);
    }
    .card-video-pill:active {
      transform: scale(0.95);
    }
    .card-video-pill .pill-play-icon {
      color: #F8D888;
    }
    .card-video-pill .pill-sound-icon {
      opacity: .9;
      margin-left: 1px;
    }
    .card-video-pill.sound-active {
      background: #15803d !important;
      border-color: #4ade80 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(21,128,61,.5) !important;
    }
    .card-video-pill.sound-active .pill-play-icon,
    .card-video-pill.sound-active .pill-sound-icon {
      color: #ffffff !important;
    }
    .showcase-body {
      min-height: 92px;
      padding: 14px 16px 16px;
      background: #ffffff;
    }
    .showcase-publisher-row {
      margin-bottom: 8px;
    }
    .showcase-publisher-row .publisher-mini {
      position: static !important;
      left: auto !important;
      bottom: auto !important;
      display: inline-flex !important;
      padding: 3px 10px 3px 4px !important;
      background: #F8FAFC !important;
      border: 1px solid #E2E8F0 !important;
      border-radius: 999px !important;
      box-shadow: none !important;
      max-width: 100% !important;
    }
    .showcase-publisher-row .publisher-mini-avatar {
      width: 22px !important;
      height: 22px !important;
      flex: 0 0 22px !important;
      font-size: 10px !important;
      border-radius: 50% !important;
    }
    .showcase-publisher-row .publisher-mini-name {
      font-size: 11.5px !important;
      color: #334155 !important;
      font-weight: 700 !important;
    }
    .showcase-publisher-row .publisher-mini-loc {
      font-size: 11px !important;
      color: #94a3b8 !important;
    }
    .showcase-card-title {
      min-height: 42px;
      font-size: 14.5px;
      line-height: 1.4;
      font-weight: 700;
      color: #0F172A;
      margin-bottom: 4px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      transition: color 0.2s ease;
    }
    .showcase-card:hover .showcase-card-title {
      color: #A8843F;
    }
    .showcase-card-loc {
      font-size: 12.5px;
      line-height: 1.4;
      color: #64748B;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 6px;
    }
    .showcase-price-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 10px;
      padding-top: 8px;
      border-top: 1px solid #F1F5F9;
    }
    .showcase-price {
      display: block;
      font-size: 17px;
      font-weight: 850;
      color: #A8843F;
      letter-spacing: -0.01em;
    }
    .showcase-stats-wrap .property-card-stats-badge {
      position: static !important;
      bottom: auto !important;
      right: auto !important;
      display: inline-flex !important;
      padding: 3px 8px !important;
      font-size: 10.5px !important;
      background: #F8FAFC !important;
      color: #64748B !important;
      border: 1px solid #E2E8F0 !important;
      border-radius: 999px !important;
      box-shadow: none !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
    }
    .showcase-stats-wrap .property-card-stats-badge svg {
      stroke: #64748B !important;
    }
    .showcase-stats-wrap .stat-badge-views,
    .showcase-stats-wrap .stat-badge-clicks {
      color: #64748B !important;
    }

    /* Flagship Property Reels Buttons & Floating Action Pill (Luxury Theme Match) */
    .reels-flagship-nav-btn {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      padding: 6px 15px !important;
      border-radius: 999px !important;
      background: linear-gradient(135deg, #0B1C33 0%, #172D4D 45%, #C5A059 100%) !important;
      color: #ffffff !important;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      font-size: 13px !important;
      font-weight: 800 !important;
      letter-spacing: 0.02em !important;
      text-decoration: none !important;
      box-shadow: 0 4px 18px rgba(197, 160, 89, 0.4), 0 2px 6px rgba(11, 28, 51, 0.5) !important;
      border: 1.5px solid rgba(248, 216, 136, 0.75) !important;
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
      position: relative !important;
      overflow: hidden !important;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6) !important;
      animation: reelsGoldPulse 2.4s infinite ease-in-out !important;
    }
    .reels-flagship-nav-btn:hover {
      transform: translateY(-2px) scale(1.06) !important;
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      text-shadow: none !important;
      box-shadow: 0 6px 24px rgba(197, 160, 89, 0.6) !important;
      border-color: #ffffff !important;
    }
    .reels-flagship-nav-btn:active {
      transform: scale(0.96) !important;
    }
    .reels-flagship-nav-btn .flame-icon {
      font-size: 14px;
      display: inline-block;
      animation: flameWiggle 1.6s infinite ease-in-out;
    }
    @keyframes reelsGoldPulse {
      0%, 100% {
        box-shadow: 0 4px 18px rgba(197, 160, 89, 0.4), 0 2px 6px rgba(11, 28, 51, 0.5);
      }
      50% {
        box-shadow: 0 4px 26px rgba(197, 160, 89, 0.75), 0 0 14px rgba(248, 216, 136, 0.6);
      }
    }
    @keyframes flameWiggle {
      0%, 100% { transform: rotate(0deg) scale(1); }
      25% { transform: rotate(-8deg) scale(1.15); }
      75% { transform: rotate(8deg) scale(1.15); }
    }

    /* Floating Reels Action Pill on Mobile & Tablet (Cohesive Luxury Design & Perfect Placement) */
    .reels-floating-fab {
      position: fixed;
      bottom: 24px;
      right: 20px;
      z-index: 1000;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      border-radius: 999px;
      background: linear-gradient(135deg, #0B1C33 0%, #182E4B 50%, #8C6B32 85%, #C5A059 100%);
      color: #ffffff !important;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      font-size: 13.5px;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-decoration: none !important;
      border: 1.5px solid rgba(248, 216, 136, 0.85);
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.55), 0 4px 16px rgba(197, 160, 89, 0.45);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
      animation: fabFloat 3s infinite ease-in-out;
      cursor: pointer;
    }
    .reels-floating-fab:hover {
      transform: translateY(-4px) scale(1.06);
      border-color: #ffffff;
      box-shadow: 0 12px 32px rgba(197, 160, 89, 0.65), 0 4px 18px rgba(11, 28, 51, 0.6);
      color: #ffffff !important;
    }
    .reels-floating-fab:active {
      transform: scale(0.95);
    }
    .reels-floating-fab .fab-flame {
      font-size: 16px;
      display: inline-block;
      animation: flameWiggle 1.4s infinite ease-in-out;
    }
    .reels-floating-fab .fab-tag {
      font-size: 10px;
      font-weight: 900;
      padding: 2.5px 8px;
      border-radius: 999px;
      background: rgba(248, 216, 136, 0.22);
      border: 1px solid rgba(248, 216, 136, 0.6);
      color: #F8D888;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    @keyframes fabFloat {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-5px);
      }
    }

    /* Coordinated Position for Scroll-To-Top button (Sits neatly above the FAB, never overlapping) */
    .to-top {
      bottom: 84px !important;
      right: 22px !important;
      width: 40px !important;
      height: 40px !important;
      font-size: 16px !important;
      border: 1px solid rgba(197, 160, 89, 0.35) !important;
      box-shadow: 0 4px 16px rgba(11, 28, 51, 0.25) !important;
      z-index: 999 !important;
    }

    /* Override nav scrolled state so reels button ALWAYS stays vibrant */
    .dd-navbar:not(.scrolled) .reels-flagship-nav-btn,
    .dd-navbar.scrolled .reels-flagship-nav-btn {
      color: #ffffff !important;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6) !important;
    }

    /* Video on card hover styles */
    .showcase-media.has-hover-video {
      cursor: pointer;
    }
    .showcase-hover-video {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      z-index: 2;
      opacity: 1; /* Always directly visible for video cards like Kasa Reels */
      transition: opacity .25s ease;
      background: #0B1C33;
    }
    .showcase-media.video-playing .showcase-hover-video {
      opacity: 1;
    }

    /* ==========================================================================
       5. Media Queries: รองรับทุกขนาดหน้าจอ (Desktop, Laptop, Tablet, Mobile)
       ========================================================================== */
    @media (max-width: 1280px) {
      .dd-desktop-menu {
        gap: 12px !important;
        margin-left: 10px !important;
      }
      .dd-nav-left, .dd-nav-right {
        gap: 10px !important;
      }
      .dd-menu-item {
        font-size: 13px !important;
      }
      .dd-icon-btn, .dd-btn-login {
        padding: 6px 12px !important;
        font-size: 12.5px !important;
      }
      .reels-flagship-nav-btn {
        padding: 5px 11px !important;
        font-size: 12px !important;
      }
    }

    @media (min-width: 993px) and (max-width: 1100px) {
      .dd-navbar .dd-nav-left {
        gap: 8px;
      }
      .dd-navbar .dd-logo,
      .dd-navbar .kasa-logo,
      .dd-navbar .sp-logo {
        max-width: 170px;
      }
      .dd-navbar .kasa-logo-img,
      .dd-navbar .sp-logo .kasa-logo-img {
        height: 44px;
        max-width: 170px;
      }
      .dd-navbar .dd-desktop-menu {
        gap: 8px !important;
        margin-left: 6px !important;
      }
      .dd-navbar .dd-desktop-menu .dd-menu-item,
      .dd-navbar .dd-desktop-menu .nav-dropdown-toggle {
        font-size: 12px !important;
      }
      .dd-navbar .dd-desktop-menu .reels-flagship-nav-btn {
        padding: 5px 8px !important;
        font-size: 11px !important;
      }
      .dd-navbar .dd-nav-right {
        gap: 6px;
      }
      .dd-navbar .dd-nav-right .dd-icon-btn,
      .dd-navbar .dd-nav-right .dd-btn-login {
        padding: 6px 8px !important;
        font-size: 11px !important;
      }
      .dd-navbar .lang-toggle {
        margin-left: 0 !important;
        padding: 5px 8px;
        font-size: 11px;
      }
    }

    @media (min-width: 993px) and (max-width: 1100px) {
      .dd-desktop-menu .dd-menu-item[href="favorites.php"],
      .dd-desktop-menu .dd-menu-item[href="notifications.php"],
      .dd-desktop-menu .dd-menu-item[href="advertise.php"] {
        display: none !important;
      }
    }

    /* ลดรายการเมนูลงเป็นขั้น ๆ ตามความกว้าง — เดิมเริ่มที่ 1120px ซึ่งสายเกินไป
       ทำให้จอ 1200-1400px มีของล้นออกนอกจอ (ปุ่ม 🌐 EN ไปโผล่ที่ x=1462 บนจอ 1366) */
    @media (max-width: 1400px) {
      .dd-desktop-menu .dd-menu-item[href="advertise.php"] { display: none !important; }
    }
    @media (max-width: 1300px) {
      .dd-desktop-menu .dd-menu-item[href="favorites.php"],
      .dd-desktop-menu .dd-menu-item[href="notifications.php"] { display: none !important; }
      .dd-nav-right .dd-icon-btn[href="submit.php"] { display: none !important; }
    }
    @media (max-width: 1200px) {
      .dd-desktop-menu .dd-menu-item[href="project.php"] { display: none !important; }
    }
    @media (max-width: 1120px) {
      .dd-desktop-menu .dd-menu-item[href="advertise.php"] {
        display: none !important;
      }
      .dd-nav-right .dd-icon-btn[href="submit.php"] {
        display: none !important;
      }
    }

    @media (min-width: 993px) and (max-width: 1020px) {
      .dd-navbar .dd-nav-right .dd-icon-btn[href="submit-reel.php"] {
        display: none !important;
      }
      .dd-navbar .dd-nav-right .lang-toggle {
        display: none !important;
      }
    }

    @media (max-width: 992px) {
      .dd-desktop-menu, .dd-nav-right {
        display: none !important;
      }
      .mobile-hamburger-btn {
        display: inline-flex !important;
        flex: 0 0 auto;
      }
      .dd-navbar {
        position: fixed !important;
        min-height: 62px;
        left: 0;
        right: 0;
        padding: 8px 16px;
        background: rgba(11, 28, 51, 0.96) !important;
        border-bottom: 1px solid rgba(197, 160, 89, 0.28);
        box-shadow: 0 5px 18px rgba(6, 15, 28, 0.22);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
      }
      .dd-navbar:not(.scrolled) .dd-logo,
      .dd-navbar:not(.scrolled) .mobile-hamburger-btn {
        color: #ffffff !important;
      }
      .dd-navbar:not(.scrolled) .brand-mark {
        color: #0B1C33 !important;
      }
      .dd-hero {
        padding: calc(96px + env(safe-area-inset-top, 0px)) 16px 36px;
        min-height: auto;
      }
      .dd-hero-media-wrap,
      .dd-hero-media,
      .dd-hero-overlay {
        max-width: none !important;
      }
      .dd-hero-heading-wrap {
        width: 100%;
        max-width: 680px;
        padding: 0 4px;
      }
      .mobile-drawer {
        width: min(310px, 85vw);
        max-width: 85vw;
        height: 100vh;
        height: 100dvh;
        padding-bottom: env(safe-area-inset-bottom, 0px);
      }
      .mobile-drawer-overlay {
        width: 100%;
        height: 100vh;
        height: 100dvh;
      }
      .mobile-drawer {
        right: 0;
        transform: translateX(110%);
      }
      .mobile-drawer.active {
        transform: translateX(0);
      }
      .dd-hero {
        overflow-x: clip;
      }
      .home-category-icons {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-x: contain;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 10px;
        width: calc(100% + 32px);
        max-width: calc(100% + 32px);
        margin: 14px -16px 0;
        padding: 4px 12px 10px;
      }
      .home-category-icons::-webkit-scrollbar {
        display: none;
      }
      .home-category-item {
        flex: 0 0 76px;
        width: 76px;
        min-width: 0;
        scroll-snap-align: start;
        padding: 10px 4px 8px;
      }
      .home-category-label {
        white-space: normal;
        overflow-wrap: anywhere;
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
      /* Quick AI Search บนมือถือ: ช่องพิมพ์เต็มแถว แล้วปุ่มขึ้นบรรทัดใหม่ */
      .qs-form {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .qs-form .qs-input-wrap {
        width: 100% !important;
        min-width: 0 !important;
        padding-right: 6px;
      }
      .qs-filter-btn {
        height: 36px;
        padding: 0 10px;
        font-size: 12px;
      }
      .qs-filter-btn .qs-filter-label {
        display: none;
      }
      .qs-submit {
        width: 100% !important;
        min-width: 0 !important;
      }
      .qs-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .qs-panel {
        top: calc(100% + 8px);
        padding: 14px;
        border-radius: 14px;
        max-height: 62vh;
        overflow-y: auto;
      }
      .qs-panel-foot {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .qs-apply { width: 100%; }
      .featured-nav {
        grid-template-columns: none;
        grid-auto-flow: column;
        grid-auto-columns: minmax(0, 74%);
        gap: 12px;
        overflow-x: auto;
        overscroll-behavior-x: contain;
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
        grid-auto-columns: minmax(0, 78%);
        gap: 12px;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        padding: 2px 2px 14px;
        scroll-snap-type: x mandatory;
        scrollbar-width: thin;
      }
      .showcase-card {
        min-width: 0;
        scroll-snap-align: start;
      }
      .interactive-tools-grid {
        grid-template-columns: minmax(0, 1fr) !important;
      }
      .interactive-tool-card {
        min-width: 0;
        padding: 20px 16px !important;
        border-radius: 18px !important;
      }
      .interactive-tool-card strong,
      .interactive-tool-card small,
      .interactive-tool-card label {
        overflow-wrap: anywhere;
      }
    }

    @media (max-width: 480px) {
      .dd-navbar {
        padding-right: max(14px, env(safe-area-inset-right, 0px));
        padding-left: max(14px, env(safe-area-inset-left, 0px));
      }
      .home-category-icons {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-x: contain;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 10px;
        width: calc(100% + 32px);
        max-width: calc(100% + 32px);
        margin: 14px -16px 0;
        padding: 4px 12px 10px;
      }
      .home-category-icons::-webkit-scrollbar {
        display: none;
      }
      .home-category-item {
        flex: 0 0 76px;
        width: 76px;
        min-width: 0;
        scroll-snap-align: start;
        padding: 10px 4px 8px;
      }
      .home-category-icon {
        width: 48px;
        height: 48px;
      }
      .home-category-label {
        white-space: normal;
        overflow-wrap: anywhere;
      }
      .dd-hero {
        padding-top: calc(96px + env(safe-area-inset-top, 0px));
        min-height: auto;
      }
      .dd-hero h1 {
        font-size: 23px !important;
        line-height: 1.35;
      }
      .dd-hero p {
        font-size: 13px !important;
        line-height: 1.6;
      }
      .showcase-grid {
        grid-auto-columns: minmax(0, 86%);
      }
      .featured-nav {
        grid-auto-columns: minmax(0, 82%);
      }
    }
  </style>
  <link rel="stylesheet" href="css/home.css?v=<?= e((string) filemtime(__DIR__ . '/css/home.css')) ?>">
  <?php /* ต้องอยู่หลัง <style> ของหน้านี้ เพื่อให้กฏ drawer/hamburger ชนะ CSS เฉพาะของหน้าแรก */ ?>
  <?php render_mobile_nav_assets(); ?>
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
      <a href="reels.php" class="reels-flagship-nav-btn" title="<?= e(t('ดูคลิปสั้นพาทัวร์บ้าน & คอนโด สไตล์ Reels', 'Watch Short-form Property Video Tours')) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block; vertical-align:-2px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
        <span><?= e(t('Property Reels', 'Property Reels')) ?></span>
      </a>
      <a href="ai-search.php" class="dd-menu-item" title="<?= e(t('ค้นหาด้วยภาษาธรรมชาติ', 'Natural language search')) ?>"><?= e(t('ค้นหาด้วย AI', 'AI Search')) ?></a>
      <a href="project.php" class="dd-menu-item"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="favorites.php" class="dd-menu-item" title="<?= e(t('ทรัพย์ที่บันทึกไว้', 'Saved Properties')) ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="display:inline-block; vertical-align:-1px; color:#ef4444;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        <span><?= e(t('ที่ชอบ', 'Favorites')) ?></span>
        <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:999px; margin-left:2px;"></span>
      </a>
      <a href="notifications.php" class="dd-menu-item" title="<?= e(t('การแจ้งเตือน', 'Notifications')) ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block; vertical-align:-1px; color:#C5A059;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span><?= e(t('แจ้งเตือน', 'Alerts')) ?></span>
        <span class="sp-notif-badge" style="display:none; background:#ef4444; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:999px; margin-left:2px;"></span>
      </a>
      <a href="advertise.php" class="dd-menu-item"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <a href="submit.php" class="dd-menu-item"><?= e(t('ลงประกาศกับเรา', 'List with us')) ?></a>
    </nav>
  </div>

  <!-- ปุ่มขวา Desktop -->
  <div class="dd-nav-right">
    <a href="submit-reel.php" class="dd-icon-btn" style="background:linear-gradient(135deg,rgba(248,216,136,0.18),rgba(197,160,89,0.12)); border:1.5px solid rgba(248,216,136,0.7); color:#F8D888; font-weight:850; box-shadow:0 2px 10px rgba(197,160,89,0.25);" title="<?= e(t('ลงคลิป Reels ด่วนใน 30 วินาที', 'Post quick reel')) ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block; vertical-align:-1px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
      <span><?= e(t('ลงคลิป Reels', 'Post Reel')) ?></span>
    </a>
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
  <?php /* ปุ่ม hamburger + drawer มาจาก includes/mobile_nav.php */ ?>
  <?php render_mobile_nav(); ?>
</header>

<!-- Hero Section & Search -->
<section class="dd-hero">
  <!-- Dynamic Hero Media Layer (Auto-scaled for desktop & mobile) -->
  <div class="dd-hero-media-wrap" aria-hidden="true">
    <?php if ($heroBg['type'] === 'video'): ?>
      <?php
      /* วิดีโอ hero เป็น 4K ~75 MB — เดิม preload="auto" ทำให้เบราว์เซอร์โหลดทันที
         แล้ว abort/restart ซ้ำ จนหน้าโหลดเกิน 400 MB ต่อครั้ง
         แก้: preload="none" + poster เป็นรูปเล็ก + เล่นเมื่อ idle
         (ไม่ใช้ไฟล์ 1.5 MB เป็น poster เพราะกินเวลาโหลดมากกว่าที่ประหยัดได้) */
      $heroPoster = '';
      $posterCandidates = [
          'images/hero-poster.jpg',
          'images/hero-poster.webp',
          'uploads/hero/hero-20261103-111224-24d430b8.jpg', // 50 KB ภาพนิ่งใช้แทนได้
          'images/hero/hero-20261103-111224-24d430b8.jpg',
      ];
      foreach ($posterCandidates as $candidate) {
          if (is_file(__DIR__ . '/' . $candidate)) {
              $heroPoster = $candidate;
              break;
          }
      }
      ?>
      <video class="dd-hero-media js-hero-video" muted loop playsinline preload="none"
             <?php if ($heroPoster !== ''): ?>poster="<?= e($heroPoster) ?>"<?php endif; ?>
             data-src="<?= e($heroBg['url']) ?>"
             aria-hidden="true" tabindex="-1"></video>
    <?php else: ?>
      <img class="dd-hero-media" src="<?= e($heroBg['url']) ?>" alt="PropertyGO Hero Background" fetchpriority="high" decoding="async">
    <?php endif; ?>
    <div class="dd-hero-overlay" style="background: rgba(11, 28, 51, <?= (float)($heroBg['opacity'] ?? 0.45) ?>);"></div>
  </div>

  <div class="dd-hero-heading-wrap reveal-hero" style="text-align: center; color: #fff; margin-bottom: 8px;">
    <span class="hero-eyebrow"><?= e(t('ค้นหาบ้านในแบบที่เป็นคุณ', 'Find a home that feels like you')) ?></span>
    <h1><?= e(t('บ้านที่ใช่ เริ่มต้นจากทำเลที่ชอบ', 'The right home starts with the right location')) ?></h1>
    <p><?= e(t('ค้นหาบ้าน คอนโด และอสังหาริมทรัพย์ทั่วไทย พร้อมติดต่อเจ้าของได้โดยตรง', 'Search houses, condos and properties across Thailand — contact owners directly.')) ?></p>
  </div>

  <div class="dd-search-container reveal-hero" style="--reveal-delay: 120ms;">
    <div class="dd-tabs">
      <button type="button" class="dd-tab active" aria-pressed="true" onclick="setTypeFilter('', this)"><?= e(t('ทั้งหมด', 'All')) ?></button>
      <button type="button" class="dd-tab" aria-pressed="false" onclick="setTypeFilter('sale', this)"><?= e(t('ซื้อ', 'Buy')) ?></button>
      <button type="button" class="dd-tab" aria-pressed="false" onclick="setTypeFilter('rent', this)"><?= e(t('เช่า', 'Rent')) ?></button>
    </div>

    <form method="get" action="ai-search.php" class="dd-search-bar qs-form" id="quickAiForm">
      <input type="hidden" name="type" id="formTypeInput" value="">

      <div class="dd-search-input-wrap qs-input-wrap">
        <svg class="qs-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="q" id="quickAiInput" autocomplete="off" placeholder="<?= e(t('ค้นหาทำเล เช่น สุขุมวิท, ภูเก็ต, ลาดพร้าว', 'Search a location, e.g. Sukhumvit, Phuket, Lat Phrao')) ?>" value="<?= e($_GET['q'] ?? '') ?>">

        <button type="button" class="qs-filter-btn" id="qsFilterBtn" aria-expanded="false" aria-controls="qsFilterPanel" title="<?= e(t('กรองสิ่งอำนวยความสะดวก', 'Filter by amenities')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>
          <span class="qs-filter-label"><?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?></span>
          <span class="qs-filter-count" id="qsFilterCount" hidden>0</span>
          <svg class="qs-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
      </div>

      <select name="category" id="qsCategory" class="qs-cat" aria-label="<?= e(t('ประเภทอสังหาฯ', 'Property type')) ?>">
        <option value=""><?= e(t('ทุกประเภทอสังหาฯ', 'All property types')) ?></option>
        <?php foreach (PROPERTY_CATEGORIES as $key => $label): if ($key === 'other') continue; ?>
          <option value="<?= e($key) ?>"><?= e(category_label_bi($key)) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="dd-btn-search qs-submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.2 13.6 8l4.8 1.6-4.8 1.6L12 16l-1.6-4.8L5.6 9.6 10.4 8Z"></path></svg>
        <span><?= e(t('ค้นหาด้วย AI', 'AI Search')) ?></span>
      </button>

      <div class="qs-panel" id="qsFilterPanel" hidden>
        <div class="qs-panel-head">
          <strong><?= e(t('เลือกสิ่งที่ต้องมี', 'What must it have?')) ?></strong>
          <button type="button" class="qs-clear" id="qsClear"><?= e(t('ล้างทั้งหมด', 'Clear all')) ?></button>
        </div>
        <div class="qs-options" role="group" aria-label="<?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?>">
          <?php
          $quickAmenities = [
              'park'      => [t('ที่จอดรถ', 'Parking'), '<path d="M5 17h14"/><path d="M6.5 17 5 12.5 7 7h10l2 5.5L17.5 17"/><circle cx="7.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>'],
              'fitness'   => [t('ฟิตเนส', 'Fitness'), '<path d="M6.5 6.5v11M17.5 6.5v11M3 9.5v5M21 9.5v5M6.5 12h11"/>'],
              'pool'      => [t('สระว่ายน้ำ', 'Pool'), '<path d="M2 16c1.5 0 1.5 1.5 3 1.5s1.5-1.5 3-1.5 1.5 1.5 3 1.5S14.5 16 16 16s1.5 1.5 3 1.5S20.5 16 22 16"/><path d="M7 13V5a2 2 0 0 1 4 0"/><path d="M17 13V5"/>'],
              'security'  => [t('รปภ. 24 ชม.', '24h security'), '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>'],
              'furniture' => [t('พร้อมเฟอร์นิเจอร์', 'Furnished'), '<path d="M4 18v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6"/><path d="M2 18h20M6 10V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/>'],
              'elevator'  => [t('ลิฟต์', 'Elevator'), '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="m9 8 1.5-2L12 8"/><path d="m12 16 1.5 2L15 16"/>'],
              'transit'   => [t('ใกล้รถไฟฟ้า', 'Near transit'), '<rect x="5" y="3" width="14" height="13" rx="3"/><path d="M5 10h14M9 16l-2 4M15 16l2 4"/>'],
              'quiet'     => [t('บรรยากาศเงียบ', 'Quiet area'), '<path d="M3 12h3l3-4 3 8 3-4h6"/>'],
          ];
          foreach ($quickAmenities as $amKey => [$amLabel, $amIcon]): ?>
            <label class="qs-option">
              <input type="checkbox" name="am[]" value="<?= e($amKey) ?>" data-qs-amenity>
              <span class="qs-option-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              </span>
              <span class="qs-option-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?= $amIcon ?></svg></span>
              <span class="qs-option-text"><?= e($amLabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="qs-panel-foot">
          <span><?= e(t('เลือกแล้วกดค้นหา ระบบจะกรองเฉพาะที่ตรงทุกข้อ', 'Pick options, then search — only listings with all of them')) ?></span>
          <button type="button" class="qs-apply" id="qsApply"><?= e(t('ใช้ตัวกรอง', 'Apply')) ?></button>
        </div>
      </div>
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
<nav class="container featured-nav reveal" aria-label="<?= e(t('หมวดอสังหาริมทรัพย์แนะนำ', 'Featured property categories')) ?>">
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
        <span class="featured-nav-placeholder" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:rgba(255,255,255,0.85);"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
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
    <div class="showcase-header reveal">
      <div class="showcase-title-wrap">
        <h2 class="showcase-title"><?= e($sectionTitle) ?></h2>
      </div>
      <a href="search.php?category=<?= e(rawurlencode($featuredSection['category'])) ?>" class="showcase-more"><?= e(t('ดูประกาศทั้งหมด ›', 'View all ›')) ?></a>
    </div>

    <?php if ($featuredSection['listings']): ?>
      <div class="showcase-grid">
        <?php foreach ($featuredSection['listings'] as $listingIndex => $listing): ?>
          <a href="property.php?id=<?= (int)$listing['id'] ?>" class="showcase-card reveal" style="--reveal-delay: <?= (int)$listingIndex * 80 ?>ms;">
            <?php
              $featuredFilename = $featuredImages[(int)$listing['id']] ?? '';
              $featuredImagePath = $featuredFilename ? __DIR__ . '/uploads/' . $featuredFilename : '';
              $featuredImageUrl = $featuredFilename ? 'uploads/' . $featuredFilename : '';
              $featuredImageExists = $featuredFilename !== '' && is_file($featuredImagePath);

              $videoFilename = basename((string)($listing['video_file'] ?? ''));
              $videoPath = $videoFilename !== '' ? __DIR__ . '/uploads/' . $videoFilename : '';
              $videoUrl = ($videoFilename !== '' && is_file($videoPath)) ? 'uploads/' . rawurlencode($videoFilename) : '';
              $hasVideo = $videoUrl !== '';
            ?>
            <div class="showcase-media<?= $hasVideo ? ' has-hover-video' : '' ?>"<?= $hasVideo ? ' data-card-video="' . e($videoUrl) . '"' : '' ?>>
              <?php if ($hasVideo): ?>
                <?php /* เดิม src= + autoplay ทำให้การ์ดทุกใบโหลดวิดีโอ 8 MB อัตโนมัติ
                         (หน้าแรกมีวิดีโอ 3-5 ตัว = 25-40 MB โดยไม่จำเป็น)
                         แก้: preload="none" + ไม่มี src จนกว่าจะ hover/แตะ */ ?>
                <video class="showcase-hover-video" muted loop playsinline preload="none"
                       data-lazy-video="<?= e($videoUrl) ?>"
                       poster="<?= e($featuredImageUrl) ?>" aria-hidden="true" tabindex="-1"></video>
              <?php endif; ?>
              <?php if ($featuredImageExists): ?>
                <img src="<?= e($featuredImageUrl) ?>" class="showcase-img" alt="<?= e($listing['title']) ?>" loading="lazy" decoding="async" width="640" height="441">
              <?php else: ?>
                <div class="showcase-img noimg" aria-hidden="true"></div>
              <?php endif; ?>
              <div class="showcase-badges-left">
                <span class="showcase-tag"><?= e(is_en() ? (property_category_en_label($featuredSection['category']) ?? (PROPERTY_CATEGORIES[$featuredSection['category']] ?? $featuredSection['category'])) : (PROPERTY_CATEGORIES[$featuredSection['category']] ?? $featuredSection['category'])) ?></span>
                <?php if (!empty($listing['is_featured'])): ?><span class="showcase-featured-tag"><?= e(t('แนะนำ', 'Featured')) ?></span><?php endif; ?>
              </div>
              <div class="showcase-actions-right">
                <?php if ($hasVideo): ?>
                  <button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>" title="<?= e(t('วิดีโอคลิป (แตะเปิด/ปิดเสียง)', 'Video (tap to unmute)')) ?>">
                    <svg class="pill-play-icon" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    <span class="pill-text"><?= e(t('วิดีโอ', 'Video')) ?></span>
                    <svg class="pill-sound-icon sound-muted" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('ปิดเสียงอยู่', 'Muted')) ?>"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                    <svg class="pill-sound-icon sound-unmuted" style="display:none;" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('เปิดเสียงแล้ว', 'Playing with sound')) ?>"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                  </button>
                <?php endif; ?>
                <button type="button" class="sp-fav-card-btn showcase-fav-btn" data-fav-toggle data-fav-id="<?= (int)$listing['id'] ?>" aria-label="<?= e(t('บันทึกในรายการที่ชอบ', 'Save to favorites')) ?>">
                  <span class="fav-heart-icon">
                    <svg class="heart-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                  </span>
                </button>
              </div>
            </div>
            <div class="showcase-body">
              <div class="showcase-publisher-row">
                <?php render_publisher_mini($listing); ?>
              </div>
              <div class="showcase-card-title"><?= e($listing['title']) ?></div>
              <div class="showcase-card-loc"><?= e($listing['location'] ?: '-') ?></div>
              
              <div class="showcase-price-row">
                <div class="showcase-price">
                  <?php
                    require_once __DIR__ . '/includes/price_helpers.php';
                    echo render_price_badge((int)$listing['price'], !empty($listing['original_price']) ? (int)$listing['original_price'] : null, $listing['type']);
                  ?>
                </div>
                <div class="showcase-stats-wrap">
                  <?php
                    echo render_card_stats_badge((int)($listing['views_count'] ?? 0), (int)($listing['clicks_count'] ?? 0));
                  ?>
                </div>
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
    
    <div class="reveal" style="text-align:center; max-width:680px; margin:0 auto 36px;">
      <span style="font-size:11px; font-weight:800; color:#8C6B32; background:rgba(197,160,89,.14); padding:4px 14px; border-radius:999px; letter-spacing:0.12em; text-transform:uppercase;">Interactive Property Suite</span>
      <h2 style="font-size:clamp(22px, 3.2vw, 32px); font-weight:800; color:#0B1C33; margin:10px 0 8px;"><?= e(t('เครื่องมือวิเคราะห์และตัวช่วยประเมินอสังหาฯ', 'Property Analytics & Valuation Tools')) ?></h2>
      <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0;"><?= e(t('คำนวณค่างวดผ่อนบ้าน คอนโด หรือสำรวจผลตอบแทนการลงทุน (Rental Yield) ได้แบบเรียลไทม์ทันที', 'Estimate home or condo monthly installments and explore Rental Yield in real-time.')) ?></p>
    </div>

    <div class="interactive-tools-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap:24px;">
      <!-- แต่ละการ์ด fade เข้าเมื่อเลื่อนถึง (กำหนด delay ใน CSS ด้านล่าง) -->
      
      <!-- Card 1: เครื่องมือคำนวณเงินผ่อนบ้าน-คอนโดแบบเรียลไทม์ -->
      <div class="interactive-tool-card reveal" style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06); position:relative; overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(197,160,89,.15); color:#8C6B32;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="16" y1="14" x2="16" y2="18"></line><path d="M16 10h.01"></path><path d="M12 10h.01"></path><path d="M8 10h.01"></path><path d="M12 14h.01"></path><path d="M8 14h.01"></path><path d="M12 18h.01"></path><path d="M8 18h.01"></path></svg>
            </span>
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
      <div class="interactive-tool-card reveal" style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(21,128,61,.12); color:#15803d;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            </span>
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

          <div style="font-size:12px; color:#475569; line-height:1.5; background:#fafafa; border-radius:8px; padding:8px 12px; display:flex; align-items:flex-start; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path></svg>
            <div><strong><?= e(t('คำแนะนำนักลงทุน:', 'Investor Tip:')) ?></strong> <?= e(t('Yield ระดับ 5% - 7% ถือเป็นเกณฑ์ที่ดีมากสำหรับคอนโดย่าน CBD และแนวรถไฟฟ้า', 'A 5% - 7% yield is considered excellent for CBD and transit-oriented condos.')) ?></div>
          </div>

          <a href="category.php?page=condo" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px; border-radius:10px; background:#0B1C33; color:#fff; font-weight:700; font-size:13px; text-decoration:none; transition:all .15s;" onmouseover="this.style.background='#C5A059';this.style.color='#0B1C33'" onmouseout="this.style.background='#0B1C33';this.style.color='#fff'">
            <span><?= e(t('ดูคอนโดทำเลน่าลงทุน Yield สูง', 'Explore high-yield investment condos')) ?></span> <span>›</span>
          </a>
        </div>
      </div>

      <!-- Card 3: ควิซด่วนค้นหาบ้าน/คอนโดที่ใช่คุณ (Property Matchmaker) -->
      <div class="interactive-tool-card reveal" style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:28px 24px; box-shadow:0 10px 30px rgba(11,28,51,.06);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(121,85,217,.12); color:#7955d9;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
            </span>
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
              <button type="button" class="quiz-btn active" data-cat="condo" onclick="selectQuizOption(this, 'cat')"><?= e(t('คอนโด', 'Condo')) ?></button>
              <button type="button" class="quiz-btn" data-cat="house" onclick="selectQuizOption(this, 'cat')"><?= e(t('บ้าน', 'House')) ?></button>
              <button type="button" class="quiz-btn" data-cat="townhouse" onclick="selectQuizOption(this, 'cat')"><?= e(t('ทาวน์โฮม', 'Townhouse')) ?></button>
            </div>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px;"><?= e(t('3. สไตล์ทำเลที่ชอบ:', '3. Location style:')) ?></label>
            <select id="quizZoneSelect" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; background:#fff; font-weight:600;">
              <option value="สุขุมวิท"><?= e(t('ติดรถไฟฟ้า BTS/MRT ใจกลางเมือง (สุขุมวิท/อโศก)', 'Near BTS/MRT City Center (Sukhumvit/Asoke)')) ?></option>
              <option value="สาทร"><?= e(t('ย่านธุรกิจและการเงิน (สาทร/สีลม)', 'Business & Financial District (Sathorn/Silom)')) ?></option>
              <option value="อารีย์"><?= e(t('ย่านคาเฟ่ ไลฟ์สไตล์ชิคๆ (อารีย์/พหลโยธิน)', 'Chic Cafe & Lifestyle Hub (Ari/Phaholyothin)')) ?></option>
              <option value="บางนา"><?= e(t('บ้านเดี่ยวบรรยากาศเงียบสงบ (บางนา/ศรีนครินทร์)', 'Quiet Residential Houses (Bangna/Srinakarin)')) ?></option>
              <option value="เชียงใหม่"><?= e(t('ต่างจังหวัดบรรยากาศร่มรื่น (เชียงใหม่)', 'Scenic Northern Living (Chiang Mai)')) ?></option>
              <option value="หัวหิน"><?= e(t('ตากอากาศติดชายหาด (หัวหิน/ชะอำ)', 'Beachfront Getaways (Hua Hin/Cha-am)')) ?></option>
            </select>
          </div>

          <button type="button" onclick="goToMatchedProperties()" style="margin-top:4px; width:100%; padding:12px; border:0; border-radius:10px; background:linear-gradient(135deg, #C5A059, #A8843F); color:#0B1C33; font-weight:800; font-size:14px; cursor:pointer; box-shadow:0 4px 14px rgba(197,160,89,.35); transition:transform .15s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
            <?= e(t('แสดงทรัพย์ที่ตรงกับคุณทันที', 'Show Matching Properties Instantly')) ?>
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
      <span style="display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:rgba(197,160,89,0.14); color:#8C6B32;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
      </span>
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
        <details class="dd-home-faq reveal" style="border:1px solid #e2e8f0; border-radius:14px; background:#fafbfc; padding:14px 18px; transition:background 0.2s;">
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
  // Safety net: ถ้า observer ล้มเหลวหรือ JS error ภายหลัง ให้เนื้อหาไม่ค้างที่ opacity 0
  window.addEventListener('load', () => {
    window.setTimeout(() => {
      document.querySelectorAll('.reveal:not(.is-visible), .reveal-hero:not(.is-visible)').forEach((el) => {
        if (el.classList.contains('is-hidden-again')) return;
        if (document.documentElement.classList.contains('reveal-ready')) return;
        const r = el.getBoundingClientRect();
        if (r.bottom > 0 && r.top < window.innerHeight) {
          el.classList.add('is-visible', 'is-done');
        }
      });
    }, 1200);
  });

  // ── Scroll reveal: fade + slide เมื่อเลื่อนถึง (IntersectionObserver, CPU ต่ำ) ──
  (function initScrollReveal() {
    const items = Array.from(document.querySelectorAll('.reveal, .reveal-hero'));
    if (!items.length) return;

    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // reduced-motion / เบราว์เซอร์เก่า: โชว์ทั้งหมด ไม่ต้องทำแอนิเมชันย้อนกลับ
    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach((el) => el.classList.add('is-visible', 'is-done'));
      return;
    }
    document.documentElement.classList.add('reveal-ready');

    // เนื้อหาที่อยู่ใน viewport ตั้งแต่แรก (หรือกด back กลับมา) ให้โชว์ทันทีแบบไม่หน่วง
    items.forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.bottom > 0 && r.top < window.innerHeight) {
        el.classList.add('is-visible', 'is-done');
      }
    });

    // ── ย้อนไป-ย้อนมาได้ (reversible) ─────────────────────────────────────────
    // ทำไมไม่ใช้ IntersectionObserver:
    //   observer ตัดสินจาก "ตำแหน่ง" แต่ย้อนกลับได้ต้องตัดสินจาก "ทิศทาง" 
    //   พอ element ถูกหุบแล้ว observer จะยิงซ้ำไม่หยุด เพราะจุดตัด (threshold/rootMargin)
    //   อยู่ใกล้กันเกินไป → กระพริบ + callback ค้างคิวตีกัน (คือ flicker ที่เจอ)
    // ฉะนั้นเปลี่ยนมาใช้ rAF ตัวเดียว อ่าน scrollY ต่อเฟรม แล้วคำนวณตำแหน่งจริงของ element
    //   ข้อดี: 1 เฟรมตัดสินใจครั้งเดียว, ลำดับเหตุการณ์แน่นอน, ไม่มี callback ย้อนหลัง
    //   และกำหนด "เส้นตาย" เป็น px ที่ชัดเจน + deadband ตามทิศทาง (hysteresis)
    const vhOf = () => window.innerHeight || document.documentElement.clientHeight;

    // เส้นตัด (คำนวณสดทุกเฟรม):
    //   โผล่ : ขอบบนของ element ลอดขึ้นมาเหนือ "เส้นล่าง" (vh * IN_LINE)
    //   หุบ  : ทำได้เฉพาะเมื่อ element "หลุดจากจอไปแล้วจริง" (ขอบบนจมลงใต้ vh + DEAD)
    //          → ระหว่างที่ยังเห็นอยู่แม้บางส่วน จะไม่หุบเลย จึงไม่มีอาการกระพริบตอนชะลอความเร็ว
    //          → และเพราะเกิดตอนไม่เห็นแล้ว อนิเมชันจบไปก่อนไถกลับมาเจอ = ดูเนียน
    const IN_LINE = 0.96;   // โผล่เมื่อขอบบน element ลอดขึ้นมาในโซนล่าง 4% ของจอ
    const OUT_DEAD = 80;    // ต้องพ้นจอลงไปเกิน 80px ถึงจะหุบ (กันการสลับไปมารัว ๆ)

    const shown = (el) => el.classList.contains('is-visible');

    const revealIn = (el, stagger) => {
      el.style.transitionDelay = stagger ? '' : '0ms';
      el.classList.remove('is-hidden-again');
      el.classList.add('is-visible', 'is-done');
    };

    const revealOut = (el) => {
      el.style.transitionDelay = '0ms';   // ย้อนกลับไม่ต้องรอ stagger
      el.classList.remove('is-visible', 'is-done');
      el.classList.add('is-hidden-again');
    };

    let lastY = window.scrollY || 0;
    let dir = 1;            // 1 = เลื่อนลง, -1 = เลื่อนขึ้น
    let ticking = false;
    let idle = 0;           // เฟรมที่เลื่อนไม่ถึง 2px ติดกัน (ถือว่าหยุด)

    // 1 รอบ = ตัดสินทุก element จากตำแหน่งปัจจุบัน + ทิศทาง
    const pass = () => {
      ticking = false;
      const vh = vhOf();
      const y = window.scrollY || 0;
      const dy = y - lastY;

      if (Math.abs(dy) >= 1) {
        dir = dy > 0 ? 1 : -1;
        idle = 0;
      } else if (idle < 12) {
        idle++;
      }

      const inLine = vh * IN_LINE;
      const outLine = vh + OUT_DEAD;   // ขอบบนของ element ต้องจมลงไปเกินขอบล่างจอ + deadband

      for (let i = 0; i < items.length; i++) {
        const el = items[i];
        // จัดกลุ่ม element ใน "แถว" เดียวกันให้ยิง stagger พร้อมกันดูเป็นระลอก
        const r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) continue;
        const isShown = shown(el);

        if (!isShown) {
          // จะโผล่เมื่อ: ขอบบนยังไม่เลยพ้นจอ (r.bottom > 0) และขอบบนลอดขึ้นมาเหนือเส้นล่าง
          if (r.bottom > 0 && r.top < inLine) {
            // เลื่อนขึ้นแล้วเจอ element ที่เพิ่งมาจากข้างบน (เช่น กระโดดด้วย anchor) → ไม่ต้องหน่วง
            revealIn(el, dir > 0 || idle > 0);
          }
        } else {
          // จะหุบเมื่อ: element หลุดลงไปใต้จอไปแล้วจริง (และผู้ใช้กำลังเลื่อนขึ้น)
          // เงื่อนไขเดียว ไม่ผูกกับทิศทางของ frame ก่อนหน้า → ระหว่าง "ขยับเล็กน้อย" จะไม่สลับสถานะ
          if (r.top > outLine && dir < 0) {
            revealOut(el);
          }
        }
      }

      lastY = y;
    };

    const request = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(pass);
    };

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request, { passive: true });

    // หลังโหลด/กลับมาจาก bfcache: คำนวณใหม่ทันที 1 รอบ
    request();
    window.addEventListener('pageshow', request);
  })();

  function setTypeFilter(val, el) {
    document.getElementById('formTypeInput').value = val;
    document.querySelectorAll('.dd-tab').forEach(tab => {
      tab.classList.remove('active');
      tab.setAttribute('aria-pressed', 'false');
    });
    el.classList.add('active');
    el.setAttribute('aria-pressed', 'true');
  }

  // ── Quick AI Search: dropdown สิ่งอำนวยความสะดวก ──
  (() => {
    const form = document.getElementById('quickAiForm');
    const panel = document.getElementById('qsFilterPanel');
    const toggle = document.getElementById('qsFilterBtn');
    const countBadge = document.getElementById('qsFilterCount');
    const clearBtn = document.getElementById('qsClear');
    const applyBtn = document.getElementById('qsApply');
    if (!form || !panel || !toggle) return;

    const boxes = () => Array.from(form.querySelectorAll('[data-qs-amenity]'));

    const syncCount = () => {
      const n = boxes().filter((b) => b.checked).length;
      if (countBadge) {
        countBadge.textContent = String(n);
        countBadge.hidden = n === 0;
      }
    };

    const open = () => {
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      if (panel.hidden) open(); else close();
    });

    clearBtn?.addEventListener('click', () => {
      boxes().forEach((b) => { b.checked = false; });
      syncCount();
    });

    applyBtn?.addEventListener('click', () => {
      close();
      form.requestSubmit();
    });

    boxes().forEach((b) => b.addEventListener('change', syncCount));
    syncCount();

    // ปิดเมื่อคลิกนอกกรอบ / กด Esc
    document.addEventListener('click', (e) => {
      if (panel.hidden) return;
      if (!panel.contains(e.target) && !toggle.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !panel.hidden) {
        close();
        toggle.focus();
      }
    });

    // ถ้าเคยเลือกไว้แล้วกลับมา ให้คงสถานะที่เลือก (อ่านจาก URL)
    const params = new URLSearchParams(window.location.search);
    const picked = params.getAll('am[]');
    if (picked.length) {
      boxes().forEach((b) => { b.checked = picked.includes(b.value); });
      syncCount();
    }
  })();

  // ระบบเปิด-ปิด Mobile Drawer Menu
  const openDrawerBtn = document.getElementById('openDrawerBtn');
  const closeDrawerBtn = document.getElementById('closeDrawerBtn');
  const drawerOverlay = document.getElementById('drawerOverlay');
  const mobileDrawer = document.getElementById('mobileDrawer');

  function openDrawer() {
    if (!mobileDrawer || !drawerOverlay || !openDrawerBtn) return;
    mobileDrawer.classList.add('active');
    drawerOverlay.classList.add('active');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    openDrawerBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    closeDrawerBtn?.focus();
  }

  function closeDrawer() {
    if (!mobileDrawer || !drawerOverlay) return;
    mobileDrawer.classList.remove('active');
    drawerOverlay.classList.remove('active');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    openDrawerBtn?.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    openDrawerBtn?.focus();
  }

  openDrawerBtn?.addEventListener('click', openDrawer);
  closeDrawerBtn?.addEventListener('click', closeDrawer);
  drawerOverlay?.addEventListener('click', closeDrawer);
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

  // Card Video Hover & Mobile Scroll-into-view Engine (Default Muted to prevent audio clashes)
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || window.matchMedia('(hover: none)').matches;
  const allCardVideos = Array.from(document.querySelectorAll('.showcase-media[data-card-video]'));
  let currentlyPlayingAudioCard = null; // Ensure only ONE card plays audio at any time

  allCardVideos.forEach(mediaWrap => {
    const videoSrc = mediaWrap.getAttribute('data-card-video');
    if (!videoSrc) return;

    let videoEl = mediaWrap.querySelector('video.showcase-hover-video');
    const pill = mediaWrap.querySelector('.card-video-pill');
    const soundMutedIcon = pill?.querySelector('.sound-muted');
    const soundUnmutedIcon = pill?.querySelector('.sound-unmuted');

    const updateSoundUI = (isMuted) => {
      if (pill) {
        pill.classList.toggle('sound-active', !isMuted);
        pill.title = isMuted ? '<?= e(t('วิดีโอคลิป (แตะเปิดเสียง)', 'Video (tap to unmute)')) ?>' : '<?= e(t('กำลังเล่นเสียง (แตะเพื่อปิดเสียง)', 'Playing with sound (tap to mute)')) ?>';
      }
      if (soundMutedIcon) soundMutedIcon.style.display = isMuted ? 'inline-block' : 'none';
      if (soundUnmutedIcon) soundUnmutedIcon.style.display = isMuted ? 'none' : 'inline-block';
    };

    const getOrCreateVideo = () => {
      if (videoEl) return videoEl;
      videoEl = document.createElement('video');
      videoEl.className = 'showcase-hover-video';
      videoEl.src = videoSrc;
      videoEl.loop = true;
      videoEl.playsInline = true;
      videoEl.preload = 'auto';
      // Default: Muted to avoid multiple videos clashing with noise
      videoEl.muted = true;
      videoEl.volume = 0.85;
      mediaWrap.appendChild(videoEl);
      updateSoundUI(true);
      return videoEl;
    };

    // ⚠ ไม่เล่นทันทีตอน init — เดิมบรรทัดนี้ทำให้การ์ดทุกใบโหลดวิดีโอพร้อมกัน
    // ตอนเปิดหน้า (หน้าแรกมีวิดีโอหลายตัว × 8 MB = หลายสิบ MB โดยไม่จำเป็น)
    // ตอนนี้ตั้งค่าไว้เลย แต่จะโหลด src จริงเมื่อ hover/แตะ/เลื่อนเข้าจอเท่านั้น
    if (videoEl) {
      videoEl.muted = true;
      videoEl.volume = 0.85;
      updateSoundUI(true);
    }

    // ติด src ให้วิดีโอ (เรียกครั้งแรกเท่านั้น)
    const ensureVideoSrc = () => {
      const v = getOrCreateVideo();
      if (!v.getAttribute('src') || v.getAttribute('src') === '') {
        const lazySrc = v.getAttribute('data-lazy-video') || videoSrc;
        if (lazySrc) { v.src = lazySrc; }
      }
      return v;
    };

    const startPlay = () => {
      const v = ensureVideoSrc();
      const playPromise = v.play();
      if (playPromise !== undefined) {
        playPromise.then(() => {
          mediaWrap.classList.add('video-playing');
        }).catch(() => {});
      }
    };

    let isUserEngaged = false; // When user clicks pill on desktop, keep sound active

    const stopPlay = (force = false) => {
      // If user unmuted the sound, do not pause on mouse leave
      if (!force && isUserEngaged && !isTouchDevice) {
        return;
      }
      if (videoEl) {
        // When stopping force, mute back
        if (force) {
          videoEl.muted = true;
          updateSoundUI(true);
          isUserEngaged = false;
          if (currentlyPlayingAudioCard === mediaWrap) {
            currentlyPlayingAudioCard = null;
          }
        }
      }
    };

    // Desktop: Trigger visual play on hover / focus (silent)
    const card = mediaWrap.closest('.showcase-card') || mediaWrap;
    card.addEventListener('mouseenter', () => {
      if (!isUserEngaged) {
        startPlay();
      }
    });
    card.addEventListener('mouseleave', () => stopPlay(false));
    card.addEventListener('focusin', () => {
      if (!isUserEngaged) {
        startPlay();
      }
    });
    card.addEventListener('focusout', () => stopPlay(false));

    // Video Pill Tap/Click: Click to play with sound & stays playing (persistent) or click again to close
    if (pill) {
      pill.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const v = getOrCreateVideo();

        // If it's already playing with sound and user is engaged, clicking mutes it
        if (isUserEngaged && !v.muted) {
          v.muted = true;
          isUserEngaged = false;
          if (currentlyPlayingAudioCard === mediaWrap) {
            currentlyPlayingAudioCard = null;
          }
          updateSoundUI(true);
          return;
        }

        // Mute any other card that might be currently playing audio
        if (currentlyPlayingAudioCard && currentlyPlayingAudioCard !== mediaWrap) {
          currentlyPlayingAudioCard._muteAudio?.();
        }

        isUserEngaged = true;
        v.muted = false;
        v.volume = 0.85;
        currentlyPlayingAudioCard = mediaWrap;
        v.play().catch(() => {});
        updateSoundUI(false);
      });
    }

    mediaWrap._startPlay = startPlay;
    mediaWrap._stopPlay = (force = true) => stopPlay(force);
    mediaWrap._muteAudio = () => {
      if (videoEl) {
        videoEl.muted = true;
        updateSoundUI(true);
      }
      isUserEngaged = false;
    };
  });

  // Mobile & iPad: Optimize video playback on scroll (pause off-screen videos to save CPU/battery)
  if ('IntersectionObserver' in window && allCardVideos.length > 0) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const media = entry.target;
        const v = media.querySelector('video.showcase-hover-video');
        if (!v) return;
        if (entry.isIntersecting) {
          v.play().catch(() => {});
        } else {
          v.pause();
        }
      });
    }, {
      threshold: 0.15,
      rootMargin: '50px 0px 50px 0px'
    });

    allCardVideos.forEach(media => observer.observe(media));
  }
</script>
<!-- Floating Reels Action Pill on Mobile & Tablet (Always Visible & Accessible) -->
<a href="reels.php" class="reels-floating-fab" title="<?= e(t('ดูคลิปสั้นพาทัวร์บ้าน & คอนโด สไตล์ Reels', 'Watch Short-form Property Video Tours')) ?>" aria-label="<?= e(t('ดูคลิปสั้นพาทัวร์บ้าน & คอนโด', 'Property Reels')) ?>">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
  <span>Property Reels</span>
  <span class="fab-tag">FEED</span>
</a>

<script src="js/app.js?v=<?= time() ?>"></script>
<script>
/* Hero video: วิดีโอเป็น 4K ~75 MB — ห้ามโหลดระหว่างที่หน้าหลักยังโหลดไม่เสร็จ
   ลำดับ: 1) แสดง poster (50 KB) ทันที  2) รอ load + idle 4s  3) ค่อยต่อวิดีโอ
   - ข้ามการโหลดทันทีถ้าเน็ตช้า (effectiveType 2g/slow-2g) / saveData / reduced-motion
   - หยุดเล่นเมื่อเลื่อนออกจากจอ (ประหยัด CPU/แบต) */
(function () {
  var v = document.querySelector('.js-hero-video');
  if (!v) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var conn = navigator.connection || {};
  var saveData = conn.saveData === true;
  var slowNet = /(^|-)2g$/.test(conn.effectiveType || '');
  if (reduce || saveData || slowNet) return; // ใช้ poster แทน

  var started = false;
  function start() {
    if (started) return;
    started = true;
    var src = v.getAttribute('data-src');
    if (!src) return;
    v.src = src;
    v.load();
    var p = v.play();
    if (p && p.catch) p.catch(function () {});
  }

  function schedule() {
    setTimeout(function () {
      if ('requestIdleCallback' in window) {
        requestIdleCallback(start, { timeout: 3000 });
      } else {
        start();
      }
    }, 4000);
  }

  if (document.readyState === 'complete') {
    schedule();
  } else {
    window.addEventListener('load', schedule, { once: true });
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!started) return;
        if (e.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
        else { v.pause(); }
      });
    }, { threshold: 0.05 }).observe(v);
  }
})();
</script>
</body>
</html>
