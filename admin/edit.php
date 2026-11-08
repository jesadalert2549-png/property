<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin('listings', 'edit');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$property = [
    'title' => '',
    'type' => 'sale',
    'category' => 'condo',
    'price' => '',
    'location' => '',
    'map_url' => '',
    'contact_name' => '',
    'phone' => '',
    'line_id' => '',
    'description' => '',
    'bedrooms' => '',
    'bathrooms' => '',
    'area_sqm' => '',
    'land_area' => '',
    'parking_spaces' => '',
    'floor_number' => '',
    'furnished' => '',
    'amenities' => '',
    'nearby_places' => '',
    'status' => 'available'
];

// หากเป็นการแก้ไข ให้ดึงข้อมูลเดิมขึ้นมาแสดง
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $property = $fetched;
        if ($property['lat'] !== null && $property['lng'] !== null) {
            $property['map_url'] = 'https://www.google.com/maps?q=' . $property['lat'] . ',' . $property['lng'];
        }
    } else {
        set_flash('ไม่พบประกาศที่ต้องการแก้ไข');
        redirect('index.php');
    }
}

$errors = [];
if (request_is_post()) {
    csrf_check();
    
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'sale';
    $category = $_POST['category'] ?? 'condo';
    $price = trim($_POST['price'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $mapUrl = trim($_POST['map_url'] ?? '');
    $mapCoords = parse_map_coords($mapUrl);
    $property['map_url'] = $mapUrl;
    $contact_name = trim($_POST['contact_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lineId = clean_line_id($_POST['line_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bedrooms = ($_POST['bedrooms'] ?? '') !== '' ? (int)$_POST['bedrooms'] : null;
    $bathrooms = ($_POST['bathrooms'] ?? '') !== '' ? (int)$_POST['bathrooms'] : null;
    $area_sqm = ($_POST['area_sqm'] ?? '') !== '' ? (float)$_POST['area_sqm'] : null;
    $land_area = ($_POST['land_area'] ?? '') !== '' ? (float)$_POST['land_area'] : null;
    $parking_spaces = ($_POST['parking_spaces'] ?? '') !== '' ? (int)$_POST['parking_spaces'] : null;
    $floor_number = trim($_POST['floor_number'] ?? '');
    $furnished = trim($_POST['furnished'] ?? '');
    $amenities = trim($_POST['amenities'] ?? '');
    $nearby_places = trim($_POST['nearby_places'] ?? '');
    $status = $_POST['status'] ?? 'available';

    if ($title === '') $errors[] = 'กรุณากรอกหัวข้อประกาศ';
    if ($price === '' || !is_numeric($price)) $errors[] = 'กรุณากรอกราคาให้ถูกต้องเป็นตัวเลข';
    if ($location === '') $errors[] = 'กรุณากรอกทำเลที่ตั้ง';
    if ($mapUrl !== '' && $mapCoords === null) $errors[] = 'ลิงก์ Google Maps ไม่ถูกต้อง';
    $errors = array_merge($errors, property_detail_errors($_POST));
    if (!valid_line_id($lineId)) $errors[] = 'LINE ID ต้องเป็น 4-20 ตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด หรือขีดล่าง';

    $postedPrice = (int)$price;
    $postedOrigPrice = (!empty($_POST['original_price']) && is_numeric($_POST['original_price'])) ? (int)$_POST['original_price'] : null;

    if (empty($errors)) {
        if ($id > 0) {
            $prevPrice = (int)($property['price'] ?? 0);
            $finalOrigPrice = $postedOrigPrice;
            if ($finalOrigPrice === null && $postedPrice !== $prevPrice && $prevPrice > 0) {
                $finalOrigPrice = $prevPrice;
            }
            if ($finalOrigPrice !== null && $postedPrice === $finalOrigPrice) {
                $finalOrigPrice = null;
            }

            // อัปเดตข้อมูล
            $stmt = $pdo->prepare("UPDATE properties SET title = ?, type = ?, category = ?, property_category = ?, price = ?, original_price = ?, location = ?, lat = ?, lng = ?, contact_name = ?, phone = ?, line_id = ?, description = ?, bedrooms = ?, bathrooms = ?, area_sqm = ?, land_area = ?, parking_spaces = ?, floor_number = ?, furnished = ?, amenities = ?, nearby_places = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $type, $category, $category, $postedPrice, $finalOrigPrice, $location, $mapCoords[0] ?? null, $mapCoords[1] ?? null, $contact_name, $phone, $lineId, $description, $bedrooms, $bathrooms, $area_sqm, $land_area, $parking_spaces, $floor_number, $furnished, $amenities, $nearby_places, $status, $id]);
            set_flash('อัปเดตข้อมูลประกาศเรียบร้อยแล้ว');

            // แจ้งเตือนเมื่อราคาลดลง
            if ($finalOrigPrice !== null && $postedPrice < $finalOrigPrice) {
                require_once __DIR__ . '/../includes/notifications.php';
                $diff = $finalOrigPrice - $postedPrice;
                $pct = round(($diff / $finalOrigPrice) * 100);
                $titleNotif = '📉 ปรับลดราคาพิเศษ! ' . mb_substr($title, 0, 45) . ' (ลด ฿' . number_format($diff) . ')';
                $msgNotif = 'ประกาศ "' . $title . '" ในทำเล ' . $location . ' ปรับลดราคาจาก ฿' . number_format($finalOrigPrice) . ' เหลือเพียง ฿' . number_format($postedPrice) . ' (-' . $pct . '%) รีบดูรายละเอียดก่อนปิดการขาย!';

                send_user_notification(0, 'price_drop', $titleNotif, $msgNotif, 'property.php?id=' . $id, $id, '📉');

                $favUsers = $pdo->prepare('SELECT user_id FROM property_favorites WHERE property_id = ?');
                $favUsers->execute([$id]);
                foreach ($favUsers->fetchAll(PDO::FETCH_COLUMN) as $fUid) {
                    send_user_notification(
                        (int)$fUid,
                        'price_drop',
                        '❤️ ทรัพย์ที่คุณบันทึกไว้ปรับลดราคา!',
                        'ทรัพย์ที่คุณเคยกดหัวใจไว้ "' . $title . '" ลดราคาลง ฿' . number_format($diff) . ' เหลือ ฿' . number_format($postedPrice) . ' รีบเข้าไปตรวจสอบด่วน!',
                        'property.php?id=' . $id,
                        $id,
                        '🔥'
                    );
                }
            }
        } else {
            // เพิ่มข้อมูลใหม่ (อนุมัติอัตโนมัติเมื่อเพิ่มจากหลังบ้าน)
            $stmt = $pdo->prepare("INSERT INTO properties (title, type, category, property_category, price, original_price, location, lat, lng, contact_name, phone, line_id, description, bedrooms, bathrooms, area_sqm, land_area, parking_spaces, floor_number, furnished, amenities, nearby_places, status, approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([$title, $type, $category, $category, $postedPrice, $postedOrigPrice, $location, $mapCoords[0] ?? null, $mapCoords[1] ?? null, $contact_name, $phone, $lineId, $description, $bedrooms, $bathrooms, $area_sqm, $land_area, $parking_spaces, $floor_number, $furnished, $amenities, $nearby_places, $status]);
            $id = $pdo->lastInsertId();
            set_flash('เพิ่มประกาศใหม่เรียบร้อยแล้ว');
        }

        // จัดการลบรูปภาพเดิมที่แอดมินเลือกติ๊ก 
        if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_id = (int)$img_id;
                $st_img = $pdo->prepare("SELECT filename FROM property_images WHERE id = ? AND property_id = ?");
                $st_img->execute([$img_id, $id]);
                $filename = $st_img->fetchColumn();
                if ($filename) {
                    if (function_exists('delete_image_file')) {
                        delete_image_file($filename);
                    } else {
                        @unlink(UPLOAD_DIR . $filename);
                    }
                    $pdo->prepare("DELETE FROM property_images WHERE id = ?")->execute([$img_id]);
                }
            }
        }

        // จัดการอัปโหลดรูปภาพเพิ่มเติม (ถ้ามีเลือกไฟล์มา)
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['images']['name'][$key];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $new_filename = uniqid('prop_', true) . '.' . $ext;
                        if (move_uploaded_file($tmp_name, UPLOAD_DIR . $new_filename)) {
                            $sort_order = (int)$pdo->query("SELECT IFNULL(MAX(sort_order), 0) + 1 FROM property_images WHERE property_id = $id")->fetchColumn();
                            $pdo->prepare("INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, ?)")->execute([$id, $new_filename, $sort_order]);
                        }
                    }
                }
            }
        }

        redirect('index.php');
    }
}

// ดึงรายการรูปภาพทั้งหมดของประกาศนี้ (กรณีแก้ไข)
$images = [];
if ($id > 0) {
    $img_stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY sort_order, id");
    $img_stmt->execute([$id]);
    $images = $img_stmt->fetchAll();
}

$flash = get_flash();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id > 0 ? e(t('แก้ไขประกาศ', 'Edit Listing')) : e(t('เพิ่มประกาศใหม่', 'Add New Listing')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .edit-page-wrap { max-width: 860px; margin: 32px auto 70px; padding: 0 20px; }
    .edit-page-box { background: #fff; padding: 36px; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(23,27,61,.06); }
    .edit-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #edf2f7; }
    .edit-page-header h1 { margin: 0; font-size: 22px; color: #251c42; }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>

<div class="edit-page-wrap">
  <div class="edit-page-box">
    <div class="edit-page-header">
      <h1><?= $id > 0 ? e(t('✏️ แก้ไขประกาศทรัพย์สิน #', '✏️ Edit Property Listing #')) . (int)$id : e(t('➕ เพิ่มประกาศทรัพย์สินใหม่', '➕ Add New Property Listing')) ?></h1>
      <a href="index.php" class="btn-action btn-gray"><?= e(t('← กลับหน้ารายการ', '← Back to Listings')) ?></a>
    </div>
    
    <?php if (!empty($errors)): ?>
      <div class="errors">
        <ul style="margin: 0; padding-left: 20px;">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">

      <div class="form-group">
        <label for="title"><?= e(t('หัวข้อประกาศ *', 'Listing Title *')) ?></label>
        <input type="text" id="title" name="title" class="form-control" value="<?= e($property['title']) ?>" placeholder="<?= e(t('เช่น ขายคอนโดหรู ใจกลางเมือง สุขุมวิท', 'e.g. Luxury Condo in Sukhumvit')) ?>" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="type"><?= e(t('ประเภทการทำธุรกรรม *', 'Transaction Type *')) ?></label>
          <select id="type" name="type" class="form-control">
            <option value="sale" <?= $property['type'] === 'sale' ? 'selected' : '' ?>><?= e(t('ขาย (Sale)', 'For Sale (Sale)')) ?></option>
            <option value="rent" <?= $property['type'] === 'rent' ? 'selected' : '' ?>><?= e(t('ให้เช่า (Rent)', 'For Rent (Rent)')) ?></option>
          </select>
        </div>

        <div class="form-group">
          <label for="category"><?= e(t('ประเภทอสังหาริมทรัพย์ *', 'Property Category *')) ?></label>
          <select id="category" name="category" class="form-control">
            <?php foreach (PROPERTY_CATEGORIES as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $property['category'] === $slug ? 'selected' : '' ?>><?= e(category_label_bi($slug)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="price"><?= e(t('ราคาปัจจุบัน (บาท) *', 'Current Price (THB) *')) ?></label>
          <input type="number" id="price" name="price" class="form-control" value="<?= e($property['price']) ?>" placeholder="e.g. 2500000" required>
        </div>

        <div class="form-group">
          <label for="original_price"><?= e(t('ราคาเดิมก่อนลด / ขีดฆ่า (บาท - ไม่บังคับ)', 'Original Price / Strikethrough (Optional)')) ?></label>
          <input type="number" id="original_price" name="original_price" class="form-control" value="<?= e($property['original_price'] ?? '') ?>" placeholder="<?= e(t('เช่น 3000000 เพื่อแสดงขีดฆ่า', 'e.g. 3000000')) ?>">
          <small style="color:#64748B; font-size:11.5px;"><?= e(t('ระบบจะสร้างราคาขีดฆ่า ป้ายลดราคา และส่งแจ้งเตือนให้อัตโนมัติ', 'Shows strikethrough, discount badge and sends price-drop alerts.')) ?></small>
        </div>
      </div>

      <div class="form-group">
        <label for="status"><?= e(t('สถานะทรัพย์สิน *', 'Property Status *')) ?></label>
        <select id="status" name="status" class="form-control">
          <option value="available" <?= $property['status'] === 'available' ? 'selected' : '' ?>><?= e(t('กำลังประกาศ (Available)', 'Available (Active)')) ?></option>
          <option value="done" <?= $property['status'] === 'done' ? 'selected' : '' ?>><?= e(t('ขาย/ปล่อยเช่าแล้ว (Done)', 'Sold / Rented (Closed)')) ?></option>
        </select>
      </div>

      <div class="form-group">
        <label for="location"><?= e(t('ทำเลที่ตั้ง / โซน *', 'Location / Area *')) ?></label>
        <input type="text" id="location" name="location" class="form-control" value="<?= e($property['location']) ?>" placeholder="<?= e(t('เช่น ถ.สุขุมวิท แขวงคลองเตย กรุงเทพฯ', 'e.g. Sukhumvit Rd, Khlong Toei, Bangkok')) ?>" required>
      </div>

      <div class="form-group">
        <label for="map_url"><?= e(t('ลิงก์ Google Maps (ไม่บังคับ)', 'Google Maps Link (Optional)')) ?></label>
        <input type="url" id="map_url" name="map_url" class="form-control" value="<?= e($property['map_url'] ?? '') ?>" placeholder="<?= e(t('วางลิงก์ Google Maps ที่ปักหมุดทรัพย์แล้ว', 'Paste Google Maps pin link here')) ?>">
      </div>

      <div class="grid-2">
        <div class="form-group"><label for="bedrooms"><?= e(t('ห้องนอน', 'Bedrooms')) ?></label><input type="number" min="0" name="bedrooms" id="bedrooms" class="form-control" value="<?= e($property['bedrooms'] ?? '') ?>"></div>
        <div class="form-group"><label for="bathrooms"><?= e(t('ห้องน้ำ', 'Bathrooms')) ?></label><input type="number" min="0" name="bathrooms" id="bathrooms" class="form-control" value="<?= e($property['bathrooms'] ?? '') ?>"></div>
        <div class="form-group"><label for="area_sqm"><?= e(t('พื้นที่ใช้สอย (ตร.ม.)', 'Floor Area (sq.m.)')) ?></label><input type="number" min="0" step="0.01" name="area_sqm" id="area_sqm" class="form-control" value="<?= e($property['area_sqm'] ?? '') ?>"></div>
        <div class="form-group"><label for="land_area"><?= e(t('พื้นที่ดิน (ตร.ว.)', 'Land Area (sq.wa)')) ?></label><input type="number" min="0" step="0.01" name="land_area" id="land_area" class="form-control" value="<?= e($property['land_area'] ?? '') ?>"></div>
        <div class="form-group"><label for="parking_spaces"><?= e(t('ที่จอดรถ (คัน)', 'Parking Spaces')) ?></label><input type="number" min="0" name="parking_spaces" id="parking_spaces" class="form-control" value="<?= e($property['parking_spaces'] ?? '') ?>"></div>
        <div class="form-group"><label for="floor_number"><?= e(t('ชั้น', 'Floor Number')) ?></label><input type="text" name="floor_number" id="floor_number" class="form-control" value="<?= e($property['floor_number'] ?? '') ?>"></div>
        <div class="form-group"><label for="furnished"><?= e(t('เฟอร์นิเจอร์', 'Furnishing')) ?></label><input type="text" name="furnished" id="furnished" class="form-control" value="<?= e($property['furnished'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label for="amenities"><?= e(t('สิ่งอำนวยความสะดวก (คั่นด้วยจุลภาค)', 'Amenities (comma-separated)')) ?></label><textarea name="amenities" id="amenities" class="form-control" rows="3"><?= e($property['amenities'] ?? '') ?></textarea></div>
      <div class="form-group"><label for="nearby_places"><?= e(t('สถานที่ใกล้เคียง (คั่นด้วยจุลภาคหรือขึ้นบรรทัดใหม่)', 'Nearby Places (comma-separated or one per line)')) ?></label><textarea name="nearby_places" id="nearby_places" class="form-control" rows="3"><?= e($property['nearby_places'] ?? '') ?></textarea></div>

      <div class="grid-2">
        <div class="form-group">
          <label for="contact_name"><?= e(t('ชื่อผู้ติดต่อ', 'Contact Name')) ?></label>
          <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= e($property['contact_name']) ?>" placeholder="<?= e(t('ชื่อเจ้าหน้าที่ หรือ เจ้าของทรัพย์', 'Staff or property owner name')) ?>">
        </div>

        <div class="form-group">
          <label for="phone"><?= e(t('เบอร์โทรศัพท์ติดต่อ', 'Contact Phone')) ?></label>
          <input type="text" id="phone" name="phone" class="form-control" value="<?= e($property['phone']) ?>" placeholder="e.g. 095-906-5867">
        </div>
        <div class="form-group">
          <label for="line_id">LINE ID (<?= e(t('ไม่บังคับ', 'Optional')) ?>)</label>
          <input type="text" id="line_id" name="line_id" maxlength="20" class="form-control" value="<?= e($property['line_id'] ?? '') ?>" placeholder="e.g. yourlineid">
        </div>
      </div>

      <div class="form-group">
        <label for="description"><?= e(t('รายละเอียดเพิ่มเติม', 'Additional Description')) ?></label>
        <textarea id="description" name="description" class="form-control" rows="6" placeholder="<?= e(t('ระบุรายละเอียด จุดเด่น สิ่งอำนวยความสะดวก ของอสังหาริมทรัพย์...', 'Property highlights, features, and key information...')) ?>"><?= e($property['description']) ?></textarea>
      </div>

      <div class="form-group">
        <label for="images"><?= e(t('อัปโหลดรูปภาพประกาศเพิ่มเติม (เลือกได้หลายรูป)', 'Upload Additional Photos (multiple allowed)')) ?></label>
        <input type="file" id="images" name="images[]" class="form-control" accept="image/*" multiple style="padding: 8px;">
        <span style="font-size: 12px; color: #64748b; margin-top: 4px; display: block;"><?= e(t('รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 5MB ต่อรูป', 'Supported formats: JPG, PNG, WEBP (max 5MB per photo)')) ?></span>
        
        <?php if (!empty($images)): ?>
          <div style="margin-top: 16px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;"><?= e(t('รูปภาพที่มีอยู่เดิม (ติ๊กเลือกเพื่อลบรูปที่ไม่ต้องการ):', 'Existing photos (check to delete):')) ?></label>
            <div class="image-preview-grid">
              <?php foreach ($images as $img): ?>
                <div class="preview-item">
                  <img src="../uploads/<?= e($img['filename']) ?>" alt="<?= e(t('รูปประกาศ', 'Listing photo')) ?>">
                  <label>
                    <input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>"> <?= e(t('ลบรูปนี้', 'Delete photo')) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div style="margin-top: 32px;">
        <button type="submit" class="btn-submit"><?= $id > 0 ? e(t('💾 บันทึกการแก้ไข', '💾 Save Changes')) : e(t('🚀 บันทึกและเผยแพร่ประกาศ', '🚀 Save & Publish Listing')) ?></button>
      </div>
    </form>

  </div>
</div>

</body>
</html>

