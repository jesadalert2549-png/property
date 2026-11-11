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
    // ดึงทรัพย์ที่บันทึกไว้ของสมาชิกจากฐานข้อมูล (ดึงเฉพาะฟิลด์ที่จำเป็นต่อการแสดงผล)
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.type, p.price, p.original_price, p.location, p.property_category, p.views_count, p.clicks_count, p.video_file,
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
            "SELECT p.id, p.title, p.type, p.price, p.original_price, p.location, p.property_category, p.views_count, p.clicks_count, p.video_file,
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
      background: #0B1C33;
      overflow: hidden;
    }
    .fav-card-media .card-video-stream {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      z-index: 2;
      opacity: 1;
      background: #0B1C33;
    }
    .fav-card-media .card-video-pill {
      position: absolute;
      top: 10px;
      left: 10px;
      z-index: 10;
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
      user-select: none;
    }
    .fav-card-media .card-video-pill:hover {
      background: #0B1C33;
      border-color: #F8D888;
      transform: scale(1.08);
      box-shadow: 0 4px 12px rgba(197,160,89,.35);
    }
    .fav-card-media .card-video-pill:active { transform: scale(0.95); }
    .fav-card-media .card-video-pill.sound-active {
      background: #15803d !important;
      border-color: #4ade80 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(21,128,61,.5) !important;
    }
    .fav-card-media .card-video-pill.sound-active svg { color: #ffffff !important; }
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
          $videoFilename = basename((string)($item['video_file'] ?? ''));
          $videoPath = $videoFilename !== '' ? UPLOAD_DIR . $videoFilename : '';
          $videoUrl = ($videoFilename !== '' && is_file($videoPath)) ? 'uploads/' . rawurlencode($videoFilename) : '';
          $hasVideo = $videoUrl !== '';
        ?>
        <article class="fav-item-card" id="favCard-<?= (int)$item['id'] ?>">
          <div class="fav-card-media"<?= $hasVideo ? ' data-card-video="' . e($videoUrl) . '"' : '' ?>>
            <a href="property.php?id=<?= (int)$item['id'] ?>">
              <?php if ($hasVideo): ?>
                <video class="card-video-stream" src="<?= e($videoUrl) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= e($coverUrl) ?>"></video>
              <?php endif; ?>
              <img src="<?= e($coverUrl) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async">
            </a>
            <?php if ($hasVideo): ?>
              <button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>" title="<?= e(t('วิดีโอคลิป (แตะเปิด/ปิดเสียง)', 'Video (tap to unmute)')) ?>">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                <span><?= e(t('วิดีโอ', 'Video')) ?></span>
                <svg class="sound-muted" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('ปิดเสียงอยู่', 'Muted')) ?>"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                <svg class="sound-unmuted" style="display:none;" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" title="<?= e(t('เปิดเสียงแล้ว', 'Playing with sound')) ?>"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
              </button>
            <?php endif; ?>
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

<script>
  // Kasa Reels-style Feed Video Stream & Sound Toggle for Favorites Page
  (() => {
    const cards = Array.from(document.querySelectorAll('.fav-card-media[data-card-video]'));
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
