<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin('listings', 'create');

$pdo = db();

$errors = [];
$token = csrf_token();
$title = '';
$type = 'sale';
$category = 'condo';
$price = '';
$location = '';
$mapUrl = '';
$contact_name = '';
$phone = '';
$lineId = '';
$description = '';
$bedrooms = $bathrooms = $area_sqm = $land_area = $parking_spaces = '';
$floor_number = $furnished = $amenities = $nearby_places = '';
$status = 'available';

if (request_is_post()) {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'sale';
    $category = $_POST['category'] ?? 'condo';
    $price = trim($_POST['price'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $mapUrl = trim($_POST['map_url'] ?? '');
    $mapCoords = parse_map_coords($mapUrl);
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

    // ตรวจสอบความถูกต้องของข้อมูล
    if ($title === '') {
        $errors[] = 'กรุณากรอกหัวข้อประกาศ';
    }
    if ($price === '' || !is_numeric($price)) {
        $errors[] = 'กรุณากรอกราคาให้ถูกต้องเป็นตัวเลข';
    }
    if ($location === '') {
        $errors[] = 'กรุณากรอกทำเลที่ตั้ง';
    }
    if ($mapUrl !== '' && $mapCoords === null) {
        $errors[] = 'ลิงก์ Google Maps ไม่ถูกต้อง';
    }
    $errors = array_merge($errors, property_detail_errors($_POST));
    if (!valid_line_id($lineId)) {
        $errors[] = 'LINE ID ต้องเป็น 4-20 ตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด หรือขีดล่าง';
    }

    if (empty($errors)) {
        $uploadedVideo = null;
        if (!empty($_FILES['video']['name'])) {
            try {
                $uploadedVideo = save_uploaded_property_video($_FILES['video'] ?? []);
            } catch (RuntimeException $re) {
                $errors[] = $re->getMessage();
            }
        }
    }

    if (empty($errors)) {
        // เพิ่มข้อมูลใหม่ (เพิ่มจากหลังบ้านอนุมัติให้อัตโนมัติเป็น 'approved')
        $stmt = $pdo->prepare("INSERT INTO properties (title, type, category, property_category, price, location, lat, lng, contact_name, phone, line_id, description, bedrooms, bathrooms, area_sqm, land_area, parking_spaces, floor_number, furnished, amenities, nearby_places, status, approval, video_file, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())");
        $stmt->execute([$title, $type, $category, $category, $price, $location, $mapCoords[0] ?? null, $mapCoords[1] ?? null, $contact_name, $phone, $lineId, $description, $bedrooms, $bathrooms, $area_sqm, $land_area, $parking_spaces, $floor_number, $furnished, $amenities, $nearby_places, $status, $uploadedVideo]);
        $new_property_id = $pdo->lastInsertId();

        // จัดการอัปโหลดรูปภาพ (ถ้ามีเลือกไฟล์มา)
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['images']['name'][$key];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $new_filename = uniqid('prop_', true) . '.' . $ext;
                        if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                            // หาค่าลำดับรูปภาพ
                            $sort_order = (int)$pdo->query("SELECT IFNULL(MAX(sort_order), 0) + 1 FROM property_images WHERE property_id = " . (int)$new_property_id)->fetchColumn();
                            
                            $img_stmt = $pdo->prepare("INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, ?)");
                            $img_stmt->execute([$new_property_id, $new_filename, $sort_order]);
                        }
                    }
                }
            }
        }

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('เพิ่มประกาศใหม่', 'Add New Listing')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .add-page-wrap { max-width: 860px; margin: 32px auto 70px; padding: 0 20px; }
    .add-page-box { background: #fff; padding: 36px; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(23,27,61,.06); }
    .add-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #edf2f7; }
    .add-page-header h1 { margin: 0; font-size: 22px; color: #251c42; }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>

<div class="add-page-wrap">
  <div class="add-page-box">
    <div class="add-page-header">
      <h1><?= e(t('➕ เพิ่มประกาศทรัพย์สินใหม่', '➕ Add New Property Listing')) ?></h1>
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
        <input type="text" id="title" name="title" class="form-control" value="<?= e($title) ?>" placeholder="<?= e(t('เช่น ขายคอนโดหรู ใจกลางเมือง สุขุมวิท', 'e.g. Luxury Condo in Sukhumvit')) ?>" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="type"><?= e(t('ประเภทการทำธุรกรรม *', 'Transaction Type *')) ?></label>
          <select id="type" name="type" class="form-control">
            <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>><?= e(t('ขาย (Sale)', 'For Sale (Sale)')) ?></option>
            <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>><?= e(t('ให้เช่า (Rent)', 'For Rent (Rent)')) ?></option>
          </select>
        </div>

        <div class="form-group">
          <label for="category"><?= e(t('ประเภทอสังหาริมทรัพย์ *', 'Property Category *')) ?></label>
          <select id="category" name="category" class="form-control">
            <?php foreach (PROPERTY_CATEGORIES as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $category === $slug ? 'selected' : '' ?>><?= e(category_label_bi($slug)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="price"><?= e(t('ราคา (บาท) *', 'Price (THB) *')) ?></label>
          <input type="number" id="price" name="price" class="form-control" value="<?= e($price) ?>" placeholder="e.g. 2500000" required>
        </div>

        <div class="form-group">
          <label for="status"><?= e(t('สถานะทรัพย์สิน *', 'Property Status *')) ?></label>
          <select id="status" name="status" class="form-control">
            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>><?= e(t('กำลังประกาศ (Available)', 'Available (Active)')) ?></option>
            <option value="done" <?= $status === 'done' ? 'selected' : '' ?>><?= e(t('ขาย/ปล่อยเช่าแล้ว (Done)', 'Sold / Rented (Closed)')) ?></option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="location"><?= e(t('ทำเลที่ตั้ง / โซน *', 'Location / Area *')) ?></label>
        <input type="text" id="location" name="location" class="form-control" value="<?= e($location) ?>" placeholder="<?= e(t('เช่น ถ.สุขุมวิท แขวงคลองเตย กรุงเทพฯ', 'e.g. Sukhumvit Rd, Khlong Toei, Bangkok')) ?>" required>
      </div>

      <div class="form-group">
        <label for="map_url"><?= e(t('ลิงก์ Google Maps (ไม่บังคับ)', 'Google Maps Link (Optional)')) ?></label>
        <input type="url" id="map_url" name="map_url" class="form-control" value="<?= e($mapUrl) ?>" placeholder="<?= e(t('วางลิงก์ Google Maps ที่ปักหมุดทรัพย์แล้ว', 'Paste Google Maps pin link here')) ?>">
      </div>

      <div class="grid-2">
        <div class="form-group"><label for="bedrooms"><?= e(t('ห้องนอน', 'Bedrooms')) ?></label><input type="number" min="0" name="bedrooms" id="bedrooms" class="form-control" value="<?= e($bedrooms) ?>"></div>
        <div class="form-group"><label for="bathrooms"><?= e(t('ห้องน้ำ', 'Bathrooms')) ?></label><input type="number" min="0" name="bathrooms" id="bathrooms" class="form-control" value="<?= e($bathrooms) ?>"></div>
        <div class="form-group"><label for="area_sqm"><?= e(t('พื้นที่ใช้สอย (ตร.ม.)', 'Floor Area (sq.m.)')) ?></label><input type="number" min="0" step="0.01" name="area_sqm" id="area_sqm" class="form-control" value="<?= e($area_sqm) ?>"></div>
        <div class="form-group"><label for="land_area"><?= e(t('พื้นที่ดิน (ตร.ว.)', 'Land Area (sq.wa)')) ?></label><input type="number" min="0" step="0.01" name="land_area" id="land_area" class="form-control" value="<?= e($land_area) ?>"></div>
        <div class="form-group"><label for="parking_spaces"><?= e(t('ที่จอดรถ (คัน)', 'Parking Spaces')) ?></label><input type="number" min="0" name="parking_spaces" id="parking_spaces" class="form-control" value="<?= e($parking_spaces) ?>"></div>
        <div class="form-group"><label for="floor_number"><?= e(t('ชั้น', 'Floor Number')) ?></label><input type="text" name="floor_number" id="floor_number" class="form-control" value="<?= e($floor_number) ?>"></div>
        <div class="form-group"><label for="furnished"><?= e(t('เฟอร์นิเจอร์', 'Furnishing')) ?></label><input type="text" name="furnished" id="furnished" class="form-control" value="<?= e($furnished) ?>"></div>
      </div>
      <div class="form-group"><label for="amenities"><?= e(t('สิ่งอำนวยความสะดวก (คั่นด้วยจุลภาค)', 'Amenities (comma-separated)')) ?></label><textarea name="amenities" id="amenities" class="form-control" rows="3"><?= e($amenities) ?></textarea></div>
      <div class="form-group"><label for="nearby_places"><?= e(t('สถานที่ใกล้เคียง (คั่นด้วยจุลภาคหรือขึ้นบรรทัดใหม่)', 'Nearby Places (comma-separated or one per line)')) ?></label><textarea name="nearby_places" id="nearby_places" class="form-control" rows="3"><?= e($nearby_places) ?></textarea></div>

      <div class="grid-2">
        <div class="form-group">
          <label for="contact_name"><?= e(t('ชื่อผู้ติดต่อ', 'Contact Name')) ?></label>
          <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= e($contact_name) ?>" placeholder="<?= e(t('ชื่อเจ้าหน้าที่ หรือ เจ้าของทรัพย์', 'Staff or property owner name')) ?>">
        </div>

        <div class="form-group">
          <label for="phone"><?= e(t('เบอร์โทรศัพท์ติดต่อ', 'Contact Phone')) ?></label>
          <input type="text" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="e.g. 095-906-5867">
        </div>
        <div class="form-group">
          <label for="line_id">LINE ID (<?= e(t('ไม่บังคับ', 'Optional')) ?>)</label>
          <input type="text" id="line_id" name="line_id" maxlength="20" class="form-control" value="<?= e($lineId) ?>" placeholder="e.g. yourlineid">
        </div>
      </div>

      <div class="form-group">
        <label for="description"><?= e(t('รายละเอียดเพิ่มเติม', 'Additional Description')) ?></label>
        <textarea id="description" name="description" class="form-control" rows="6" placeholder="<?= e(t('ระบุรายละเอียด จุดเด่น สิ่งอำนวยความสะดวก ของอสังหาริมทรัพย์...', 'Property highlights, features, and key information...')) ?>"><?= e($description) ?></textarea>
      </div>

      <div class="form-group">
        <label for="images"><?= e(t('อัปโหลดรูปภาพประกาศ (เลือกได้หลายรูป)', 'Upload Listing Photos (multiple allowed)')) ?></label>
        <input type="file" id="images" name="images[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple style="padding: 10px;">
        <span style="font-size: 12px; color: #64748b; margin-top: 4px; display: block;"><?= e(t('รองรับไฟล์ JPG, JPEG, PNG, WEBP', 'Supported formats: JPG, JPEG, PNG, WEBP')) ?></span>
      </div>

      <div class="form-group" style="background: #fafbfc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-top: 20px;">
        <label for="video" style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 6px;">
          🎬 <?= e(t('วิดีโอคลิปของประกาศ (เมื่อเอาเมาส์วางที่การ์ดหน้าแรกจะเล่นพร้อมเสียง)', 'Property Video Clip (Plays with sound on hover on homepage)')) ?>
        </label>
        <span style="font-size: 12px; color: #64748b; margin-bottom: 8px; display: block;"><?= e(t('รองรับไฟล์ MP4, WebM หรือ MOV ขนาดไม่เกิน 50 MB', 'Supports MP4, WebM or MOV up to 50 MB')) ?></span>
        <input type="file" id="video" name="video" class="form-control" accept="video/mp4,video/webm,video/quicktime" style="padding: 10px;">
      </div>

      <div style="margin-top: 32px;">
        <button type="submit" class="btn-submit"><?= e(t('🚀 บันทึกและเผยแพร่ประกาศทันที', '🚀 Save & Publish Listing Instantly')) ?></button>
      </div>

    </form>

  </div>
</div>

</body>
</html>

