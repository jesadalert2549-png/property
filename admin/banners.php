<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/banners.php';
require_admin();

$pdo = db();
ensure_banner_table($pdo);
$message = '';
$errors = [];
$slots = banner_slots();

if (request_is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $slot = (string)($_POST['slot'] ?? 'generic');
        $title = trim((string)($_POST['title'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

        if (!array_key_exists($slot, $slots)) $errors[] = 'สล็อตตำแหน่งไม่ถูกต้อง';
        if ($linkUrl !== '' && !preg_match('#^(https?://|/)#i', $linkUrl)) $errors[] = 'ลิงก์ต้องขึ้นต้นด้วย http(s):// หรือ /';

        if (!$errors) {
            try {
                $media = save_banner_media($_FILES['media'] ?? []);
                $pdo->prepare(
                    'INSERT INTO promo_banners (slot, title, media_type, media_file, link_url, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$slot, mb_substr($title, 0, 120), $media['type'], $media['filename'], mb_substr($linkUrl, 0, 500), $sortOrder]);
                $message = 'เพิ่มสื่อโปรโมชันในตำแหน่ง ' . ($slots[$slot]['label'] ?? $slot) . ' เรียบร้อยแล้ว';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'save_hero') {
        try {
            $opacity = isset($_POST['hero_opacity']) ? (float)$_POST['hero_opacity'] : 0.45;
            $res = save_hero_background($_FILES['hero_media'] ?? [], $opacity);
            $message = 'อัปเดตสื่อพื้นหลังหลักหน้าแรกเรียบร้อยแล้ว (' . ($res['type'] === 'video' ? 'วิดีโอลูป MP4' : 'ภาพนิ่ง') . ') — แสดงผลบนหน้าแรกทันที';
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'reset_hero') {
        reset_hero_background();
        $message = 'คืนค่าภาพพื้นหลังหลักหน้าแรกเป็นค่าเริ่มต้นเรียบร้อยแล้ว';
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE promo_banners SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
        $message = 'เปลี่ยนสถานะการแสดงผลแล้ว';
    } elseif ($action === 'sort') {
        $id = (int)($_POST['id'] ?? 0);
        $dir = ($_POST['dir'] ?? 'up') === 'down' ? 1 : -1;
        $row = $pdo->prepare('SELECT slot, sort_order FROM promo_banners WHERE id = ?');
        $row->execute([$id]);
        $item = $row->fetch();
        if ($item) {
            $pdo->prepare('UPDATE promo_banners SET sort_order = GREATEST(0, sort_order + ?) WHERE id = ?')
                ->execute([$dir, $id]);
        }
        $message = 'จัดลำดับแล้ว';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT media_file FROM promo_banners WHERE id = ?');
        $stmt->execute([$id]);
        $file = (string)$stmt->fetchColumn();
        $pdo->prepare('DELETE FROM promo_banners WHERE id = ?')->execute([$id]);
        if ($file !== '') delete_banner_media($file);
        $message = 'ลบสื่อโปรโมชันแล้ว';
    } else {
        $errors[] = 'คำสั่งไม่ถูกต้อง';
    }
}

$all = $pdo->query(
    'SELECT id, slot, title, media_type, media_file, link_url, sort_order, enabled
     FROM promo_banners
     ORDER BY slot, sort_order, id'
)->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนสื่อในแต่ละสล็อต
$slotCounts = [];
foreach ($all as $b) {
    if (!empty($b['enabled'])) {
        $slotCounts[$b['slot']] = ($slotCounts[$b['slot']] ?? 0) + 1;
    }
}

$token = csrf_token();
$heroBg = get_hero_background();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการแบนเนอร์และสื่อโปรโมชันเว็บ | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .promo-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 30px; border-radius:20px; background:linear-gradient(120deg,#0B1C33,#182C48); color:#fff; border:1px solid rgba(197,160,89,.3); margin-bottom:22px; }
    .promo-hero .eyebrow { color:#C5A059; font-size:11px; font-weight:800; letter-spacing:.16em; }
    .promo-hero h1 { margin:6px 0 2px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .promo-card { background:#fff; border:1px solid #e3e9ef; border-radius:20px; padding:28px 30px; margin-bottom:24px; box-shadow:0 10px 28px rgba(11,28,51,.06); }
    .promo-card h2 { margin:0 0 6px; color:#0B1C33; font-size:18px; }
    .promo-card .intro { margin:0 0 20px; color:#64748b; font-size:13.5px; line-height:1.6; }

    /* Visual Blueprint / Layout Wireframe Map */
    .wireframe-container { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:18px; margin-bottom:24px; }
    .wireframe-card { background:#F8FAFC; border:1px solid #E2E8F0; border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px; }
    .wireframe-title { font-size:13px; font-weight:800; color:#0B1C33; display:flex; align-items:center; gap:8px; }
    .wireframe-mockup { display:flex; flex-direction:column; gap:8px; background:#fff; border:1px solid #CBD5E1; border-radius:12px; padding:12px; box-shadow:inset 0 2px 6px rgba(0,0,0,.02); }

    .mockup-item { padding:8px 10px; border-radius:8px; font-size:11.5px; font-weight:700; text-align:center; transition:all .2s ease; cursor:pointer; user-select:none; }
    .mockup-static { background:#F1F5F9; color:#94A3B8; border:1px dashed #CBD5E1; cursor:default; }
    .mockup-slot { background:linear-gradient(135deg,#FFF8E6,#FDF3D6); border:1.5px solid #C5A059; color:#8C6B32; box-shadow:0 2px 8px rgba(197,160,89,.2); }
    .mockup-slot:hover { background:linear-gradient(135deg,#FDF0C8,#F8E3A8); transform:scale(1.02); box-shadow:0 4px 14px rgba(197,160,89,.4); }
    .mockup-slot.active { background:#C5A059; color:#0B1C33; border-color:#8C6B32; box-shadow:0 0 0 3px rgba(197,160,89,.35); font-weight:900; }
    .mockup-active-count { display:inline-block; margin-left:4px; padding:1px 5px; border-radius:999px; background:#0B1C33; color:#E5CB96; font-size:10px; }

    /* Form Grid */
    .add-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:16px; }
    .field { display:grid; gap:6px; font-size:13px; font-weight:700; color:#34495d; }
    .field.wide { grid-column:1/-1; }
    .field input, .field select { width:100%; min-height:46px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-size:14px; background:#fff; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:#C5A059; box-shadow:0 0 0 3px rgba(197,160,89,.2); }
    .field small { font-weight:500; color:#8090a0; font-size:11.5px; }

    /* Selected Slot Info Box */
    .slot-info-box { grid-column:1/-1; padding:14px 18px; border-radius:12px; background:#F9F9FB; border:1px solid #E2E8F0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .slot-info-left { display:flex; align-items:center; gap:10px; }
    .slot-info-badge { padding:3px 9px; border-radius:999px; background:#0B1C33; color:#E5CB96; font-size:11px; font-weight:800; }
    .slot-info-desc { font-size:12.5px; color:#475569; }

    /* Live Preview Box */
    .preview-box { grid-column:1/-1; padding:16px; border-radius:16px; background:#F8FAFC; border:1.5px dashed #CBD5E1; text-align:center; min-height:120px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; }
    .preview-box.has-file { border-style:solid; border-color:#C5A059; background:#fff; }
    .preview-media-frame { max-width:100%; max-height:280px; border-radius:12px; overflow:hidden; box-shadow:0 8px 24px rgba(11,28,51,.12); background:#0B1C33; display:none; }
    .preview-media-frame img, .preview-media-frame video { max-width:100%; max-height:280px; display:block; object-fit:cover; }

    .btn-add { border:0; border-radius:12px; min-height:50px; padding:0 24px; background:linear-gradient(135deg,#C5A059,#A8843F); color:#0B1C33; font:inherit; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 4px 16px rgba(197,160,89,.35); transition:transform .18s, box-shadow .18s; }
    .btn-add:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(197,160,89,.45); }

    /* Filter Tabs */
    .filter-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #E2E8F0; }
    .filter-tab { padding:7px 16px; border-radius:999px; font-size:13px; font-weight:700; color:#475569; background:#F1F5F9; border:1px solid #CBD5E1; text-decoration:none; cursor:pointer; transition:all .18s; }
    .filter-tab:hover, .filter-tab.active { background:#0B1C33; color:#fff; border-color:#0B1C33; }
    .filter-tab.active { box-shadow:0 4px 12px rgba(11,28,51,.25); }

    /* Banner Row in Media List */
    .banner-groups { display:grid; gap:22px; }
    .banner-group-title { font-size:15px; font-weight:800; color:#0B1C33; display:flex; align-items:center; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid #F1F5F9; margin-bottom:12px; }
    .banner-row { display:flex; align-items:center; gap:16px; padding:14px 16px; border:1px solid #E2E8F0; border-radius:16px; background:#fff; margin-bottom:12px; box-shadow:0 4px 14px rgba(11,28,51,.04); transition:transform .18s, box-shadow .18s; }
    .banner-row:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(11,28,51,.08); border-color:#C5A059; }
    .banner-thumb { flex:0 0 160px; height:90px; border-radius:10px; overflow:hidden; background:#0B1C33; position:relative; }
    .banner-thumb img, .banner-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
    .banner-meta { flex:1; min-width:0; }
    .banner-meta strong { display:block; color:#0B1C33; font-size:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:4px; }
    .banner-meta small { color:#64748B; font-size:12px; display:block; line-height:1.5; }

    .type-tag { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:800; margin-right:4px; }
    .type-tag.image { background:#EBF3FE; color:#1A73E8; border:1px solid #4285F4; }
    .type-tag.video { background:#FFF0F0; color:#DC2626; border:1px solid #EF4444; }
    .onoff { display:inline-flex; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:800; }
    .onoff.on { background:#E8FDF0; color:#04A747; border:1px solid #06C755; }
    .onoff.off { background:#F1F5F9; color:#64748B; border:1px solid #CBD5E1; }

    .banner-actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    .mini-btn { border:0; border-radius:8px; padding:7px 12px; font:inherit; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
    .mini-btn.blue { background:#EBF3FE; color:#1A73E8; }
    .mini-btn.gray { background:#F1F5F9; color:#475569; border:1px solid #E2E8F0; }
    .mini-btn.red { background:#FFF1F1; color:#ae3333; }
    .mini-btn.live { background:rgba(197,160,89,.15); color:#8C6B32; border:1px solid rgba(197,160,89,.4); }
    .mini-btn.live:hover { background:rgba(197,160,89,.3); }

    .alert-ok { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#effaf3; border:1px solid #bbebcd; color:#187243; font-size:14px; font-weight:600; }
    .alert-err { padding:13px 16px; margin-bottom:18px; border-radius:12px; background:#fff1f1; border:1px solid #fac5c5; color:#ae3333; font-size:14px; font-weight:600; }

    @media (max-width: 900px) {
      .add-grid { grid-template-columns:1fr; }
      .banner-row { flex-wrap:wrap; }
      .banner-thumb { flex:1 1 100%; height:140px; }
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">
  <div class="promo-hero">
    <div>
      <span class="eyebrow">PORTAL ADS & DECORATION SYSTEM</span>
      <h1>จัดการแบนเนอร์และสื่อโปรโมชันเว็บ</h1>
      <p style="margin:0;color:rgba(255,255,255,.78);font-size:13.5px;">เลือกตำแหน่งลงโฆษณาได้อิสระทุกส่วนของเว็บ รองรับรูปภาพ โปสเตอร์ และวิดีโอลูป พร้อมระบบจำลองตำแหน่งสด</p>
    </div>
    <span style="display:inline-flex;padding:10px 18px;border-radius:999px;background:rgba(197,160,89,.18);border:1px solid rgba(197,160,89,.45);color:#E5CB96;font-size:13px;font-weight:700;">🎬 ภาพนิ่ง · GIF · MP4 วนลูป</span>
  </div>

  <?php if ($message): ?><div class="alert-ok">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert-err">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- 0. จัดการภาพหรือวิดีโอพื้นหลังหลัก Hero Section หน้าแรก -->
  <section class="promo-card" id="heroBgSection">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
      <h2>🌄 สื่อพื้นหลังหลักหน้าแรก (Main Hero Background Media)</h2>
      <a href="../index.php" target="_blank" rel="noopener" class="mini-btn live">👁️ ดูบนหน้าแรกจริง ↗</a>
    </div>
    <p class="intro">
      อัปเดตรูปภาพหรือคลิปวิดีโอวนลูปในส่วนหัวของหน้าแรก (Hero Section) โดยตรง เว็บไซต์จะปรับขนาดให้เต็มพื้นที่อัตโนมัติทั้งบนคอมพิวเตอร์และมือถือ ไม่ต้องเปิดโค้ดแก้เองอีกต่อไป
    </p>

    <!-- กล่องแสดงสถานะพื้นหลังปัจจุบัน -->
    <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:16px;padding:16px;margin-bottom:20px;">
      <div style="flex:0 0 240px;height:130px;border-radius:12px;overflow:hidden;background:#0B1C33;position:relative;box-shadow:0 4px 14px rgba(11,28,51,.15);">
        <?php if ($heroBg['type'] === 'video'): ?>
          <video src="../<?= e($heroBg['url']) ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;display:block;"></video>
        <?php else: ?>
          <img src="../<?= e($heroBg['url']) ?>" alt="Current Hero" style="width:100%;height:100%;object-fit:cover;display:block;">
        <?php endif; ?>
        <div style="position:absolute;inset:0;background:rgba(11,28,51,<?= (float)$heroBg['opacity'] ?>);pointer-events:none;"></div>
        <span style="position:absolute;top:8px;left:8px;padding:3px 9px;border-radius:999px;font-size:10.5px;font-weight:800;background:rgba(11,28,51,.85);color:#E5CB96;border:1px solid rgba(197,160,89,.4);">
          <?= $heroBg['type'] === 'video' ? '🎬 คลิปวิดีโอลูป' : '🖼️ รูปภาพนิ่ง' ?>
        </span>
      </div>

      <div style="flex:1;min-width:240px;">
        <strong style="font-size:15px;color:#0B1C33;display:block;margin-bottom:4px;">
          สถานะ: <?= $heroBg['is_default'] ? 'ภาพเริ่มต้น (Default Luxury View)' : 'สื่อที่คุณอัปโหลดเอง (Custom Media)' ?>
        </strong>
        <p style="margin:0 0 10px;font-size:12.5px;color:#64748B;line-height:1.5;">
          ไฟล์: <code><?= e($heroBg['filename']) ?></code> · ความมืดเงา Overlay: <strong><?= round($heroBg['opacity'] * 100) ?>%</strong>
        </p>

        <?php if (!$heroBg['is_default']): ?>
          <form method="post" onsubmit="return confirm('คืนค่าเป็นภาพเริ่มต้นใช่หรือไม่?');" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="reset_hero">
            <button type="submit" class="mini-btn gray" style="color:#c64e42;">↺ คืนค่าพื้นหลังเดิม</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- ฟอร์มอัปโหลดสื่อพื้นหลังใหม่ -->
    <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;align-items:end;">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="save_hero">

      <label class="field">
        <span>เลือกไฟล์สื่อพื้นหลังใหม่ (รูปภาพ JPG, PNG, WebP หรือ คลิป MP4, WebM ≤ 50MB)</span>
        <input type="file" name="hero_media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" onchange="previewHeroFile(this)" required>
        <small>💡 แนะนำขนาด: 1920 x 800 ถึง 1920 x 1080 px (สัดส่วน 16:9 แนวนอน) ระบบจะขยายครอบคลุมเต็มจอ 100% อัตโนมัติทั้งบนคอมและมือถือ</small>
      </label>

      <label class="field">
        <span>ความมืดของเงา Overlay ทับสื่อ (ช่วยให้กล่องค้นหาและข้อความอ่านง่าย)</span>
        <select name="hero_opacity">
          <option value="0.30" <?= abs($heroBg['opacity'] - 0.30) < 0.05 ? 'selected' : '' ?>>30% (สว่างมาก เน้นเห็นวิวชัด)</option>
          <option value="0.45" <?= abs($heroBg['opacity'] - 0.45) < 0.05 ? 'selected' : '' ?>>45% (มาตรฐาน สวยกำลังดี แนะนำ)</option>
          <option value="0.55" <?= abs($heroBg['opacity'] - 0.55) < 0.05 ? 'selected' : '' ?>>55% (เข้มปานกลาง ช่องค้นหาเด่นชัด)</option>
          <option value="0.70" <?= abs($heroBg['opacity'] - 0.70) < 0.05 ? 'selected' : '' ?>>70% (เข้มมาก เน้นความคมชัดของข้อความ)</option>
        </select>
        <small>ระบบจะซ้อนเงาสีกรมท่าเข้มให้อัตโนมัติ</small>
      </label>

      <!-- กล่องพรีวิวสดสำหรับไฟล์ Hero -->
      <div id="heroPreviewContainer" style="display:none;grid-column:1/-1;background:#0B1C33;border-radius:16px;overflow:hidden;position:relative;max-height:260px;text-align:center;">
        <img id="heroPreviewImg" style="display:none;width:100%;height:100%;max-height:260px;object-fit:cover;">
        <video id="heroPreviewVid" autoplay muted loop playsinline style="display:none;width:100%;height:100%;max-height:260px;object-fit:cover;"></video>
        <div id="heroPreviewOverlay" style="position:absolute;inset:0;background:rgba(11,28,51,0.45);pointer-events:none;"></div>
        <div style="position:absolute;bottom:10px;left:14px;color:#fff;font-size:12px;font-weight:700;z-index:2;" id="heroPreviewLabel"></div>
      </div>

      <button type="submit" class="btn-add" style="grid-column:1/-1;">💾 บันทึกและเปลี่ยนพื้นหลังหน้าแรกทันที</button>
    </form>
  </section>

  <!-- 1. แผนผังจำลองตำแหน่งบนหน้าเว็บ (Interactive Slot Blueprint) -->
  <section class="promo-card">
    <h2>🗺️ แผนผังตำแหน่งบนหน้าเว็บ (คลิกเพื่อเลือกตำแหน่งลงแบนเนอร์)</h2>
    <p class="intro">คลิกที่แถบสีทองด้านล่างเพื่อเลือกตำแหน่งที่ต้องการลงโฆษณา ระบบจะเลือกสล็อตและแสดงรายละเอียดให้ทันที</p>

    <div class="wireframe-container">
      <!-- 1.1 หน้าแรก Blueprint -->
      <div class="wireframe-card">
        <span class="wireframe-title">🌐 หน้าแรก (Home Page)</span>
        <div class="wireframe-mockup">
          <div class="mockup-item mockup-slot" data-slot-btn="home_hero_top" onclick="selectSlot('home_hero_top')">
            🏅 แถบบนสุด Header Bar
            <?php if (!empty($slotCounts['home_hero_top'])): ?><span class="mockup-active-count"><?= $slotCounts['home_hero_top'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-static">🧭 Navigation Bar & Logo</div>
          <div class="mockup-item mockup-static">🔍 Hero Search Box</div>
          <div class="mockup-item mockup-slot active" data-slot-btn="home_below_hero" onclick="selectSlot('home_below_hero')">
            🖼️ แบนเนอร์ใหญ่ใต้ช่องค้นหา (Hero Billboard)
            <?php if (!empty($slotCounts['home_below_hero'])): ?><span class="mockup-active-count"><?= $slotCounts['home_below_hero'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-static">🏢 ไอคอนหมวดหมู่ (คอนโด/บ้าน/ที่ดิน)</div>
          <div class="mockup-item mockup-slot" data-slot-btn="home_category_gap" onclick="selectSlot('home_category_gap')">
            🎞️ ระหว่างกลุ่มประกาศ (Mid-Page Gap)
            <?php if (!empty($slotCounts['home_category_gap'])): ?><span class="mockup-active-count"><?= $slotCounts['home_category_gap'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-static">🏡 การ์ดประกาศอสังหาฯ</div>
          <div class="mockup-item mockup-slot" data-slot-btn="home_footer_above" onclick="selectSlot('home_footer_above')">
            📣 เหนือ Footer หน้าแรก (Pre-Footer)
            <?php if (!empty($slotCounts['home_footer_above'])): ?><span class="mockup-active-count"><?= $slotCounts['home_footer_above'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-static">📄 Footer & Version Stamp</div>
        </div>
      </div>

      <!-- 1.2 หน้ารายละเอียดทรัพย์ Blueprint -->
      <div class="wireframe-card">
        <span class="wireframe-title">🏠 หน้ารายละเอียดทรัพย์ (Property Detail)</span>
        <div class="wireframe-mockup">
          <div class="mockup-item mockup-static">🖼️ แกลเลอรีรูปถ่ายทรัพย์สิน</div>
          <div class="mockup-item mockup-slot" data-slot-btn="property_detail_top" onclick="selectSlot('property_detail_top')">
            🏠 ใต้รูปถ่ายทรัพย์สิน (Banner ใหญ่)
            <?php if (!empty($slotCounts['property_detail_top'])): ?><span class="mockup-active-count"><?= $slotCounts['property_detail_top'] ?></span><?php endif; ?>
          </div>
          <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:6px;">
            <div class="mockup-item mockup-static" style="min-height:70px;">รายละเอียด & สเปกทรัพย์</div>
            <div class="mockup-item mockup-slot" data-slot-btn="property_sidebar" onclick="selectSlot('property_sidebar')" style="min-height:70px;display:grid;place-items:center;">
              📌 ในคอลัมน์ติดต่อ
              <?php if (!empty($slotCounts['property_sidebar'])): ?><span class="mockup-active-count"><?= $slotCounts['property_sidebar'] ?></span><?php endif; ?>
            </div>
          </div>
          <div class="mockup-item mockup-static">📍 แผนที่ & ข้อมูลผู้ประกาศ</div>
        </div>
      </div>

      <!-- 1.3 หมวดหมู่ & ค้นหา Blueprint -->
      <div class="wireframe-card">
        <span class="wireframe-title">🔍 หมวดหมู่, ค้นหา & โครงการ</span>
        <div class="wireframe-mockup">
          <div class="mockup-item mockup-slot" data-slot-btn="category_top" onclick="selectSlot('category_top')">
            🏬 ด้านบนหน้าหมวดหมู่คอนโด/บ้าน
            <?php if (!empty($slotCounts['category_top'])): ?><span class="mockup-active-count"><?= $slotCounts['category_top'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-slot" data-slot-btn="search_top" onclick="selectSlot('search_top')">
            🔍 ด้านบนหน้าผลการค้นหา
            <?php if (!empty($slotCounts['search_top'])): ?><span class="mockup-active-count"><?= $slotCounts['search_top'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-slot" data-slot-btn="project_page" onclick="selectSlot('project_page')">
            🏢 หน้าโครงการพัฒนาใหม่
            <?php if (!empty($slotCounts['project_page'])): ?><span class="mockup-active-count"><?= $slotCounts['project_page'] ?></span><?php endif; ?>
          </div>
          <div class="mockup-item mockup-slot" data-slot-btn="generic" onclick="selectSlot('generic')">
            ✨ พื้นที่อเนกประสงค์ทั่วไป (Fallback)
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. ฟอร์มอัปโหลดสื่อและตัวอย่างสด Real-time -->
  <section class="promo-card" id="uploadSection">
    <h2>➕ อัปโหลดสื่อโฆษณา / โปรโมชันใหม่</h2>
    <p class="intro">ใส่ไฟล์ภาพนิ่ง โปสเตอร์ หรือคลิปวิดีโอ MP4 สั้น วิดีโอจะเล่นวนลูปอัตโนมัติ (Muted Autoplay Loop) และปรับขนาดตามหน้าจอทันที</p>

    <form method="post" enctype="multipart/form-data" class="add-grid">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="add">

      <div class="slot-info-box" id="slotInfoPanel">
        <div class="slot-info-left">
          <span class="slot-info-badge" id="slotBadge">หน้าแรก</span>
          <strong id="slotLabel" style="color:#0B1C33;font-size:14px;">ใต้ช่องค้นหาหน้าแรก (Hero Billboard)</strong>
        </div>
        <div class="slot-info-desc" id="slotDesc">ขนาดแนะนำ: 1200 x 320 px หรือคลิป MP4 16:9 แนวนอน</div>
      </div>

      <label class="field">
        <span>ตำแหน่งที่ลง (เลือกสล็อต)</span>
        <select name="slot" id="slotSelect" onchange="syncSlotSelection(this.value)" required>
          <?php foreach ($slots as $key => $meta): ?>
            <option value="<?= e($key) ?>" <?= $key === 'home_below_hero' ? 'selected' : '' ?>>
              <?= e($meta['icon'] . ' [' . $meta['badge'] . '] ' . $meta['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span>ชื่อหัวข้อ / คำอธิบายภาพ (Alt Text)</span>
        <input type="text" name="title" maxlength="120" placeholder="เช่น โปรโมชันผ่อนเบาๆ หรือ คอนโดทำเลเด่น">
      </label>

      <label class="field">
        <span>ลิงก์เมื่อคลิกแบนเนอร์ (ไม่บังคับ)</span>
        <input type="text" name="link_url" placeholder="https://... หรือ /search.php?category=condo">
        <small>หากใส่เป็นลิงก์ภายนอก ระบบจะเปิดในแท็บใหม่ให้อัตโนมัติ</small>
      </label>

      <label class="field">
        <span>ลำดับการแสดง (Sort Order)</span>
        <input type="number" name="sort_order" min="0" value="0">
        <small>ตัวเลขน้อยจะแสดงก่อนในสล็อตเดียวกัน</small>
      </label>

      <label class="field wide">
        <span>เลือกไฟล์สื่อ (รูปภาพ JPG, PNG, WebP, GIF หรือ คลิปวิดีโอ MP4, WebM ขนาดไม่เกิน 40 MB)</span>
        <input type="file" name="media" id="mediaInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" onchange="previewMediaFile(this)" required>
      </label>

      <!-- Real-time Live Preview Box -->
      <div class="preview-box" id="previewBox">
        <div id="previewPlaceholder" style="color:#94A3B8;font-size:13px;">
          📸 <strong>ตัวอย่างสด (Real-time Preview)</strong><br>
          เลือกไฟล์ภาพหรือวิดีโอด้านบนเพื่อดูตัวอย่างทันทีก่อนบันทึก
        </div>
        <div class="preview-media-frame" id="previewFrame">
          <img id="previewImage" style="display:none;" alt="Preview">
          <video id="previewVideo" style="display:none;" autoplay muted loop playsinline></video>
        </div>
        <div id="previewFileInfo" style="display:none;font-size:12px;color:#0B1C33;font-weight:700;"></div>
      </div>

      <button type="submit" class="btn-add wide">🚀 อัปโหลดและเผยแพร่ลงเว็บไซต์ทันที</button>
    </form>
  </section>

  <!-- 3. รายการสื่อทั้งหมด พร้อมปุ่มดูในหน้าจริง -->
  <section class="promo-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
      <h2>🗂️ สื่อโฆษณาและโปรโมชันในระบบ (<?= count($all) ?> รายการ)</h2>
      <a href="../index.php" target="_blank" rel="noopener" class="mini-btn live">↗ เปิดดูหน้าแรกเว็บไซต์</a>
    </div>
    <p class="intro">จัดลำดับ เปลี่ยนตำแหน่ง เปิด-ปิดการแสดงผล หรือคลิกเพื่อดูตัวอย่างบนหน้าจริงได้ทันที</p>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
      <button type="button" class="filter-tab active" onclick="filterBanners('all', this)">ทั้งหมด (<?= count($all) ?>)</button>
      <button type="button" class="filter-tab" onclick="filterBanners('home', this)">หน้าแรก</button>
      <button type="button" class="filter-tab" onclick="filterBanners('property', this)">หน้ารายละเอียดทรัพย์</button>
      <button type="button" class="filter-tab" onclick="filterBanners('category', this)">หมวดหมู่ & ค้นหา</button>
      <button type="button" class="filter-tab" onclick="filterBanners('other', this)">โครงการ & อื่นๆ</button>
    </div>

    <?php if (!$all): ?>
      <p class="intro" style="font-style:italic;padding:20px;text-align:center;background:#F9F9FB;border-radius:12px;">ยังไม่มีสื่อโปรโมชัน ลองเลือกตำแหน่งและอัปโหลดรายการแรกด้านบนได้เลย</p>
    <?php else: ?>
      <div class="banner-groups">
        <?php
        $grouped = [];
        foreach ($all as $row) { $grouped[$row['slot']][] = $row; }
        foreach ($grouped as $slotKey => $rows):
          $slotMeta = $slots[$slotKey] ?? ['icon'=>'✨','label'=>$slotKey,'page'=>'index.php','target_url'=>'index.php','badge'=>'ทั่วไป','desc'=>''];
          $groupClass = str_starts_with($slotKey, 'home_') ? 'group-home' : (str_starts_with($slotKey, 'property_') ? 'group-property' : (in_array($slotKey, ['category_top','search_top'], true) ? 'group-category' : 'group-other'));
        ?>
          <div class="banner-group-item <?= e($groupClass) ?>">
            <div class="banner-group-title">
              <span>
                <?= e($slotMeta['icon']) ?> <strong><?= e($slotMeta['label']) ?></strong>
                <span style="display:inline-block;margin-left:6px;padding:2px 7px;border-radius:999px;background:#F1F5F9;color:#64748B;font-size:11px;font-weight:700;">(<?= count($rows) ?> รายการ)</span>
              </span>
              <a href="../<?= e($slotMeta['target_url'] ?? 'index.php') ?>" target="_blank" rel="noopener" class="mini-btn live">
                👁️ ดูในหน้าจริง ↗
              </a>
            </div>

            <?php foreach ($rows as $row): ?>
              <div class="banner-row">
                <div class="banner-thumb">
                  <?php if (($row['media_type'] ?? '') === 'video'): ?>
                    <video src="../uploads/promo/<?= e(basename($row['media_file'])) ?>" autoplay muted loop playsinline preload="metadata"></video>
                  <?php else: ?>
                    <img src="../uploads/promo/<?= e(basename($row['media_file'])) ?>" alt="<?= e($row['title']) ?>" loading="lazy">
                  <?php endif; ?>
                </div>

                <div class="banner-meta">
                  <strong><?= e($row['title'] ?: '(ไม่มีหัวข้อ)') ?></strong>
                  <small>
                    <span class="type-tag <?= e($row['media_type']) ?>">
                      <?= $row['media_type'] === 'video' ? '🎬 วิดีโอลูป (Auto-Loop)' : '🖼️ ภาพนิ่ง' ?>
                    </span>
                    <span class="onoff <?= $row['enabled'] ? 'on' : 'off' ?>">
                      <?= $row['enabled'] ? '● กำลังแสดงบนเว็บ' : '○ ซ่อนอยู่ (หยุดชั่วคราว)' ?>
                    </span>
                    · ลำดับ: <strong><?= (int)$row['sort_order'] ?></strong>
                  </small>
                  <?php if (!empty($row['link_url'])): ?>
                    <small style="margin-top:4px;">🔗 ลิงก์: <a href="<?= e($row['link_url']) ?>" target="_blank" rel="noopener" style="color:#A8843F;font-weight:700;"><?= e($row['link_url']) ?> ↗</a></small>
                  <?php endif; ?>
                </div>

                <div class="banner-actions">
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="mini-btn blue"><?= $row['enabled'] ? '⏸ ซ่อน' : '▶ แสดง' ?></button>
                  </form>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="sort">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="dir" value="up">
                    <button type="submit" class="mini-btn gray" title="เลื่อนขึ้น">▲</button>
                  </form>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="sort">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="dir" value="down">
                    <button type="submit" class="mini-btn gray" title="เลื่อนลง">▼</button>
                  </form>
                  <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันลบแบนเนอร์นี้ออกจากระบบ?');">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="mini-btn red">🗑 ลบ</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
// ข้อมูลสล็อตสำหรับอัปเดตแผงคำแนะนำและแผนผังจำลอง
const slotData = <?= json_encode($slots, JSON_UNESCAPED_UNICODE) ?>;

function selectSlot(slotKey) {
  const sel = document.getElementById('slotSelect');
  if (sel) {
    sel.value = slotKey;
    syncSlotSelection(slotKey);
    // เลื่อนลงไปยังฟอร์มอัปโหลด
    document.getElementById('uploadSection').scrollIntoView({ behavior: 'smooth' });
  }
}

function syncSlotSelection(slotKey) {
  // ไฮไลต์บน Blueprint
  document.querySelectorAll('[data-slot-btn]').forEach(el => {
    el.classList.toggle('active', el.dataset.slotBtn === slotKey);
  });

  // อัปเดตข้อมูลคำแนะนำ
  const meta = slotData[slotKey];
  if (meta) {
    document.getElementById('slotBadge').textContent = meta.badge || 'ทั่วไป';
    document.getElementById('slotLabel').textContent = (meta.icon || '') + ' ' + (meta.label || slotKey);
    document.getElementById('slotDesc').textContent = 'ขนาดแนะนำ: ' + (meta.rec_size || 'ปรับอัตโนมัติ') + ' (' + (meta.desc || '') + ')';
  }
}

// พรีวิวไฟล์สำหรับสื่อพื้นหลัง Hero หน้าแรก
function previewHeroFile(input) {
  const file = input.files && input.files[0];
  const box = document.getElementById('heroPreviewContainer');
  const img = document.getElementById('heroPreviewImg');
  const vid = document.getElementById('heroPreviewVid');
  const label = document.getElementById('heroPreviewLabel');
  if (!file) {
    box.style.display = 'none';
    img.src = '';
    vid.src = '';
    return;
  }
  const isVideo = file.type.startsWith('video/');
  const fileMb = (file.size / (1024 * 1024)).toFixed(2);
  const blobUrl = URL.createObjectURL(file);
  box.style.display = 'block';
  if (isVideo) {
    img.style.display = 'none';
    img.src = '';
    vid.style.display = 'block';
    vid.src = blobUrl;
    vid.play().catch(() => {});
    label.innerHTML = '🎬 พรีวิวคลิปวิดีโอวนลูป: ' + file.name + ' (' + fileMb + ' MB)';
  } else {
    vid.style.display = 'none';
    vid.pause();
    vid.src = '';
    img.style.display = 'block';
    img.src = blobUrl;
    label.innerHTML = '🖼️ พรีวิวรูปภาพพื้นหลัง: ' + file.name + ' (' + fileMb + ' MB)';
  }
}

// ระบบพรีวิวไฟล์สดก่อนอัปโหลด (ภาพ & วิดีโอ)
function previewMediaFile(input) {
  const file = input.files && input.files[0];
  const box = document.getElementById('previewBox');
  const frame = document.getElementById('previewFrame');
  const placeholder = document.getElementById('previewPlaceholder');
  const img = document.getElementById('previewImage');
  const vid = document.getElementById('previewVideo');
  const info = document.getElementById('previewFileInfo');

  if (!file) {
    box.classList.remove('has-file');
    frame.style.display = 'none';
    placeholder.style.display = 'block';
    info.style.display = 'none';
    img.src = '';
    vid.src = '';
    return;
  }

  const isVideo = file.type.startsWith('video/');
  const fileMb = (file.size / (1024 * 1024)).toFixed(2);
  const blobUrl = URL.createObjectURL(file);

  box.classList.add('has-file');
  frame.style.display = 'block';
  placeholder.style.display = 'none';
  info.style.display = 'block';

  if (isVideo) {
    img.style.display = 'none';
    img.src = '';
    vid.style.display = 'block';
    vid.src = blobUrl;
    vid.play().catch(() => {});
    info.innerHTML = '🎬 <strong>คลิปวิดีโอ MP4/WebM:</strong> ' + file.name + ' (' + fileMb + ' MB) — <em>เล่นซ้ำอัตโนมัติ</em>';
  } else {
    vid.style.display = 'none';
    vid.pause();
    vid.src = '';
    img.style.display = 'block';
    img.src = blobUrl;
    info.innerHTML = '🖼️ <strong>ไฟล์ภาพ:</strong> ' + file.name + ' (' + fileMb + ' MB)';
  }
}

// กรองรายการสื่อตามกลุ่มตำแหน่ง
function filterBanners(group, btn) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');

  document.querySelectorAll('.banner-group-item').forEach(el => {
    if (group === 'all') {
      el.style.display = 'block';
    } else if (el.classList.contains('group-' + group)) {
      el.style.display = 'block';
    } else {
      el.style.display = 'none';
    }
  });
}
</script>
</body>
</html>
