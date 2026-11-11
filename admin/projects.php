<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin('projects', 'view');

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$canManageProjects = $isFounder || staff_can(db(), $staffId, 'projects', 'manage');
$canEditProjects   = $isFounder || staff_can(db(), $staffId, 'projects', 'edit') || $canManageProjects;
$canCreateProjects = $isFounder || staff_can(db(), $staffId, 'projects', 'create') || $canManageProjects;
$canDeleteProjects = $isFounder || staff_can(db(), $staffId, 'projects', 'delete') || $canManageProjects;

$pdo = db();
$projectId = max(0, (int)($_GET['id'] ?? 0));
$project = [
    'title' => '', 'developer' => '', 'category' => '', 'project_status' => '',
    'completion_year' => '', 'unit_count' => '', 'price_from' => '', 'price_to' => '',
    'location' => '', 'address' => '', 'description' => '', 'amenities' => '',
    'nearby_places' => '', 'map_url' => '', 'contact_name' => '', 'phone' => '',
    'line_id' => '', 'is_published' => 0,
];
$images = [];
$units = [];
$errors = [];

if ($projectId > 0) {
    $statement = $pdo->prepare('SELECT * FROM development_projects WHERE id = ? LIMIT 1');
    $statement->execute([$projectId]);
    $savedProject = $statement->fetch();
    if (!$savedProject) {
        http_response_code(404);
        exit('ไม่พบโครงการที่ต้องการแก้ไข');
    }
    $project = array_merge($project, $savedProject);
    $statement = $pdo->prepare('SELECT * FROM project_images WHERE project_id = ? ORDER BY sort_order, id');
    $statement->execute([$projectId]);
    $images = $statement->fetchAll();
    $statement = $pdo->prepare('SELECT * FROM project_units WHERE project_id = ? ORDER BY sort_order, id');
    $statement->execute([$projectId]);
    $units = $statement->fetchAll();
}

if (request_is_post()) {
    csrf_check();
    if ($projectId > 0 && !$canEditProjects) {
        $errors[] = 'สิทธิ์ถูกจำกัด: คุณไม่ได้รับสิทธิ์แก้ไขโครงการ กรุณาติดต่อ Founder & CEO';
    } elseif ($projectId === 0 && !$canCreateProjects) {
        $errors[] = 'สิทธิ์ถูกจำกัด: คุณไม่ได้รับสิทธิ์เพิ่มโครงการใหม่ กรุณาติดต่อ Founder & CEO';
    } else {
        foreach (['title', 'developer', 'category', 'project_status', 'location', 'address', 'description', 'amenities', 'nearby_places', 'map_url', 'contact_name', 'phone', 'line_id'] as $field) {
        $project[$field] = trim((string)($_POST[$field] ?? ''));
    }
    foreach (['completion_year', 'unit_count', 'price_from', 'price_to'] as $field) {
        $project[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $project['is_published'] = isset($_POST['is_published']) ? 1 : 0;

    if (mb_strlen($project['title']) < 3) $errors[] = 'กรุณากรอกชื่อโครงการอย่างน้อย 3 ตัวอักษร';
    if ($project['location'] === '') $errors[] = 'กรุณากรอกทำเลของโครงการ';
    $numericLabels = ['completion_year' => 'ปีที่คาดว่าจะแล้วเสร็จ', 'unit_count' => 'จำนวนยูนิต', 'price_from' => 'ราคาเริ่มต้น', 'price_to' => 'ราคาสูงสุด'];
    foreach (['completion_year' => [1900, 2200], 'unit_count' => [1, 100000], 'price_from' => [0, 999999999999], 'price_to' => [0, 999999999999]] as $field => [$minimum, $maximum]) {
        if ($project[$field] !== '' && (!ctype_digit($project[$field]) || (int)$project[$field] < $minimum || (int)$project[$field] > $maximum)) {
            $errors[] = 'กรุณากรอก' . $numericLabels[$field] . 'เป็นตัวเลขที่ถูกต้อง';
        }
    }
    if ($project['price_from'] !== '' && $project['price_to'] !== '' && (int)$project['price_to'] < (int)$project['price_from']) {
        $errors[] = 'ราคาสูงสุดต้องไม่น้อยกว่าราคาเริ่มต้น';
    }
    if ($project['map_url'] !== '' && (!filter_var($project['map_url'], FILTER_VALIDATE_URL)
        || !in_array(strtolower((string)parse_url($project['map_url'], PHP_URL_SCHEME)), ['http', 'https'], true))) $errors[] = 'ลิงก์แผนที่ไม่ถูกต้อง';
    $phone = preg_replace('/[\s-]/', '', $project['phone']);
    if ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) $errors[] = 'เบอร์โทรโครงการต้องเป็นตัวเลข 9-10 หลัก';
    if (!valid_line_id(clean_line_id($project['line_id']))) $errors[] = 'LINE ID ต้องมี 4-20 ตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด หรือขีดล่าง';

    $unitRows = [];
    $unitNames = $_POST['unit_name'] ?? [];
    if (is_array($unitNames)) {
        foreach ($unitNames as $index => $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $unitRows[] = [
                'name' => mb_substr($name, 0, 120),
                'unit_type' => mb_substr(trim((string)($_POST['unit_type'][$index] ?? '')), 0, 80),
                'bedrooms' => trim((string)($_POST['unit_bedrooms'][$index] ?? '')),
                'bathrooms' => trim((string)($_POST['unit_bathrooms'][$index] ?? '')),
                'area_sqm' => trim((string)($_POST['unit_area'][$index] ?? '')),
                'land_area' => trim((string)($_POST['unit_land'][$index] ?? '')),
                'price_from' => trim((string)($_POST['unit_price'][$index] ?? '')),
            ];
        }
    }
    foreach ($unitRows as $row) {
        foreach (['bedrooms' => 99, 'bathrooms' => 99, 'area_sqm' => 999999, 'land_area' => 999999, 'price_from' => 999999999999] as $field => $maximum) {
            if ($row[$field] !== '' && (!is_numeric($row[$field]) || (float)$row[$field] < 0 || (float)$row[$field] > $maximum)) {
                $errors[] = 'รายละเอียดแบบบ้านมีค่าตัวเลขไม่ถูกต้อง';
                break 2;
            }
        }
    }

    $fileCount = count_selected_files($_FILES['images'] ?? []);
    if ($fileCount > MAX_IMAGES) $errors[] = 'เพิ่มรูปได้สูงสุด ' . MAX_IMAGES . ' รูปต่อครั้ง';

    if (!$errors) {
        $uploadedImages = [];
        try {
            $uploadedImages = save_uploaded_images($_FILES['images'] ?? [], MAX_IMAGES);
            $pdo->beginTransaction();
            $values = [
                $project['title'], $project['developer'], $project['category'], $project['project_status'],
                $project['completion_year'] === '' ? null : (int)$project['completion_year'],
                $project['unit_count'] === '' ? null : (int)$project['unit_count'],
                $project['price_from'] === '' ? null : (int)$project['price_from'],
                $project['price_to'] === '' ? null : (int)$project['price_to'],
                $project['location'], $project['address'], $project['description'], $project['amenities'],
                $project['nearby_places'], $project['map_url'], $project['contact_name'],
                $phone, clean_line_id($project['line_id']),
                $project['is_published'],
            ];
            if ($projectId > 0) {
                $statement = $pdo->prepare('UPDATE development_projects SET title=?, developer=?, category=?, project_status=?, completion_year=?, unit_count=?, price_from=?, price_to=?, location=?, address=?, description=?, amenities=?, nearby_places=?, map_url=?, contact_name=?, phone=?, line_id=?, is_published=? WHERE id=?');
                $statement->execute([...$values, $projectId]);
            } else {
                $statement = $pdo->prepare('INSERT INTO development_projects (title, developer, category, project_status, completion_year, unit_count, price_from, price_to, location, address, description, amenities, nearby_places, map_url, contact_name, phone, line_id, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($values);
                $projectId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM project_units WHERE project_id = ?')->execute([$projectId]);
            $unitStatement = $pdo->prepare('INSERT INTO project_units (project_id, name, unit_type, bedrooms, bathrooms, area_sqm, land_area, price_from, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($unitRows as $order => $row) {
                $unitStatement->execute([
                    $projectId, $row['name'], $row['unit_type'],
                    $row['bedrooms'] === '' ? null : (int)$row['bedrooms'],
                    $row['bathrooms'] === '' ? null : (int)$row['bathrooms'],
                    $row['area_sqm'] === '' ? null : (float)$row['area_sqm'],
                    $row['land_area'] === '' ? null : (float)$row['land_area'],
                    $row['price_from'] === '' ? null : (int)$row['price_from'], $order,
                ]);
            }

            $imageStatement = $pdo->prepare('INSERT INTO project_images (project_id, filename, sort_order) VALUES (?, ?, ?)');
            $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM project_images WHERE project_id = ' . $projectId)->fetchColumn();
            foreach ($uploadedImages as $filename) $imageStatement->execute([$projectId, $filename, $nextOrder++]);
            $pdo->commit();
            redirect('projects.php?id=' . $projectId . '&saved=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($uploadedImages as $filename) delete_image_file($filename);
            error_log('Project save failed: ' . $exception->getMessage());
            $errors[] = 'บันทึกโครงการไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองใหม่';
        }
    }
    }
    $units = $unitRows;
}

$token = csrf_token();
$allProjects = $pdo->query('SELECT id, title, developer, location, is_published, updated_at FROM development_projects ORDER BY updated_at DESC, id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('จัดการโครงการ', 'Manage Projects')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .project-admin-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.75fr);gap:22px;align-items:start}.project-admin-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 8px 24px #2b21500b}.project-admin-card h1,.project-admin-card h2{margin-top:0}.project-admin-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.project-admin-form .wide{grid-column:1/-1}.project-admin-form label{display:grid;gap:6px;font-weight:700;color:#334155}.project-admin-form input,.project-admin-form textarea,.project-admin-form select{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:11px 12px;font:inherit;background:#fff}.project-admin-form textarea{min-height:110px;resize:vertical}.project-admin-hint{font-size:12px;color:#64748b;font-weight:400}.project-unit-admin{border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin:10px 0}.project-unit-admin .unit-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.project-unit-admin .unit-fields label{font-size:12px}.project-admin-images{display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,1fr));gap:9px;margin-top:12px}.project-admin-images img{width:100%;height:85px;object-fit:cover;border-radius:9px}.project-admin-list{display:grid;gap:10px}.project-admin-list a{display:block;padding:14px;border:1px solid #e2e8f0;border-radius:12px;color:#2b2150;text-decoration:none}.project-admin-list a:hover{border-color:#7955d9}.project-admin-list small{display:block;color:#64748b;margin-top:4px}.project-publish-row{display:flex;gap:10px;align-items:center;font-weight:600}.project-publish-row input{width:auto}.project-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px}.project-actions button,.project-actions a,.add-unit-button{border:0;border-radius:999px;padding:11px 17px;font-weight:800;text-decoration:none;cursor:pointer}.project-actions button,.add-unit-button{background:#7955d9;color:#fff}.project-actions a{background:#f1f5f9;color:#2b2150}.project-save-message{padding:12px 14px;border-radius:10px;background:#ecfdf5;color:#047857;margin-bottom:16px}@media(max-width:920px){.project-admin-grid{grid-template-columns:1fr}}@media(max-width:620px){.project-admin-card{padding:16px}.project-admin-form,.project-unit-admin .unit-fields{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<div class="wrap" style="margin-top:20px;">
  <div class="project-admin-grid">
    <section class="project-admin-card">
      <h1><?= $projectId ? e(t('แก้ไขโครงการ', 'Edit Project')) : e(t('เพิ่มโครงการใหม่', 'Add New Project')) ?></h1>
      <p class="muted"><?= e(t('กรอกเฉพาะข้อมูลที่มี ช่องเสริมเว้นว่างได้ โครงการจะยังไม่แสดงหน้าเว็บจนกว่าจะเลือกเผยแพร่', 'Fill in available details. Optional fields can be left blank. Projects will not appear publicly until published.')) ?></p>
      <?php if (isset($_GET['saved'])): ?><div class="project-save-message"><?= e(t('บันทึกโครงการเรียบร้อยแล้ว', 'Project saved successfully.')) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="alert error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="project-admin-form" id="projectForm">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <?php foreach ([
          'title' => t('ชื่อโครงการ *', 'Project Name *'),
          'developer' => t('ผู้พัฒนาโครงการ', 'Developer'),
          'category' => t('ประเภทโครงการ', 'Project Type'),
          'project_status' => t('สถานะโครงการ', 'Project Status'),
          'completion_year' => t('ปีที่คาดว่าจะแล้วเสร็จ', 'Estimated Completion Year'),
          'unit_count' => t('จำนวนยูนิต', 'Unit Count'),
          'price_from' => t('ราคาเริ่มต้น (บาท)', 'Starting Price (THB)'),
          'price_to' => t('ราคาสูงสุด (บาท)', 'Maximum Price (THB)'),
          'location' => t('ทำเล *', 'Location *'),
          'address' => t('ที่อยู่ / จุดสังเกต', 'Address / Landmark'),
          'contact_name' => t('ชื่อผู้ติดต่อ', 'Contact Name'),
          'phone' => t('เบอร์โทรศัพท์', 'Phone Number'),
          'line_id' => 'LINE ID',
          'map_url' => t('ลิงก์ Google Maps', 'Google Maps Link')
        ] as $field=>$label): ?>
          <label class="<?= in_array($field,['title','location','address','map_url'],true)?'wide':'' ?>"><?= e($label) ?><input name="<?= e($field) ?>" value="<?= e($project[$field] ?? '') ?>" <?= in_array($field,['title','location'],true)?'required':'' ?> <?= $field==='map_url'?'type="url"':'' ?>></label>
        <?php endforeach; ?>
        <label class="wide"><?= e(t('รายละเอียดโครงการ', 'Project Description')) ?><textarea name="description" rows="6" placeholder="<?= e(t('จุดเด่น แนวคิดโครงการ รูปแบบการอยู่อาศัย และข้อมูลที่ผู้สนใจควรรู้', 'Project highlights, living concept, and key information.')) ?>"><?= e($project['description']) ?></textarea></label>
        <label class="wide"><?= e(t('สิ่งอำนวยความสะดวก', 'Amenities')) ?> <span class="project-admin-hint"><?= e(t('ใส่หนึ่งรายการต่อบรรทัด หรือคั่นด้วยจุลภาค', 'One item per line or comma-separated')) ?></span><textarea name="amenities" placeholder="<?= e(t('คลับเฮาส์&#10;สระว่ายน้ำ&#10;ระบบรักษาความปลอดภัย', 'Clubhouse&#10;Swimming Pool&#10;Security System')) ?>"><?= e($project['amenities']) ?></textarea></label>
        <label class="wide"><?= e(t('สถานที่ใกล้เคียง', 'Nearby Places')) ?> <span class="project-admin-hint"><?= e(t('ใส่ชื่อสถานที่และระยะทางตามข้อมูลจริง', 'Add nearby landmarks and distances')) ?></span><textarea name="nearby_places" placeholder="<?= e(t('สถานีรถไฟฟ้า 800 เมตร&#10;โรงเรียน...', 'BTS Station 800m&#10;School...')) ?>"><?= e($project['nearby_places']) ?></textarea></label>
        <div class="wide"><h2><?= e(t('แบบบ้าน / รูปแบบยูนิต', 'House Designs / Unit Types')) ?></h2><p class="project-admin-hint"><?= e(t('เพิ่มได้หลายแบบ กรอกเฉพาะข้อมูลที่มี', 'You can add multiple unit types')) ?></p>
          <div id="projectUnits">
            <?php foreach ($units as $unit): ?>
              <div class="project-unit-admin"><div class="unit-fields">
                <label>ชื่อแบบ<input name="unit_name[]" value="<?= e($unit['name']) ?>" placeholder="เช่น Type A"></label>
                <label>ประเภท<input name="unit_type[]" value="<?= e($unit['unit_type']) ?>" placeholder="บ้านเดี่ยว 2 ชั้น"></label>
                <label>ห้องนอน<input type="number" min="0" max="99" name="unit_bedrooms[]" value="<?= e($unit['bedrooms'] ?? '') ?>"></label>
                <label>ห้องน้ำ<input type="number" min="0" max="99" name="unit_bathrooms[]" value="<?= e($unit['bathrooms'] ?? '') ?>"></label>
                <label>พื้นที่ใช้สอย (ตร.ม.)<input type="number" min="0" step="0.01" name="unit_area[]" value="<?= e($unit['area_sqm'] ?? '') ?>"></label>
                <label>พื้นที่ดิน (ตร.ว.)<input type="number" min="0" step="0.01" name="unit_land[]" value="<?= e($unit['land_area'] ?? '') ?>"></label>
                <label>ราคาเริ่มต้น (บาท)<input type="number" min="0" name="unit_price[]" value="<?= e($unit['price_from'] ?? '') ?>"></label>
                <label><span>&nbsp;</span><button type="button" class="remove-project-unit">นำแบบนี้ออก</button></label>
              </div></div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="add-unit-button" id="addProjectUnit">+ เพิ่มแบบบ้าน / ยูนิต</button>
        </div>
        <label class="wide">เพิ่มรูปภาพโครงการ <span class="project-admin-hint">เลือกได้สูงสุด <?= MAX_IMAGES ?> รูปต่อครั้ง · jpg, png, webp</span><input type="file" name="images[]" id="projectImages" accept="image/jpeg,image/png,image/webp" multiple></label>
        <div class="project-admin-images wide" id="projectImagePreview">
          <?php foreach ($images as $image): ?><img src="../uploads/<?= e($image['filename']) ?>" alt="รูปโครงการเดิม" loading="lazy" decoding="async"><?php endforeach; ?>
        </div>
        <?php if (($projectId > 0 && $canEditProjects) || ($projectId === 0 && $canCreateProjects)): ?>
          <label class="project-publish-row wide"><input type="checkbox" name="is_published" value="1" <?= !empty($project['is_published'])?'checked':'' ?>> <?= e(t('เผยแพร่หน้าโครงการ', 'Publish on project page')) ?></label>
          <div class="project-actions wide"><button type="submit"><?= e(t('บันทึกโครงการ', 'Save Project')) ?></button><a href="projects.php"><?= e(t('เริ่มเพิ่มโครงการใหม่', 'Start New Project')) ?></a></div>
        <?php else: ?>
          <div class="wide" style="font-size:13px;color:#64748B;font-style:italic;padding:12px;background:#F9F9FB;border-radius:10px;">👁 <?= e(t('ดูข้อมูลโครงการได้อย่างเดียว', 'View-only mode')) ?></div>
        <?php endif; ?>
      </form>
    </section>
    <aside class="project-admin-card"><h2><?= e(t('โครงการทั้งหมด', 'All Projects')) ?></h2><div class="project-admin-list">
      <?php if (!$allProjects): ?><p class="muted"><?= e(t('ยังไม่มีโครงการ', 'No projects yet')) ?></p><?php endif; ?>
      <?php foreach ($allProjects as $row): ?><a href="projects.php?id=<?= (int)$row['id'] ?>"><strong><?= e($row['title']) ?></strong><small><?= e($row['location']) ?> · <?= !empty($row['is_published']) ? e(t('เผยแพร่แล้ว', 'Published')) : e(t('ฉบับร่าง', 'Draft')) ?></small></a><?php endforeach; ?>
    </div></aside>
  </div>
</div>
<template id="projectUnitTemplate"><div class="project-unit-admin"><div class="unit-fields">
  <label>ชื่อแบบ<input name="unit_name[]" placeholder="เช่น Type A"></label>
  <label>ประเภท<input name="unit_type[]" placeholder="บ้านเดี่ยว 2 ชั้น"></label>
  <label>ห้องนอน<input type="number" min="0" max="99" name="unit_bedrooms[]"></label>
  <label>ห้องน้ำ<input type="number" min="0" max="99" name="unit_bathrooms[]"></label>
  <label>พื้นที่ใช้สอย (ตร.ม.)<input type="number" min="0" step="0.01" name="unit_area[]"></label>
  <label>พื้นที่ดิน (ตร.ว.)<input type="number" min="0" step="0.01" name="unit_land[]"></label>
  <label>ราคาเริ่มต้น (บาท)<input type="number" min="0" name="unit_price[]"></label>
  <label><span>&nbsp;</span><button type="button" class="remove-project-unit">นำแบบนี้ออก</button></label>
</div></div></template>
<script>
  const projectUnits = document.getElementById('projectUnits');
  document.getElementById('addProjectUnit').addEventListener('click', () => projectUnits.append(document.getElementById('projectUnitTemplate').content.cloneNode(true)));
  projectUnits.addEventListener('click', event => { if (event.target.closest('.remove-project-unit')) event.target.closest('.project-unit-admin').remove(); });
  const projectImages = document.getElementById('projectImages');
  projectImages.addEventListener('change', () => {
    const preview = document.getElementById('projectImagePreview');
    preview.replaceChildren();
    Array.from(projectImages.files || []).slice(0, <?= MAX_IMAGES ?>).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.alt = file.name; preview.append(image);
    });
  });
</script>
</body>
</html>

