<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS featured_properties (
    property_id INT NOT NULL PRIMARY KEY,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_featured_properties_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    KEY idx_featured_properties_order (sort_order, property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$tab = in_array($_GET['tab'] ?? '', ['pending', 'approved', 'rejected', 'flagged', 'all'], true)
    ? $_GET['tab']
    : 'pending';

// ===== รับคำสั่งจากปุ่มต่าง ๆ =====
if (request_is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['feature', 'unfeature', 'feature_up', 'feature_down'], true)) {
        $propertyCheck = $pdo->prepare('SELECT approval, status FROM properties WHERE id = ? LIMIT 1');
        $propertyCheck->execute([$id]);
        $targetProperty = $propertyCheck->fetch();

        if ($action === 'unfeature') {
            $pdo->prepare('DELETE FROM featured_properties WHERE property_id = ?')->execute([$id]);
            set_flash('นำประกาศออกจากรายการโปรโมตแล้ว');
        } elseif ($action === 'feature' && (!$targetProperty || $targetProperty['approval'] !== 'approved' || $targetProperty['status'] !== 'available')) {
            set_flash('โปรโมตได้เฉพาะประกาศที่อนุมัติแล้วและยังพร้อมขาย/เช่า');
        } elseif ($action === 'feature') {
            $currentFeature = $pdo->prepare('SELECT 1 FROM featured_properties WHERE property_id = ?');
            $currentFeature->execute([$id]);
            if ($currentFeature->fetchColumn()) {
                set_flash('ประกาศนี้อยู่ในรายการโปรโมตแล้ว');
            } else {
                $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM featured_properties')->fetchColumn();
                $pdo->prepare('INSERT INTO featured_properties (property_id, sort_order) VALUES (?, ?)')->execute([$id, $nextOrder]);
                set_flash('เพิ่มประกาศเข้าโปรโมตหน้าแรกแล้ว');
            }
        } else {
            $featuredRows = $pdo->query('SELECT property_id, sort_order FROM featured_properties ORDER BY sort_order, property_id')->fetchAll();
            $currentIndex = null;
            foreach ($featuredRows as $index => $featuredRow) {
                if ((int)$featuredRow['property_id'] === $id) { $currentIndex = $index; break; }
            }
            $direction = $action === 'feature_up' ? -1 : 1;
            $swapIndex = $currentIndex === null ? null : $currentIndex + $direction;
            if ($currentIndex === null || !isset($featuredRows[$swapIndex])) {
                set_flash('ประกาศนี้ไม่มีรายการให้เลื่อนในทิศทางนั้น');
            } else {
                $currentRow = $featuredRows[$currentIndex];
                $swapRow = $featuredRows[$swapIndex];
                $swap = $pdo->prepare('UPDATE featured_properties SET sort_order = CASE property_id WHEN ? THEN ? WHEN ? THEN ? END WHERE property_id IN (?, ?)');
                $swap->execute([
                    (int)$currentRow['property_id'], (int)$swapRow['sort_order'],
                    (int)$swapRow['property_id'], (int)$currentRow['sort_order'],
                    (int)$currentRow['property_id'], (int)$swapRow['property_id'],
                ]);
                set_flash('ปรับลำดับประกาศโปรโมตแล้ว');
            }
        }
    } elseif ($id > 0 && $action === 'approve') {
        $pdo->prepare("UPDATE properties SET approval = 'approved' WHERE id = ?")->execute([$id]);
        set_flash('อนุมัติประกาศเรียบร้อยแล้ว ประกาศแสดงผลบนหน้าเว็บไซต์ทันที');
    } elseif ($id > 0 && $action === 'reject') {
        $pdo->prepare("UPDATE properties SET approval = 'rejected' WHERE id = ?")->execute([$id]);
        set_flash('ไม่อนุมัติประกาศนี้');
    } elseif ($id > 0 && $action === 'unpublish') {
        $pdo->prepare("UPDATE properties SET approval = 'rejected' WHERE id = ?")->execute([$id]);
        set_flash('ซ่อนประกาศนี้จากหน้าเว็บไซต์แล้ว');
    } elseif ($id > 0 && $action === 'toggle') {
        $pdo->prepare("UPDATE properties SET status = IF(status = 'done', 'available', 'done') WHERE id = ?")->execute([$id]);
        set_flash('อัปเดตสถานะการขาย/เช่าเรียบร้อยแล้ว');
    } elseif ($id > 0 && $action === 'delete') {
        $imgs = $pdo->prepare('SELECT filename FROM property_images WHERE property_id = ?');
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $file) {
            delete_image_file($file);
        }
        $pdo->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
        set_flash('ลบประกาศถาวรเรียบร้อยแล้ว');
    }

    redirect('index.php?tab=' . $tab);
}

// ===== จำนวนประกาศแต่ละสถานะ =====
$counts = array_merge(
    ['pending' => 0, 'approved' => 0, 'rejected' => 0],
    $pdo->query('SELECT approval, COUNT(*) FROM properties GROUP BY approval')->fetchAll(PDO::FETCH_KEY_PAIR)
);
$counts['all'] = array_sum($counts);
$counts['flagged'] = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'approved' AND verification_status = 'flagged'")->fetchColumn();

// ===== ดึงรายการตามแท็บ พร้อมรูปหน้าปก =====
$select = "SELECT p.*,
    fp.sort_order AS featured_order,
    (SELECT filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
    FROM properties p
    LEFT JOIN featured_properties fp ON fp.property_id = p.id";

if ($tab === 'all') {
    $rows = $pdo->query($select . ' ORDER BY p.created_at DESC')->fetchAll();
} elseif ($tab === 'flagged') {
    $rows = $pdo->query($select . " WHERE p.approval = 'approved' AND p.verification_status = 'flagged' ORDER BY p.created_at DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare($select . ' WHERE p.approval = ? ORDER BY p.created_at DESC');
    $stmt->execute([$tab]);
    $rows = $stmt->fetchAll();
}
$featuredRows = $pdo->query('SELECT property_id, sort_order FROM featured_properties ORDER BY sort_order, property_id')->fetchAll();
$featuredRankById = [];
foreach ($featuredRows as $rank => $featuredRow) $featuredRankById[(int)$featuredRow['property_id']] = $rank + 1;
$featuredTotal = count($featuredRows);

// ===== สถิติภาพรวมสำหรับการ์ดบนแดชบอร์ด =====
$activeProps = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available' AND approval = 'approved'")->fetchColumn();
$saleProps   = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'sale'")->fetchColumn();
$rentProps   = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE type = 'rent'")->fetchColumn();
$totalUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$visitors30  = (int)$pdo->query('SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
$views30     = (int)$pdo->query('SELECT COUNT(*) FROM visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
$doneProps   = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'done'")->fetchColumn();

$flash = get_flash();
$token = csrf_token();

$approvalLabel = [
    'pending'  => t('รออนุมัติ', 'Pending'),
    'approved' => t('แสดงอยู่', 'Active'),
    'rejected' => t('ไม่อนุมัติ', 'Rejected'),
];
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('แดชบอร์ดผู้ดูแล | ระบบหลังบ้าน', 'Admin Dashboard | Standard Property')) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
</head>
<body class="admin-body">
<?php require __DIR__ . '/_sidebar.php'; ?>

<div class="topbar">
  <strong>🏠 <?= e(t('ระบบจัดการประกาศทรัพย์สิน', 'Property Management System')) ?></strong>
  <nav>
    <a href="add.php">+ <?= e(t('เพิ่มประกาศใหม่', 'Add New Listing')) ?></a>
    <a href="projects.php">🏙️ <?= e(t('จัดการโครงการ', 'Manage Projects')) ?></a>
    <a href="featured.php">🏷️ <?= e(t('หมวดแนะนำหน้าแรก', 'Featured Categories')) ?></a>
    <a href="category-images.php">🖼️ <?= e(t('ภาพหมวดหมู่', 'Category Images')) ?></a>
    <a href="analytics.php">📊 <?= e(t('วิเคราะห์สถิติ', 'Analytics')) ?></a>
    <a href="pdf-report.php">🧾 <?= e(t('รายงาน PDF', 'PDF Report')) ?></a>
    <a href="../index.php" target="_blank"><?= e(t('ดูหน้าเว็บไซต์', 'View Website')) ?></a>
    <a href="../logout.php"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
    <?php render_lang_toggle(); ?>
  </nav>
</div>

<div class="wrap">

  <?php if ($flash): ?>
    <div class="flash"><?= e($flash) ?></div>
  <?php endif; ?>

  <section class="featured-manage-banner">
    <span class="featured-manage-icon" aria-hidden="true">★</span>
    <div><strong><?= e(t('โปรโมตและจัดลำดับทรัพย์หน้าแรก', 'Promote & Order Featured Properties')) ?></strong><small><?= e(t('กำลังโปรโมต', 'Currently promoting')) ?> <?= number_format($featuredTotal) ?> <?= e(t('รายการ · เลือกแท็บ “แสดงอยู่” แล้วใช้ปุ่มโปรโมต/เลื่อนลำดับในแต่ละประกาศ', 'listings · Select "Active" tab and use promote/order buttons on each listing')) ?></small></div>
    <a href="?tab=approved#propertyList"><?= e(t('ไปจัดการประกาศที่อนุมัติแล้ว ↗', 'Manage approved listings ↗')) ?></a>
  </section>

  <!-- ===== Hero แดชบอร์ด ===== -->
  <section class="dash-hero">
    <div class="dash-hero-text">
      <span class="dash-eyebrow">ADMIN DASHBOARD</span>
      <h1><?= e(t('แดชบอร์ดผู้ดูแลระบบ', 'Admin Dashboard')) ?></h1>
      <p><?= e(t('ภาพรวมประกาศและสถิติทั้งหมดของเว็บไซต์ — อัปเดต ณ', 'Overview of all listings and stats — Updated on')) ?> <?= e(date('d/m/Y H:i')) ?> <?= e(t('น.', '')) ?></p>
      <div class="dash-hero-actions">
        <a class="btn-hero" href="pdf-report.php">🧾 <?= e(t('สร้างรายงาน PDF สรุปสถิติ', 'Generate PDF Report')) ?></a>
        <a class="btn-hero ghost" href="analytics.php">📊 <?= e(t('ดูสถิติเชิงลึก', 'View In-depth Stats')) ?></a>
      </div>
    </div>
    <div class="dash-hero-ring" aria-hidden="true"></div>
  </section>

  <!-- ===== การ์ดสถิติ ===== -->
  <section class="stat-cards">
    <div class="scard">
      <div class="scard-icon i-blue">📦</div>
      <div><div class="scard-num"><?= number_format($counts['all']) ?></div><div class="scard-lbl"><?= e(t('ประกาศทั้งหมด', 'Total listings')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-amber">⏳</div>
      <div><div class="scard-num"><?= number_format($counts['pending']) ?></div><div class="scard-lbl"><?= e(t('รออนุมัติ', 'Pending review')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-green">✅</div>
      <div><div class="scard-num"><?= number_format($activeProps) ?></div><div class="scard-lbl"><?= e(t('เผยแพร่และพร้อมขาย/เช่า', 'Active & available')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-orange">🎉</div>
      <div><div class="scard-num"><?= number_format($doneProps) ?></div><div class="scard-lbl"><?= e(t('ขาย/เช่าแล้ว', 'Sold / Rented')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-purple">👥</div>
      <div><div class="scard-num"><?= number_format($visitors30) ?></div><div class="scard-lbl"><?= e(t('ผู้เข้าชมไม่ซ้ำ 30 วัน', 'Unique visitors 30d')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-teal">👀</div>
      <div><div class="scard-num"><?= number_format($views30) ?></div><div class="scard-lbl"><?= e(t('ยอดเข้าชม 30 วัน', 'Total views 30d')) ?></div></div>
    </div>
    <div class="scard">
      <div class="scard-icon i-pink">👤</div>
      <div><div class="scard-num"><?= number_format($totalUsers) ?></div><div class="scard-lbl"><?= e(t('สมาชิกทั้งหมด', 'Total members')) ?></div></div>
    </div>
  </section>

  <div class="box" id="propertyList">
    <!-- เมนูแท็บสถานะ -->
    <div class="tabs">
      <?php
      $tabLabels = [
        'pending'  => t('รออนุมัติ', 'Pending'),
        'approved' => t('แสดงอยู่', 'Active'),
        'flagged'  => t('ระบบติดธงตรวจซ้ำ', 'Flagged'),
        'rejected' => t('ซ่อนแล้ว', 'Hidden/Rejected'),
        'all'      => t('ทั้งหมด', 'All'),
      ];
      foreach ($tabLabels as $key => $label):
      ?>
        <a href="?tab=<?= $key ?>" class="<?= $tab === $key ? 'on' : '' ?>">
          <?= $label ?>
          <span class="pill"><?= (int)($counts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
      <p class="muted"><?= e(t('ไม่มีประกาศในหมวดหมู่นี้', 'No listings in this category')) ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><?= e(t('รูปภาพ', 'Image')) ?></th>
              <th><?= e(t('ข้อมูลประกาศ', 'Listing Info')) ?></th>
              <th><?= e(t('ผู้ติดต่อ', 'Contact')) ?></th>
              <th><?= e(t('สถานะอนุมัติ', 'Approval')) ?></th>
              <th><?= e(t('สถานะทรัพย์', 'Property Status')) ?></th>
              <th><?= e(t('การจัดการ', 'Actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): $featuredRank = $featuredRankById[(int)$p['id']] ?? null; ?>
              <tr>
                <td>
                  <div class="thumb">
                    <?php if ($p['cover']): ?>
                      <img src="../uploads/<?= e($p['cover']) ?>" alt="<?= e(t('รูปประกาศ', 'Listing image')) ?>">
                    <?php else: ?>
                      <?= e(t('ไม่มีรูป', 'No image')) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <strong style="font-size: 15px; color: #1e293b;"><?= e($p['title']) ?></strong>
                  <?php if ($featuredRank !== null): ?><span class="tag featured-property-tag"><?= e(t('โปรโมตหน้าแรก #', 'Homepage Featured #')) ?><?= (int)$featuredRank ?></span><?php endif; ?><br>
                  <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                    <span class="tag <?= e($p['type']) ?>"><?= type_label($p['type']) ?></span>
                    <span style="font-weight: 600; color: #261d3b;"><?= price_label($p['type'], (int)$p['price']) ?></span>
                  </div>
                  <span class="small" style="display: block; margin-top: 4px;">📍 <?= e($p['location']) ?></span>
                </td>
                <td class="small">
                  <strong><?= e($p['contact_name']) ?></strong><br>
                  📞 <?= e($p['phone']) ?>
                  <?php if (!empty($p['line_id'])): ?><br><a href="https://line.me/R/ti/p/~<?= rawurlencode($p['line_id']) ?>" target="_blank" rel="noopener noreferrer">LINE: <?= e($p['line_id']) ?></a><?php endif; ?>
                </td>
                <td>
                  <span class="tag <?= e($p['approval']) ?>"><?= $approvalLabel[$p['approval']] ?? $p['approval'] ?></span>
                  <?php if (($p['verification_status'] ?? 'passed') === 'flagged'): ?><small style="display:block;max-width:220px;margin-top:6px;color:#b45309;">⚠ <?= e($p['verification_notes'] ?: 'ระบบพบข้อมูลที่ควรตรวจซ้ำ') ?></small><?php endif; ?>
                </td>
                <td>
                  <?php if ($p['status'] === 'done'): ?>
                    <span class="tag done"><?= $p['type'] === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว' ?></span>
                  <?php else: ?>
                    <span class="tag open"><?= e(t('กำลังประกาศ', 'Active')) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="actions">
                    <?php if ($p['approval'] !== 'approved'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="approve" class="btn-action btn-green"><?= e(t('อนุมัติ', 'Approve')) ?></button>
                      </form>
                    <?php endif; ?>

                    <?php if ($p['approval'] === 'pending'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="reject" class="btn-action btn-gray"><?= e(t('ไม่อนุมัติ', 'Reject')) ?></button>
                      </form>
                    <?php endif; ?>

                    <?php if ($p['approval'] === 'approved'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="toggle" class="btn-action btn-accent">
                          <?= $p['status'] === 'done' ? t('เปิดขายอีกครั้ง', 'Relist') : ($p['type'] === 'rent' ? t('ปล่อยเช่าแล้ว', 'Mark Rented') : t('ขายแล้ว', 'Mark Sold')) ?>
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($p['approval'] === 'approved' && ($p['verification_status'] ?? '') === 'flagged'): ?>
                      <form method="post" style="display:inline;" onsubmit="return confirm('ซ่อนประกาศที่ระบบติดธงนี้จากหน้าเว็บไซต์หรือไม่?');">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="unpublish" class="btn-action btn-gray"><?= e(t('ซ่อนประกาศ', 'Hide Listing')) ?></button>
                      </form>
                    <?php endif; ?>

                    <?php if ($featuredRank !== null): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="feature_up" class="btn-action btn-order" aria-label="<?= e(t('เลื่อนโปรโมตขึ้น', 'Move up')) ?>" <?= $featuredRank <= 1 ? 'disabled' : '' ?>><?= e(t('↑ เลื่อนขึ้น', '↑ Move Up')) ?></button>
                      </form>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="feature_down" class="btn-action btn-order" aria-label="<?= e(t('เลื่อนโปรโมตลง', 'Move down')) ?>" <?= $featuredRank >= $featuredTotal ? 'disabled' : '' ?>><?= e(t('↓ เลื่อนลง', '↓ Move Down')) ?></button>
                      </form>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="unfeature" class="btn-action btn-feature-remove"><?= e(t('นำออก', 'Remove')) ?></button>
                      </form>
                    <?php elseif ($p['approval'] === 'approved' && $p['status'] === 'available'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button name="action" value="feature" class="btn-action btn-feature"><?= e(t('★ โปรโมตหน้าแรก', '★ Feature')) ?></button>
                      </form>
                    <?php endif; ?>

                    <a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn-action btn-edit"><?= e(t('แก้ไข', 'Edit')) ?></a>

                    <form method="post" style="display:inline;" onsubmit="return confirm('⚠️ ยืนยันการลบประกาศนี้ถาวรใช่หรือไม่?');">
                      <input type="hidden" name="csrf" value="<?= e($token) ?>">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button name="action" value="delete" class="btn-action btn-red"><?= e(t('ลบ', 'Delete')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

