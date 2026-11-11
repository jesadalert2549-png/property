<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/banners.php';
current_lang();
require_once __DIR__ . '/includes/profile_schema.php';
ensure_profile_schema(db());

$projectId = max(0, (int)($_GET['id'] ?? 0));
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$projects = [];
$project = null;
$images = [];
$units = [];

if ($projectId > 0) {
    $statement = db()->prepare($isAdmin
        ? 'SELECT dp.*, u.id AS publisher_user_id, u.full_name AS publisher_name, u.avatar_path AS publisher_avatar, u.agent_status AS publisher_agent_status FROM development_projects dp LEFT JOIN users u ON u.id = dp.owner_user_id WHERE dp.id = ? LIMIT 1'
        : 'SELECT dp.*, u.id AS publisher_user_id, u.full_name AS publisher_name, u.avatar_path AS publisher_avatar, u.agent_status AS publisher_agent_status FROM development_projects dp LEFT JOIN users u ON u.id = dp.owner_user_id WHERE dp.id = ? AND dp.is_published = 1 LIMIT 1');
    $statement->execute([$projectId]);
    $project = $statement->fetch() ?: null;
    if (!$project) http_response_code(404);
    if ($project) {
        $statement = db()->prepare('SELECT filename FROM project_images WHERE project_id = ? ORDER BY sort_order, id');
        $statement->execute([$projectId]);
        $images = $statement->fetchAll(PDO::FETCH_COLUMN);
        $statement = db()->prepare('SELECT * FROM project_units WHERE project_id = ? ORDER BY sort_order, id');
        $statement->execute([$projectId]);
        $units = $statement->fetchAll();
    }
} else {
    $projects = db()->query("SELECT dp.id, dp.title, dp.category, dp.developer, dp.location, dp.project_status, dp.price_from, dp.unit_count, dp.updated_at,
        u.full_name AS publisher_name, u.avatar_path AS publisher_avatar, u.agent_status AS publisher_agent_status
        FROM development_projects dp LEFT JOIN users u ON u.id = dp.owner_user_id
        WHERE dp.is_published = 1 ORDER BY dp.updated_at DESC, dp.id DESC")->fetchAll();
}
$projectCovers = [];
if ($projects) {
    $projectIds = array_map('intval', array_column($projects, 'id'));
    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $coverStatement = db()->prepare("SELECT project_id, filename FROM project_images WHERE project_id IN ($placeholders) ORDER BY sort_order, id");
    $coverStatement->execute($projectIds);
    foreach ($coverStatement->fetchAll() as $coverRow) {
        $projectCovers[(int)$coverRow['project_id']] ??= $coverRow['filename'];
    }
}

$pageTitle = $project ? $project['title'] . ' | ' . SITE_NAME : t('โครงการอสังหาริมทรัพย์', 'Property Projects') . ' | ' . SITE_NAME;
$pageDescription = $project
    ? mb_substr(trim(strip_tags($project['description'])), 0, 155)
    : t('สำรวจข้อมูลโครงการอสังหาริมทรัพย์ แบบบ้าน ทำเล สิ่งอำนวยความสะดวก และช่องทางสอบถาม', 'Explore property projects: house designs, locations, amenities and enquiry channels.');
$canonical = page_url('project.php' . ($project ? '?id=' . (int)$project['id'] : ''));
$amenities = $project ? array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string)$project['amenities'])))) : [];
$nearby = $project ? array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string)$project['nearby_places'])))) : [];
$safeMapUrl = $project && in_array(strtolower((string)parse_url($project['map_url'], PHP_URL_SCHEME)), ['http', 'https'], true) ? $project['map_url'] : '';
$projectMapQuery = $project ? trim((string)($project['address'] ?? '') . ' ' . (string)($project['location'] ?? '')) : '';
$projectMapQuery = $safeMapUrl !== '' ? $safeMapUrl : $projectMapQuery;
$priceLabel = static function ($value): string {
    return $value !== null && $value !== '' ? number_format((float)$value) . (is_en() ? ' THB' : ' บาท') : '';
};
$projectPublisherName = $project ? (trim((string)($project['publisher_name'] ?? '')) ?: (trim((string)($project['contact_name'] ?? '')) ?: t('ผู้ประกาศโครงการ', 'Project lister'))) : '';
$projectPublisherAvatar = $project ? basename((string)($project['publisher_avatar'] ?? '')) : '';
$projectPublisherAvatarExists = $projectPublisherAvatar !== '' && is_file(__DIR__ . '/uploads/avatars/' . $projectPublisherAvatar);
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head($pageTitle, $pageDescription, $canonical); ?>
  <?php lang_toggle_head(); ?>
  <meta name="theme-color" content="#f7f8fa">
  <style>
    :root{--ink:#0B1C33;--muted:#64748b;--line:#e5eaf0;--brand:#C5A059;--brand-dark:#A8843F;--canvas:#F9F9FB;--white:#fff;--shadow:0 18px 45px rgba(11,28,51,.08)}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--canvas);color:var(--ink);font-family:"Fenice Std","Noto Serif Thai","Prompt","Inter",system-ui,serif}a{color:inherit}.project-header{position:sticky;top:0;z-index:30;background:#0B1C33;border-bottom:1px solid rgba(197,160,89,.22);backdrop-filter:blur(14px)}.project-header-inner{max-width:1240px;height:72px;margin:auto;padding:0 22px;display:flex;align-items:center;justify-content:space-between;gap:20px}.project-brand{text-decoration:none;font-weight:900;display:flex;align-items:center;gap:10px}.project-brand-mark{display:grid;place-items:center;width:39px;height:39px;border-radius:12px;background:#C5A059;color:#0B1C33;font-size:12px}.project-nav{display:flex;align-items:center;gap:20px;font-size:14px;font-weight:600;color:#CBD5E1}.project-nav a{text-decoration:none;color:#CBD5E1;transition:color .2s ease}.project-nav a:hover{color:#C5A059}.project-cta{background:linear-gradient(135deg,#C5A059,#A8843F)!important;color:#0B1C33!important;border-radius:999px;padding:9px 18px;font-weight:800}.project-cta-project{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.08);color:#C5A059!important;border:1px solid rgba(197,160,89,.4);border-radius:999px;padding:9px 18px;font-weight:700;transition:all .18s}.project-cta-project:hover{background:rgba(197,160,89,.15);border-color:#C5A059}.project-cta-project:focus-visible,.project-submit-button:focus-visible,.project-empty-cta:focus-visible{outline:3px solid #C5A059;outline-offset:3px}.project-menu{display:none;border:1px solid rgba(197,160,89,.4);background:#0B1C33;color:#C5A059;border-radius:10px;padding:8px 12px;font-size:20px}.project-main{max-width:1240px;margin:auto;padding:26px 22px 70px}.project-breadcrumb{display:flex;gap:9px;align-items:center;flex-wrap:wrap;color:var(--muted);font-size:13px;margin-bottom:20px}.project-breadcrumb a{text-decoration:none}.project-breadcrumb a:hover{color:var(--brand)}.project-hero{display:grid;grid-template-columns:1.3fr .7fr;gap:28px;align-items:end;margin-bottom:24px}.project-kicker{color:var(--brand);font-weight:900;font-size:12px;letter-spacing:.15em;text-transform:uppercase}.project-hero h1{font-size:clamp(30px,5vw,48px);line-height:1.18;letter-spacing:-.035em;margin:8px 0}.project-hero p{color:var(--muted);margin:0}.project-price{background:white;border:1px solid var(--line);border-radius:20px;padding:18px 22px;box-shadow:var(--shadow)}.project-price small{display:block;color:var(--muted)}.project-price strong{display:block;margin-top:4px;font-size:24px;color:var(--brand)}.project-gallery{display:grid;grid-template-columns:1.35fr .65fr;grid-template-rows:220px 220px;gap:10px;overflow:hidden;border-radius:24px}.project-gallery button{border:0;padding:0;overflow:hidden;background:#e6ebef;cursor:zoom-in}.project-gallery button:first-child{grid-row:span 2}.project-gallery img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}.project-gallery button:hover img{transform:scale(1.035)}.project-no-image{display:grid;place-items:center;height:100%;min-height:220px;background:linear-gradient(135deg,#e7eef3,#f7e4d6);color:#53677b;font-weight:800}.project-badges{display:flex;flex-wrap:wrap;gap:9px;margin:18px 0 28px}.project-badge{padding:8px 12px;background:white;border:1px solid var(--line);border-radius:999px;color:#42566a;font-size:13px;font-weight:700}.project-section{margin-top:28px;padding:clamp(20px,4vw,34px);background:white;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow)}.project-section h2{font-size:23px;line-height:1.3;margin:0 0 18px}.project-description{white-space:pre-line;color:#526478;line-height:1.9;margin:0}.project-info-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.project-info{padding:15px;border-radius:15px;background:var(--canvas);border:1px solid var(--line)}.project-info small{display:block;color:var(--muted);font-size:12px}.project-info strong{display:block;margin-top:6px;font-size:15px;overflow-wrap:anywhere}.project-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.project-card{overflow:hidden;background:white;border:1px solid var(--line);border-radius:22px;text-decoration:none;box-shadow:var(--shadow);transition:transform .2s,border-color .2s}.project-card:hover{transform:translateY(-3px);border-color:#f0b389}.project-card-image{height:220px;position:relative;background:linear-gradient(135deg,#e7eef3,#f7e4d6);overflow:hidden}.project-card-image img{width:100%;height:100%;object-fit:cover}.project-card-body{padding:19px}.project-card-body small{color:var(--brand);font-weight:800}.project-card-body h2{font-size:20px;margin:7px 0}.project-card-body p{margin:0;color:var(--muted);font-size:14px}.project-card-facts{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);margin-top:15px;padding-top:14px;font-size:13px;font-weight:800}.project-feature-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}.project-feature{display:flex;align-items:flex-start;gap:10px;padding:13px;background:#fbfcfd;border:1px solid var(--line);border-radius:14px}.project-feature i{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:9px;background:#fff1e8;color:var(--brand);font-style:normal;font-weight:900}.project-feature span{line-height:1.5}.project-units{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.project-unit{padding:18px;border:1px solid var(--line);border-radius:17px;background:#fff}.project-unit h3{margin:0;font-size:18px}.project-unit p{margin:5px 0;color:var(--muted);font-size:13px}.project-unit-facts{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.project-unit-facts span{padding:6px 9px;border-radius:999px;background:var(--canvas);font-size:12px;font-weight:700}.project-contact{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;background:var(--ink);color:white}.project-contact p{margin:5px 0 0;color:#d9e3ec}.project-contact h2{margin:0}.project-contact-actions{display:flex;gap:10px;flex-wrap:wrap}.project-contact-actions a{display:inline-flex;justify-content:center;align-items:center;min-height:44px;padding:0 17px;border-radius:999px;background:var(--brand);text-decoration:none;font-weight:800;color:white}.project-contact-actions .line{background:#06c755}.project-contact-actions .outline{background:transparent;border:1px solid #ffffff66}.project-empty{grid-column:1/-1;padding:54px 24px;text-align:center;background:white;border:1px dashed #cbd5e1;border-radius:22px;color:var(--muted)}.project-empty strong{display:block;color:var(--ink);font-size:19px;margin-bottom:7px}.project-empty-cta{display:inline-flex;align-items:center;justify-content:center;margin-top:12px;min-height:48px;padding:0 23px;border-radius:999px;background:linear-gradient(135deg,#ed8953,#c96946);color:#fff!important;text-decoration:none;font-weight:900;box-shadow:0 8px 20px rgba(217,92,35,.25);transition:transform .18s,box-shadow .18s}.project-empty-cta:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(217,92,35,.34)}.project-map-link{display:inline-flex;margin-top:14px;color:var(--brand-dark);font-weight:800}.project-lightbox{width:min(94vw,1100px);max-height:90vh;padding:0;border:0;border-radius:16px;background:#111}.project-lightbox::backdrop{background:#261d3bcc}.project-lightbox img{display:block;max-width:100%;max-height:85vh;margin:auto;object-fit:contain}.project-lightbox button{position:absolute;right:10px;top:10px;width:40px;height:40px;border:0;border-radius:50%;font-size:23px;cursor:pointer}.project-footer{background:#34274e;color:#d8e2eb;padding:30px 20px;text-align:center}.project-footer a{color:white}.project-owner-preview{padding:12px 15px;border-radius:12px;background:#fff7ed;color:#9a3412;margin-bottom:16px}
    @media(max-width:860px){.project-nav{display:none}.project-menu{display:block}.project-hero{grid-template-columns:1fr}.project-price{max-width:360px}.project-list{grid-template-columns:repeat(2,minmax(0,1fr))}.project-info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.project-feature-list{grid-template-columns:repeat(2,minmax(0,1fr))}.project-units{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){.project-header-inner{height:62px;padding:0 15px}.project-main{padding:18px 14px 45px}.project-gallery{grid-template-columns:1fr 1fr;grid-template-rows:190px 130px}.project-gallery button:first-child{grid-column:span 2;grid-row:auto}.project-list,.project-info-grid,.project-feature-list,.project-units{grid-template-columns:1fr}.project-section{padding:20px 16px}.project-contact{align-items:flex-start}.project-contact-actions{width:100%}.project-contact-actions a{flex:1}}
    .project-gallery{position:relative;display:block;height:auto;overflow:visible;background:transparent;touch-action:pan-y}.project-gallery-track{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:clamp(135px,19vw,230px);gap:10px;overflow:hidden;border-radius:24px}.project-gallery .project-gallery-slide{min-width:0;min-height:0;height:auto;overflow:hidden;border-radius:18px;cursor:zoom-in}.project-gallery .project-gallery-slide:first-child{grid-column:span 2;grid-row:span 2}.project-gallery .project-gallery-slide img{width:100%;height:100%;object-fit:cover}.project-gallery-count,.project-lightbox-count{position:absolute;right:14px;bottom:14px;padding:7px 11px;border-radius:999px;background:#111827b8;color:white;font-size:12px;font-weight:800}.project-lightbox-frame{position:relative;display:grid;place-items:center;background:#111;min-height:200px}.project-lightbox-frame img{max-width:94vw;max-height:85vh;object-fit:contain}.project-lightbox-arrow{position:absolute;z-index:2;top:50%;display:grid;place-items:center;width:44px;height:44px;transform:translateY(-50%);border:0;border-radius:50%;background:#0009;color:#fff;font-size:27px;cursor:pointer}.project-lightbox-arrow.prev{left:12px}.project-lightbox-arrow.next{right:12px}.project-map-frame{display:block;width:100%;height:360px;border:0;border-radius:16px;background:#eef2f7}.project-map-link{margin-top:12px}
    @media(max-width:560px){.project-gallery-track{grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:clamp(120px,34vw,185px);gap:8px;border-radius:18px}.project-gallery .project-gallery-slide,.project-gallery .project-gallery-slide:first-child{grid-column:auto;grid-row:auto;border-radius:14px}.project-gallery .project-gallery-slide:first-child{grid-column:span 2}.project-map-frame{height:290px}.project-lightbox-arrow{width:40px;height:40px}}
    @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important}}
    .project-lightbox .project-lightbox-arrow { right:auto; top:50%; }
    .project-lightbox .project-lightbox-arrow.prev { left:12px; }
    .project-lightbox .project-lightbox-arrow.next { left:auto; right:12px; }
    .project-cta { background:linear-gradient(135deg,#f5a36c,#e27a43); }
    .project-cta:hover,.project-cta-project:hover { filter:brightness(.97) saturate(1.05); }
    .publisher-profile { display:flex;align-items:center;gap:13px;min-width:min(100%,300px); }
    .publisher-profile>div { display:flex;align-items:center;gap:7px;flex-wrap:wrap; }
    .publisher-profile small { width:100%;color:#d8d2e8;font-size:12px; }
    .publisher-profile strong { font-size:17px; }
    .publisher-profile .publisher-avatar { display:grid;flex:0 0 54px;width:54px;height:54px;place-items:center;overflow:hidden;border-radius:50%;background:#fff;color:#6044a6;font-size:19px;font-weight:900; }
    .publisher-profile .publisher-avatar img { width:100%;height:100%;object-fit:cover; }
    .publisher-profile .agent-verified-badge { background:#e8fff5;color:#08784c; }
    .publisher-profile p { width:100%;margin:0;color:#e1dceb; }
    .project-contact .publisher-profile-link { color:#fff;text-decoration:none; }
    .project-contact .publisher-profile-link:hover { opacity:.9; }
    .publisher-mini { position:absolute; left:9px; bottom:9px; z-index:3; display:inline-flex; align-items:center; gap:6px; max-width:calc(100% - 18px); padding:3px 9px 3px 3px; border-radius:999px; background:rgba(255,255,255,.88); border:1px solid rgba(255,255,255,.65); box-shadow:0 2px 8px rgba(15,30,55,.16); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
    .publisher-mini-avatar { display:grid; flex:0 0 20px; width:20px; height:20px; place-items:center; overflow:hidden; border-radius:50%; background:linear-gradient(135deg,#efe7fb,#e0d2f5); color:#6044a6; font-size:9px; font-weight:900; }
    .publisher-mini-avatar img { width:100%; height:100%; object-fit:cover; }
    .publisher-mini-text { display:flex; flex-direction:column; min-width:0; line-height:1.25; }
    .publisher-mini-name { display:inline-flex; align-items:center; gap:3px; max-width:130px; color:#241d3e; font-size:10.5px; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .publisher-mini-loc { max-width:130px; color:#7a8699; font-size:9px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .publisher-verified-dot { flex:0 0 auto; display:inline-grid; place-items:center; width:11px; height:11px; border-radius:50%; background:#12a15c; box-shadow:0 0 0 2px rgba(255,255,255,.9); color:#fff; font-size:7px; font-style:normal; font-weight:900; }
    body { font-family: "Prompt", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
    h1, h2, h3, h4, .project-hero h1, .project-kicker { font-family: "Prompt", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; letter-spacing: -0.015em; }
    .to-top {
      position: fixed;
      right: 16px;
      bottom: 16px;
      z-index: 99;
      display: grid;
      place-items: center;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #0B1C33;
      color: #C5A059;
      border: 1px solid rgba(197, 160, 89, 0.4);
      box-shadow: 0 4px 14px rgba(11, 28, 51, 0.2);
      cursor: pointer;
      transition: opacity .2s, transform .2s;
      opacity: 0;
      pointer-events: none;
    }
    .to-top.show {
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>
<body class="page-project">
<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php', 'kasa-logo--dark') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="search.php"><?= e(t('ค้นหาทรัพย์', 'Search')) ?></a>
      <a href="favorites.php">❤️ <?= e(t('ที่ชอบ', 'Favorites')) ?> <span class="sp-fav-count-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="notifications.php">🔔 <?= e(t('แจ้งเตือน', 'Alerts')) ?> <span class="sp-notif-badge" style="display:none; background:#b91c1c; color:#fff; font-size:11px; padding:1px 6px; border-radius:999px;"></span></a>
      <a href="project.php"><?= e(t('โครงการใหม่', 'Projects')) ?></a>
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

<main class="project-main">
  <?php render_banner_slot('generic', 'compact'); ?>
  <nav class="project-breadcrumb" aria-label="breadcrumb"><a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a><span>/</span><a href="project.php"><?= e(t('โครงการ', 'Projects')) ?></a><?php if ($project): ?><span>/</span><span><?= e($project['title']) ?></span><?php endif; ?></nav>

  <?php if ($project && isset($_GET["submitted"])): ?><div class="project-owner-preview"><?= e(t('ส่งโครงการเรียบร้อยแล้ว ตอนนี้แสดงบนหน้าเว็บไซต์สาธารณะแล้ว', 'Project submitted — it is now published on the public website.')) ?></div><?php endif; ?>
  <?php if ($projectId > 0 && !$project): ?>
    <section class="project-empty"><strong><?= e(t('ไม่พบโครงการนี้', 'Project not found')) ?></strong><p><?= e(t('โครงการอาจถูกนำออกหรือยังไม่ได้เผยแพร่', 'The project may have been removed or is not published yet.')) ?></p><a href="project.php"><?= e(t('กลับไปดูโครงการทั้งหมด', 'Back to all projects')) ?></a></section>
  <?php elseif (!$project): ?>
    <div class="project-hero"><div><span class="project-kicker">PROJECT COLLECTION</span><h1><?= e(t('เลือกโครงการที่ใช่', 'Choose the right project')) ?><br><?= e(t('สำหรับชีวิตของคุณ', 'for your life')) ?></h1><p><?= e(t('รวมข้อมูลโครงการ ทำเล แบบยูนิต สิ่งอำนวยความสะดวก และช่องทางสอบถามไว้ในหน้าเดียว', 'Project details, locations, unit types, amenities and enquiry channels — all in one place.')) ?></p></div><div class="project-price"><small><?= e(t('โครงการที่เปิดเผยข้อมูล', 'Published projects')) ?></small><strong><?= number_format(count($projects)) ?> <?= e(t('โครงการ', 'projects')) ?></strong><small><?= e(t('รายละเอียดจากข้อมูลที่ผู้ดูแลเพิ่มไว้', 'Details provided by administrators')) ?></small></div></div>
    <section class="project-list" aria-label="<?= e(t('โครงการอสังหาริมทรัพย์', 'Property projects')) ?>">
      <?php foreach ($projects as $item):
        $cover = $projectCovers[(int)$item['id']] ?? ''; ?>
        <a class="project-card" href="project.php?id=<?= (int)$item['id'] ?>">
          <div class="project-card-image"><?php if ($cover): ?><img src="uploads/<?= e($cover) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php endif; ?><?php render_publisher_mini($item); ?></div>
          <div class="project-card-body"><small><?= e($item['category'] ?: t('โครงการอสังหาริมทรัพย์', 'Property project')) ?><?= $item['developer'] ? ' · ' . e($item['developer']) : '' ?></small><h2><?= e($item['title']) ?></h2><p><?= e($item['location']) ?><?= $item['project_status'] ? ' · ' . e($item['project_status']) : '' ?></p>
            <div class="project-card-facts"><span><?= $item['price_from'] !== null ? e(t('เริ่ม ', 'From ') . $priceLabel($item['price_from'])) : e(t('สอบถามราคา', 'Price on request')) ?></span><span><?= $item['unit_count'] ? e(number_format((int)$item['unit_count']) . t(' ยูนิต', ' units')) : e(t('ดูรายละเอียด', 'View details')) ?></span></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$projects): ?><div class="project-empty"><strong><?= e(t('ยังไม่มีโครงการที่เผยแพร่', 'No published projects yet')) ?></strong><p><?= e(t('มีโครงการจริงหรือไม่? สมัครสมาชิกและลงโครงการพร้อมรูปได้ในแบบฟอร์มเดียว', 'Have a real project? Register and submit your project with photos in a single form.')) ?></p><a class="project-empty-cta" href="submit-project.php">＋ <?= e(t('สมัครและลงโครงการ', 'Register & submit a project')) ?></a><?php if ($isAdmin): ?><p><a href="admin/projects.php"><?= e(t('จัดการโครงการ', 'Manage projects')) ?></a></p><?php endif; ?></div><?php endif; ?>
    </section>
  <?php else: ?>
    <?php if (!$project['is_published'] && $isAdmin): ?><div class="project-owner-preview"><?= e(t('กำลังดูตัวอย่างโครงการฉบับร่าง ซึ่งผู้เข้าชมทั่วไปยังมองไม่เห็น · ', 'Viewing a draft preview — not visible to the public · ')) ?><a href="admin/projects.php?id=<?= (int)$project['id'] ?>"><?= e(t('กลับไปแก้ไข', 'Back to edit')) ?></a></div><?php endif; ?>
    <div class="project-hero"><div><span class="project-kicker"><?= e(t('โครงการอสังหาริมทรัพย์', 'Property project')) ?><?= $project['category'] ? ' · ' . e($project['category']) : '' ?></span><h1><?= e($project['title']) ?></h1><p><?= e($project['location']) ?><?= $project['developer'] ? ' · ' . e(t('พัฒนาโดย ', 'Developed by ')) . e($project['developer']) : '' ?></p></div>
      <div class="project-price"><small><?= e(t('ราคาโครงการ', 'Project pricing')) ?></small><strong><?php if ($project['price_from'] !== null): ?><?= e(t('เริ่ม ', 'From ')) . e($priceLabel($project['price_from'])) ?><?php else: ?><?= e(t('สอบถามราคา', 'Price on request')) ?><?php endif; ?></strong><?php if ($project['price_to'] !== null): ?><small><?= e(t('ราคาสูงสุด ', 'Up to ') . $priceLabel($project['price_to'])) ?></small><?php endif; ?></div>
    </div>

    <section class="project-gallery" aria-label="<?= e(t('ภาพโครงการ', 'Project photos')) ?>">
      <div class="project-gallery-track" id="projectGalleryTrack">
        <?php if ($images): foreach (array_slice($images, 0, 10) as $index => $image): ?><button class="project-gallery-slide" type="button" data-project-image="uploads/<?= e($image) ?>" aria-label="<?= e(t('เปิดดูรูปโครงการ ', 'Open project photo ') . ($index + 1)) ?>"><img src="uploads/<?= e($image) ?>" alt="<?= e($project['title'] . ' ' . t('ภาพที่', 'photo') . ' ' . ($index + 1)) ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></button><?php endforeach; else: ?><div class="project-no-image" style="min-width:100%"><?= e(t('ยังไม่มีรูปภาพโครงการ', 'No project photos yet')) ?></div><?php endif; ?>
      </div>
      <?php if ($images): ?><span class="project-gallery-count" data-project-count><?= e(count($images) . t(' รูป', ' photos')) ?></span><?php endif; ?>
    </section>

    <div class="project-badges"><?php foreach ([$project['category'], $project['project_status'], $project['completion_year'] ? t('คาดว่าจะแล้วเสร็จ ' . $project['completion_year'], 'Estimated completion ' . $project['completion_year']) : '', $project['unit_count'] ? number_format((int)$project['unit_count']) . t(' ยูนิต', ' units') : ''] as $badge): if ($badge !== ''): ?><span class="project-badge"><?= e($badge) ?></span><?php endif; endforeach; ?></div>

    <section class="project-section"><h2><?= e(t('รายละเอียดโครงการ', 'Project details')) ?></h2><p class="project-description"><?= e($project['description'] ?: t('ผู้ดูแลยังไม่ได้เพิ่มรายละเอียดโครงการ', 'The administrator has not added project details yet.')) ?></p></section>

    <section class="project-section"><h2><?= e(t('ข้อมูลสำคัญ', 'Key information')) ?></h2><div class="project-info-grid">
      <?php foreach ([[t('ผู้พัฒนา','Developer'),$project['developer']],[t('ประเภทโครงการ','Project type'),$project['category']],[t('สถานะ','Status'),$project['project_status']],[t('ปีที่คาดว่าจะเสร็จ','Estimated completion'),$project['completion_year'] ? (string)$project['completion_year'] : t('ยังไม่ระบุ','Not specified')],[t('จำนวนยูนิต','Unit count'),$project['unit_count'] ? number_format((int)$project['unit_count']) . t(' ยูนิต',' units') : t('ยังไม่ระบุ','Not specified')],[t('ทำเล','Location'),$project['location']],[t('ที่อยู่','Address'),$project['address']]] as [$label,$value]): if ($value !== ''): ?><div class="project-info"><small><?= e($label) ?></small><strong><?= e($value) ?></strong></div><?php endif; endforeach; ?>
      <div class="project-info"><small><?= e(t('ราคาเริ่มต้น', 'Starting price')) ?></small><strong><?= $project['price_from'] !== null ? e($priceLabel($project['price_from'])) : e(t('สอบถามราคา', 'Price on request')) ?></strong></div>
    </div></section>

    <section class="project-section" aria-label="<?= e(t('แผนที่โครงการ', 'Project map')) ?>"><h2><?= e(t('แผนที่และทำเลโครงการ', 'Project map & location')) ?></h2>
      <iframe class="project-map-frame" title="<?= e(t('แผนที่ทำเล ', 'Location map of ') . $project['title']) ?>" src="https://maps.google.com/maps?q=<?= e(rawurlencode($projectMapQuery)) ?>&amp;z=14&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      <?php if ($safeMapUrl): ?><a class="project-map-link" href="<?= e($safeMapUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('เปิดพิกัดใน Google Maps ↗', 'Open coordinates in Google Maps ↗')) ?></a><?php else: ?><a class="project-map-link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($projectMapQuery)) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('เปิดทำเลใน Google Maps ↗', 'Open location in Google Maps ↗')) ?></a><?php endif; ?>
    </section>

    <?php if ($units): ?><section class="project-section"><h2><?= e(t('แบบบ้านและรูปแบบยูนิต', 'House designs & unit types')) ?></h2><div class="project-units">
      <?php foreach ($units as $unit): ?><article class="project-unit"><h3><?= e($unit['name']) ?></h3><?php if ($unit['unit_type']): ?><p><?= e($unit['unit_type']) ?></p><?php endif; ?><div class="project-unit-facts">
        <?php if ($unit['bedrooms'] !== null): ?><span><?= (int)$unit['bedrooms'] . t(' ห้องนอน', ' bedrooms') ?></span><?php endif; ?>
        <?php if ($unit['bathrooms'] !== null): ?><span><?= (int)$unit['bathrooms'] . t(' ห้องน้ำ', ' bathrooms') ?></span><?php endif; ?>
        <?php if ($unit['area_sqm'] !== null): ?><span><?= e(number_format((float)$unit['area_sqm'], 2)) . t(' ตร.ม.', ' sq.m.') ?></span><?php endif; ?>
        <?php if ($unit['land_area'] !== null): ?><span><?= e(t('ที่ดิน ', 'Land ')) . e(number_format((float)$unit['land_area'], 2)) . t(' ตร.ว.', ' sq.wa') ?></span><?php endif; ?>
        <?php if ($unit['price_from'] !== null): ?><span><?= e(t('เริ่ม ', 'From ') . $priceLabel($unit['price_from'])) ?></span><?php endif; ?>
      </div></article><?php endforeach; ?>
    </div></section><?php endif; ?>

    <?php if ($amenities): ?><section class="project-section"><h2><?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?></h2><div class="project-feature-list"><?php foreach ($amenities as $amenity): ?><div class="project-feature"><i aria-hidden="true">✓</i><span><?= e($amenity) ?></span></div><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($nearby): ?><section class="project-section"><h2><?= e(t('สถานที่ใกล้เคียง', 'Nearby places')) ?></h2><div class="project-feature-list"><?php foreach ($nearby as $place): ?><div class="project-feature"><i aria-hidden="true">⌖</i><span><?= e($place) ?></span></div><?php endforeach; ?></div></section><?php endif; ?>

    <section class="project-section project-contact" id="contact"><?php if (!empty($project['publisher_user_id'])): ?><a class="publisher-profile publisher-profile-link" href="member.php?id=<?= (int)$project['publisher_user_id'] ?>" aria-label="<?= e(t('ดูโปรไฟล์และโครงการของ ', 'View profile and projects of ') . $projectPublisherName) ?>"><?php else: ?><div class="publisher-profile"><?php endif; ?><span class="publisher-avatar"><?php if ($projectPublisherAvatarExists): ?><img src="uploads/avatars/<?= e($projectPublisherAvatar) ?>" alt="<?= e(t('รูปโปรไฟล์ ', 'Profile photo of ') . $projectPublisherName) ?>"><?php else: ?><?= e(mb_strtoupper(mb_substr($projectPublisherName, 0, 1))) ?><?php endif; ?></span><div><small><?= e(t('ผู้ลงโครงการ · ดูโปรไฟล์', 'Project lister · View profile')) ?></small><strong><?= e($projectPublisherName) ?></strong><?php if (($project['publisher_agent_status'] ?? '') === 'verified'): ?><span class="agent-verified-badge"><?= e(t('✓ Agent รับรอง', '✓ Verified agent')) ?></span><?php endif; ?><p><?= e($project['phone'] ? t('ติดต่อ ' . $project['phone'], 'Contact ' . $project['phone']) : t('ดูข้อมูลติดต่อโครงการด้านล่าง', 'See project contact details below')) ?></p></div><?= !empty($project['publisher_user_id']) ? '</a>' : '</div>' ?><div class="project-contact-actions">
      <?php if ($project['phone']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $project['phone'])) ?>"><?= e(t('โทรสอบถาม', 'Call')) ?></a><?php endif; ?>
      <?php if ($project['line_id']): ?><a class="line" href="https://line.me/R/ti/p/~<?= rawurlencode($project['line_id']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('ติดต่อผ่าน LINE', 'Contact via LINE')) ?></a><?php endif; ?>
      <a class="outline" href="project.php"><?= e(t('ดูโครงการอื่น', 'Other projects')) ?></a>
    </div></section>
  <?php endif; ?>
</main>

<dialog class="project-lightbox" id="projectLightbox"><div class="project-lightbox-frame"><button type="button" id="projectLightboxClose" aria-label="<?= e(t('ปิด', 'Close')) ?>">×</button><button type="button" class="project-lightbox-arrow prev" data-project-lightbox-prev aria-label="<?= e(t('รูปก่อนหน้า', 'Previous image')) ?>">‹</button><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="<?= e(t('ภาพโครงการขนาดใหญ่', 'Large project photo')) ?>" id="projectLightboxImage"><button type="button" class="project-lightbox-arrow next" data-project-lightbox-next aria-label="<?= e(t('รูปถัดไป', 'Next image')) ?>">›</button><span class="project-lightbox-count" data-project-lightbox-count></span></div></dialog>
<script>
  const projectMenuButton = document.getElementById('projectMenuButton');
  const projectMobileMenu = document.getElementById('projectMobileMenu');
  projectMenuButton.addEventListener('click', () => { projectMobileMenu.hidden = !projectMobileMenu.hidden; projectMenuButton.setAttribute('aria-expanded', String(!projectMobileMenu.hidden)); });
  const lightbox = document.getElementById('projectLightbox');
  const lightboxImage = document.getElementById('projectLightboxImage');
  const projectSlides = Array.from(document.querySelectorAll('[data-project-image]'));
  let projectImageIndex = 0;
  const showProjectImage = (index, open = false) => {
    if (!projectSlides.length) return;
    projectImageIndex = (index + projectSlides.length) % projectSlides.length;
    lightboxImage.src = projectSlides[projectImageIndex].dataset.projectImage;
    lightboxImage.alt = projectSlides[projectImageIndex].querySelector('img')?.alt || '<?= e(t('ภาพโครงการ', 'Project photo')) ?>';
    const count = `${projectImageIndex + 1} / ${projectSlides.length}`;
    document.querySelector('[data-project-lightbox-count]')?.replaceChildren(document.createTextNode(count));
    if (open && !lightbox.open) lightbox.showModal();
  };
  projectSlides.forEach((button, index) => button.addEventListener('click', () => showProjectImage(index, true)));
  document.querySelector('[data-project-lightbox-prev]')?.addEventListener('click', () => showProjectImage(projectImageIndex - 1));
  document.querySelector('[data-project-lightbox-next]')?.addEventListener('click', () => showProjectImage(projectImageIndex + 1));
  document.getElementById('projectLightboxClose').addEventListener('click', () => lightbox.close());
  lightbox.addEventListener('click', event => { if (event.target === lightbox) lightbox.close(); });
  let projectTouchX = 0;
  lightbox.addEventListener('touchstart', event => { projectTouchX = event.changedTouches[0]?.clientX ?? 0; }, {passive:true});
  lightbox.addEventListener('touchend', event => { const diff = (event.changedTouches[0]?.clientX ?? projectTouchX) - projectTouchX; if (Math.abs(diff) > 45) showProjectImage(projectImageIndex + (diff < 0 ? 1 : -1)); }, {passive:true});
</script>
<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>
</body>
</html>
