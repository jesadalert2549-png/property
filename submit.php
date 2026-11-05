<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/site_shell.php';

start_session();
current_lang();
if (empty($_SESSION['user_id'])) {
    redirect('login.php?return_to=submit.php');
}

$errors = [];
$submissionResult = null;
if (request_method() === 'GET') {
    if (($_GET['posted'] ?? '') === '1') {
        $submissionResult = $_SESSION['listing_submission_result'] ?? null;
    } else {
        unset($_SESSION['listing_submission_result']);
    }
} else {
    unset($_SESSION['listing_submission_result']);
}
$success = is_array($submissionResult) && (int)($submissionResult['id'] ?? 0) > 0;
$publishedId = $success ? (int)$submissionResult['id'] : 0;
$publishedWithFlag = $success && !empty($submissionResult['flagged']);
$profileStatement = db()->prepare('SELECT full_name, phone, line_id FROM users WHERE id = ? LIMIT 1');
$profileStatement->execute([(int)$_SESSION['user_id']]);
$profile = $profileStatement->fetch() ?: [];
$old = [
    'contact_name' => $profile['full_name'] ?? '',
    'phone' => $profile['phone'] ?? '',
    'line_id' => $profile['line_id'] ?? '',
];

if (request_is_post()) {
    csrf_check();

    if (!empty($_POST['website'])) {
        $errors[] = t('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', 'An error occurred. Please try again.');
    }
    if (empty($_POST['agree'])) {
        $errors[] = t('กรุณายอมรับข้อตกลงการใช้งานและนโยบายความเป็นส่วนตัว', 'Please accept the Terms of Use and Privacy Policy.');
    }

    [$data, $inputErrors] = read_listing_input($_POST);
    $errors = array_merge($errors, $inputErrors);
    $errors = array_merge($errors, listing_completeness_errors($data, $_POST));
    $old = array_merge($old, $_POST);

    $fileCount = count_selected_files($_FILES['images'] ?? []);
    if ($fileCount < 1) {
        $errors[] = t('กรุณาอัปโหลดรูปทรัพย์อย่างน้อย 1 รูป', 'Please upload at least 1 property photo.');
    } elseif ($fileCount > MAX_IMAGES) {
        $errors[] = t('อัปโหลดได้สูงสุด ' . MAX_IMAGES . ' รูป', 'You can upload up to ' . MAX_IMAGES . ' photos.');
    }

    if (!$errors) {
        $rateLimit = db()->prepare('SELECT COUNT(*) FROM properties WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $rateLimit->execute([(int)$_SESSION['user_id']]);
        if ((int)$rateLimit->fetchColumn() >= 5) {
            $errors[] = t('บัญชีนี้ลงประกาศครบ 5 รายการใน 1 ชั่วโมงแล้ว กรุณาลองใหม่ภายหลัง', 'This account has already posted 5 listings within 1 hour. Please try again later.');
        }
    }

    if (!$errors) {
        $uploadedImages = [];
        $stage = 'upload';
        try {
            $uploadedImages = save_uploaded_images($_FILES['images'] ?? [], MAX_IMAGES);
            if (count($uploadedImages) < 1) {
                foreach ($uploadedImages as $file) delete_image_file($file);
                $uploadedImages = [];
                $errors[] = t('ไฟล์รูปไม่ถูกต้อง กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB', 'Invalid image file. Please choose a JPG, PNG or WebP image no larger than 5 MB.');
            } else {
                $stage = 'database';
                $pdo = db();
                $pdo->beginTransaction();

                $userId = (int)$_SESSION['user_id'];
                $duplicate = $pdo->prepare("SELECT id FROM properties WHERE approval = 'approved' AND LOWER(title) = LOWER(?) AND LOWER(location) = LOWER(?) ORDER BY id DESC LIMIT 1");
                $duplicate->execute([$data['title'], $data['location']]);
                $duplicateId = $duplicate->fetchColumn();
                $verificationStatus = $duplicateId ? 'flagged' : 'passed';
                $verificationNotes = $duplicateId ? t('พบประกาศที่มีหัวข้อและทำเลคล้ายกัน (#' . (int)$duplicateId . ') โปรดตรวจสอบข้อมูลอีกครั้ง', 'A listing with a similar title and location was found (#' . (int)$duplicateId . '). Please review the information again.') : null;

                $stmt = $pdo->prepare(
                    "INSERT INTO properties
                        (title, type, category, property_category, status, approval, verification_status, verification_notes,
                         price, location, lat, lng, description, bedrooms, bathrooms, area_sqm, land_area, parking_spaces, floor_number, furnished,
                         amenities, nearby_places, contact_name, phone, line_id, user_id)
                     VALUES (?, ?, ?, ?, 'available', 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data['title'], $data['type'], $data['property_category'], $data['property_category'],
                    $verificationStatus, $verificationNotes, $data['price'], $data['location'], $data['lat'], $data['lng'], $data['description'],
                    $data['bedrooms'], $data['bathrooms'], $data['area_sqm'], $data['land_area'], $data['parking_spaces'], $data['floor_number'],
                    $data['furnished'], $data['amenities'], $data['nearby_places'], $data['contact_name'], $data['phone'], $data['line_id'], $userId,
                ]);
                $propertyId = (int)$pdo->lastInsertId();

                $imgStmt = $pdo->prepare('INSERT INTO property_images (property_id, filename, sort_order) VALUES (?, ?, ?)');
                foreach ($uploadedImages as $order => $file) {
                    $imgStmt->execute([$propertyId, $file, $order]);
                }
                $pdo->commit();
                $_SESSION['listing_submission_result'] = [
                    'id' => $propertyId,
                    'flagged' => (bool)$duplicateId,
                ];
                redirect('submit.php?posted=1');
            }

        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($uploadedImages as $file) {
                delete_image_file($file);
            }
            error_log('Listing submission failed during ' . $stage . ': ' . $ex->getMessage());
            $errors[] = $stage === 'upload'
                ? t('บันทึกรูปภาพไม่สำเร็จ กรุณาตรวจสิทธิ์เขียนโฟลเดอร์ uploads และลองใหม่', 'Failed to save images. Please check write permissions for the uploads folder and try again.')
                : t('บันทึกประกาศไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หากยังพบปัญหาโปรดแจ้งผู้ดูแลระบบ', 'Failed to save the listing. Please try again. If the problem persists, please contact the administrator.');
        }
    }
}

$token = csrf_token();
$v = fn(string $key) => e($old[$key] ?? '');
$cat = $old['property_category'] ?? '';
// ค่าเดิมภาษาไทยเก็บใน DB คงเดิม (ไม่กระทบ backend) — แสดง label ตามภาษาที่เลือก
$furnishedLabelsEn = [
    'เฟอร์นิเจอร์ครบ' => 'Fully furnished',
    'เฟอร์นิเจอร์บางส่วน' => 'Partially furnished',
    'ไม่มีเฟอร์นิเจอร์' => 'Unfurnished',
];
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('ลงประกาศฟรี', 'Post a Free Listing')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <?php site_controls_head(); lang_toggle_head(); ?>
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <?= render_logo_html('index.php') ?>
    <nav class="site-nav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
      <a href="index.php"><?= e(t('หน้าแรก', 'Home')) ?></a>
      <?php render_nav_dropdowns(); ?>
      <a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
      <a href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'admin/index.php' : 'dashboard.php' ?>"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a><a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
      <?php else: ?>
        <a href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
      <?php endif; ?>
      <?php render_lang_toggle(); ?>
    </nav>
  </div>
</header>

<main class="section">
  <div class="container narrow listing-page">
    <?php if ($success): ?>
      <div class="form-box center">
        <span class="listing-success-mark" aria-hidden="true">✓</span>
        <h1 class="section-title"><?= e(t('ประกาศของคุณเผยแพร่แล้ว', 'Your listing is now live')) ?></h1>
        <?php if ($publishedWithFlag): ?>
          <p><?= e(t('ระบบพบประกาศที่มีชื่อและทำเลคล้ายกัน จึงติดธงให้ผู้ดูแลตรวจสอบเพิ่มเติม ประกาศของคุณยังแสดงบนเว็บไซต์แล้ว', 'The system found a listing with a similar title and location, so it has been flagged for additional review by our team. Your listing is already displayed on the website.')) ?></p>
        <?php else: ?>
          <p><?= e(t('ข้อมูลและรูปภาพผ่านการตรวจความครบถ้วนเบื้องต้น และแสดงบนเว็บไซต์ได้ทันที', 'Your information and photos passed the initial completeness check and are now visible on the website.')) ?></p>
        <?php endif; ?>
        <p>
          <a href="property.php?id=<?= (int)$publishedId ?>" class="btn"><?= e(t('ดูประกาศของฉัน', 'View my listing')) ?></a>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn"><?= e(t('กลับสู่พื้นที่ส่วนตัว', 'Back to my dashboard')) ?></a>
          <?php else: ?>
            <a href="index.php" class="btn"><?= e(t('กลับหน้าแรก', 'Back to home page')) ?></a>
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <div class="form-box">
        <div class="listing-intro">
          <span class="listing-eyebrow"><?= e(t('ลงประกาศกับ Standard Property', 'List with Standard Property')) ?></span>
          <h1><?= e(t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')) ?></h1>
          <p><?= e(t('กรอกข้อมูลจำเป็นไม่กี่ช่อง แล้วเพิ่มรูปทรัพย์อย่างน้อย 1 รูป รายละเอียดอื่นเติมได้ตามสะดวก', 'Fill in just a few required fields, then add at least 1 photo of the property. Everything else is optional.')) ?></p>
        </div>
        <div class="listing-progress" aria-label="<?= e(t('ขั้นตอนการลงประกาศ', 'Listing steps')) ?>">
          <span><b>1</b> <?= e(t('ข้อมูลและพิกัด', 'Details & location')) ?></span><i></i>
          <span><b>2</b> <?= e(t('รายละเอียดทรัพย์', 'Property details')) ?></span><i></i>
          <span><b>3</b> <?= e(t('รูปจริงและติดต่อ', 'Photos & contact')) ?></span>
        </div>
        <p class="listing-required-note"><span aria-hidden="true">*</span> <?= e(t('ช่องจำเป็นมีเครื่องหมายดอกจัน รายละเอียดอื่นกรอกเพิ่มได้ตามสะดวก · เพื่อป้องกันสแปม บัญชีลงได้ไม่เกิน 5 ประกาศต่อชั่วโมง', 'Required fields are marked with an asterisk. Everything else is optional · To prevent spam, each account can post up to 5 listings per hour.')) ?></p>

        <?php if ($errors): ?>
          <div class="alert error" id="listingFormErrors" role="alert" tabindex="-1" aria-live="assertive">
            <?php foreach ($errors as $err): ?>
              <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-grid listing-form">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">

          <div class="hp" aria-hidden="true">
            <label><?= e(t('เว็บไซต์', 'Website')) ?></label>
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>01</span><div><h2><?= e(t('ข้อมูลอสังหาริมทรัพย์', 'Property information')) ?></h2><p><?= e(t('ข้อมูลหลักที่จะแสดงบนหน้าประกาศ', 'The key details shown on your listing page')) ?></p></div></div>
            <div class="listing-form-fields">
              <label><?= e(t('ประเภทอสังหาฯ', 'Property type')) ?> *
                <select name="property_category" id="propertyCategory" required>
                  <option value=""><?= e(t('เลือกประเภทอสังหาฯ', 'Select property type')) ?></option>
                  <?php foreach (PROPERTY_CATEGORIES as $key => $label):
                    if ($key === 'other') continue; ?>
                    <option value="<?= e($key) ?>" <?= $cat === $key ? 'selected' : '' ?>><?= e(is_en() ? (property_category_en_label($key) ?? $label) : $label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><?= e(t('หัวข้อประกาศ', 'Listing title')) ?> *
                <input type="text" name="title" minlength="5" value="<?= $v('title') ?>" placeholder="<?= e(t('เช่น คอนโดใกล้ BTS อโศก', 'e.g. Condo near BTS Asok')) ?>" required>
              </label>
              <fieldset class="listing-type-field field-full">
                <legend><?= e(t('ต้องการ', 'Looking to')) ?> *</legend>
                <div class="listing-type-options">
                  <label><input type="radio" name="type" value="sale" <?= ($old['type'] ?? '') !== 'rent' ? 'checked' : '' ?>> <?= e(t('ขาย', 'For sale')) ?></label>
                  <label><input type="radio" name="type" value="rent" <?= ($old['type'] ?? '') === 'rent' ? 'checked' : '' ?>> <?= e(t('ให้เช่า', 'For rent')) ?></label>
                </div>
              </fieldset>
              <label><?= e(t('ราคา (บาท)', 'Price (THB)')) ?> *
                <input type="text" inputmode="numeric" name="price" value="<?= $v('price') ?>" placeholder="<?= e(t('เช่น 3500000', 'e.g. 3500000')) ?>" required>
              </label>
              <label class="field-full"><?= e(t('ทำเล / ที่อยู่', 'Location / Address')) ?> *
                <input type="text" name="location" value="<?= $v('location') ?>" placeholder="<?= e(t('เช่น สุขุมวิท 39, กรุงเทพฯ', 'e.g. Sukhumvit 39, Bangkok')) ?>" required>
              </label>
              <label><?= e(t('ห้องนอน', 'Bedrooms')) ?>
                <input type="number" name="bedrooms" id="bedrooms" min="0" max="99" value="<?= $v('bedrooms') ?>" placeholder="<?= e(t('เช่น 2', 'e.g. 2')) ?>">
              </label>
              <label><?= e(t('ห้องน้ำ', 'Bathrooms')) ?>
                <input type="number" name="bathrooms" id="bathrooms" min="0" max="99" value="<?= $v('bathrooms') ?>" placeholder="<?= e(t('เช่น 2', 'e.g. 2')) ?>">
              </label>
              <label><?= e(t('พื้นที่ใช้สอย (ตร.ม.)', 'Floor area (sq.m.)')) ?>
                <input type="number" name="area_sqm" id="area_sqm" min="0" step="0.01" value="<?= $v('area_sqm') ?>" placeholder="<?= e(t('เช่น 85.5', 'e.g. 85.5')) ?>">
              </label>
              <label><?= e(t('พื้นที่ดิน (ตร.ว.)', 'Land area (sq.wa)')) ?>
                <input type="number" name="land_area" id="land_area" min="0" step="0.01" value="<?= $v('land_area') ?>" placeholder="<?= e(t('เช่น 50', 'e.g. 50')) ?>">
              </label>
              <label><?= e(t('ที่จอดรถ (คัน)', 'Parking spaces')) ?>
                <input type="number" name="parking_spaces" id="parking_spaces" min="0" max="99" value="<?= $v('parking_spaces') ?>" placeholder="<?= e(t('ไม่บังคับ', 'Optional')) ?>">
              </label>
              <label><?= e(t('ชั้น', 'Floor')) ?>
                <input type="text" name="floor_number" id="floor_number" value="<?= $v('floor_number') ?>" placeholder="<?= e(t('เช่น 12 หรือ 1-2', 'e.g. 12 or 1-2')) ?>">
              </label>
              <label><?= e(t('เฟอร์นิเจอร์', 'Furnishing')) ?>
                <select name="furnished" id="furnished">
                  <option value=""><?= e(t('เลือกสถานะเฟอร์นิเจอร์', 'Select furnishing status')) ?></option>
                  <?php foreach ($furnishedLabelsEn as $furnishedValue => $furnishedLabel):
                    $furnishedDisplay = is_en() ? $furnishedLabel : $furnishedValue; ?>
                    <option value="<?= e($furnishedValue) ?>" <?= ($old['furnished'] ?? '') === $furnishedValue ? 'selected' : '' ?>><?= e($furnishedDisplay) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field-full"><?= e(t('สิ่งอำนวยความสะดวก (ไม่บังคับ)', 'Amenities (optional)')) ?>
                <textarea name="amenities" rows="3" placeholder="<?= e(t('เช่น สระว่ายน้ำ, ฟิตเนส, รปภ.', 'e.g. Swimming pool, gym, 24-hour security')) ?>"><?= $v('amenities') ?></textarea>
              </label>
              <label class="field-full"><?= e(t('สถานที่ใกล้เคียง (ไม่บังคับ)', 'Nearby places (optional)')) ?>
                <textarea name="nearby_places" rows="3" placeholder="<?= e(t('เช่น BTS อโศก, โรงเรียน, ห้างสรรพสินค้า', 'e.g. BTS Asok, schools, shopping malls')) ?>"><?= $v('nearby_places') ?></textarea>
              </label>
              <div class="field-full listing-map-picker">
                <div class="listing-map-heading">
                  <div><strong><?= e(t('ตำแหน่งบนแผนที่', 'Map location')) ?> <span>(<?= e(t('ไม่บังคับ', 'optional')) ?>)</span></strong><small><?= e(t('เปิดแผนที่แล้วแตะเพื่อปักหมุด หรือลากหมุดไปยังตำแหน่งทรัพย์', 'Open the map, then tap to drop a pin or drag it to the property position')) ?></small></div>
                  <button type="button" class="listing-map-toggle" id="listingMapToggle" aria-expanded="false" aria-controls="listingMapPanel"><?= e(t('เปิดแผนที่', 'Open map')) ?></button>
                </div>
                <div id="listingMapPanel" class="listing-map-panel" hidden>
                  <div id="listingMap" class="listing-map" role="application" aria-label="<?= e(t('แผนที่เลือกตำแหน่งทรัพย์', 'Map for selecting the property position')) ?>"></div>
                  <p class="listing-map-help"><?= e(t('เลื่อนและซูมแผนที่ได้ จากนั้นแตะตำแหน่งเพื่อวางหมุด สามารถลากหมุดเพื่อปรับตำแหน่งได้', 'Pan and zoom the map, then tap a position to place the pin. You can also drag the pin to adjust it.')) ?></p>
                </div>
                <div class="listing-map-selection">
                  <span id="listingMapStatus" role="status" aria-live="polite"><?= e(t('ยังไม่ได้เลือกตำแหน่ง · สามารถลงประกาศโดยไม่เลือกแผนที่ได้', 'No position selected · You can post a listing without choosing a map position')) ?></span>
                  <button type="button" id="listingMapClear" class="listing-map-clear" hidden><?= e(t('ล้างหมุด', 'Clear pin')) ?></button>
                </div>
                <input type="hidden" name="map_lat" id="listingMapLat" value="<?= e((string)($old['map_lat'] ?? '')) ?>">
                <input type="hidden" name="map_lng" id="listingMapLng" value="<?= e((string)($old['map_lng'] ?? '')) ?>">
              </div>
            </div>
          </section>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>02</span><div><h2><?= e(t('รายละเอียดประกาศ', 'Listing description')) ?></h2><p><?= e(t('บอกจุดเด่นของทรัพย์เพื่อช่วยให้ผู้สนใจตัดสินใจ', 'Highlight what makes the property special to help buyers decide')) ?></p></div></div>
            <div class="listing-form-fields">
              <label class="field-full"><?= e(t('รายละเอียดเพิ่มเติม', 'Description')) ?> *
                <textarea name="description" minlength="20" rows="5" required placeholder="<?= e(t('บอกจุดเด่นหรือรายละเอียดที่ผู้สนใจควรรู้ อย่างน้อย 20 ตัวอักษร', 'Describe the highlights or details interested buyers should know. At least 20 characters.')) ?>"><?= $v('description') ?></textarea>
              </label>
            </div>
          </section>

          <section class="listing-form-section">
            <div class="listing-section-heading"><span>03</span><div><h2><?= e(t('รูปภาพและข้อมูลติดต่อ', 'Photos &amp; contact information')) ?></h2><p><?= e(t('รูปภาพและเบอร์โทรจะแสดงในหน้ารายละเอียดประกาศ', 'Photos and phone number are shown on the listing page')) ?></p></div></div>
            <div class="listing-form-fields">
              <label class="listing-upload field-full">
                <strong><?= e(t('เพิ่มรูปภาพอสังหาริมทรัพย์', 'Add property photos')) ?> *</strong>
                <small><?= e(t('อย่างน้อย 1 รูป สูงสุด ', 'At least 1 photo, up to ')) ?><?= MAX_IMAGES ?><?= e(t(' รูป · JPG, PNG หรือ WebP · ไม่เกิน 5 MB ต่อรูป', ' · JPG, PNG or WebP · Max 5 MB each')) ?></small>
                <input id="listingImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
              </label>
              <div id="listingImagePreview" class="listing-image-preview field-full" aria-live="polite"></div>
              <label><?= e(t('ชื่อผู้ติดต่อ', 'Contact name')) ?> *
                <input type="text" name="contact_name" value="<?= $v('contact_name') ?>" placeholder="<?= e(t('ชื่อที่ให้ผู้สนใจติดต่อ', 'Name for interested buyers to contact')) ?>" required>
                <small><?= e(t('เติมชื่อจากบัญชีของคุณให้แล้ว แก้ไขได้ก่อนส่ง', 'Pre-filled from your account. You can edit it before submitting.')) ?></small>
              </label>
              <label><?= e(t('เบอร์โทรศัพท์', 'Phone number')) ?> *
                <input type="tel" name="phone" value="<?= $v('phone') ?>" placeholder="095-906-5867" required>
              </label>
              <label><?= e(t('LINE ID (ไม่บังคับ)', 'LINE ID (optional)')) ?>
                <input type="text" name="line_id" maxlength="20" value="<?= $v('line_id') ?>" placeholder="<?= e(t('เช่น yourlineid', 'e.g. yourlineid')) ?>">
              </label>
            </div>
          </section>

          <div class="alert" role="note"><?= e(t('ระบบตรวจความครบถ้วนของข้อมูล รูปภาพ พิกัด และประกาศซ้ำ แล้วเผยแพร่ทันทีเมื่อผ่านเกณฑ์ การตรวจนี้ไม่สามารถยืนยันกรรมสิทธิ์หรือรับรองว่าทรัพย์มีอยู่จริงได้ โปรดกรอกข้อมูลตามจริงและใส่ภาพถ่ายของทรัพย์', 'The system checks completeness of information, photos, coordinates and duplicates, then publishes immediately once approved. This check cannot verify ownership or confirm that the property exists. Please provide accurate information and upload real photos of the property.')) ?></div>

          <label class="check listing-agreement">
            <input type="checkbox" name="agree" value="1" required>
            <span><?= e(t('ข้าพเจ้ายืนยันว่าเป็นเจ้าของทรัพย์หรือได้รับอนุญาตให้ลงประกาศ ข้อมูลและรูปภาพเป็นความจริง และยอมรับข้อตกลงการใช้งานกับนโยบายความเป็นส่วนตัว', 'I confirm that I am the owner of this property or have permission to list it. The information and photos are accurate, and I accept the Terms of Use and Privacy Policy.')) ?></span>
          </label>

          <button type="submit" class="btn btn-block listing-submit"><?= e(t('ตรวจข้อมูลและเผยแพร่ทันที', 'Review & publish now')) ?></button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php
render_page_cta([
    'title'   => t('ประกาศเสร็จแล้ว? ลองดูคู่มือทำเลยอดนิยม', 'Listing posted? Explore popular location guides'),
    'text'    => t('เรียนรู้ว่าทำเลแบบไหนปล่อยเช่าได้ดี หรือเริ่มดูราคาตลาดในหมวดยอดนิยมของเรา', 'Learn which locations rent out well, or start comparing market prices in our popular categories'),
    'primary' => ['index.php', t('กลับหน้าแรก', 'Back to home page')],
    'ghost'   => ['dashboard.php', t('จัดการประกาศของฉัน', 'Manage my listings')],
]);
?>

<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>
<script>
  const imageInput = document.getElementById('listingImages');
  const imagePreview = document.getElementById('listingImagePreview');
  if (imageInput && imagePreview) {
    let previewUrls = [];
    imageInput.addEventListener('change', () => {
      imageInput.setCustomValidity(imageInput.files.length < 1 ? '<?= e(t('กรุณาเลือกรูปทรัพย์อย่างน้อย 1 รูป', 'Please select at least 1 property photo.')) ?>' : '');
      previewUrls.forEach(url => URL.revokeObjectURL(url));
      previewUrls = [];
      imagePreview.replaceChildren();

      Array.from(imageInput.files || []).slice(0, <?= MAX_IMAGES ?>).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const image = document.createElement('img');
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        image.src = url;
        image.alt = file.name;
        imagePreview.append(image);
      });
    });
  }
  const propertyCategory = document.getElementById('propertyCategory');
  // Optional property details remain optional for every category.
  const mapToggle = document.getElementById('listingMapToggle');
  const mapPanel = document.getElementById('listingMapPanel');
  const mapElement = document.getElementById('listingMap');
  const mapLatInput = document.getElementById('listingMapLat');
  const mapLngInput = document.getElementById('listingMapLng');
  const mapStatus = document.getElementById('listingMapStatus');
  const mapClear = document.getElementById('listingMapClear');
  let listingMap = null;
  let listingMarker = null;
  const savedLat = Number(mapLatInput?.value);
  const savedLng = Number(mapLngInput?.value);
  const hasSavedCoords = mapLatInput?.value !== '' && mapLngInput?.value !== '' && Number.isFinite(savedLat) && Number.isFinite(savedLng) && Math.abs(savedLat) <= 90 && Math.abs(savedLng) <= 180;
  if (hasSavedCoords) {
    mapStatus.textContent = `<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${savedLat.toFixed(6)}, ${savedLng.toFixed(6)}`;
    mapClear.hidden = false;
  }
  const updateMapSelection = (lat, lng) => {
    const coords = [lat, lng];
    mapLatInput.value = Number(lat).toFixed(6);
    mapLngInput.value = Number(lng).toFixed(6);
    if (!listingMarker) {
      listingMarker = L.marker(coords, { draggable: true }).addTo(listingMap);
      listingMarker.on('dragend', () => {
        const point = listingMarker.getLatLng();
        updateMapSelection(point.lat, point.lng);
      });
    } else {
      listingMarker.setLatLng(coords);
    }
    mapStatus.textContent = `<?= e(t('เลือกตำแหน่งแล้ว', 'Position selected')) ?> · ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
    mapClear.hidden = false;
  };
  const openListingMap = () => {
    mapPanel.hidden = false;
    mapToggle.setAttribute('aria-expanded', 'true');
    mapToggle.textContent = '<?= e(t('ปิดแผนที่', 'Close map')) ?>';
    if (typeof L === 'undefined') {
      mapStatus.textContent = '<?= e(t('โหลดแผนที่ไม่สำเร็จ คุณยังลงประกาศได้โดยไม่เลือกตำแหน่ง', 'Failed to load the map. You can still post a listing without selecting a position.')) ?>';
      return;
    }
    const selectedLat = Number(mapLatInput.value);
    const selectedLng = Number(mapLngInput.value);
    const hasSelection = mapLatInput.value !== '' && mapLngInput.value !== '' && Number.isFinite(selectedLat) && Number.isFinite(selectedLng) && Math.abs(selectedLat) <= 90 && Math.abs(selectedLng) <= 180;
    if (!listingMap) {
      listingMap = L.map(mapElement, { scrollWheelZoom: false }).setView(hasSelection ? [selectedLat, selectedLng] : [13.2, 101.0], hasSelection ? 15 : 6);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
      }).addTo(listingMap);
      listingMap.on('click', event => updateMapSelection(event.latlng.lat, event.latlng.lng));
      if (hasSelection) updateMapSelection(selectedLat, selectedLng);
    }
    requestAnimationFrame(() => listingMap.invalidateSize());
  };
  mapToggle?.addEventListener('click', () => {
    if (mapPanel.hidden) {
      openListingMap();
    } else {
      mapPanel.hidden = true;
      mapToggle.setAttribute('aria-expanded', 'false');
      mapToggle.textContent = '<?= e(t('เปิดแผนที่', 'Open map')) ?>';
    }
  });
  mapClear?.addEventListener('click', () => {
    mapLatInput.value = '';
    mapLngInput.value = '';
    listingMarker?.remove();
    listingMarker = null;
    mapStatus.textContent = '<?= e(t('ยังไม่ได้เลือกตำแหน่ง · สามารถลงประกาศโดยไม่เลือกแผนที่ได้', 'No position selected · You can post a listing without choosing a map position')) ?>';
    mapClear.hidden = true;
  });
  const listingErrors = document.getElementById('listingFormErrors');
  if (listingErrors) {
    requestAnimationFrame(() => {
      listingErrors.focus({ preventScroll: true });
      listingErrors.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
</script>
</body>
</html>
