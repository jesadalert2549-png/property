<?php
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

$success = false;
$error = '';

if (request_is_post()) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || $message === '') {
        $error = t('กรุณากรอกชื่อและข้อความให้ครบถ้วน', 'Please enter your name and message.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO visitor_logs (name, email, message, ip_address) VALUES (?, ?, ?, '')");
        $stmt->execute([$name, $email, $message]);
        $success = true;
    }
}

// ดึงรายการผู้เข้าชมล่าสุด 50 รายการ
$visitors = $pdo->query("SELECT name, message, created_at FROM visitor_logs ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('สมุดเยี่ยมชมเว็บไซต์', 'Guestbook')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="../css/style.css?v=<?= e((string) filemtime(__DIR__ . '/../css/style.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .guestbook-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
    .visitor-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 10px; margin-bottom: 12px; }
    .visitor-header { display: flex; justify-content: space-between; font-weight: 600; color: var(--primary); margin-bottom: 6px; }
    .visitor-time { font-size: 12px; color: var(--muted); font-weight: normal; }
  </style>
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('../index.php') ?>
    <nav class="site-nav">
      <a href="../index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <a href="../search.php"><?= e(t('ค้นหา', 'Search')) ?></a>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="guestbook-container">
  <h1 class="section-title"><?= e(t('สมุดเยี่ยมชมเว็บไซต์ (Guestbook)', 'Website Guestbook')) ?></h1>
  <p class="center muted"><?= e(t('ทักทายหรือฝากข้อความไว้ให้เจ้าของบ้านและผู้เยี่ยมชมท่านอื่นได้เห็นกันครับ', 'Say hello or leave a message for the property owners and other visitors.')) ?></p>

  <?php if ($success): ?>
    <div class="alert success"><?= e(t('บันทึกข้อความของคุณเรียบร้อยแล้ว ขอบคุณที่แวะมาเยี่ยมชมครับ!', 'Your message has been saved. Thank you for visiting!')) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="form-box" style="margin-bottom: 30px;">
    <form method="post" class="form-grid">
      <label><?= e(t('ชื่อของคุณ *', 'Your name *')) ?>
        <input type="text" name="name" required placeholder="<?= e(t('เช่น คุณสมชาย', 'e.g. John Doe')) ?>">
      </label>
      <label><?= e(t('อีเมล (ไม่แสดงสาธารณะ)', 'Email (private)')) ?>
        <input type="email" name="email" placeholder="email@example.com">
      </label>
      <label><?= e(t('ข้อความ / ทักทาย *', 'Message / Greeting *')) ?>
        <textarea name="message" rows="3" required placeholder="<?= e(t('เขียนข้อความของคุณที่นี่...', 'Write your message here...')) ?>"></textarea>
      </label>
      <button type="submit" class="btn btn-block">✍️ <?= e(t('ลงชื่อเยี่ยมชม', 'Sign Guestbook')) ?></button>
    </form>
  </div>

  <h2><?= e(t('รายชื่อผู้ที่เคยแวะมาเยี่ยมชมล่าสุด', 'Recent Visitors')) ?></h2>
  <?php if (!$visitors): ?>
    <p class="muted center"><?= e(t('ยังไม่มีผู้ฝากข้อความ เป็นคนแรกที่ทักทายได้เลย!', 'No messages yet. Be the first to leave one!')) ?></p>
  <?php else: ?>
    <div style="margin-top: 16px;">
      <?php foreach ($visitors as $v): ?>
        <div class="visitor-card">
          <div class="visitor-header">
            <span>👤 <?= e($v['name']) ?></span>
            <span class="visitor-time">🕒 <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></span>
          </div>
          <p style="margin: 0; color: #334155;"><?= nl2br(e($v['message'])) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<footer class="site-footer" style="margin-top: 40px;">
  <div class="container">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
      <p style="margin:0;">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> <?= e(t('สงวนลิขสิทธิ์', 'All rights reserved.')) ?></p>
      <div class="seo-footer-version-tag">
        <span class="version-dot" aria-hidden="true"></span>
        <span class="version-text"><?= e(SITE_VERSION) ?></span>
      </div>
    </div>
  </div>
</footer>

</body>
</html>
