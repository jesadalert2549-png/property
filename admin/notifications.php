<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/notifications.php';
require_admin();

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageMarketing = $isFounder || staff_can(db(), $staffId, 'marketing', 'manage');

$pdo = db();
ensure_notification_tables($pdo);

$message = '';
$errors = [];

if (request_is_post()) {
    csrf_check();
    if (!$canManageMarketing) {
        $errors[] = 'สิทธิ์ถูกจำกัด: เฉพาะ Founder & CEO หรือฝ่ายการตลาดที่ได้รับมอบหมายเท่านั้น';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        // ส่งข้อความบรอดแคสต์ใหม่ (Broadcast Property Insight / Deal Alert)
        if ($action === 'send_broadcast') {
            $type = in_array($_POST['type'] ?? '', ['price_drop', 'hot_deal', 'prime_location', 'new_match', 'system'], true) ? $_POST['type'] : 'hot_deal';
            $title = trim((string)($_POST['title'] ?? ''));
            $content = trim((string)($_POST['message'] ?? ''));
            $linkUrl = trim((string)($_POST['link_url'] ?? ''));
            $propertyId = (int)($_POST['property_id'] ?? 0);
            $icon = trim((string)($_POST['icon'] ?? '🔔')) ?: '🔔';

            if (mb_strlen($title) < 2) {
                $errors[] = 'กรุณากรอกหัวข้อข้อความแจ้งเตือน';
            } elseif (mb_strlen($content) < 5) {
                $errors[] = 'กรุณากรอกรายละเอียดข้อความให้ครบถ้วน';
            } else {
                $id = send_user_notification(0, $type, $title, $content, $linkUrl ?: null, $propertyId ?: null, $icon);
                if ($id > 0) {
                    $message = "ส่งการแจ้งเตือนบรอดแคสต์ \"{$title}\" ถึงผู้ใช้งานทุกคนเรียบร้อยแล้ว!";
                } else {
                    $errors[] = 'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
                }
            }
        }

        // ลบข้อความแจ้งเตือน
        if ($action === 'delete_notification') {
            $notifId = (int)($_POST['notif_id'] ?? 0);
            if ($notifId > 0) {
                $pdo->prepare('DELETE FROM user_notifications WHERE id = ?')->execute([$notifId]);
                $pdo->prepare('DELETE FROM notification_reads WHERE notification_id = ?')->execute([$notifId]);
                $message = "ลบข้อความแจ้งเตือนเรียบร้อยแล้ว";
            }
        }
    }
}

// รายการข้อความในระบบ
$recentNotifs = $pdo->query('SELECT * FROM user_notifications ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$totalBroadcasts = (int)$pdo->query('SELECT COUNT(*) FROM user_notifications WHERE user_id = 0')->fetchColumn();
$totalPersonal   = (int)$pdo->query('SELECT COUNT(*) FROM user_notifications WHERE user_id > 0')->fetchColumn();

// ดึงรายการทรัพย์ล่าสุดสำหรับทำ Shortcut ส่งไว
$recentProperties = $pdo->query("SELECT id, title, price, location FROM properties WHERE approval = 'approved' ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ส่งข้อความ &amp; การแจ้งเตือนอสังหาฯ (Smart Push Alerts) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .notif-admin-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
      padding: 28px 32px;
      border-radius: 24px;
      background: linear-gradient(135deg,#0B1C33,#1e3a8a);
      color: #fff;
      border: 1px solid rgba(197,160,89,.35);
      margin-bottom: 26px;
      box-shadow: 0 14px 34px rgba(11,28,51,.12);
    }
    .notif-admin-hero .eyebrow { color:#C5A059; font-size:11.5px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .notif-admin-hero h1 { margin:8px 0 4px; color:#fff; font-size:clamp(22px,3vw,30px); }

    .dash-card { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:24px 28px; margin-bottom:24px; box-shadow:0 8px 24px rgba(11,28,51,.04); }
    .btn-gold {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 0 22px; border: 0; border-radius: 10px;
      background: linear-gradient(135deg,#C5A059,#A8843F); color: #0B1C33; font: inherit; font-size: 13.5px; font-weight: 800; cursor: pointer; text-decoration: none;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1140px); margin:38px auto 70px;">

  <div class="notif-admin-hero">
    <div>
      <span class="eyebrow"><?= e(t('BROADCAST & SMART NOTIFICATION ENGINE', 'BROADCAST & SMART NOTIFICATION ENGINE')) ?></span>
      <h1>📢 <?= e(t('ส่งข้อความแจ้งเตือนอัจฉริยะ (Property Push Alerts)', 'Property Push Alerts & Broadcast Center')) ?></h1>
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">
        <?= e(t('ส่งการแจ้งเตือนเรื่องทรัพย์ลดราคา (Price Drop), ดีลทำเลทอง (Prime Location) หรือข่าวสารสิทธิประโยชน์เข้ากล่องข้อความของผู้ใช้ทุกคนทันที', 'Broadcast real-time price drops, prime location opportunities, and member benefits directly to user inboxes.')) ?>
      </p>
    </div>
    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
      <span style="font-size:12px; color:#E5CB96; background:rgba(255,255,255,.08); padding:5px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.14);">
        ⚡ <?= e(t('ข้อความบรอดแคสต์ทั้งหมด: ' . number_format($totalBroadcasts) . ' รายการ', 'Total Broadcasts: ' . number_format($totalBroadcasts))) ?>
      </span>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success" style="margin-bottom:20px;">✅ <?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error" style="margin-bottom:20px;">⚠️ <?= e($err) ?></div><?php endforeach; ?>

  <!-- ฟอร์มสร้างข้อความแจ้งเตือนใหม่ -->
  <section class="dash-card">
    <h2 style="margin:0 0 6px; font-size:18px; color:#0B1C33;">✉️ <?= e(t('สร้างการแจ้งเตือนส่งหากล่องข้อความผู้ใช้ทุกคน', 'Create & Broadcast Property Alert to All Users')) ?></h2>
    <p style="margin:0 0 20px; font-size:13px; color:#64748B;"><?= e(t('เมื่อกดส่ง ข้อความนี้จะไปขึ้นที่กระดิ่งแจ้งเตือน 🔔 บน Navbar ของผู้ใช้ทุกคนทันที', 'Once sent, this alert will immediately trigger the notification bell 🔔 across all user screens.')) ?></p>

    <?php if ($canManageMarketing): ?>
      <form method="post" style="display:flex; flex-direction:column; gap:16px;">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="send_broadcast">

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ประเภทการแจ้งเตือน', 'Alert Category')) ?></label>
            <select name="type" id="alertType" onchange="autoFillIcon(this.value)" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
              <option value="hot_deal"><?= e(t('🔥 ดีลพิเศษ / ทรัพย์ลดราคา (Hot Deal / Price Drop)', '🔥 Hot Deal / Price Drop')) ?></option>
              <option value="prime_location"><?= e(t('🚇 ทำเลทองศักยภาพสูง (Prime Transit Hub)', '🚇 Prime Location / Transit Hub')) ?></option>
              <option value="price_drop"><?= e(t('📉 ทรัพย์ปรับลดราคาใหม่ (Discount Alert)', '📉 Discount / Markdown Alert')) ?></option>
              <option value="system"><?= e(t('💡 คำแนะนำและสิทธิประโยชน์ (Tips & System)', '💡 Tips & Member Benefits')) ?></option>
            </select>
          </div>

          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ไอคอนแสดงหน้าข้อความ', 'Leading Emoji Icon')) ?></label>
            <input type="text" name="icon" id="alertIcon" value="🔥" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
          </div>
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('เลือกทรัพย์ที่ต้องการโปรโมต (ทางลัดกรอกข้อมูลอัตโนมัติ)', 'Select Listing Shortcut (Auto-fill title & price)')) ?></label>
          <select id="propSelect" onchange="autoFillProperty(this)" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit;">
            <option value=""><?= e(t('-- หรือเลือกประกาศในเว็บเพื่อดึงชื่อและราคาอัตโนมัติ --', '-- Select an active listing to auto-fill details --')) ?></option>
            <?php foreach ($recentProperties as $rp): ?>
              <option value="<?= (int)$rp['id'] ?>" data-title="<?= e($rp['title']) ?>" data-price="<?= number_format((int)$rp['price']) ?>" data-loc="<?= e($rp['location']) ?>">
                #<?= (int)$rp['id'] ?> - <?= e($rp['title']) ?> (฿<?= number_format((int)$rp['price']) ?> · <?= e($rp['location']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('หัวข้อแจ้งเตือน (Notification Title) *', 'Notification Title *')) ?></label>
          <input type="text" name="title" id="alertTitle" placeholder="<?= e(t('เช่น 🔥 คอนโดสุขุมวิท ปรับราคาลดพิเศษ พร้อมโอนทันที!', 'e.g. 🔥 Sukhumvit Condo Markdown Alert! Ready to transfer')) ?>" required style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
        </div>

        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('เนื้อหาข้อความ (Message Content) *', 'Message Content *')) ?></label>
          <textarea name="message" id="alertMessage" rows="3" placeholder="<?= e(t('ระบุรายละเอียดที่เป็นประโยชน์ เช่น \'ทำเลใกล้ BTS ทองหล่อ ราคาคุ้มค่าที่สุดในย่าน เหมาะสำหรับอยู่อาศัยหรือปล่อยเช่า yield สูง...\'', 'Enter alert details e.g. Close to BTS Thong Lo, highest rental yield in prime Bangkok...')) ?>" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:13px; box-sizing:border-box;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('ลิงก์ปลายทาง (Target URL)', 'Target URL / Path')) ?></label>
            <input type="text" name="link_url" id="alertLink" placeholder="เช่น property.php?id=1 หรือ search.php?category=condo" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:4px;"><?= e(t('Property ID (ถ้ามี)', 'Property ID (Optional)')) ?></label>
            <input type="number" name="property_id" id="alertPropId" placeholder="e.g. 1" style="width:100%; min-height:40px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box;">
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" class="btn-gold">🚀 <?= e(t('ส่งการแจ้งเตือนทันที', 'Broadcast Notification Now')) ?></button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <!-- ตารางประวัติข้อความแจ้งเตือนที่เคยส่ง -->
  <section class="dash-card">
    <h2 style="margin:0 0 14px; font-size:18px; color:#0B1C33;">📋 <?= e(t('ประวัติการแจ้งเตือนในระบบ (ล่าสุด ' . count($recentNotifs) . ' รายการ)', 'Notification Dispatch History (Latest ' . count($recentNotifs) . ' logs)')) ?></h2>

    <?php if (!$recentNotifs): ?>
      <p style="text-align:center; padding:30px; background:#f8fafc; border-radius:12px; color:#64748B;"><?= e(t('ยังไม่มีข้อความแจ้งเตือนในระบบ', 'No notifications recorded yet')) ?></p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#f8fafc; text-align:left; border-bottom:2px solid #e2e8f0;">
              <th style="padding:10px 12px;"><?= e(t('ไอคอน', 'Icon')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('หัวข้อ & เนื้อหา', 'Title & Content')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('ประเภท', 'Type')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('เป้าหมาย', 'Audience')) ?></th>
              <th style="padding:10px 12px;"><?= e(t('เวลาที่ส่ง', 'Sent At')) ?></th>
              <th style="padding:10px 12px; text-align:right;"><?= e(t('จัดการ', 'Action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentNotifs as $rn): ?>
              <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px 12px; font-size:20px;"><?= e($rn['icon']) ?></td>
                <td style="padding:10px 12px;">
                  <strong style="color:#0B1C33; display:block;"><?= e($rn['title']) ?></strong>
                  <span style="font-size:12px; color:#64748B; display:block; max-width:480px;"><?= e($rn['message']) ?></span>
                </td>
                <td style="padding:10px 12px;">
                  <span style="font-size:11px; padding:2px 8px; border-radius:999px; background:#f1f5f9; color:#475569; font-weight:700;">
                    <?= e($rn['type']) ?>
                  </span>
                </td>
                <td style="padding:10px 12px; font-size:12px; color:#1d4ed8; font-weight:700;">
                  <?= (int)$rn['user_id'] === 0 ? e(t('🌐 ทุกคน (Broadcast)', '🌐 All Users (Broadcast)')) : (e(t('👤 สมาชิก #', '👤 User #')) . (int)$rn['user_id']) ?>
                </td>
                <td style="padding:10px 12px; font-size:11.5px; color:#64748B;">
                  <?= e(date('d/m/Y H:i', strtotime($rn['created_at']))) ?>
                </td>
                <td style="padding:10px 12px; text-align:right;">
                  <?php if ($canManageMarketing): ?>
                    <form method="post" onsubmit="return confirm('<?= e(t('ต้องการลบข้อความแจ้งเตือนนี้ใช่หรือไม่?', 'Delete this notification?')) ?>');" style="display:inline; margin:0;">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="delete_notification">
                      <input type="hidden" name="notif_id" value="<?= (int)$rn['id'] ?>">
                      <button type="submit" class="btn" style="padding:4px 8px; font-size:11px; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; cursor:pointer;"><?= e(t('ลบ', 'Delete')) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</main>

<script>
  function autoFillIcon(type) {
    const map = {
      'hot_deal': '🔥',
      'prime_location': '🚇',
      'price_drop': '📉',
      'new_match': '✨',
      'system': '💡'
    };
    if (map[type]) {
      document.getElementById('alertIcon').value = map[type];
    }
  }

  function autoFillProperty(select) {
    const opt = select.selectedOptions[0];
    if (!opt || !opt.value) return;
    const title = opt.getAttribute('data-title');
    const price = opt.getAttribute('data-price');
    const loc = opt.getAttribute('data-loc');

    document.getElementById('alertTitle').value = '🔥 ดีลเด็ดราคาคุ้มค่า: ' + title;
    document.getElementById('alertMessage').value = 'พบประกาศทำเลดีในย่าน ' + loc + ' ราคาพิเศษเพียง ฿' + price + ' สภาพแวดล้อมน่าอยู่ เหมาะทั้งอยู่อาศัยและลงทุน รีบดูรายละเอียดก่อนปิดการขาย!';
    document.getElementById('alertLink').value = 'property.php?id=' + opt.value;
    document.getElementById('alertPropId').value = opt.value;
  }
</script>
</body>
</html>
