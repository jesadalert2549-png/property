<?php
require_once __DIR__ . '/includes/mobile_nav.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
current_lang();

$listingId = max(0, (int)($_GET['listing_id'] ?? 0));
$mode = ($_GET['mode'] ?? '') === 'report' ? 'report' : 'promotion';
$listingTitle = '';
if ($listingId > 0) {
    $statement = db()->prepare("SELECT title FROM properties WHERE id = ? AND approval = 'approved' LIMIT 1");
    $statement->execute([$listingId]);
    $listingTitle = (string)($statement->fetchColumn() ?: '');
    if ($listingTitle === '') $listingId = 0;
}
$pageTitle = $mode === 'report' ? t('แจ้งประกาศที่น่าสงสัย', 'Report a suspicious listing') . ' | ' . SITE_NAME : t('ติดต่อโฆษณาและโปรโมตประกาศ', 'Advertise & promote listings') . ' | ' . SITE_NAME;
$pageDescription = t('ติดต่อทีมงานเพื่อสอบถามตำแหน่งแนะนำและช่วงเวลาโปรโมตประกาศ หรือแจ้งข้อมูลประกาศที่อาจไม่ถูกต้อง', 'Contact the team to enquire about featured positions and promotion windows, or report listings that may be inaccurate.');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php seo_head($pageTitle, $pageDescription, page_url('advertise.php')); ?>
  <?php lang_toggle_head(); ?>
  <style>
    .ad-page{min-height:70vh;padding:38px 18px 70px;background:linear-gradient(145deg,#f7f9fb,#f7f5ff)}.ad-card{max-width:850px;margin:auto;padding:clamp(22px,5vw,44px);border:1px solid #e5eaf0;border-radius:24px;background:white;box-shadow:0 20px 55px rgba(24,50,75,.1)}.ad-kicker{color:#7955d9;font-size:12px;font-weight:900;letter-spacing:.16em}.ad-card h1{margin:8px 0 10px;color:#2b2150;font-size:clamp(28px,5vw,42px);line-height:1.2}.ad-lead{color:#617286;line-height:1.8}.ad-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin:23px 0}.ad-option{display:flex;gap:12px;align-items:flex-start;padding:16px;border:1px solid #e5eaf0;border-radius:16px;background:#fbfcfd}.ad-option input{margin-top:4px;accent-color:#7955d9}.ad-option strong{display:block;color:#2b2150}.ad-option small{display:block;margin-top:4px;color:#64748b;line-height:1.5}.ad-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:22px}.ad-form label{display:grid;gap:7px;color:#334155;font-weight:700}.ad-form label.full{grid-column:1/-1}.ad-form input,.ad-form select,.ad-form textarea{width:100%;min-height:45px;padding:11px 13px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#2b2150;font:inherit}.ad-form textarea{min-height:110px;resize:vertical}.ad-submit{grid-column:1/-1;min-height:48px;border:0;border-radius:999px;background:#7955d9;color:white;font:inherit;font-weight:900;cursor:pointer}.ad-submit:hover{background:#583caa}.ad-footnote{margin:15px 0 0;color:#64748b;font-size:13px;line-height:1.7}.ad-contact{margin-top:22px;padding:16px;border-radius:14px;background:#f6f8fa;color:#526478}.ad-contact a{color:#583caa;font-weight:800}.ad-alert{margin:15px 0;padding:12px 14px;border-radius:12px;background:#fff7ed;color:#9a3412}.ad-nav{display:flex;justify-content:center;gap:16px;margin:18px auto 0;flex-wrap:wrap;color:#526478;font-weight:700}.ad-nav a{text-decoration:none}.ad-nav a:hover{color:#7955d9}@media(max-width:600px){.ad-options,.ad-form{grid-template-columns:1fr}.ad-form label.full,.ad-submit{grid-column:auto}}
  </style>
  <?php render_mobile_nav_assets(); ?>
  <style>
    .ad-page{position:relative;isolation:isolate;display:grid;align-items:center;background:radial-gradient(ellipse at 12% 15%,rgba(53,105,137,.12),transparent 34%),radial-gradient(ellipse at 90% 70%,rgba(114,87,245,.12),transparent 33%),linear-gradient(145deg,#f5f8fb,#f7f5ff)}
    .ad-card{position:relative;border-color:#e6ebf0;border-radius:28px;box-shadow:0 28px 75px rgba(24,50,75,.13)}
    .ad-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid #ffe1ce;border-radius:999px;background:#f7f5ff;letter-spacing:.1em}
    .ad-card h1{margin-top:16px;letter-spacing:-.5px}
    .ad-lead{max-width:690px;font-size:16px}
    .ad-options{gap:15px;margin:27px 0}
    .ad-option{min-height:100px;align-items:center;border-color:#e5eaf0;border-radius:18px;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
    .ad-option:hover{transform:translateY(-2px);border-color:#f2b58e;box-shadow:0 9px 22px rgba(24,50,75,.07)}
    .ad-option:has(input:checked){border-color:#7955d9;background:linear-gradient(145deg,#f7f5ff,#fff);box-shadow:0 0 0 3px rgba(114,87,245,.09)}
    .ad-option input{width:18px;height:18px;flex:none}
    .ad-form{gap:17px;margin-top:26px}
    .ad-form label{gap:8px}
    .ad-form input,.ad-form textarea{border-color:#dbe3ea;border-radius:12px;transition:border-color .18s ease,box-shadow .18s ease}
    .ad-form input:focus,.ad-form textarea:focus{outline:0;border-color:#7955d9;box-shadow:0 0 0 4px rgba(114,87,245,.12)}
    .ad-submit{min-height:54px;border-radius:14px;box-shadow:0 9px 20px rgba(114,87,245,.22);transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
    .ad-submit:hover{transform:translateY(-1px);box-shadow:0 12px 25px rgba(114,87,245,.28)}
    .ad-contact{border:1px solid #e8edf2;background:#f8fafc}
    .ad-nav a{padding:8px 12px;border-radius:999px;background:#f5f7f9}
    .ad-nav a:hover{background:#fff1e8}
    @media(max-width:600px){.ad-page{padding:26px 14px 46px}.ad-card{border-radius:20px}.ad-option{min-height:auto}}
    @media(prefers-reduced-motion:reduce){.ad-option,.ad-submit{transition:none}}
  </style>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?> <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="notifications.php">🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?> <span class="sp-notif-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'Projects')) ?></a>
      <a href="advertise.php" aria-current="page"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
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
<?php render_mobile_nav(); ?>
<main class="ad-page"><article class="ad-card">
  <span class="ad-kicker">STANDARD PROPERTY · PROMOTION</span>
  <?php if ($mode === 'report'): ?>
    <h1><?= e(t('แจ้งประกาศที่น่าสงสัย', 'Report a suspicious listing')) ?></h1>
    <p class="ad-lead"><?= e(t('ช่วยแจ้งข้อมูลที่ดูไม่ถูกต้อง เช่น รูปไม่ตรงกับทรัพย์ รายละเอียดขัดแย้ง หรือสงสัยว่าเป็นประกาศซ้ำ ทีมงานจะตรวจสอบและดำเนินการตามข้อมูลที่ได้รับ', 'Help us flag inaccurate details — photos that don\'t match the property, contradictory descriptions, or suspected duplicates. Our team will review and act on what we receive.')) ?></p>
  <?php else: ?>
    <h1><?= e(t('ติดต่อโฆษณาและโปรโมตประกาศ', 'Advertise & promote listings')) ?></h1>
    <p class="ad-lead"><?= e(t('ต้องการให้ประกาศของคุณขึ้นตำแหน่งแนะนำก่อน หรือวางแผนโปรโมตภายหลัง? ส่งรายละเอียดมาคุยกับทีมงานได้เลย เรายืนยันตำแหน่งและช่วงเวลาก่อนดำเนินการ', 'Want your listing featured first, or planning a promotion later? Send us the details and we\'ll confirm position and timing before proceeding.')) ?></p>
  <?php endif; ?>

  <?php if ($listingId): ?><div class="ad-alert"><?= e(t('อ้างอิงประกาศ ', 'Referring to listing #') . $listingId) ?> · <?= e($listingTitle) ?></div><?php endif; ?>

  <?php if ($mode !== 'report'): ?>
    <div class="ad-options">
      <label class="ad-option"><input type="radio" name="timingChoice" form="advertiseForm" value="<?= e(t('ขึ้นแนะนำก่อน / โดยเร็ว', 'Featured first / as soon as possible')) ?>" checked><span><strong><?= e(t('ขึ้นแนะนำก่อน', 'Featured first')) ?></strong><small><?= e(t('สอบถามตำแหน่งโปรโมตโดยเร็ว ทีมงานยืนยันรายละเอียดก่อน', 'Enquire about a promo slot ASAP — the team confirms details first.')) ?></small></span></label>
      <label class="ad-option"><input type="radio" name="timingChoice" form="advertiseForm" value="<?= e(t('โปรโมตภายหลัง', 'Promote later')) ?>"><span><strong><?= e(t('โปรโมตภายหลัง', 'Promote later')) ?></strong><small><?= e(t('ระบุวันที่หรือช่วงเวลาที่ต้องการไว้ในข้อความ', 'Include your preferred dates or window in the message.')) ?></small></span></label>
    </div>
  <?php endif; ?>

  <form class="ad-form" id="advertiseForm">
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <label><?= e(t('ชื่อผู้ติดต่อ', 'Contact name')) ?> *<input name="name" autocomplete="name" required></label>
    <label><?= e(t('อีเมล', 'Email')) ?> *<input name="email" type="email" autocomplete="email" required></label>
    <label><?= e(t('เบอร์โทรศัพท์', 'Phone')) ?><input name="phone" type="tel" autocomplete="tel"></label>
    <label><?= $mode === 'report' ? e(t('รหัสประกาศที่ต้องการแจ้ง', 'Listing ID to report')) : e(t('รหัสประกาศที่ต้องการโปรโมต', 'Listing ID to promote')) ?><input name="listing_id" type="number" min="1" value="<?= $listingId ?: '' ?>" placeholder="<?= e(t('เช่น 123', 'e.g. 123')) ?>"></label>
    <?php if ($mode === 'report'): ?>
      <label class="full"><?= e(t('รายละเอียดสิ่งที่พบ', 'Details of what you found')) ?> *<textarea name="details" required placeholder="<?= e(t('อธิบายข้อมูลที่น่าสงสัยและเหตุผลที่แจ้ง', 'Describe the suspicious details and your reason for reporting')) ?>"></textarea></label>
    <?php else: ?>
      <label class="full"><?= e(t('ข้อความเพิ่มเติม', 'Additional message')) ?><textarea name="details" placeholder="<?= e(t('ระบุวันที่ต้องการโปรโมต กลุ่มลูกค้า หรือคำถามเพิ่มเติม', 'Specify promotion dates, target audience or further questions')) ?>"></textarea></label>
    <?php endif; ?>
    <button class="ad-submit" type="submit"><?= $mode === 'report' ? e(t('เปิดอีเมลเพื่อส่งรายงาน', 'Open email to send report')) : e(t('เปิดอีเมลเพื่อติดต่อโฆษณา', 'Open email to enquire')) ?></button>
  </form>
  <p class="ad-footnote"><?= e(t('ปุ่มนี้จะเปิดอีเมลฉบับร่างที่กรอกข้อมูลให้ ยังไม่มีการส่งข้อมูลจนกว่าคุณจะกดส่งในแอปอีเมล', 'This button opens a pre-filled email draft. Nothing is sent until you press send in your email app.')) ?></p>
  <div class="ad-contact"><?= e(t('ติดต่อโดยตรง: ', 'Contact directly: ')) ?><a href="mailto:<?= e(CONTACT_EMAIL) ?>?subject=<?= rawurlencode($mode === 'report' ? t('แจ้งประกาศที่น่าสงสัย', 'Report a suspicious listing') : t('สอบถามโปรโมตประกาศ', 'Listing promotion enquiry')) ?>"><?= e(CONTACT_EMAIL) ?></a></div>
  <nav class="ad-nav" aria-label="<?= e(t('ลิงก์ที่เกี่ยวข้อง', 'Related links')) ?>"><a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a><a href="project.php"><?= e(t('ดูโครงการ', 'View projects')) ?></a><a href="submit.php"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a></nav>
</article></main>
<?php render_site_footer(); ?>
<script>
  document.getElementById('advertiseForm').addEventListener('submit', event => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const timing = document.querySelector('input[name="timingChoice"]:checked')?.value || '';
    const mode = form.get('mode') === 'report' ? '<?= e(t('แจ้งประกาศที่น่าสงสัย', 'Report a suspicious listing')) ?>' : '<?= e(t('สอบถามโปรโมตประกาศ', 'Listing promotion enquiry')) ?>';
    const subject = `${mode}${form.get('listing_id') ? ' #' + form.get('listing_id') : ''}`;
    const body = [
      `${'<?= e(t('ชื่อผู้ติดต่อ: ', 'Contact name: ')) ?>'}${form.get('name')}`,
      `${'<?= e(t('อีเมล: ', 'Email: ')) ?>'}${form.get('email')}`,
      `${'<?= e(t('เบอร์โทร: ', 'Phone: ')) ?>'}${form.get('phone') || '-'}`,
      `${'<?= e(t('รหัสประกาศ: ', 'Listing ID: ')) ?>'}${form.get('listing_id') || '-'}`,
      timing ? `${'<?= e(t('ช่วงเวลา: ', 'Timing: ')) ?>'}${timing}` : '',
      '',
      '<?= e(t('รายละเอียด:', 'Details:')) ?>',
      form.get('details') || '-'
    ].filter(Boolean).join('\n');
    window.location.href = `mailto:<?= e(CONTACT_EMAIL) ?>?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  });
</script>
</body>
</html>
