<?php
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/staff.php';
current_lang();

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
$isPartner = ($_SESSION['role'] ?? '') === 'partner';
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$partnerTitle = $_SESSION['partner_title'] ?? ($isFounder ? 'FOUNDER & CEO' : 'PARTNER');

// รายการชุดเครื่องมือการบริหารทั้งหมดของระบบ จัดหมวดหมู่ชัดเจนสำหรับ Founder & CEO
$catalog = [
    'listings' => [
        'title' => t('จัดการประกาศ', 'Listings'),
        'icon'  => '▦',
        'items' => [
            ['index.php', '▦', t('ประกาศทั้งหมด', 'All Listings'), t('ตรวจอนุมัติ ซ่อน และจัดการ', 'Review, approve & manage')],
            ['add.php', '＋', t('เพิ่มประกาศใหม่', 'Add New Listing'), t('ลงทรัพย์ใหม่เข้าสู่ระบบ', 'Post new property')],
        ],
    ],
    'projects' => [
        'title' => t('โครงการใหม่', 'Projects'),
        'icon'  => '⌂',
        'items' => [
            ['projects.php', '⌂', t('รายการโครงการ', 'Project Portfolio'), t('โครงการบ้าน คอนโด พัฒนาใหม่', 'New property developments')],
        ],
    ],
    'agents' => [
        'title' => t('ตัวแทน Agent', 'Agents'),
        'icon'  => '✓',
        'items' => [
            ['agents.php', '✓', t('ตรวจรับรอง Agent', 'Verify Agents'), t('ตรวจสอบบัตรและเอกสารตัวแทน', 'ID verification & badges')],
        ],
    ],
    'marketing' => [
        'title' => t('การตลาด & SEO', 'Marketing & SEO'),
        'icon'  => '📣',
        'items' => [
            ['seo.php', '⚡', t('SEO & AI เจาะ 77 จว. & CBD', 'SEO & AI Geo Engine'), t('เจาะทำเลทองและคีย์เวิร์ด AI', 'Target prime zones & AI prompts')],
            ['featured.php', '✦', t('หมวดหมู่แนะนำหน้าแรก', 'Featured Sections'), t('โปรโมตคอนโด บ้านเดี่ยวหน้าแรก', 'Homepage highlighted categories')],
            ['banners.php', '🎬', t('สื่อโปรโมชัน / วิดีโอลูป', 'Promo Media & Ads'), t('แบนเนอร์ วิดีโอโฆษณาหน้าแรก', 'Media ads & looping video')],
        ],
    ],
    'content' => [
        'title' => t('เนื้อหา & ภาพ', 'Content'),
        'icon'  => '🖼️',
        'items' => [
            ['favicon.php', '🎨', t('โลโก้ & Favicon (SEO)', 'Favicon & Brand Icon'), t('จัดการไอคอนที่แสดงบน Google Search', 'Google SERP icon & tab favicon')],
            ['category-images.php', '▧', t('ภาพหมวดหมู่อสังหาฯ', 'Category Covers'), t('ปรับเปลี่ยนภาพปกหมวดต่างๆ', 'Custom category cover images')],
        ],
    ],
    'analytics' => [
        'title' => t('สถิติ & รายงาน', 'Analytics'),
        'icon'  => '📊',
        'items' => [
            ['analytics.php', '◷', t('สถิติผู้เข้าชมเชิงลึก', 'Traffic Analytics'), t('วิเคราะห์ยอดวิวและผู้ใช้', 'Real-time traffic & views')],
            ['pdf-report.php', '▤', t('สร้างรายงานสรุป PDF', 'PDF Report Generator'), t('พิมพ์เอกสารสรุปผลประจำเดือน', 'Monthly executive report')],
        ],
    ],
    'founder_only' => [
        'title' => t('สมาชิก & เอกสิทธิ์', 'Members & Founder'),
        'icon'  => '👑',
        'items' => [
            ['members.php', '👥', t('จัดการสมาชิก & โควตา', 'Member Governance'), t('ปรับสิทธิ์และจำกัดโควตาประกาศ', 'Quota, tiers & user permissions')],
            ['features.php', '🗺️', t('แผนที่ฟีเจอร์ทั้งหมด', 'Feature Map'), t('ตรวจสอบฟีเจอร์และนวัตกรรมทั้งหมดของระบบ', 'All active system features & audit')],
            ['partners.php', '👑', t('จัดการหุ้นส่วน & สิทธิ์', 'Partners & Access'), t('กำหนดสิทธิ์รายบุคคลตามตำแหน่ง', 'Role-based access control')],
        ],
    ],
    'settings' => [
        'title' => t('ตั้งค่าระบบ', 'Settings'),
        'icon'  => '⚙',
        'items' => [
            ['version.php', '⬇', t('เวอร์ชันระบบ', 'System Version'), t('ปรับเปลี่ยนเวอร์ชันแพลตฟอร์ม', 'Version stamping & release')],
            ['oauth.php', '🔒', t('Social Login (Google/LINE)', 'Social Login Setup'), t('ตั้งค่าการเข้าสู่ระบบผ่านโซเชียล', 'OAuth API configuration')],
        ],
    ],
];

// เตรียมเมนูตามสิทธิ์ (สำหรับ Dropdown และ Sidebar)
$activeCatalog = [];
if ($isFounder) {
    $activeCatalog = $catalog;
} else {
    $perms = staff_permissions(db(), $staffId);
    foreach ($catalog as $catKey => $catData) {
        $allowedItems = [];
        foreach ($catData['items'] as $item) {
            $file = $item[0];
            $can = false;
            if ($catKey === 'listings') {
                if ($file === 'index.php' && (!empty($perms['listings']['view']) || !empty($perms['listings']['manage']))) $can = true;
                if ($file === 'add.php' && (!empty($perms['listings']['create']) || !empty($perms['listings']['manage']))) $can = true;
            } elseif ($catKey === 'projects') {
                if (!empty($perms['projects']['view']) || !empty($perms['projects']['manage'])) $can = true;
            } elseif ($catKey === 'agents') {
                if (!empty($perms['agents']['view']) || !empty($perms['agents']['manage'])) $can = true;
            } elseif ($catKey === 'marketing') {
                if (!empty($perms['marketing']['view']) || !empty($perms['marketing']['manage'])) $can = true;
            } elseif ($catKey === 'content') {
                if (!empty($perms['content']['view']) || !empty($perms['content']['manage'])) $can = true;
            } elseif ($catKey === 'analytics') {
                if (!empty($perms['analytics']['view']) || !empty($perms['analytics']['manage'])) $can = true;
            } elseif ($catKey === 'settings') {
                if (!empty($perms['settings']['manage'])) $can = true;
            }
            if ($can) {
                $allowedItems[] = $item;
            }
        }
        if (!empty($allowedItems)) {
            $catData['items'] = $allowedItems;
            $activeCatalog[$catKey] = $catData;
        }
    }
}

// แบนเป็น flat list สำหรับ compatibility และนับจำนวน
$adminNavItems = [];
foreach ($activeCatalog as $catData) {
    foreach ($catData['items'] as $it) {
        $adminNavItems[] = [$it[0], $it[1], $it[2]];
    }
}
if (!$adminNavItems) {
    $adminNavItems = [['index.php', '▦', t('หน้าหลัก', 'Home')]];
}

// ข้อมูลโปรไฟล์ของผู้ดูแล/หุ้นส่วน
if ($isFounder) {
    $profileQuery = db()->prepare('SELECT full_name, username, email, avatar_path FROM users WHERE id = ? LIMIT 1');
    $profileQuery->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $adminProfile = $profileQuery->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'username' => 'jesada', 'email' => '', 'avatar_path' => null];
    $badgeText = 'FOUNDER & CEO';
    $badgeBrand = '👑 FOUNDER & CEO';
} else {
    $profileQuery = db()->prepare('SELECT full_name, username, email FROM staff_accounts WHERE id = ? LIMIT 1');
    $profileQuery->execute([$staffId]);
    $adminProfile = $profileQuery->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'username' => (string)($_SESSION['username'] ?? 'partner'), 'email' => '', 'avatar_path' => null];
    $badgeText = mb_strtoupper($partnerTitle);
    $badgeBrand = '💼 ' . $badgeText;
}

$adminDisplayName = trim((string)$adminProfile['full_name']) ?: (string)$adminProfile['username'];
$adminInitial = mb_strtoupper(mb_substr($adminDisplayName, 0, 1));
$currentAdminPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<header class="admin-desktop-navbar">
  <?= render_logo_html('index.php', 'sp-logo--dark') ?><span class="admin-brand-admin-label"><?= e($badgeBrand) ?></span>
  <button class="admin-nav-scroll-btn admin-nav-scroll-prev" type="button" aria-label="<?= e(t('เลื่อนเมนูไปทางซ้าย', 'Scroll menu left')) ?>" aria-controls="adminNavbarLinks" hidden>‹</button>
  <nav class="admin-navbar-links" id="adminNavbarLinks" tabindex="0" aria-label="<?= e(t('เมนูผู้ดูแล', 'Admin menu')) ?>">
    <?php foreach ($activeCatalog as $catKey => $catData): ?>
      <?php
      $items = $catData['items'];
      $isSingle = count($items) === 1;
      $hasActiveChild = false;
      foreach ($items as $it) {
          if ($currentAdminPage === $it[0] || ($it[0] === 'add.php' && $currentAdminPage === 'edit.php')) {
              $hasActiveChild = true;
              break;
          }
      }
      ?>
      <?php if ($isSingle): ?>
        <a href="<?= e($items[0][0]) ?>" class="admin-nav-item-link <?= $hasActiveChild ? 'active' : '' ?>">
          <span class="admin-nav-item-icon"><?= e($items[0][1]) ?></span>
          <span><?= e($catData['title']) ?></span>
        </a>
      <?php else: ?>
        <div class="admin-nav-dropdown <?= $hasActiveChild ? 'has-active' : '' ?>" tabindex="0">
          <button type="button" class="admin-nav-dropdown-btn <?= $hasActiveChild ? 'active' : '' ?>">
            <span class="admin-nav-item-icon"><?= e($catData['icon']) ?></span>
            <span><?= e($catData['title']) ?></span>
            <span class="admin-nav-caret">▼</span>
          </button>
          <div class="admin-nav-dropdown-menu">
            <div class="admin-dropdown-header">
              <strong><?= e($catData['icon']) ?> <?= e($catData['title']) ?></strong>
              <small><?= count($items) ?> <?= e(t('เครื่องมือจัดการ', 'management tools')) ?></small>
            </div>
            <?php foreach ($items as [$href, $icon, $label, $desc]): ?>
              <a href="<?= e($href) ?>" class="admin-dropdown-item <?= ($currentAdminPage === $href || ($href === 'add.php' && $currentAdminPage === 'edit.php')) ? 'active' : '' ?>">
                <span class="admin-dropdown-icon"><?= e($icon) ?></span>
                <div class="admin-dropdown-text">
                  <span class="admin-dropdown-title"><?= e($label) ?></span>
                  <?php if (!empty($desc)): ?>
                    <span class="admin-dropdown-desc"><?= e($desc) ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($isFounder): ?>
      <a href="profile.php" class="admin-nav-item-link <?= $currentAdminPage === 'profile.php' ? 'active' : '' ?>">
        <span class="admin-nav-item-icon">⚙</span>
        <span><?= e(t('โปรไฟล์', 'Profile')) ?></span>
      </a>
    <?php endif; ?>
  </nav>
  <button class="admin-nav-scroll-btn admin-nav-scroll-next" type="button" aria-label="<?= e(t('เลื่อนเมนูไปทางขวา', 'Scroll menu right')) ?>" aria-controls="adminNavbarLinks" hidden>›</button>
  <div class="admin-navbar-account">
    <div class="admin-navbar-user-info">
      <?php if (!empty($adminProfile['avatar_path']) && is_file(__DIR__ . '/../uploads/avatars/' . basename($adminProfile['avatar_path']))): ?>
        <img class="admin-navbar-avatar" src="../uploads/avatars/<?= e(basename($adminProfile['avatar_path'])) ?>" alt="">
      <?php else: ?>
        <span class="admin-navbar-avatar admin-navbar-avatar-fallback"><?= e($adminInitial) ?></span>
      <?php endif; ?>
      <span class="admin-navbar-user-name" title="<?= e($adminDisplayName) ?>"><?= e($adminDisplayName) ?></span>
    </div>
    <a href="../index.php" target="_blank" rel="noopener" class="admin-view-site-btn">🌐 <?= e(t('ดูเว็บ', 'View Site')) ?></a>
    <span class="admin-navbar-lang"><?php render_lang_toggle(); ?></span>
    <a href="../logout.php" class="admin-navbar-logout"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
  </div>
</header>

  <div class="admin-mobile-topbar" aria-label="<?= e(t('แถบเครื่องมือมือถือ', 'Mobile admin bar')) ?>">
    <button class="admin-sidebar-toggle" type="button" aria-label="<?= e(t('เปิดเมนูผู้ดูแล', 'Open admin menu')) ?>" aria-expanded="false" aria-controls="adminSidebar">☰</button>
    <div class="admin-mobile-brand">
      <?= render_logo_html('index.php', 'sp-logo--dark') ?>
      <span class="admin-mobile-badge"><?= e($badgeBrand) ?></span>
    </div>
    <div class="admin-mobile-actions">
      <a href="../index.php" target="_blank" rel="noopener" class="admin-mobile-site-btn" title="<?= e(t('ดูหน้าเว็บไซต์', 'View public site')) ?>">🌐</a>
      <a href="../logout.php" class="admin-mobile-logout-btn" title="<?= e(t('ออกจากระบบ', 'Log out')) ?>">✕</a>
    </div>
  </div>
  <div class="admin-sidebar-backdrop" data-admin-sidebar-close hidden></div>

<aside class="admin-sidebar" id="adminSidebar" aria-label="<?= e(t('เมนูผู้ดูแลระบบ', 'Admin sidebar')) ?>">
  <?= render_logo_html('index.php', 'sp-logo--dark') ?><span class="admin-brand-admin-label"><?= e($badgeBrand) ?></span>

  <section class="admin-user-card" aria-label="<?= e(t('บัญชีผู้บริหาร', 'Executive account')) ?>">
    <?php if (!empty($adminProfile['avatar_path']) && is_file(__DIR__ . '/../uploads/avatars/' . basename($adminProfile['avatar_path']))): ?>
      <img class="admin-avatar admin-avatar-photo" src="../uploads/avatars/<?= e(basename($adminProfile['avatar_path'])) ?>" alt="รูปผู้ดูแล">
    <?php else: ?>
      <span class="admin-avatar"><?= e($adminInitial) ?></span>
    <?php endif; ?>
    <span class="admin-user-meta">
      <strong><?= e($adminDisplayName) ?></strong>
      <small><?= e($adminProfile['email']) ?></small>
    </span>
    <span class="admin-role-badge"><?= e($badgeText) ?></span>
  </section>

  <div class="admin-sidebar-groups">
    <?php foreach ($activeCatalog as $catKey => $catData): ?>
      <div class="admin-sidebar-group">
        <div class="admin-sidebar-group-title">
          <span><?= e($catData['icon']) ?> <?= e($catData['title']) ?></span>
        </div>
        <nav class="admin-sidebar-nav">
          <?php foreach ($catData['items'] as [$href, $icon, $label, $desc]): ?>
            <a href="<?= e($href) ?>" class="<?= ($currentAdminPage === $href || ($href === 'add.php' && $currentAdminPage === 'edit.php')) ? 'active' : '' ?>">
              <span class="admin-nav-icon" aria-hidden="true"><?= e($icon) ?></span>
              <div class="admin-sidebar-link-text">
                <span class="admin-sidebar-link-label"><?= e($label) ?></span>
                <?php if (!empty($desc)): ?>
                  <small class="admin-sidebar-link-sub"><?= e($desc) ?></small>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="admin-sidebar-label"><?= e(t('บัญชีของฉัน', 'My Account')) ?></div>
  <nav class="admin-sidebar-nav">
    <?php if ($isFounder): ?>
      <a href="profile.php" class="<?= $currentAdminPage === 'profile.php' ? 'active' : '' ?>"><span class="admin-nav-icon" aria-hidden="true">⚙</span><?= e(t('โปรไฟล์และบัญชี', 'Profile & Account')) ?></a>
    <?php endif; ?>
    <a href="../index.php" target="_blank" rel="noopener"><span class="admin-nav-icon" aria-hidden="true">↗</span><?= e(t('ดูหน้าเว็บไซต์', 'View Website')) ?></a>
  </nav>

  <div class="admin-sidebar-footer-actions" style="display:flex;align-items:center;gap:8px;margin-top:auto;padding-top:10px;">
    <?php render_lang_toggle(); ?>
    <a class="admin-sidebar-logout" href="../logout.php" style="flex:1;margin-top:0;"><span aria-hidden="true">⇥</span> <?= e(t('ออกจากระบบ', 'Log out')) ?></a>
  </div>

  <div style="display:flex;justify-content:center;width:100%;margin-top:8px;">
    <div class="admin-sidebar-version"><?= e(SITE_NAME) ?> · <?= e(SITE_VERSION) ?></div>
  </div>
</aside>

<script>
(() => {
  const sidebar = document.getElementById('adminSidebar');
  const toggle = document.querySelector('.admin-sidebar-toggle');
  const backdrop = document.querySelector('.admin-sidebar-backdrop');
  if (!sidebar || !toggle || !backdrop) return;
  document.body.classList.add('has-admin-sidebar');
  const close = () => {
    sidebar.classList.remove('open');
    backdrop.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'เปิดเมนูผู้ดูแล');
    toggle.textContent = '☰';
    document.body.classList.remove('admin-sidebar-open');
  };
  toggle.addEventListener('click', () => {
    const open = sidebar.classList.toggle('open');
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'ปิดเมนูผู้ดูแล' : 'เปิดเมนูผู้ดูแล');
    toggle.textContent = open ? '×' : '☰';
    document.body.classList.toggle('admin-sidebar-open', open);
  });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 900) close(); });
})();

/* ── CEO Navbar Dropdowns: คลิกเปิด-ปิด (รองรับมือถือ/แท็บเล็ตและคีย์บอร์ด) ── */
(() => {
  'use strict';
  const dropdowns = Array.from(document.querySelectorAll('.admin-nav-dropdown'));
  if (!dropdowns.length) return;

  const closeAll = (except) => {
    dropdowns.forEach(d => {
      if (d !== except) {
        d.classList.remove('open');
        const btn = d.querySelector('.admin-nav-dropdown-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
  };

  dropdowns.forEach(dropdown => {
    const btn = dropdown.querySelector('.admin-nav-dropdown-btn');
    if (!btn) return;

    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-haspopup', 'true');

    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const willOpen = !dropdown.classList.contains('open');
      closeAll(dropdown);
      dropdown.classList.toggle('open', willOpen);
      btn.setAttribute('aria-expanded', String(willOpen));
      window.dispatchEvent(new CustomEvent('admin-nav-dropdown-change', { detail: { dropdown: willOpen ? dropdown : null } }));
    });

    // แตะ/คลิกรายการในเมนูแล้วปิด dropdown ทันที (กันสะดุดตอนนำทาง)
    dropdown.querySelectorAll('.admin-dropdown-item').forEach(item => {
      item.addEventListener('click', () => closeAll(null));
    });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.admin-nav-dropdown')) closeAll(null);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAll(null);
  });

  // เปลี่ยนหน้า/ย้าย focus ออกนอกเมนู → ปิดให้เอง
  window.addEventListener('pagehide', () => closeAll(null));
  document.addEventListener('focusin', (event) => {
    if (!event.target.closest('.admin-nav-dropdown')) closeAll(null);
  });
})();

/* ── Horizontal navigation controls: buttons, wheel, touch and mouse drag ── */
(() => {
  const nav = document.getElementById('adminNavbarLinks');
  if (!nav) return;
  const previous = document.querySelector('.admin-nav-scroll-prev');
  const next = document.querySelector('.admin-nav-scroll-next');
  const update = () => {
    const maxScroll = nav.scrollWidth - nav.clientWidth;
    const scrollable = maxScroll > 2;
    nav.classList.toggle('is-scrollable', scrollable);
    if (previous) {
      previous.hidden = !scrollable;
      previous.disabled = nav.scrollLeft <= 2;
    }
    if (next) {
      next.hidden = !scrollable;
      next.disabled = nav.scrollLeft >= maxScroll - 2;
    }
    positionDropdown();
  };
  const positionDropdown = (targetDropdown) => {
    if (window.innerWidth <= 900) return;
    const dropdown = targetDropdown || nav.querySelector('.admin-nav-dropdown.open');
    if (!dropdown) return;
    const button = dropdown.querySelector('.admin-nav-dropdown-btn');
    const panel = dropdown.querySelector('.admin-nav-dropdown-menu');
    if (!button || !panel) return;
    const rect = button.getBoundingClientRect();
    const panelWidth = Math.min(340, window.innerWidth - 24);
    // วางตรงกับหัวข้อพอดี (ชิดซ้ายของปุ่มหัวข้อ) โดยไม่หลุดขอบซ้าย/ขวาของหน้าจอ
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - panelWidth - 12));
    const navbarBottom = nav.closest('.admin-desktop-navbar')?.getBoundingClientRect().bottom || 68;
    const top = Math.max(rect.bottom + 6, navbarBottom + 4);
    panel.style.setProperty('--dd-left', `${left}px`);
    panel.style.setProperty('--dd-top', `${top}px`);
  };
  previous && previous.addEventListener('click', () => nav.scrollBy({ left: -Math.max(180, nav.clientWidth * 0.7), behavior: 'smooth' }));
  next && next.addEventListener('click', () => nav.scrollBy({ left: Math.max(180, nav.clientWidth * 0.7), behavior: 'smooth' }));
  nav.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', () => update());
  window.addEventListener('admin-nav-dropdown-change', (event) => positionDropdown(event.detail?.dropdown));
  nav.addEventListener('wheel', event => {
    if (nav.scrollWidth > nav.clientWidth && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
      nav.scrollLeft += event.deltaY;
      event.preventDefault();
    }
  }, { passive: false });

  let pointerStart = null;
  let suppressClick = false;
  nav.addEventListener('pointerdown', event => {
    if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('a, button')) return;
    pointerStart = { x: event.clientX, left: nav.scrollLeft, moved: false };
    nav.setPointerCapture(event.pointerId);
  });
  nav.addEventListener('pointermove', event => {
    if (!pointerStart) return;
    const distance = event.clientX - pointerStart.x;
    if (Math.abs(distance) > 4) pointerStart.moved = true;
    if (pointerStart.moved) {
      nav.scrollLeft = pointerStart.left - distance;
      nav.classList.add('is-dragging');
    }
  });
  const endDrag = () => {
    if (pointerStart && pointerStart.moved) {
      suppressClick = true;
      window.setTimeout(() => { suppressClick = false; }, 0);
    }
    pointerStart = null;
    nav.classList.remove('is-dragging');
  };
  nav.addEventListener('pointerup', endDrag);
  nav.addEventListener('pointercancel', endDrag);
  nav.addEventListener('click', event => {
    if (suppressClick) {
      event.preventDefault();
      event.stopPropagation();
      suppressClick = false;
    }
  }, true);
  update();
  requestAnimationFrame(update);
})();
</script>
