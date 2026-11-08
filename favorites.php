<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();

$pdo = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$isLoggedIn = $userId > 0;

$items = [];
$guestIdsParam = trim((string)($_GET['ids'] ?? ''));

if ($isLoggedIn) {
    // ดึงทรัพย์ที่บันทึกไว้ของสมาชิกจากฐานข้อมูล
    $stmt = $pdo->prepare(
        "SELECT p.*,
                (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
         FROM property_favorites f
         JOIN properties p ON p.id = f.property_id
         WHERE f.user_id = ? AND p.approval = 'approved'
         ORDER BY f.id DESC"
    );
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($guestIdsParam !== '') {
    // ผู้เยี่ยมชมที่ส่ง query id มา (จาก LocalStorage)
    $cleanIds = array_filter(array_map('intval', explode(',', $guestIdsParam)), fn($id) => $id > 0);
    if (!empty($cleanIds)) {
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.*,
                    (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
             FROM properties p
             WHERE p.id IN ($placeholders) AND p.approval = 'approved'
             ORDER BY FIELD(p.id, $placeholders)"
        );
        $stmt->execute([...$cleanIds, ...$cleanIds]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = t('ทรัพย์ที่ชอบ & รายการที่บันทึกไว้', 'My Favorite Properties & Saved List') . ' | ' . SITE_NAME;
$pageDesc  = t('รวมรายการบ้าน คอนโด ที่ดิน และอสังหาริมทรัพย์ที่คุณกดใจบันทึกไว้เพื่อกลับมาดูหรือเปรียบเทียบในภายหลัง', 'Your curated list of saved and favorite properties on ' . SITE_NAME);
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <?php seo_head($pageTitle, $pageDesc, site_url() . '/favorites.php', false); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    .fav-hero {
      background: linear-gradient(135deg, #0B1C33 0%, #172D4D 100%);
      color: #fff;
      padding: 44px 0 38px;
      margin-bottom: 32px;
      border-bottom: 2px solid rgba(197, 160, 89, 0.35);
    }
    .fav-hero h1 {
      margin: 0 0 8px;
      font-size: clamp(22px, 3.2vw, 30px);
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .fav-hero p {
      margin: 0;
      color: rgba(255, 255, 255, 0.8);
      font-size: 14px;
    }
    .fav-card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 48px;
    }
    .fav-item-card {
      background: #fff;
      border: 1px solid #dfe3e8;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(11,28,51,.04);
      display: flex;
      flex-direction: column;
      position: relative;
      transition: transform .2s, box-shadow .2s;
    }
    .fav-item-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px rgba(11,28,51,.08);
      border-color: #C5A059;
    }
    .fav-card-media {
      position: relative;
      aspect-ratio: 4 / 3;
      background: #e2e8f0;
      overflow: hidden;
    }
    .fav-card-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .fav-card-heart-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(0, 0, 0, 0.08);
      display: grid;
      place-items: center;
      font-size: 17px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transition: transform 0.2s, background 0.2s;
      z-index: 2;
    }
    .fav-card-heart-btn:hover {
      transform: scale(1.1);
      background: #fff;
    }
    .fav-card-body {
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }
    .fav-card-title {
      font-size: 15px;
      font-weight: 800;
      color: #0B1C33;
      text-decoration: none;
      line-height: 1.35;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .fav-card-title:hover {
      color: #C5A059;
    }
    .fav-card-loc {
      font-size: 12.5px;
      color: #64748B;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .fav-card-price {
      font-size: 18px;
      font-weight: 900;
      color: #A8843F;
      margin-top: auto;
      padding-top: 8px;
      border-top: 1px solid #f1f5f9;
    }
    .fav-empty-box {
      text-align: center;
      padding: 60px 20px;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      max-width: 540px;
      margin: 20px auto 60px;
      box-shadow: 0 8px 24px rgba(11,28,51,.04);
    }
  </style>
</head>
<body style="background:#F9F9FB;">

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php" style="color:#C5A059; font-weight:800;">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?> <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'Projects')) ?></a>
      <?php if ($isLoggedIn): ?>
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

<div class="fav-hero">
  <div class="container">
    <h1>❤️ <?= e(t('ทรัพย์ที่กดใจและบันทึกไว้', 'My Saved & Favorite Properties')) ?></h1>
    <p><?= e(t('รายการประกาศที่คุณบันทึกไว้สำหรับกลับมาดูราคา เปรียบเทียบทำเล หรือติดต่อผู้ขายในภายหลัง', 'Properties you have saved for easy viewing, price comparison, or contacting sellers.')) ?></p>
  </div>
</div>

<main class="container">
  <?php if (!empty($items)): ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <strong style="color:#0B1C33; font-size:15px;"><?= e(t('พบ ' . count($items) . ' รายการที่บันทึกไว้', count($items) . ' saved properties')) ?></strong>
      <a href="search.php" style="color:#64748B; font-size:13px; text-decoration:underline;"><?= e(t('+ ค้นหาทรัพย์อื่นเพิ่มเติม', '+ Browse more properties')) ?></a>
    </div>

    <div class="fav-card-grid" id="favGrid">
      <?php foreach ($items as $item): ?>
        <?php
          $coverFilename = $item['cover'] ?? '';
          $coverPath = $coverFilename ? __DIR__ . '/uploads/' . $coverFilename : '';
          $coverUrl = ($coverFilename && is_file($coverPath)) ? 'uploads/' . rawurlencode($coverFilename) : 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg';
        ?>
        <article class="fav-item-card" id="favCard-<?= (int)$item['id'] ?>">
          <div class="fav-card-media">
            <a href="property.php?id=<?= (int)$item['id'] ?>">
              <img src="<?= e($coverUrl) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async">
            </a>
            <button type="button" class="sp-fav-card-btn fav-card-heart-btn" data-fav-toggle data-fav-id="<?= (int)$item['id'] ?>" data-is-fav="1" aria-label="<?= e(t('ลบออกจากรายการที่ชอบ', 'Remove from favorites')) ?>">
              <span class="fav-heart-icon">
                <svg class="heart-svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
              </span>
            </button>
            <?php
              require_once __DIR__ . '/includes/price_helpers.php';
              echo render_card_stats_badge((int)($item['views_count'] ?? 0), (int)($item['clicks_count'] ?? 0));
            ?>
          </div>
          <div class="fav-card-body">
            <span style="font-size:11px; font-weight:700; color:#8C6B32; background:rgba(197,160,89,.12); padding:2px 8px; border-radius:4px; align-self:flex-start;">
              <?= e(category_label_bi($item['property_category'])) ?> · <?= $item['type'] === 'rent' ? e(t('ให้เช่า', 'For rent')) : e(t('ขาย', 'For sale')) ?>
            </span>
            <a href="property.php?id=<?= (int)$item['id'] ?>" class="fav-card-title"><?= e($item['title']) ?></a>
            <div class="fav-card-loc">⌖ <?= e($item['location'] ?: '-') ?></div>
            <div class="fav-card-price">
              <?php
                require_once __DIR__ . '/includes/price_helpers.php';
                echo render_price_badge((int)$item['price'], !empty($item['original_price']) ? (int)$item['original_price'] : null, $item['type']);
              ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <!-- กล่องว่างเปล่ากรณีที่ยังไม่มีรายการ -->
    <div class="fav-empty-box" id="favEmptyNotice">
      <div style="font-size:44px; margin-bottom:12px;">🤍</div>
      <h2 style="font-size:20px; color:#0B1C33; font-weight:800; margin:0 0 8px;"><?= e(t('ยังไม่มีทรัพย์ที่คุณบันทึกไว้', 'No saved properties yet')) ?></h2>
      <p style="color:#64748B; font-size:13.5px; line-height:1.6; margin:0 0 20px;">
        <?= e(t('เมื่อคุณกำลังดูรายการบ้านหรือคอนโด เพียงกดปุ่มรูปหัวใจ หรือปุ่ม "บันทึก" ประกาศนั้นจะถูกเก็บไว้ที่หน้านี้ให้คุณทันที', 'When browsing homes or condos, click the heart icon or "Save" button to keep listings here.')) ?>
      </p>
      <a href="search.php" class="btn" style="display:inline-flex; align-items:center; gap:8px; padding:10px 24px; border-radius:999px; background:#0B1C33; color:#fff; text-decoration:none; font-weight:700; font-size:14px;">
        ⌕ <?= e(t('เริ่มค้นหาอสังหาริมทรัพย์', 'Start exploring properties')) ?>
      </a>
    </div>
  <?php endif; ?>
</main>

<script>
  // หากยังไม่ได้ล็อกอิน และยังไม่มีพารามิเตอร์ ?ids ใน URL ให้ดึงจาก LocalStorage มารีเฟรช
  document.addEventListener('DOMContentLoaded', () => {
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    if (!isLoggedIn && !window.location.search.includes('ids=')) {
      try {
        const guestData = localStorage.getItem('sp_guest_favorites');
        if (guestData) {
          const ids = JSON.parse(guestData);
          if (Array.isArray(ids) && ids.length > 0) {
            window.location.href = 'favorites.php?ids=' + encodeURIComponent(ids.join(','));
          }
        }
      } catch(e) {}
    }
  });
</script>

<?php render_site_footer(); ?>
</body>
</html>
