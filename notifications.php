<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();

$userId = (int)($_SESSION['user_id'] ?? 0);
$isLoggedIn = $userId > 0;

$pdo = db();
ensure_notification_tables($pdo);
ensure_smart_property_insights();

// ดึงรายการแจ้งเตือน
$notifications = get_user_notifications($userId, 40);
$unreadCount = unread_notification_count($userId);

$pageTitle = t('กล่องข้อความ & การแจ้งเตือนอสังหาฯ', 'Smart Property Notifications & Inbox') . ' | ' . SITE_NAME;
$pageDesc  = t('ศูนย์รวมข้อความแจ้งเตือนราคาลด ทำเลทอง และข้อมูลอัปเดตอสังหาริมทรัพย์ที่คุณสนใจ', 'Your personalized real estate notifications, price drop alerts, and prime location deals.');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <?php seo_head($pageTitle, $pageDesc, site_url() . '/notifications.php', false); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    .notif-hero {
      background: linear-gradient(135deg, #0B1C33 0%, #1a365d 100%);
      color: #fff;
      padding: 42px 0 36px;
      margin-bottom: 30px;
      border-bottom: 2px solid rgba(197, 160, 89, 0.35);
    }
    .notif-hero h1 {
      margin: 0 0 6px;
      font-size: clamp(22px, 3vw, 28px);
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .notif-hero p {
      margin: 0;
      color: rgba(255, 255, 255, 0.82);
      font-size: 13.5px;
    }

    .notif-list-box {
      max-width: 820px;
      margin: 0 auto 60px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .notif-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 18px 22px;
      display: flex;
      gap: 16px;
      box-shadow: 0 4px 16px rgba(11,28,51,.04);
      transition: all .18s ease;
      position: relative;
    }
    .notif-card:hover {
      border-color: #C5A059;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(11,28,51,.08);
    }
    .notif-card.is-unread {
      background: #fffdfa;
      border-left: 4px solid #C5A059;
    }
    .notif-icon-circle {
      display: grid;
      place-items: center;
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: rgba(197, 160, 89, 0.12);
      font-size: 22px;
      flex-shrink: 0;
    }
    .notif-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .notif-title {
      font-size: 15px;
      font-weight: 800;
      color: #0B1C33;
      margin: 0;
      line-height: 1.35;
    }
    .notif-desc {
      font-size: 13px;
      color: #475569;
      line-height: 1.55;
      margin: 0;
    }
    .notif-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 10px;
      padding-top: 8px;
      border-top: 1px solid #f1f5f9;
      font-size: 12px;
    }
    .notif-time {
      color: #94a3b8;
    }
    .notif-action-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      color: #0B1C33;
      font-weight: 800;
      text-decoration: none;
      padding: 4px 12px;
      border-radius: 999px;
      background: #E5CB96;
      border: 1px solid #C5A059;
      font-size: 12px;
      transition: all .15s;
    }
    .notif-action-btn:hover {
      background: #C5A059;
      color: #fff;
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
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?></a>
      <a href="notifications.php" style="color:#C5A059; font-weight:800;">🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?> <?php if ($unreadCount > 0): ?><span class="sp-notif-badge" style="background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"><?= $unreadCount ?></span><?php endif; ?></a>
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

<div class="notif-hero">
  <div class="container">
    <h1>🔔 <?= e(t('กล่องข้อความ & การแจ้งเตือนอัจฉริยะ', 'Smart Notifications & Insights')) ?></h1>
    <p><?= e(t('อัปเดตประกาศราคาพิเศษ ทำเลทองที่มีสภาพคล่องสูง และข้อมูลที่เป็นประโยชน์สำหรับคุณ', 'Real-time updates on price drops, prime location highlights, and market opportunities.')) ?></p>
  </div>
</div>

<main class="container">
  <div class="notif-list-box">
    
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <strong style="color:#0B1C33; font-size:15px;">
        <?= e(t('ข้อความทั้งหมด (' . count($notifications) . ' รายการ)', 'All Notifications (' . count($notifications) . ')')) ?>
      </strong>
      <?php if ($unreadCount > 0 && $isLoggedIn): ?>
        <button type="button" onclick="markAllNotificationsRead()" style="border:1px solid #cbd5e1; background:#fff; color:#475569; padding:4px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
          ✓ <?= e(t('ทำเครื่องหมายว่าอ่านแล้วทั้งหมด', 'Mark all as read')) ?>
        </button>
      <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
      <div style="text-align:center; padding:50px 20px; background:#fff; border-radius:18px; border:1px solid #e2e8f0;">
        <div style="font-size:40px; margin-bottom:10px;">📭</div>
        <h2 style="font-size:18px; color:#0B1C33; font-weight:800; margin:0 0 6px;"><?= e(t('ไม่มีข้อความแจ้งเตือนใหม่', 'No new notifications')) ?></h2>
        <p style="color:#64748B; font-size:13px; margin:0;"><?= e(t('เมื่อมีประกาศราคาลด หรือโอกาสทำเลทองเด่น ๆ ระบบจะแจ้งเตือนให้คุณทราบที่นี่ทันที', 'We will notify you here when new price drops or prime deals match your interests.')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <?php $isUnread = empty($n['read_status']); ?>
        <article class="notif-card <?= $isUnread ? 'is-unread' : '' ?>" id="notif-<?= (int)$n['id'] ?>">
          <div class="notif-icon-circle"><?= e($n['icon'] ?: '🔔') ?></div>
          <div class="notif-content">
            <h2 class="notif-title"><?= e($n['title']) ?></h2>
            <p class="notif-desc"><?= e($n['message']) ?></p>
            
            <div class="notif-footer">
              <span class="notif-time">🕒 <?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></span>
              <div style="display:flex; gap:8px; align-items:center;">
                <?php if ($isUnread && $isLoggedIn): ?>
                  <button type="button" onclick="markNotificationRead(<?= (int)$n['id'] ?>)" style="background:none; border:none; color:#64748B; font-size:11.5px; cursor:pointer; text-decoration:underline;">
                    <?= e(t('อ่านแล้ว', 'Mark read')) ?>
                  </button>
                <?php endif; ?>
                <?php if (!empty($n['link_url'])): ?>
                  <a href="<?= e($n['link_url']) ?>" class="notif-action-btn" onclick="markNotificationRead(<?= (int)$n['id'] ?>)">
                    <?= e(t('ดูรายละเอียด', 'View Deal')) ?> ↗
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</main>

<script>
  async function markNotificationRead(id) {
    try {
      const resp = await fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_read', notification_id: id })
      });
      const data = await resp.json();
      if (data.ok) {
        const card = document.getElementById('notif-' + id);
        if (card) card.classList.remove('is-unread');
        updateGlobalBadge(data.unread_count);
      }
    } catch (e) {}
  }

  async function markAllNotificationsRead() {
    try {
      const resp = await fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_all_read' })
      });
      const data = await resp.json();
      if (data.ok) {
        document.querySelectorAll('.notif-card').forEach(c => c.classList.remove('is-unread'));
        updateGlobalBadge(0);
      }
    } catch (e) {}
  }

  function updateGlobalBadge(count) {
    document.querySelectorAll('.sp-notif-badge').forEach(b => {
      b.textContent = count > 0 ? count : '';
      b.style.display = count > 0 ? 'inline-flex' : 'none';
    });
  }
</script>

<?php render_site_footer(); ?>
</body>
</html>
