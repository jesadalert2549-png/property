<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

start_session();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if (empty($_SESSION['user_id'])) {
    redirect('login.php?return_to=' . rawurlencode('edit-listing.php?id=' . $id));
}
if ($id < 1) {
    render_not_found();
}
$pdo = db();
$userId = (int)$_SESSION['user_id'];

if (($_SESSION['role'] ?? '') === 'admin') {
    // Admin editing via member view or admin view is allowed, or allow viewing if admin
    $propertyStatement = $pdo->prepare('SELECT * FROM properties WHERE id = ? LIMIT 1');
    $propertyStatement->execute([$id]);
    $property = $propertyStatement->fetch();
    if (!$property) {
        render_not_found();
    }
} else {
    $propertyStatement = $pdo->prepare('SELECT * FROM properties WHERE id = ? AND user_id = ? LIMIT 1');
    $propertyStatement->execute([$id, $userId]);
    $property = $propertyStatement->fetch();
    if (!$property) {
        render_not_found();
    }
}

$errorMessages = [];
$successMessage = '';
if (($_GET['saved'] ?? '') === '1' && !empty($_SESSION['listing_edit_notice'])) {
    $successMessage = (string)$_SESSION['listing_edit_notice'];
    unset($_SESSION['listing_edit_notice']);
}
$deleteIds = [];

if (request_is_post()) {
    csrf_check();
    [$data, $inputErrors] = read_listing_input($_POST);
    $errorMessages = array_merge($inputErrors, listing_completeness_errors($data, $_POST));
    $status = (string)($_POST['status'] ?? 'available');
    if (!in_array($status, ['available', 'done'], true)) {
        $errorMessages[] = t('สถานะประกาศไม่ถูกต้อง', 'Invalid listing status.');
        $status = (string)$property['status'];
    }

    $postedDeleteIds = $_POST['delete_images'] ?? [];
    if (is_array($postedDeleteIds)) {
        foreach ($postedDeleteIds as $imageId) {
            if (is_scalar($imageId) && ctype_digit((string)$imageId) && (int)$imageId > 0) {
                $deleteIds[] = (int)$imageId;
            }
        }
    }
    $deleteIds = array_values(array_unique($deleteIds));

    $imagesStatement = $pdo->prepare('SELECT id, filename FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
    $imagesStatement->execute([$id]);
    $currentImages = $imagesStatement->fetchAll();
    $imagesById = [];
    foreach ($currentImages as $image) $imagesById[(int)$image['id']] = $image;
    $deleteIds = array_values(array_filter($deleteIds, static fn(int $imageId): bool => isset($imagesById[$imageId])));
    $deletedImages = array_map(static fn(int $imageId): string => (string)$imagesById[$imageId]['filename'], $deleteIds);

    $fileCount = count_selected_files($_FILES['images'] ?? []);
    $remainingImages = count($currentImages) - count($deleteIds);
    if ($remainingImages + $fileCount > MAX_IMAGES) {
        $errorMessages[] = t('รูปทั้งหมดหลังบันทึกต้องไม่เกิน ' . MAX_IMAGES . ' รูป', 'Total images after saving must not exceed ' . MAX_IMAGES . '.');
    }
    if ($remainingImages + $fileCount < 1) {
        $errorMessages[] = t('ประกาศต้องมีรูปอย่างน้อย 1 รูป', 'A listing must have at least 1 photo.');
    }

    $formValues = array_merge($property, [
        'title' => $data['title'],
        'type' => $data['type'],
        'property_category' => $data['property_category'],
        'price' => $data['price'],
        'original_price' => !empty($_POST['original_price']) ? (int)$_POST['original_price'] : null,
        'location' => $data['location'],
        'description' => $data['description'],
        'bedrooms' => $data['bedrooms'],
        'bathrooms' => $data['bathrooms'],
        'area_sqm' => $data['area_sqm'],
        'land_area' => $data['land_area'],
        'parking_spaces' => $data['parking_spaces'],
        'floor_number' => $data['floor_number'],
        'furnished' => $data['furnished'],
        'amenities' => $data['amenities'],
        'nearby_places' => $data['nearby_places'],
        'contact_name' => $data['contact_name'],
        'phone' => $data['phone'],
        'line_id' => $data['line_id'],
        'lat' => $data['lat'],
        'lng' => $data['lng'],
        'status' => $status,
    ]);

    if (!$errorMessages) {
        $uploadedImages = [];
        $newUploadedVideo = null;
        try {
            $uploadedImages = save_uploaded_images($_FILES['images'] ?? [], max(0, MAX_IMAGES - $remainingImages));
            if (!empty($_FILES['video']['name'])) {
                $newUploadedVideo = save_uploaded_property_video($_FILES['video'] ?? []);
            }
            if (count($uploadedImages) !== $fileCount) {
                foreach ($uploadedImages as $filename) delete_image_file($filename);
                if ($newUploadedVideo) delete_image_file($newUploadedVideo);
                $uploadedImages = [];
                $errorMessages[] = t('มีรูปที่อัปโหลดไม่ผ่านการตรวจ กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB ใหม่', 'Some uploads failed validation. Please choose JPG, PNG or WebP images no larger than 5 MB and try again.');
            } else {
                $duplicate = $pdo->prepare("SELECT id FROM properties WHERE id <> ? AND approval = 'approved' AND LOWER(title) = LOWER(?) AND LOWER(location) = LOWER(?) ORDER BY id DESC LIMIT 1");
                $duplicate->execute([$id, $data['title'], $data['location']]);
                $duplicateId = $duplicate->fetchColumn();
                $verificationStatus = $duplicateId ? 'flagged' : 'passed';
                $verificationNotes = $duplicateId ? t('พบประกาศที่มีหัวข้อและทำเลคล้ายกัน (#' . (int)$duplicateId . ') โปรดตรวจสอบข้อมูลอีกครั้ง', 'A listing with a similar title and location was found (#' . (int)$duplicateId . '). Please review the information again.') : null;
                $approval = $property['approval'] === 'rejected' ? 'pending' : $property['approval'];

                $oldPrice = (int)($property['price'] ?? 0);
                $newPrice = (int)$data['price'];
                $postedOrigPrice = (!empty($_POST['original_price']) && is_numeric($_POST['original_price'])) ? (int)$_POST['original_price'] : null;

                // ตรวจจับการเปลี่ยนราคาอัตโนมัติ: ถ้ามีการแก้ราคา (ไม่ว่าจะลดลง หรือเพิ่มขึ้น) และไม่ได้กรอกราคาเดิมมาเอง ให้จำราคาเดิมก่อนแก้ไว้เป็น original_price ขีดฆ่าทันที
                $finalOrigPrice = $postedOrigPrice;
                if ($finalOrigPrice === null && $newPrice !== $oldPrice && $oldPrice > 0) {
                    $finalOrigPrice = $oldPrice;
                }
                // ถ้าราคาใหม่เท่ากับราคาเดิมเป๊ะ ให้เคลียร์ขีดฆ่า
                if ($finalOrigPrice !== null && $newPrice === $finalOrigPrice) {
                    $finalOrigPrice = null;
                }

                // จัดการไฟล์วิดีโอ (ถ้าติ๊กลบ หรืออัปโหลดใหม่)
                $finalVideo = $property['video_file'] ?? null;
                if (!empty($_POST['delete_video'])) {
                    if ($finalVideo) delete_image_file($finalVideo);
                    $finalVideo = null;
                }
                if ($newUploadedVideo !== null) {
                    if ($finalVideo) delete_image_file($finalVideo);
                    $finalVideo = $newUploadedVideo;
                }

                $pdo->beginTransaction();
                $updateOwnerSql = ($_SESSION['role'] ?? '') === 'admin' ? 'WHERE id=?' : 'WHERE id=? AND user_id=?';
                $updateParams = [
                    $data['title'], $data['type'], $data['property_category'], $data['property_category'], $data['price'], $finalOrigPrice, $data['location'],
                    $data['lat'], $data['lng'], $data['description'], $data['bedrooms'], $data['bathrooms'], $data['area_sqm'], $data['land_area'],
                    $data['parking_spaces'], $data['floor_number'], $data['furnished'], $data['amenities'], $data['nearby_places'],
                    $data['contact_name'], $data['phone'], $data['line_id'], $status, $approval, $verificationStatus, $verificationNotes, $finalVideo, $id
                ];
                if (($_SESSION['role'] ?? '') !== 'admin') {
                    $updateParams[] = $userId;
                }
                $update = $pdo->prepare(
                    "UPDATE properties SET title=?, type=?, category=?, property_category=?, price=?, original_price=?, location=?, lat=?, lng=?, description=?, bedrooms=?, bathrooms=?, area_sqm=?, land_area=?, parking_spaces=?, floor_number=?, furnished=?, amenities=?, nearby_places=?, contact_name=?, phone=?, line_id=?, status=?, approval=?, verification_status=?, verification_notes=?, video_file=?, updated_at=CURRENT_TIMESTAMP $updateOwnerSql"
                );
                $update->execute($updateParams);

                if ($deleteIds) {
                    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                    $delete = $pdo->prepare("DELETE FROM property_images WHERE property_id = ? AND id IN ($placeholders)");
                    $delete->execute([$id, ...$deleteIds]);
                }
                if ($uploadedImages) {
                    $sortStatement = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM property_images WHERE property_id = ?');
                    $sortStatement->execute([$id]);
                    $sortOrder = (int)$sortStatement->fetchColumn();
                    $insertImage = $pdo->prepare('INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, ?)');
                    foreach ($uploadedImages as $filename) $insertImage->execute([$id, $filename, $sortOrder++]);
                }
                $pdo->commit();

                // ── ส่งการแจ้งเตือนลดราคาอัตโนมัติเข้ากล่องข้อความ (Price Drop Alert) ──
                if ($finalOrigPrice !== null && $newPrice < $finalOrigPrice && $approval === 'approved') {
                    require_once __DIR__ . '/includes/notifications.php';
                    $dropDiff = $finalOrigPrice - $newPrice;
                    $discountPct = round(($dropDiff / $finalOrigPrice) * 100);
                    $dropTitle = '📉 ทรัพย์ปรับลดราคา! ' . mb_substr($data['title'], 0, 45) . ' (ลด ฿' . number_format($dropDiff) . ')';
                    $dropMsg = 'ประกาศ "' . $data['title'] . '" ในทำเล ' . $data['location'] . ' ได้ปรับลดราคาลงจาก ฿' . number_format($finalOrigPrice) . ' เหลือเพียง ฿' . number_format($newPrice) . ' (-' . $discountPct . '%) โอกาสดีสำหรับผู้ที่กำลังเล็งทำเลนี้อยู่ รีบดูรายละเอียดก่อนปิดการขาย!';

                    // 1) ส่ง Broadcast หาผู้ใช้ทุกคน
                    send_user_notification(0, 'price_drop', $dropTitle, $dropMsg, 'property.php?id=' . $id, $id, '📉');

                    // 2) ส่งตรงหาผู้ใช้ที่เคยกดหัวใจ ❤️ บันทึกทรัพย์นี้ไว้เป็นพิเศษ
                    $favUsers = $pdo->prepare('SELECT user_id FROM property_favorites WHERE property_id = ? AND user_id <> ?');
                    $favUsers->execute([$id, $userId]);
                    foreach ($favUsers->fetchAll(PDO::FETCH_COLUMN) as $fUid) {
                        send_user_notification(
                            (int)$fUid,
                            'price_drop',
                            '❤️ ทรัพย์ที่คุณบันทึกไว้ปรับลดราคาพิเศษ!',
                            'ทรัพย์ที่คุณเคยกดหัวใจไว้ "' . $data['title'] . '" ลดราคาลง ฿' . number_format($dropDiff) . ' เหลือ ฿' . number_format($newPrice) . ' รีบเข้าไปตรวจสอบด่วน!',
                            'property.php?id=' . $id,
                            $id,
                            '🔥'
                        );
                    }
                }

                foreach ($deletedImages as $filename) delete_image_file($filename);
                $_SESSION['listing_edit_notice'] = $approval === 'pending'
                    ? t('บันทึกการแก้ไขแล้ว ส่งประกาศกลับไปให้ผู้ดูแลตรวจสอบ', 'Changes saved and sent for administrator review.')
                    : t('บันทึกการแก้ไขประกาศเรียบร้อยแล้ว', 'Listing changes saved successfully.');
                redirect('edit-listing.php?id=' . $id . '&saved=1');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($uploadedImages as $filename) delete_image_file($filename);
            error_log('Member listing edit failed: ' . $exception->getMessage());
            $errorMessages[] = t('บันทึกประกาศไม่สำเร็จ กรุณาลองอีกครั้ง', 'Failed to save the listing. Please try again.');
        }
    }
    $property = $formValues;
}

$imagesStatement = $pdo->prepare('SELECT id, filename FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
$imagesStatement->execute([$id]);
$images = $imagesStatement->fetchAll();
$token = csrf_token();
$propertyCategory = (string)($property['property_category'] ?? $property['category'] ?? 'condo');
$mapLat = request_is_post() ? (string)($_POST['map_lat'] ?? '') : (string)($property['lat'] ?? '');
$mapLng = request_is_post() ? (string)($_POST['map_lng'] ?? '') : (string)($property['lng'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head((is_en() ? 'Edit my listing | ' : 'แก้ไขประกาศของฉัน | ') . SITE_NAME, is_en() ? 'Manage the details and photos of your listing' : 'จัดการรายละเอียดและรูปภาพประกาศของคุณ', page_url('edit-listing.php?id=' . $id), false); ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <?php site_controls_head(); lang_toggle_head(); ?>
  <style>
    .member-edit-page { width:min(900px,calc(100% - 28px)); margin:34px auto 60px; }
    .member-edit-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .member-edit-heading h1 { margin:4px 0; color:var(--primary-dark); font-size:clamp(25px,4vw,34px); }
    .member-edit-heading p { margin:0; color:var(--muted); }
    .member-edit-card { padding:clamp(18px,4vw,30px); border:1px solid #e4e7f0; border-radius:20px; background:#fff; box-shadow:var(--shadow-sm); }
    .member-edit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .member-edit-grid label { display:grid; align-content:start; gap:6px; color:#3d455f; font-size:13px; font-weight:700; }
    .member-edit-grid label.wide { grid-column:1/-1; }
    .member-edit-grid input:not([type="checkbox"]):not([type="radio"]),.member-edit-grid select,.member-edit-grid textarea { width:100%; min-height:44px; padding:10px 12px; border:1px solid #d8ddea; border-radius:10px; background:#fff; color:#20243b; font:inherit; }
    .member-edit-grid input[type="checkbox"],.member-edit-grid input[type="radio"] { width: 17px; height: 17px; min-height: 17px; margin: 0; cursor: pointer; accent-color: #dc2626; flex-shrink: 0; }
    .member-edit-grid textarea { min-height:100px; resize:vertical; }
    .member-edit-grid input:not([type="checkbox"]):not([type="radio"]):focus,.member-edit-grid select:focus,.member-edit-grid textarea:focus { border-color:var(--accent); outline:3px solid #7955d91c; }
    .member-edit-section { grid-column:1/-1; margin:12px 0 0; padding-top:17px; border-top:1px solid #edf0f5; color:var(--primary-dark); font-size:16px; }
    .member-edit-help { color:var(--muted); font-size:11px; font-weight:450; }
    .member-edit-images { display:grid; grid-template-columns:repeat(auto-fill,minmax(112px,1fr)); gap:10px; margin-top:10px; width: 100%; box-sizing: border-box; }
    .member-edit-image { overflow:hidden; border:1px solid #e3e7ee; border-radius:11px; background:#f8f9fc; }
    .member-edit-image img { display:block; width:100%; height:88px; object-fit:cover; }
    .member-edit-image label { display:flex; align-items:center; gap:6px; padding:8px; color:#a33e4b; font-size:11px; }
    .member-edit-delete-video { display:inline-flex !important; flex-direction:row !important; align-items:center !important; gap:8px !important; font-size:13px !important; color:#dc2626 !important; font-weight:700 !important; cursor:pointer !important; margin: 4px 0 0 !important; }
    .member-edit-actions { display:flex; justify-content:space-between; gap:12px; margin-top:22px; }
    .member-edit-actions a { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:0 17px; border:1px solid #dce1ec; border-radius:12px; color:#555d73; font-weight:750; text-decoration:none; }
    .member-edit-actions button { min-height:48px; padding:0 22px; border:0; border-radius:12px; background:linear-gradient(135deg,#b39af2,#583caa); color:#fff; font:inherit; font-weight:850; cursor:pointer; }

    /* Modern Photo & Video Dropzones matching Submit.php */
    .listing-upload {
      grid-column: 1 / -1 !important;
      position: relative !important;
      border: 2px dashed #C5A059 !important;
      border-radius: 18px !important;
      background: #FAF8F5 !important;
      padding: 30px 20px !important;
      text-align: center !important;
      transition: all .22s ease !important;
      cursor: pointer !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
      box-sizing: border-box !important;
      width: 100% !important;
    }
    .listing-upload:hover {
      border-color: #A8843F !important;
      background: #F5EFE6 !important;
      transform: translateY(-2px) !important;
    }
    .listing-upload-icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 6px;
      border-radius: 50%;
      background: rgba(197, 160, 89, 0.16);
      border: 1px solid rgba(197, 160, 89, 0.4);
      display: grid;
      place-items: center;
      color: #8C6B32;
      font-size: 24px;
    }
    .listing-upload strong {
      color: #0B1C33 !important;
      font-size: 15px !important;
      font-weight: 800 !important;
      display: block !important;
    }
    .listing-upload small {
      color: #64748B !important;
      font-size: 12.5px !important;
      display: block !important;
      line-height: 1.5 !important;
    }
    .listing-upload-btn {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      margin-top: 10px !important;
      padding: 8px 18px !important;
      border-radius: 999px !important;
      background: #0B1C33 !important;
      border: 1px solid rgba(197, 160, 89, 0.5) !important;
      color: #F8D888 !important;
      font-size: 12.5px !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 12px rgba(11, 28, 51, 0.15) !important;
      pointer-events: none !important;
      transition: all .2s ease !important;
    }
    .listing-upload:hover .listing-upload-btn {
      background: #C5A059 !important;
      color: #0B1C33 !important;
      transform: scale(1.05) !important;
    }
    .listing-upload input[type="file"] {
      display: none !important;
    }
    .listing-video-preview-box {
      display: none;
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      background: #0B1C33;
      aspect-ratio: 16 / 9;
      max-height: 280px;
      margin: 12px 0;
      border: 1px solid rgba(197, 160, 89, 0.4);
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.15);
    }
    .listing-video-preview-box video {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .listing-video-remove-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
      background: rgba(11, 28, 51, 0.85);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #ffffff;
      font-size: 11.5px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      cursor: pointer;
      backdrop-filter: blur(6px);
      transition: all .18s;
    }
    .listing-video-remove-btn:hover {
      background: #dc2626;
      border-color: #dc2626;
    }
    .listing-image-preview {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 12px;
      margin-top: 12px;
    }
    .listing-preview-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      border: 2px solid #E2E8F0;
      background: #F8FAFC;
      box-shadow: 0 2px 8px rgba(15, 30, 55, .06);
    }
    .listing-preview-card img {
      width: 100%;
      height: 105px;
      object-fit: cover;
      display: block;
    }
    .listing-preview-card .new-badge {
      position: absolute;
      top: 6px;
      left: 6px;
      padding: 3px 8px;
      border-radius: 999px;
      background: #0B1C33;
      color: #F8D888;
      border: 1px solid rgba(197, 160, 89, 0.4);
      font-size: 10px;
      font-weight: 800;
    }
    @media(max-width:600px) { .member-edit-heading { align-items:flex-start; flex-direction:column; } .member-edit-grid { grid-template-columns:1fr; } .member-edit-grid label.wide,.member-edit-section,.listing-upload.wide { grid-column:auto !important; } .member-edit-actions { flex-direction:column-reverse; } .member-edit-actions>* { width:100%; } }
  </style>
</head>
<body>
<header class="site-header"><div class="container header-inner">
  <?= render_logo_html('dashboard.php#my-posts') ?>
  <nav class="site-nav"><a href="dashboard.php#my-posts"><?= e(t('กลับไปโพสต์ของฉัน', 'Back to my posts')) ?></a><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a><?php render_lang_toggle(); ?></nav>
</div></header>
<main class="member-edit-page">
  <div class="member-edit-heading"><div><span class="listing-eyebrow"><?= e(t('จัดการเนื้อหาของคุณ', 'Manage your content')) ?></span><h1><?= e(t('แก้ไขประกาศ', 'Edit listing')) ?></h1><p><?= e(t('แก้รายละเอียด รูปภาพ และตำแหน่งแผนที่ได้ที่นี่', 'Edit details, photos and the map position here.')) ?></p></div><span class="listing-status <?= $property['approval'] === 'approved' ? 'is-approved' : ($property['approval'] === 'rejected' ? 'is-rejected' : 'is-pending') ?>"><i></i><?= $property['approval'] === 'approved' ? e(t('เผยแพร่', 'Published')) : ($property['approval'] === 'rejected' ? e(t('ซ่อนอยู่', 'Hidden')) : e(t('รอตรวจสอบ', 'Pending review'))) ?></span></div>
  <section class="member-edit-card">
    <?php if ($successMessage !== ''): ?><div class="alert success" role="status"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessages): ?><div class="alert error" id="editErrors" role="alert" tabindex="-1"><?php foreach ($errorMessages as $message): ?><div>• <?= e($message) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="member-edit-grid">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <label><?= e(t('หัวข้อประกาศ', 'Listing title')) ?> *<input name="title" required minlength="5" value="<?= e($property['title']) ?>"></label>
      <label><?= e(t('ประเภทประกาศ', 'Listing type')) ?> *<select name="type"><option value="sale" <?= $property['type'] === 'sale' ? 'selected' : '' ?>><?= e(t('ขาย', 'For sale')) ?></option><option value="rent" <?= $property['type'] === 'rent' ? 'selected' : '' ?>><?= e(t('ให้เช่า', 'For rent')) ?></option></select></label>
      <label><?= e(t('ประเภททรัพย์', 'Property type')) ?> *<select name="property_category" required><?php foreach (PROPERTY_CATEGORIES as $slug => $label): if ($slug === 'other') continue; ?><option value="<?= e($slug) ?>" <?= $propertyCategory === $slug ? 'selected' : '' ?>><?= e(category_label_bi($slug)) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('ราคาปัจจุบัน (บาท)', 'Current Price (THB)')) ?> *<input name="price" type="number" min="1" step="1" required value="<?= e((string)$property['price']) ?>"></label>
      <label><?= e(t('ราคาเดิมก่อนลด / ขีดฆ่า (บาท - ไม่บังคับ)', 'Original Price / Strikethrough (Optional)')) ?><input name="original_price" type="number" min="1" step="1" placeholder="<?= e(t('เช่น ใส่ราคาเดิมที่สูงกว่าเพื่อแสดงขีดฆ่า', 'e.g. Higher original price to show strikethrough')) ?>" value="<?= e((string)($property['original_price'] ?? '')) ?>"><small class="member-edit-help"><?= e(t('ระบบจะสร้างราคาขีดฆ่าและคำนวณป้าย % ส่วนลดให้อัตโนมัติ', 'System will display strikethrough and discount % badge automatically.')) ?></small></label>
      <label class="wide"><?= e(t('ทำเล / ที่อยู่', 'Location / Address')) ?> *<input name="location" required value="<?= e($property['location']) ?>"></label>
      <label><?= e(t('สถานะประกาศ', 'Listing status')) ?><select name="status"><option value="available" <?= $property['status'] === 'available' ? 'selected' : '' ?>><?= e(t('กำลังประกาศ', 'Active')) ?></option><option value="done" <?= $property['status'] === 'done' ? 'selected' : '' ?>><?= e(t('ขาย/ให้เช่าแล้ว', 'Sold/Rented')) ?></option></select></label>
      <label><?= e(t('เบอร์โทรศัพท์', 'Phone number')) ?> *<input name="phone" type="tel" inputmode="numeric" required value="<?= e($property['phone']) ?>"></label>
      <label><?= e(t('ชื่อผู้ติดต่อ', 'Contact name')) ?> *<input name="contact_name" required value="<?= e($property['contact_name']) ?>"></label>
      <label><?= e(t('LINE ID (ไม่บังคับ)', 'LINE ID (optional)')) ?><input name="line_id" value="<?= e($property['line_id'] ?? '') ?>"></label>

      <h2 class="member-edit-section"><?= e(t('รายละเอียดเพิ่มเติม', 'Additional details')) ?></h2>
      <label><?= e(t('ห้องนอน', 'Bedrooms')) ?><input name="bedrooms" type="number" min="0" max="99" value="<?= e((string)($property['bedrooms'] ?? '')) ?>"></label>
      <label><?= e(t('ห้องน้ำ', 'Bathrooms')) ?><input name="bathrooms" type="number" min="0" max="99" value="<?= e((string)($property['bathrooms'] ?? '')) ?>"></label>
      <label><?= e(t('พื้นที่ใช้สอย (ตร.ม.)', 'Floor area (sq.m.)')) ?><input name="area_sqm" type="number" min="0" step="0.01" value="<?= e((string)($property['area_sqm'] ?? '')) ?>"></label>
      <label><?= e(t('พื้นที่ดิน (ตร.ว.)', 'Land area (sq.wa)')) ?><input name="land_area" type="number" min="0" step="0.01" value="<?= e((string)($property['land_area'] ?? '')) ?>"></label>
      <label><?= e(t('ที่จอดรถ', 'Parking spaces')) ?><input name="parking_spaces" type="number" min="0" max="99" value="<?= e((string)($property['parking_spaces'] ?? '')) ?>"></label>
      <label><?= e(t('ชั้น', 'Floor')) ?><input name="floor_number" value="<?= e((string)($property['floor_number'] ?? '')) ?>"></label>
      <label><?= e(t('เฟอร์นิเจอร์', 'Furnishing')) ?><input name="furnished" value="<?= e((string)($property['furnished'] ?? '')) ?>"></label>
      <label class="wide"><?= e(t('รายละเอียดเพิ่มเติม', 'Description')) ?> *<textarea name="description" required minlength="20"><?= e((string)$property['description']) ?></textarea><small class="member-edit-help"><?= e(t('อย่างน้อย 20 ตัวอักษร', 'At least 20 characters')) ?></small></label>
      <label class="wide"><?= e(t('สิ่งอำนวยความสะดวก (ไม่บังคับ)', 'Amenities (optional)')) ?><textarea name="amenities"><?= e((string)($property['amenities'] ?? '')) ?></textarea></label>
      <label class="wide"><?= e(t('สถานที่ใกล้เคียง (ไม่บังคับ)', 'Nearby places (optional)')) ?><textarea name="nearby_places"><?= e((string)($property['nearby_places'] ?? '')) ?></textarea></label>

      <h2 class="member-edit-section"><?= e(t('รูปภาพและแผนที่', 'Photos & map')) ?></h2>
      
      <!-- Modern Photo Upload Zone matching Submit.php -->
      <div class="listing-upload wide" id="listingImagesDropzone">
        <div class="listing-upload-icon">📷</div>
        <strong><?= e(t('เพิ่มรูปภาพอสังหาริมทรัพย์', 'Add property photos')) ?></strong>
        <small><?= e(t('แตะเพื่อเลือกรูปภาพ หรือลากไฟล์มาวาง · สูงสุดรวมไม่เกิน ', 'Tap to select photos or drag & drop · up to ')) ?><?= MAX_IMAGES ?><?= e(t(' รูป (JPG, PNG, WebP ขนาดไม่เกิน 5 MB ต่อรูป)', ' photos (JPG, PNG, WebP max 5 MB each)')) ?></small>
        <span class="listing-upload-btn">
          <span>📁</span> <span><?= e(t('เลือกรูปภาพจากเครื่อง', 'Browse Photos')) ?></span>
        </span>
        <input id="listingImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
      </div>

      <!-- Preview of newly selected photos -->
      <div id="listingImagePreview" class="listing-image-preview wide" aria-live="polite"></div>

      <?php if ($images): ?>
        <div class="wide" style="margin-top: 14px;">
          <span class="member-edit-help" style="display:block; font-weight:700; color:#3d455f; margin-bottom:8px;">
            <?= e(t('รูปภาพปัจจุบันในระบบ (ติ๊กถูกในช่องใต้รูปเพื่อลบ):', 'Current photos (check the box under a photo to delete):')) ?>
          </span>
          <div class="member-edit-images">
            <?php foreach ($images as $image): ?>
              <div class="member-edit-image">
                <img src="uploads/<?= e(rawurlencode(basename($image['filename']))) ?>" alt="<?= e(t('รูปประกาศ', 'Listing photo')) ?>" loading="lazy">
                <label>
                  <input type="checkbox" name="delete_images[]" value="<?= (int)$image['id'] ?>" <?= in_array((int)$image['id'], $deleteIds, true) ? 'checked' : '' ?>> 
                  <?= e(t('ลบรูปนี้', 'Delete this photo')) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Modern Video Upload Zone matching Submit.php -->
      <div class="listing-upload wide" id="listingVideoDropzone" style="margin-top: 10px;">
        <div class="listing-upload-icon" style="background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.35);">🎬</div>
        <strong><?= e(t('วิดีโอคลิปของประกาศ (ไม่บังคับ - พรีวิวพร้อมเสียงเมื่อวางเมาส์)', 'Property video clip (Optional - plays with sound on hover)')) ?></strong>
        <small><?= e(t('แตะเพื่อเลือกคลิปพาทัวร์ทรัพย์ · MP4, WebM หรือ MOV ขนาดไม่เกิน 50 MB', 'Tap to select video tour · MP4, WebM or MOV up to 50 MB')) ?></small>
        <span class="listing-upload-btn" style="background:#0B1C33; color:#F8D888;">
          <span>📹</span> <span><?= e(t('เลือกไฟล์วิดีโอ', 'Browse Video')) ?></span>
        </span>
        <input id="listingVideo" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" style="display:none;">
      </div>

      <!-- Video Live Preview Player Box for new upload -->
      <div class="listing-video-preview-box wide" id="listingVideoPreviewBox">
        <video id="listingVideoPreviewPlayer" controls playsinline></video>
        <button type="button" class="listing-video-remove-btn" id="listingVideoRemoveBtn">✕ <?= e(t('เปลี่ยนวิดีโอ', 'Change video')) ?></button>
      </div>

      <?php if (!empty($property['video_file']) && is_file(UPLOAD_DIR . basename($property['video_file']))): ?>
        <div class="wide" id="existingVideoBlock" style="padding: 14px 16px; background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 12px; margin-top: 8px;">
          <span style="display:block; font-size:12.5px; font-weight:700; color:#3d455f; margin-bottom:8px;">
            <?= e(t('วิดีโอปัจจุบันที่แสดงอยู่:', 'Currently active video:')) ?>
          </span>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <video src="uploads/<?= e(rawurlencode(basename($property['video_file']))) ?>" controls style="max-height:160px; max-width:100%; border-radius:8px; background:#0B1C33; display:block;"></video>
            <label class="member-edit-delete-video">
              <input type="checkbox" name="delete_video" value="1">
              <span>✕ <?= e(t('ลบคลิปวิดีโอนี้ออก', 'Delete this video')) ?></span>
            </label>
          </div>
        </div>
      <?php endif; ?>
      <div class="wide listing-map-picker">
        <div class="listing-map-heading"><div><strong><?= e(t('ตำแหน่งบนแผนที่', 'Map location')) ?> <span>(<?= e(t('ไม่บังคับ', 'optional')) ?>)</span></strong><small><?= e(t('แตะเพื่อปักหมุด หรือลากหมุดไปยังตำแหน่งทรัพย์', 'Tap to drop a pin, or drag the pin to the property position')) ?></small></div><button type="button" class="listing-map-toggle" id="editMapToggle" aria-expanded="false" aria-controls="editMapPanel"><?= e(t('เปิดแผนที่', 'Open map')) ?></button></div>
        <div id="editMapPanel" class="listing-map-panel" hidden><div id="editPropertyMap" class="listing-map" role="application" aria-label="<?= e(t('เลือกตำแหน่งประกาศ', 'Select the listing position')) ?>"></div><p class="listing-map-help"><?= e(t('เลื่อนและซูมแผนที่ได้ จากนั้นแตะเพื่อวางหมุด สามารถลากหมุดเพื่อปรับตำแหน่งได้', 'Pan and zoom the map, then tap to place the pin. You can also drag the pin to adjust it.')) ?></p></div>
        <div class="listing-map-selection"><span id="editMapStatus" role="status" aria-live="polite"><?= e(t('ยังไม่ได้เลือกตำแหน่ง', 'No position selected')) ?></span><button type="button" class="listing-map-clear" id="editMapClear" hidden><?= e(t('ล้างหมุด', 'Clear pin')) ?></button></div>
        <input type="hidden" name="map_lat" id="editMapLat" value="<?= e($mapLat) ?>"><input type="hidden" name="map_lng" id="editMapLng" value="<?= e($mapLng) ?>">
      </div>
      <div class="member-edit-actions wide"><a href="dashboard.php#my-posts"><?= e(t('ยกเลิก', 'Cancel')) ?></a><button type="submit"><?= e(t('บันทึกการแก้ไข', 'Save changes')) ?></button></div>
    </form>
  </section>
</main>
<?php render_site_footer(); ?>
<script>
(() => {
  // ── Custom Drag & Drop / Click for Photos Zone ──
  const imageInput = document.getElementById('listingImages');
  const imagesDropzone = document.getElementById('listingImagesDropzone');
  const imagePreview = document.getElementById('listingImagePreview');

  if (imageInput && imagePreview && imagesDropzone) {
    let previewUrls = [];

    const renderPreviews = (files) => {
      previewUrls.forEach(url => URL.revokeObjectURL(url));
      previewUrls = [];
      imagePreview.replaceChildren();

      files.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'listing-preview-card';

        const img = document.createElement('img');
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        img.src = url;
        img.alt = file.name;

        const badge = document.createElement('span');
        badge.className = 'new-badge';
        badge.textContent = '+ รูปใหม่ #' + (index + 1);

        item.appendChild(img);
        item.appendChild(badge);
        imagePreview.appendChild(item);
      });
    };

    imagesDropzone.addEventListener('click', (e) => {
      if (e.target !== imageInput) {
        imageInput.click();
      }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      imagesDropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        imagesDropzone.style.borderColor = '#A8843F';
        imagesDropzone.style.background = '#F5EFE6';
      });
    });
    ['dragleave', 'drop'].forEach(eventName => {
      imagesDropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        imagesDropzone.style.borderColor = '#C5A059';
        imagesDropzone.style.background = '#FAF8F5';
      });
    });
    imagesDropzone.addEventListener('drop', (e) => {
      if (e.dataTransfer?.files?.length && imageInput) {
        imageInput.files = e.dataTransfer.files;
        imageInput.dispatchEvent(new Event('change'));
      }
    });

    imageInput.addEventListener('change', () => {
      const files = Array.from(imageInput.files || []).slice(0, <?= MAX_IMAGES ?>).filter(f => f.type.startsWith('image/'));
      renderPreviews(files);
    });
  }

  // ── Custom Video Upload & Live Video Preview Box ──
  const videoInput = document.getElementById('listingVideo');
  const videoDropzone = document.getElementById('listingVideoDropzone');
  const videoPreviewBox = document.getElementById('listingVideoPreviewBox');
  const videoPreviewPlayer = document.getElementById('listingVideoPreviewPlayer');
  const videoRemoveBtn = document.getElementById('listingVideoRemoveBtn');

  videoDropzone?.addEventListener('click', (e) => {
    if (e.target !== videoInput) {
      videoInput?.click();
    }
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    videoDropzone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      videoDropzone.style.borderColor = '#A8843F';
      videoDropzone.style.background = '#F5EFE6';
    });
  });
  ['dragleave', 'drop'].forEach(eventName => {
    videoDropzone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      videoDropzone.style.borderColor = '#C5A059';
      videoDropzone.style.background = '#FAF8F5';
    });
  });
  videoDropzone?.addEventListener('drop', (e) => {
    if (e.dataTransfer?.files?.length && videoInput) {
      videoInput.files = e.dataTransfer.files;
      videoInput.dispatchEvent(new Event('change'));
    }
  });

  videoInput?.addEventListener('change', () => {
    const file = videoInput.files?.[0];
    if (file) {
      if (file.size > 50 * 1024 * 1024) {
        alert('<?= e(t('ไฟล์วิดีโอต้องมีขนาดไม่เกิน 50 MB', 'Video must be less than 50 MB')) ?>');
        videoInput.value = '';
        return;
      }
      const url = URL.createObjectURL(file);
      if (videoPreviewPlayer) {
        videoPreviewPlayer.src = url;
        videoPreviewPlayer.play().catch(() => {});
      }
      if (videoDropzone) videoDropzone.style.display = 'none';
      if (videoPreviewBox) videoPreviewBox.style.display = 'block';
    }
  });

  videoRemoveBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (videoInput) videoInput.value = '';
    if (videoPreviewPlayer) {
      videoPreviewPlayer.pause();
      videoPreviewPlayer.src = '';
    }
    if (videoPreviewBox) videoPreviewBox.style.display = 'none';
    if (videoDropzone) videoDropzone.style.display = 'flex';
  });

  // ── Map Picker ──
  const toggle = document.getElementById('editMapToggle');
  const panel = document.getElementById('editMapPanel');
  const el = document.getElementById('editPropertyMap');
  const latInput = document.getElementById('editMapLat');
  const lngInput = document.getElementById('editMapLng');
  const status = document.getElementById('editMapStatus');
  const clear = document.getElementById('editMapClear');
  let map = null, marker = null;
  const hasPoint = () => latInput.value !== '' && lngInput.value !== '' && Number.isFinite(Number(latInput.value)) && Number.isFinite(Number(lngInput.value));
  const setPoint = (lat, lng) => {
    const point = [lat, lng]; latInput.value = Number(lat).toFixed(6); lngInput.value = Number(lng).toFixed(6);
    if (!marker) { marker = L.marker(point, {draggable:true}).addTo(map); marker.on('dragend', () => { const p=marker.getLatLng(); setPoint(p.lat,p.lng); }); }
    else marker.setLatLng(point);
    status.textContent = `<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`; clear.hidden = false;
  };
  toggle?.addEventListener('click', () => {
    if (!panel.hidden) { panel.hidden=true; toggle.setAttribute('aria-expanded','false'); toggle.textContent='<?= e(t('เปิดแผนที่', 'Open map')) ?>'; return; }
    panel.hidden=false; toggle.setAttribute('aria-expanded','true'); toggle.textContent='<?= e(t('ปิดแผนที่', 'Close map')) ?>';
    if (typeof L === 'undefined') { status.textContent='<?= e(t('โหลดแผนที่ไม่สำเร็จ', 'Failed to load the map')) ?>'; return; }
    if (!map) { const point=hasPoint()?[Number(latInput.value),Number(lngInput.value)]:[13.2,101.0]; map=L.map(el,{scrollWheelZoom:false}).setView(point,hasPoint()?15:6); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map); map.on('click',event=>setPoint(event.latlng.lat,event.latlng.lng)); if(hasPoint())setPoint(...point); }
    requestAnimationFrame(()=>map.invalidateSize());
  });
  clear?.addEventListener('click',()=>{latInput.value='';lngInput.value='';marker?.remove();marker=null;status.textContent='<?= e(t('ยังไม่ได้เลือกตำแหน่ง', 'No position selected')) ?>';clear.hidden=true;});
  if (hasPoint()) { status.textContent=`<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${Number(latInput.value).toFixed(6)}, ${Number(lngInput.value).toFixed(6)}`; clear.hidden=false; }
  const errors=document.getElementById('editErrors');
  if(errors) requestAnimationFrame(()=>{errors.focus({preventScroll:true});errors.scrollIntoView({behavior:'smooth',block:'center'});});
})();
</script>
</body>
</html>
