<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
current_lang();
require_once __DIR__ . '/includes/profile_schema.php';
ensure_profile_schema(db());

$memberId = max(0, (int)($_GET['id'] ?? 0));
$profileQuery = db()->prepare("SELECT id, full_name, avatar_path, agent_status, created_at FROM users WHERE id = ? AND role = 'user' LIMIT 1");
$profileQuery->execute([$memberId]);
$profile = $profileQuery->fetch();
if (!$profile) render_not_found();

$profileName = trim((string)$profile['full_name']) ?: 'สมาชิก Standard Property';
$avatar = basename((string)($profile['avatar_path'] ?? ''));
$avatarExists = $avatar !== '' && is_file(__DIR__ . '/uploads/avatars/' . $avatar);
$listingQuery = db()->prepare("SELECT p.id, p.title, p.type, p.price, p.location, p.created_at, p.video_file,
    (SELECT i.filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
    FROM properties p WHERE p.user_id = ? AND p.approval = 'approved' ORDER BY p.created_at DESC");
$listingQuery->execute([$memberId]);
$memberListings = $listingQuery->fetchAll();
$projectQuery = db()->prepare("SELECT p.id, p.title, p.category, p.location, p.created_at,
    (SELECT i.filename FROM project_images i WHERE i.project_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
    FROM development_projects p WHERE p.owner_user_id = ? AND p.is_published = 1 ORDER BY p.created_at DESC");
$projectQuery->execute([$memberId]);
$memberProjects = $projectQuery->fetchAll();
$pageTitle = ($profileName) . (is_en() ? ' | Property Lister' : ' | ผู้ประกาศอสังหาริมทรัพย์');
$canonical = page_url('member.php?id=' . $memberId);
?>
<!doctype html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head($pageTitle, is_en() ? 'View the public property listings and projects of ' . $profileName : 'ดูโปรไฟล์และประกาศอสังหาริมทรัพย์ที่เผยแพร่ของ ' . $profileName, $canonical); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/site-shell.css?v=<?= e((string)filemtime(__DIR__ . '/css/site-shell.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
  <style>
    .public-member-card-media { position: relative; width: 100%; height: 175px; overflow: hidden; background: #0B1C33; }
    .public-member-card-media .card-video-stream { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; z-index: 2; opacity: 1; }
    .public-member-card-media .card-video-pill { position: absolute; top: 8px; right: 8px; z-index: 10; display: inline-flex; align-items: center; gap: 3.5px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; background: rgba(11,28,51,.85); color: #F8D888; border: 1px solid rgba(197,160,89,.4); backdrop-filter: blur(6px); cursor: pointer; }
    .public-member-card-media .card-video-pill.sound-active { background: #15803d !important; border-color: #4ade80 !important; color: #fff !important; }
    .public-member-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .public-member-page{min-height:65vh;padding:32px 0 60px;background:#f6f7fb}.public-member-page>.container{max-width:1080px}.public-member-hero{display:flex;align-items:center;gap:20px;padding:28px;border:1px solid #e8e4f0;border-radius:24px;background:radial-gradient(circle at 90% 0,#fff0df,transparent 30%),linear-gradient(130deg,#fff,#f5f1fc);box-shadow:0 14px 36px #251c420c}.public-member-avatar{display:grid;flex:0 0 88px;width:88px;height:88px;place-items:center;overflow:hidden;border:4px solid #fff;border-radius:28px;background:#eee8fa;color:#6044a6;font-size:34px;font-weight:900;box-shadow:0 8px 24px #251c4217}.public-member-avatar img{width:100%;height:100%;object-fit:cover}.public-member-copy{min-width:0}.public-member-copy small{color:#7955d9;font-size:11px;font-weight:900;letter-spacing:1px}.public-member-copy h1{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:5px 0;color:#2b2150;font-size:clamp(24px,4vw,34px)}.public-member-copy p{margin:0;color:#67778a;font-size:13px}.public-member-sections{display:grid;gap:25px;margin-top:28px}.public-member-heading{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:13px}.public-member-heading h2{margin:0;color:#2b2150;font-size:22px}.public-member-heading span{color:#768397;font-size:12px}.public-member-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.public-member-card{overflow:hidden;border:1px solid #e5e9ef;border-radius:17px;background:#fff;color:inherit;text-decoration:none;box-shadow:0 8px 24px #1b24350a;transition:transform .18s,border-color .18s}.public-member-card:hover{transform:translateY(-3px);border-color:#c6b4ed}.public-member-card>img,.public-member-card-cover{display:grid;width:100%;height:175px;place-items:center;object-fit:cover;background:linear-gradient(135deg,#eeeaf8,#f7e8db);color:#7955d9;font-size:35px}.public-member-card-copy{padding:14px}.public-member-card-copy small{color:#7955d9;font-size:11px;font-weight:800}.public-member-card-copy h3{margin:5px 0;color:#2b2150;font-size:16px;line-height:1.45}.public-member-card-copy p{margin:5px 0;color:#708095;font-size:12px}.public-member-empty{padding:25px;border:1px dashed #d8dce6;border-radius:16px;background:#fff;color:#788597;text-align:center;font-size:13px}.public-member-back{display:inline-flex;margin-top:22px;color:#6044a6;font-size:13px;font-weight:800;text-decoration:none}
    @media(max-width:760px){.public-member-page{padding-top:20px}.public-member-hero{align-items:flex-start;padding:20px;border-radius:19px}.public-member-avatar{flex-basis:66px;width:66px;height:66px;border-radius:21px;font-size:26px}.public-member-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.public-member-card>img,.public-member-card-cover{height:140px}.public-member-card-copy{padding:11px}.public-member-card-copy h3{font-size:14px}}
    @media(max-width:480px){.public-member-grid{grid-template-columns:1fr}.public-member-card{display:grid;grid-template-columns:115px minmax(0,1fr);min-height:112px}.public-member-card>img,.public-member-card-cover{width:115px;height:100%;min-height:112px}.public-member-card-copy{align-self:center}.public-member-empty{padding:20px 14px}}
  </style>
</head>
<body>
<?php render_site_header(); ?>
<main class="public-member-page"><div class="container">
  <section class="public-member-hero"><span class="public-member-avatar"><?php if ($avatarExists): ?><img src="uploads/avatars/<?= e($avatar) ?>" alt="<?= e(t('รูปโปรไฟล์ ', 'Profile photo of ') . $profileName) ?>" loading="lazy" decoding="async"><?php else: ?><?= e(mb_strtoupper(mb_substr($profileName, 0, 1))) ?><?php endif; ?></span><div class="public-member-copy"><small><?= e(t('ผู้ประกาศบน ', 'Lister on ') . SITE_NAME) ?></small><h1><?= e($profileName) ?><?php if ($profile['agent_status'] === 'verified'): ?><span class="agent-verified-badge" title="<?= e(t('ผู้ดูแลตรวจหลักฐาน Agent แล้ว', 'Agent credentials verified by admin')) ?>"><?= e(t('✓ Agent รับรอง', '✓ Verified agent')) ?></span><?php endif; ?></h1><p><?= e(t('แสดงเฉพาะทรัพย์และโครงการที่เผยแพร่ต่อสาธารณะ · สมาชิกตั้งแต่ ', 'Showing only publicly published properties and projects · Member since ')) . e(date('m/Y', strtotime($profile['created_at']))) ?></p></div></section>
  <div class="public-member-sections">
    <section><div class="public-member-heading"><h2><?= e(t('ประกาศอสังหาริมทรัพย์', 'Property listings')) ?></h2><span><?= e(number_format(count($memberListings)) . t(' รายการ', ' items')) ?></span></div><?php if ($memberListings): ?><div class="public-member-grid"><?php foreach ($memberListings as $item): $cover = basename((string)($item['cover'] ?? '')); $coverExists = $cover !== '' && is_file(UPLOAD_DIR . $cover); $videoFilename = basename((string)($item['video_file'] ?? '')); $videoExists = $videoFilename !== '' && is_file(UPLOAD_DIR . $videoFilename); $videoUrl = $videoExists ? 'uploads/' . rawurlencode($videoFilename) : ''; ?><a class="public-member-card" href="property.php?id=<?= (int)$item['id'] ?>"><div class="public-member-card-media"<?= $videoExists ? ' data-card-video="' . e($videoUrl) . '"' : '' ?>><?php if ($videoExists): ?><video class="card-video-stream" src="<?= e($videoUrl) ?>" autoplay muted loop playsinline preload="metadata" poster="<?= $coverExists ? 'uploads/' . e($cover) : '' ?>"></video><button type="button" class="card-video-pill" aria-label="<?= e(t('แตะเปิด/ปิดเสียงวิดีโอ', 'Toggle video sound')) ?>"><svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="M8 5v14l11-7z"/></svg><span><?= e(t('วิดีโอ', 'Video')) ?></span></button><?php endif; ?><?php if ($coverExists): ?><img src="uploads/<?= e($cover) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php else: ?><span class="public-member-card-cover">⌂</span><?php endif; ?></div><div class="public-member-card-copy"><small><?= e(is_en() ? ($item['type'] === 'rent' ? 'For rent' : 'For sale') : type_label($item['type'])) ?> · <?= e(is_en() ? number_format((int)$item['price']) . ($item['type'] === 'rent' ? ' THB/month' : ' THB') : price_label($item['type'], (int)$item['price'])) ?></small><h3><?= e($item['title']) ?></h3><p>⌖ <?= e($item['location']) ?></p></div></a><?php endforeach; ?></div><?php else: ?><div class="public-member-empty"><?= e(t('ยังไม่มีประกาศอสังหาริมทรัพย์ที่เผยแพร่', 'No published property listings yet')) ?></div><?php endif; ?></section>
    <section><div class="public-member-heading"><h2><?= e(t('โครงการ', 'Projects')) ?></h2><span><?= e(number_format(count($memberProjects)) . t(' รายการ', ' items')) ?></span></div><?php if ($memberProjects): ?><div class="public-member-grid"><?php foreach ($memberProjects as $item): $cover = basename((string)($item['cover'] ?? '')); ?><a class="public-member-card" href="project.php?id=<?= (int)$item['id'] ?>"><?php if ($cover !== '' && is_file(UPLOAD_DIR . $cover)): ?><img src="uploads/<?= e($cover) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php else: ?><span class="public-member-card-cover">⌂</span><?php endif; ?><div class="public-member-card-copy"><small><?= e($item['category'] ?: t('โครงการ', 'Project')) ?></small><h3><?= e($item['title']) ?></h3><p>⌖ <?= e($item['location']) ?></p></div></a><?php endforeach; ?></div><?php else: ?><div class="public-member-empty"><?= e(t('ยังไม่มีโครงการที่เผยแพร่', 'No published projects yet')) ?></div><?php endif; ?></section>
  </div><a class="public-member-back" href="search.php"><?= e(t('← กลับไปค้นหาอสังหาริมทรัพย์', '← Back to property search')) ?></a>
</div></main>
<?php render_site_footer(); ?>
</body></html>

<script>
  // Kasa Reels-style Feed Video Stream & Sound Toggle for Member Public Profile
  (() => {
    const cards = Array.from(document.querySelectorAll('.public-member-card-media[data-card-video]'));
    let activeCard = null;

    cards.forEach(media => {
      const src = media.getAttribute('data-card-video');
      if (!src) return;

      let vEl = media.querySelector('video.card-video-stream');
      const pill = media.querySelector('.card-video-pill');
      let userEngaged = false;

      if (vEl) {
        vEl.muted = true;
        vEl.volume = 1.0;
        vEl.play().catch(() => {});
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
            pill.classList.remove('sound-active');
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
          pill.classList.add('sound-active');
        });
      }

      media._mute = () => {
        if (vEl) vEl.muted = true;
        if (pill) pill.classList.remove('sound-active');
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
