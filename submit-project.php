<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
current_lang();

$pdo = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$isLoggedIn = $userId > 0;
$currentUser = null;
if ($isLoggedIn) {
    $userStatement = $pdo->prepare('SELECT id, username, email, full_name, role, is_banned, ban_reason, can_post_project FROM users WHERE id = ? LIMIT 1');
    $userStatement->execute([$userId]);
    $currentUser = $userStatement->fetch() ?: null;
    $isLoggedIn = $currentUser !== null;
    if (!$isLoggedIn) $userId = 0;
    elseif (!empty($currentUser['is_banned'])) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash_error'] = t('บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ', 'Your account has been suspended. Please contact support.');
        redirect('login.php');
    }
}

$errors = [];
$existingEmail = false;
$values = [
    'title' => '', 'developer' => $currentUser['full_name'] ?? '', 'category' => '',
    'project_status' => 'เปิดขาย / เปิดจอง', 'completion_year' => '', 'unit_count' => '',
    'price_from' => '', 'price_to' => '', 'location' => '', 'address' => '',
    'description' => '', 'amenities' => '', 'nearby_places' => '', 'map_url' => '',
    'contact_name' => $currentUser['full_name'] ?? '', 'phone' => '', 'line_id' => '',
];

if (request_is_post()) {
    csrf_check();
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string)($_POST[$field] ?? $values[$field]));
    }

    $ownerName = trim((string)($_POST['owner_name'] ?? ($currentUser['full_name'] ?? '')));
    $email = strtolower(trim((string)($_POST['email'] ?? ($currentUser['email'] ?? ''))));
    $password = (string)($_POST['password'] ?? '');
    $privacyAccepted = $isLoggedIn || !empty($_POST['privacy_accept']);

    if (!$isLoggedIn) {
        if (mb_strlen($ownerName) < 2 || mb_strlen($ownerName) > 120) $errors[] = t('กรุณากรอกชื่อผู้สมัคร 2-120 ตัวอักษร', 'Please enter an applicant name of 2-120 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('กรุณากรอกอีเมลให้ถูกต้อง', 'Please enter a valid email address.');
        if (strlen($password) < 8) $errors[] = t('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร', 'Password must be at least 8 characters.');
        if (!$privacyAccepted) $errors[] = t('กรุณายอมรับนโยบายความเป็นส่วนตัวเพื่อสร้างบัญชี', 'Please accept the Privacy Policy to create an account.');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $emailCheck->execute([$email]);
            $existingEmail = (bool)$emailCheck->fetchColumn();
            if ($existingEmail) $errors[] = t('อีเมลนี้มีบัญชีแล้ว กรุณาเข้าสู่ระบบก่อนส่งโครงการ', 'An account with this email already exists. Please log in before submitting a project.');
        }
    }
    if (empty($_POST['confirm_project_rights'])) $errors[] = t('กรุณายืนยันว่ามีสิทธิ์ส่งข้อมูลและรูปโครงการนี้', 'Please confirm you have the right to submit this project\'s information and photos.');

    if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 180) $errors[] = t('กรุณากรอกชื่อโครงการ 3-180 ตัวอักษร', 'Please enter a project name of 3-180 characters.');
    if (mb_strlen($values['developer']) < 2 || mb_strlen($values['developer']) > 140) $errors[] = t('กรุณากรอกชื่อบริษัทหรือผู้พัฒนาโครงการ', 'Please enter the company or developer name.');
    if ($values['location'] === '') $errors[] = t('กรุณากรอกทำเลของโครงการ', 'Please enter the project location.');
    if (mb_strlen($values['contact_name']) < 2) $errors[] = t('กรุณากรอกชื่อผู้ติดต่อโครงการ', 'Please enter a project contact name.');
    $phone = preg_replace('/[\s-]/', '', $values['phone']);
    if (!preg_match('/^[0-9]{9,10}$/', $phone)) $errors[] = t('เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก', 'Phone number must be 9-10 digits.');
    $lineId = clean_line_id($values['line_id']);
    if ($lineId !== '' && !valid_line_id($lineId)) $errors[] = t('LINE ID ไม่ถูกต้อง', 'Invalid LINE ID.');
    if ($values['map_url'] !== '' && (!filter_var($values['map_url'], FILTER_VALIDATE_URL)
        || !in_array(strtolower((string)parse_url($values['map_url'], PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $errors[] = t('ลิงก์แผนที่ต้องเป็น URL ที่ขึ้นต้นด้วย http:// หรือ https://', 'The map link must be a URL starting with http:// or https://');
    }

    foreach (['completion_year' => [1900, 2200, t('ปีแล้วเสร็จ', 'Completion year')], 'unit_count' => [1, 100000, t('จำนวนยูนิต', 'Unit count')],
        'price_from' => [0, 999999999999, t('ราคาเริ่มต้น', 'Starting price')], 'price_to' => [0, 999999999999, t('ราคาสูงสุด', 'Maximum price')]] as $field => [$minimum, $maximum, $label]) {
        if ($values[$field] !== '' && (!ctype_digit($values[$field]) || (int)$values[$field] < $minimum || (int)$values[$field] > $maximum)) {
            $errors[] = $label . t('ไม่ถูกต้อง', ' is invalid.');
        }
    }
    if ($values['price_from'] !== '' && $values['price_to'] !== '' && (int)$values['price_to'] < (int)$values['price_from']) {
        $errors[] = t('ราคาสูงสุดต้องไม่น้อยกว่าราคาเริ่มต้น', 'The maximum price must not be lower than the starting price.');
    }
    $imageCount = count_selected_files($_FILES['images'] ?? []);
    if ($imageCount < 1) $errors[] = t('กรุณาแนบรูปโครงการจริงอย่างน้อย 1 รูป', 'Please attach at least 1 real project photo.');
    if ($imageCount > MAX_IMAGES) $errors[] = t('แนบรูปได้สูงสุด ' . MAX_IMAGES . ' รูป', 'You can attach up to ' . MAX_IMAGES . ' photos.');

    if (!$errors) {
        $uploadedImages = [];
        try {
            $uploadedImages = save_uploaded_images($_FILES['images'] ?? [], MAX_IMAGES);
            if (!$uploadedImages) throw new RuntimeException(t('กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB', 'Please choose JPG, PNG or WebP images no larger than 5 MB.'));

            $pdo->beginTransaction();
            if (!$isLoggedIn) {
                $baseUsername = strtolower((string)strtok($email, '@'));
                $baseUsername = substr(preg_replace('/[^a-z0-9_.-]/', '', $baseUsername) ?: 'member', 0, 38);
                do {
                    $username = $baseUsername . '_' . bin2hex(random_bytes(3));
                    $usernameCheck = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
                    $usernameCheck->execute([$username]);
                } while ($usernameCheck->fetchColumn());
                $createUser = $pdo->prepare('INSERT INTO users (email, username, full_name, privacy_accepted_at, password_hash, role) VALUES (?, ?, ?, NOW(), ?, "user")');
                $createUser->execute([$email, $username, $ownerName, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int)$pdo->lastInsertId();
                $currentUser = ['id' => $userId, 'username' => $username, 'email' => $email, 'full_name' => $ownerName, 'role' => 'user'];
            }

            $insertProject = $pdo->prepare(
                'INSERT INTO development_projects (owner_user_id, title, developer, category, project_status, completion_year, unit_count, price_from, price_to, location, address, description, amenities, nearby_places, map_url, contact_name, phone, line_id, is_published)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $insertProject->execute([
                $userId, mb_substr($values['title'], 0, 180), mb_substr($values['developer'], 0, 140),
                mb_substr($values['category'], 0, 80), mb_substr($values['project_status'], 0, 80),
                $values['completion_year'] === '' ? null : (int)$values['completion_year'],
                $values['unit_count'] === '' ? null : (int)$values['unit_count'],
                $values['price_from'] === '' ? null : (int)$values['price_from'],
                $values['price_to'] === '' ? null : (int)$values['price_to'],
                mb_substr($values['location'], 0, 180), mb_substr($values['address'], 0, 255),
                $values['description'], $values['amenities'], $values['nearby_places'],
                mb_substr($values['map_url'], 0, 1000), mb_substr($values['contact_name'], 0, 120),
                $phone, $lineId,
            ]);
            $projectId = (int)$pdo->lastInsertId();
            $insertImage = $pdo->prepare('INSERT INTO project_images (project_id, filename, sort_order) VALUES (?, ?, ?)');
            foreach ($uploadedImages as $order => $filename) $insertImage->execute([$projectId, $filename, $order]);
            $pdo->commit();

            $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $currentUser['username'];
            $_SESSION['role'] = $currentUser['role'];
            setcookie('no_track', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
            redirect('project.php?id=' . $projectId . '&submitted=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($uploadedImages as $filename) delete_image_file($filename);
            error_log('Public project submission failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : t('ส่งข้อมูลโครงการไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองใหม่', 'Failed to submit the project. Please check your information and try again.');
        }
    }
}

$token = csrf_token();
$pageTitle = t('ลงโครงการอสังหาริมทรัพย์', 'Submit a property project') . ' | ' . SITE_NAME;
$pageDescription = t('สมัครสมาชิกและส่งข้อมูลโครงการอสังหาริมทรัพย์พร้อมรูปจริงได้ในแบบฟอร์มเดียว', 'Register and submit a property project with real photos in a single form.');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php seo_head($pageTitle, $pageDescription, page_url('submit-project.php')); ?>
  <?php lang_toggle_head(); ?>
  <style>
    :root{--ink:#0B1C33;--muted:#64748b;--line:#e5eaf0;--brand:#C5A059;--brand-dark:#A8843F;--canvas:#F9F9FB}*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font-family:"Prompt","Inter",system-ui,sans-serif}.project-submit-header{background:#0B1C33;border-bottom:1px solid rgba(197,160,89,.22)}.project-submit-nav{max-width:1100px;margin:auto;min-height:68px;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px}.project-submit-nav a{color:#CBD5E1;text-decoration:none;font-weight:700}.project-submit-nav .brand{font-size:17px;color:#C5A059}.project-submit-nav .back{color:#94A3B8}.project-submit-nav .back:hover{color:#C5A059}.project-submit-main{max-width:1000px;margin:32px auto;padding:0 18px 60px}.project-submit-hero{padding:28px 32px;background:linear-gradient(125deg,#0B1C33,#182C48);color:#fff;border:1px solid rgba(197,160,89,.25);border-radius:20px;margin-bottom:20px}.project-submit-hero small{color:#C5A059;font-weight:900;letter-spacing:.1em}.project-submit-hero h1{margin:7px 0;font-size:clamp(26px,4vw,38px)}.project-submit-hero p{margin:0;color:#CBD5E1;line-height:1.7}.project-submit-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:clamp(18px,4vw,32px);box-shadow:0 12px 34px rgba(11,28,51,.06)}.project-submit-card h2{margin:0 0 14px;font-size:20px;color:#0B1C33}.project-submit-card h3{margin:8px 0 0;font-size:16px;color:#0B1C33}.project-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.project-form .wide{grid-column:1/-1}.project-form label{display:grid;gap:6px;font-weight:750;color:#334155}.project-form input,.project-form select,.project-form textarea{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font:inherit;background:#fff}.project-form textarea{min-height:100px;resize:vertical}.project-form small{font-size:12px;color:#64748b;font-weight:400}.project-form .section-label{grid-column:1/-1;margin-top:10px;padding-top:14px;border-top:1px solid var(--line)}.project-form .privacy-row{display:flex;align-items:flex-start;gap:10px;font-size:13px;font-weight:500}.project-form .privacy-row input{width:18px;min-height:18px;flex:0 0 18px;margin:2px 0 0;accent-color:var(--brand)}.project-form .privacy-row a{color:var(--ink);font-weight:800}.project-submit-button{grid-column:1/-1;display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:16px;background:linear-gradient(135deg,#C5A059,#A8843F);color:#0B1C33;min-height:56px;padding:14px 24px;font:inherit;font-weight:900;font-size:17px;cursor:pointer;box-shadow:0 8px 22px rgba(197,160,89,.35);transition:transform .18s,box-shadow .18s}.project-submit-button:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(197,160,89,.45)}.project-submit-button:focus-visible{outline:3px solid #0B1C33;outline-offset:3px}.project-submit-errors{padding:13px 16px;margin-bottom:16px;border-radius:12px;background:#fff1f2;color:#be123c}.project-submit-login{margin:0 0 14px;color:var(--muted);font-size:14px}.project-submit-login a{color:var(--brand);font-weight:800}.project-submit-hint{padding:14px 16px;margin-top:16px;border-radius:12px;background:#f1f5f9;color:#526478;font-size:13px;line-height:1.7}@media(max-width:650px){.project-submit-main{margin:18px auto;padding:0 12px 40px}.project-submit-hero{padding:22px 20px}.project-submit-card{padding:18px 15px}.project-form{grid-template-columns:1fr}.project-form .wide,.project-form .section-label,.project-submit-button{grid-column:1}.project-submit-nav{padding:10px 14px}}
  </style>
  <style>
    .project-submit-version { padding:18px 16px 24px; color:#747b95; font-size:12px; text-align:center; }
  </style>
</head>
<body>
<header class="project-submit-header"><nav class="project-submit-nav"><a class="brand" href="project.php"><?= e(SITE_NAME) ?> · <?= e(t('ลงโครงการ', 'Submit a project')) ?></a><a class="back" href="project.php"><?= e(t('กลับไปหน้าโครงการ', 'Back to projects')) ?></a><?php render_lang_toggle(); ?></nav></header>
<main class="project-submit-main">
  <section class="project-submit-hero"><small>PROPERTY DEVELOPER</small><h1><?= e(t('ลงข้อมูลโครงการของคุณ', 'Submit your project')) ?></h1><p><?= e(t('กรอกข้อมูลโครงการและสร้างบัญชีในแบบฟอร์มเดียว ระบบจะเผยแพร่โครงการหลังส่งข้อมูลสำเร็จ คุณกลับมาใช้บัญชีเดิมเพื่อลงโครงการเพิ่มเติมได้', 'Fill in your project details and create an account in one form. The project is published right after a successful submission — reuse the same account to submit more later.')) ?></p></section>
  <section class="project-submit-card">
    <?php if ($errors): ?><div class="project-submit-errors" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?><?php if ($existingEmail): ?><a href="login.php?return_to=submit-project.php"><?= e(t('เข้าสู่ระบบแล้วส่งโครงการ', 'Log in, then submit the project')) ?></a><?php endif; ?></div><?php endif; ?>
    <?php if ($isLoggedIn): ?>
      <p class="project-submit-login"><?= e(t('กำลังส่งในบัญชี ', 'Submitting with account ') . $currentUser['email']) ?> · <a href="logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a></p>
    <?php else: ?>
      <p class="project-submit-login"><?= e(t('มีบัญชีแล้ว?', 'Already have an account?')) ?> <a href="login.php?return_to=submit-project.php"><?= e(t('เข้าสู่ระบบก่อน', 'Log in first')) ?></a> <?= e(t('แล้วกลับมาส่งโครงการได้เลย', 'then come back and submit your project.')) ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="project-form">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <?php if (!$isLoggedIn): ?>
        <h2 class="section-label"><?= e(t('สมัครสมาชิกผู้ลงโครงการ', 'Register as a project lister')) ?></h2>
        <label><?= e(t('ชื่อผู้สมัคร', 'Applicant name')) ?><input name="owner_name" required maxlength="120" autocomplete="name" value="<?= e($_POST['owner_name'] ?? '') ?>"></label>
        <label><?= e(t('อีเมล', 'Email')) ?><input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label class="wide"><?= e(t('ตั้งรหัสผ่าน', 'Set a password')) ?> <small><?= e(t('อย่างน้อย 8 ตัวอักษร', 'at least 8 characters')) ?></small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <?php endif; ?>

      <h2 class="section-label"><?= e(t('ข้อมูลโครงการ', 'Project details')) ?></h2>
      <label><?= e(t('ชื่อโครงการ', 'Project name')) ?> *<input name="title" required maxlength="180" value="<?= e($values['title']) ?>"></label>
      <label><?= e(t('บริษัท / ผู้พัฒนาโครงการ', 'Company / Developer')) ?> *<input name="developer" required maxlength="140" value="<?= e($values['developer']) ?>"></label>
      <label><?= e(t('ประเภทโครงการ', 'Project type')) ?><select name="category"><option value=""><?= e(t('เลือกประเภท', 'Select type')) ?></option><?php foreach ([['คอนโดมิเนียม','Condominium'],['บ้านเดี่ยว','Single house'],['ทาวน์โฮม','Townhouse'],['บ้านจัดสรร','Housing estate'],['อาคารพาณิชย์','Commercial building'],['อื่น ๆ','Other']] as [$categoryTh,$categoryEn]): ?><option value="<?= e($categoryTh) ?>" <?= $values['category'] === $categoryTh ? 'selected' : '' ?>><?= e(is_en() ? $categoryEn : $categoryTh) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('สถานะโครงการ', 'Project status')) ?><select name="project_status"><?php foreach ([['เปิดขาย / เปิดจอง','Now selling / pre-sale'],['กำลังก่อสร้าง','Under construction'],['พร้อมเข้าอยู่','Ready to move in'],['ปิดการขาย','Sales closed']] as [$statusTh,$statusEn]): ?><option value="<?= e($statusTh) ?>" <?= $values['project_status'] === $statusTh ? 'selected' : '' ?>><?= e(is_en() ? $statusEn : $statusTh) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('ทำเล', 'Location')) ?> *<input name="location" required maxlength="180" placeholder="<?= e(t('เช่น พระราม 2, กรุงเทพฯ', 'e.g. Rama 2, Bangkok')) ?>" value="<?= e($values['location']) ?>"></label>
      <label><?= e(t('ที่อยู่ / จุดสังเกต', 'Address / landmark')) ?><input name="address" maxlength="255" value="<?= e($values['address']) ?>"></label>
      <label><?= e(t('ปีที่คาดว่าจะแล้วเสร็จ', 'Estimated completion year')) ?><input type="number" name="completion_year" min="1900" max="2200" value="<?= e($values['completion_year']) ?>"></label>
      <label><?= e(t('จำนวนยูนิต', 'Unit count')) ?><input type="number" name="unit_count" min="1" max="100000" value="<?= e($values['unit_count']) ?>"></label>
      <label><?= e(t('ราคาเริ่มต้น (บาท)', 'Starting price (THB)')) ?><input type="number" name="price_from" min="0" value="<?= e($values['price_from']) ?>"></label>
      <label><?= e(t('ราคาสูงสุด (บาท)', 'Maximum price (THB)')) ?><input type="number" name="price_to" min="0" value="<?= e($values['price_to']) ?>"></label>
      <label class="wide"><?= e(t('รายละเอียดโครงการ', 'Project description')) ?><textarea name="description" maxlength="10000" placeholder="<?= e(t('จุดเด่น แบบบ้านหรือยูนิต และสิ่งที่ผู้สนใจควรรู้', 'Highlights, house/unit designs and what prospective buyers should know')) ?>"><?= e($values['description']) ?></textarea></label>
      <label><?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?> <small><?= e(t('คั่นแต่ละรายการด้วยเครื่องหมายจุลภาค', 'Separate items with commas')) ?></small><textarea name="amenities"><?= e($values['amenities']) ?></textarea></label>
      <label><?= e(t('สถานที่ใกล้เคียง', 'Nearby places')) ?> <small><?= e(t('คั่นแต่ละรายการด้วยเครื่องหมายจุลภาค', 'Separate items with commas')) ?></small><textarea name="nearby_places"><?= e($values['nearby_places']) ?></textarea></label>
      <label class="wide"><?= e(t('ลิงก์ Google Maps', 'Google Maps link')) ?><input type="url" name="map_url" placeholder="https://maps.google.com/..." value="<?= e($values['map_url']) ?>"></label>

      <h2 class="section-label"><?= e(t('ข้อมูลติดต่อและรูปโครงการ', 'Contact details & project photos')) ?></h2>
      <label><?= e(t('ชื่อผู้ติดต่อ', 'Contact name')) ?> *<input name="contact_name" required maxlength="120" value="<?= e($values['contact_name']) ?>"></label>
      <label><?= e(t('เบอร์โทรศัพท์', 'Phone number')) ?> *<input type="tel" name="phone" required inputmode="numeric" maxlength="14" placeholder="0812345678" value="<?= e($values['phone']) ?>"></label>
      <label>LINE ID <small><?= e(t('ไม่บังคับ', 'optional')) ?></small><input name="line_id" maxlength="40" value="<?= e($values['line_id']) ?>"></label>
      <label class="wide"><?= e(t('รูปโครงการจริง', 'Real project photos')) ?> * <small><?= e(t('อย่างน้อย 1 รูป สูงสุด ', 'At least 1 photo, up to ') . MAX_IMAGES . t(' รูป · JPG, PNG หรือ WebP · ไม่เกิน 5 MB ต่อรูป', ' photos · JPG, PNG or WebP · max 5 MB each')) ?></small><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>

      <?php if (!$isLoggedIn): ?><label class="privacy-row wide"><input type="checkbox" name="privacy_accept" value="1" required><span><?= e(t('ยอมรับ ', 'I accept the ')) ?><a href="privacy.php" target="_blank" rel="noopener"><?= e(t('นโยบายความเป็นส่วนตัว', 'Privacy Policy')) ?></a><?= e(t(' เพื่อสร้างบัญชีสมาชิก', ' to create a member account')) ?></span></label><?php endif; ?>
      <label class="privacy-row wide"><input type="checkbox" name="confirm_project_rights" value="1" required <?= !empty($_POST['confirm_project_rights']) ? 'checked' : '' ?>><span><?= e(t('ยืนยันว่าคุณมีสิทธิ์ส่งข้อมูลและรูปโครงการนี้ และเข้าใจว่าข้อมูลติดต่อกับโครงการจะแสดงต่อสาธารณะเมื่อส่งสำเร็จ', 'I confirm I have the right to submit this project\'s information and photos, and understand that the project contact details will be shown publicly after a successful submission.')) ?></span></label>
      <button class="project-submit-button" type="submit"><span aria-hidden="true">＋</span> <?= e(t('สมัครสมาชิกและลงโครงการ', 'Register & submit project')) ?></button>
    </form>
    <p class="project-submit-hint"><?= e(t('เมื่อส่งสำเร็จ โครงการจะแสดงบนหน้าโครงการทันที บัญชีของคุณใช้ส่งโครงการเพิ่มเติมได้โดยไม่ต้องสมัครซ้ำ', 'After a successful submission, the project appears on the projects page immediately. Your account can be reused for further submissions without registering again.')) ?></p>
  </section>
</main>
<footer class="project-submit-version"><?= e(SITE_NAME) ?> · <?= e(SITE_VERSION) ?></footer>
</body>
</html>
