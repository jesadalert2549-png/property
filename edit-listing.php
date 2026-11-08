<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

start_session();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if (empty($_SESSION['user_id'])) {
    redirect('login.php?return_to=' . rawurlencode('edit-listing.php?id=' . $id));
}
if (($_SESSION['role'] ?? '') === 'admin') {
    redirect('admin/edit.php?id=' . $id);
}
if ($id < 1) {
    render_not_found();
}

$userId = (int)$_SESSION['user_id'];
$pdo = db();
$propertyStatement = $pdo->prepare('SELECT * FROM properties WHERE id = ? AND user_id = ? LIMIT 1');
$propertyStatement->execute([$id, $userId]);
$property = $propertyStatement->fetch();
if (!$property) {
    render_not_found();
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
        try {
            $uploadedImages = save_uploaded_images($_FILES['images'] ?? [], max(0, MAX_IMAGES - $remainingImages));
            if (count($uploadedImages) !== $fileCount) {
                foreach ($uploadedImages as $filename) delete_image_file($filename);
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

                $pdo->beginTransaction();
                $update = $pdo->prepare(
                    'UPDATE properties SET title=?, type=?, category=?, property_category=?, price=?, original_price=?, location=?, lat=?, lng=?, description=?, bedrooms=?, bathrooms=?, area_sqm=?, land_area=?, parking_spaces=?, floor_number=?, furnished=?, amenities=?, nearby_places=?, contact_name=?, phone=?, line_id=?, status=?, approval=?, verification_status=?, verification_notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?'
                );
                $update->execute([
                    $data['title'], $data['type'], $data['property_category'], $data['property_category'], $data['price'], $finalOrigPrice, $data['location'],
                    $data['lat'], $data['lng'], $data['description'], $data['bedrooms'], $data['bathrooms'], $data['area_sqm'], $data['land_area'],
                    $data['parking_spaces'], $data['floor_number'], $data['furnished'], $data['amenities'], $data['nearby_places'],
                    $data['contact_name'], $data['phone'], $data['line_id'], $status, $approval, $verificationStatus, $verificationNotes, $id, $userId,
                ]);

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
    .member-edit-grid input,.member-edit-grid select,.member-edit-grid textarea { width:100%; min-height:44px; padding:10px 12px; border:1px solid #d8ddea; border-radius:10px; background:#fff; color:#20243b; font:inherit; }
    .member-edit-grid textarea { min-height:100px; resize:vertical; }
    .member-edit-grid input:focus,.member-edit-grid select:focus,.member-edit-grid textarea:focus { border-color:var(--accent); outline:3px solid #7955d91c; }
    .member-edit-section { grid-column:1/-1; margin:12px 0 0; padding-top:17px; border-top:1px solid #edf0f5; color:var(--primary-dark); font-size:16px; }
    .member-edit-help { color:var(--muted); font-size:11px; font-weight:450; }
    .member-edit-images { display:grid; grid-template-columns:repeat(auto-fill,minmax(112px,1fr)); gap:10px; margin-top:10px; }
    .member-edit-image { overflow:hidden; border:1px solid #e3e7ee; border-radius:11px; background:#f8f9fc; }
    .member-edit-image img { display:block; width:100%; height:88px; object-fit:cover; }
    .member-edit-image label { display:flex; align-items:center; gap:6px; padding:8px; color:#a33e4b; font-size:11px; }
    .member-edit-actions { display:flex; justify-content:space-between; gap:12px; margin-top:22px; }
    .member-edit-actions a { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:0 17px; border:1px solid #dce1ec; border-radius:12px; color:#555d73; font-weight:750; text-decoration:none; }
    .member-edit-actions button { min-height:48px; padding:0 22px; border:0; border-radius:12px; background:linear-gradient(135deg,#b39af2,#583caa); color:#fff; font:inherit; font-weight:850; cursor:pointer; }
    @media(max-width:600px) { .member-edit-heading { align-items:flex-start; flex-direction:column; } .member-edit-grid { grid-template-columns:1fr; } .member-edit-grid label.wide,.member-edit-section { grid-column:auto; } .member-edit-actions { flex-direction:column-reverse; } .member-edit-actions>* { width:100%; } }
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
      <label class="wide"><?= e(t('เพิ่มรูปภาพ', 'Add photos')) ?> <span class="member-edit-help"><?= e(t('เลือกเพิ่มหรือติ๊กภาพเดิมเพื่อลบ · รวมทั้งหมดไม่เกิน ' . MAX_IMAGES . ' รูป', 'Add new photos or tick existing ones to delete · up to ' . MAX_IMAGES . ' photos total')) ?></span><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
      <?php if ($images): ?><div class="member-edit-images wide"><?php foreach ($images as $image): ?><div class="member-edit-image"><img src="uploads/<?= e(rawurlencode(basename($image['filename']))) ?>" alt="<?= e(t('รูปประกาศ', 'Listing photo')) ?>" loading="lazy"><label><input type="checkbox" name="delete_images[]" value="<?= (int)$image['id'] ?>" <?= in_array((int)$image['id'], $deleteIds, true) ? 'checked' : '' ?>> <?= e(t('ลบรูปนี้', 'Delete this photo')) ?></label></div><?php endforeach; ?></div><?php endif; ?>
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
