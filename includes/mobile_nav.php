<?php
/**
 * Mobile navigation for pages that use the legacy <header class="site-header">.
 *
 * index.php has its own copy of the hamburger button, drawer markup, CSS and JS.
 * Pages such as project.php / search.php / category.php never got one, so on a
 * narrow screen the header collapsed to just the logo and there was no way to
 * reach the menu at all.
 *
 * This include provides the same thing as index.php (same classes, same look) so
 * a page only needs:
 *
 *     <?php render_mobile_nav(); ?>            // after </header>  → drawer markup
 *     <?php render_mobile_nav_assets(); ?>     // inside <head>    → CSS + JS
 *
 * Both are guarded by a static flag, so including them twice on one page is safe.
 * Nothing is emitted on desktop because the CSS hides the button above 900px.
 */

if (!function_exists('render_mobile_nav_assets')) {
    /** CSS + JS สำหรับปุ่ม hamburger และ drawer — วางไว้ใน <head> */
    function render_mobile_nav_assets(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
  <style>
    /* ==========================================================================
       Mobile Drawer Menu (ชุดเดียวกับ index.php — ใช้ร่วมกันทุกหน้า)
       ========================================================================== */
    /* index.php มีกฎขนาดของตัวเองที่ชนะ selector ทั่วไปได้ — ใช้ !important กัน ไม่ให้ปุ่มย่อ
       (ซ้ำซ้อนกับกฎที่อยู่ท้ายไฟล์ เจตนาคือให้ชนะได้ทุกกรณี) */
    .mobile-hamburger-btn {
      display: none;
      align-items: center;
      justify-content: center;
      width: 40px !important;
      height: 40px !important;
      padding: 0 !important;
      border: 1px solid #dce2e9;
      border-radius: 10px;
      background: #fff;
      color: #0B1C33;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
      transition: border-color .2s ease, background .2s ease;
    }
    .mobile-hamburger-btn:hover { border-color: #C5A059; background: #fffdf7; }
    .mobile-hamburger-btn:focus-visible { outline: 3px solid #C5A059; outline-offset: 2px; }

    .mobile-drawer-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .mobile-drawer-overlay.active { opacity: 1; visibility: visible; }

    .mobile-drawer {
      position: fixed;
      top: 0 !important;
      bottom: 0 !important;
      right: 0 !important;
      left: auto !important;
      transform: translateX(105%);
      width: 310px;
      max-width: 85vw;
      height: 100vh;
      height: 100dvh;
      background: #ffffff;
      z-index: 2001;
      box-shadow: -4px 0 20px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      visibility: hidden;
    }
    .mobile-drawer.active { transform: translateX(0) !important; visibility: visible; margin: 0 !important; }

    /* บางหน้า (property.php) มี CSS ระดับ global ที่ใส่ margin/padding ให้ aside
       ทำให้ drawer ลอยลงมา 16px แล้ว footer ล้นออกนอกจอ */
    .mobile-drawer,
    .mobile-drawer > div:first-child,
    .mobile-drawer .drawer-menu-list,
    .mobile-drawer .drawer-footer { margin: 0 !important; }

    /* ลูกของ drawer ต้องหดได้ (min-height:0) ไม่งั้น list จะดัน footer ล้นออกนอกจอ
       และ overflow-y:auto ของ .drawer-menu-list จะไม่มีผล */
    .mobile-drawer > div:first-child {
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden;
    }
    .drawer-header {
      flex: 0 0 auto;
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .drawer-close-btn {
      background: none;
      border: none;
      font-size: 22px;
      color: #64748b;
      cursor: pointer;
      padding: 4px;
    }
    .drawer-menu-list {
      list-style: none;
      padding: 0;
      margin: 0;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      flex: 1 1 auto;
      min-height: 0;
    }
    .drawer-menu-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      color: #261d3b;
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
    }
    .drawer-menu-item:hover { background: #f8fafc; color: #7955d9; }
    .drawer-menu-item span.arrow { color: #94a3b8; font-size: 14px; }
    .drawer-footer {
      flex: 0 0 auto;
      padding: 16px 20px calc(16px + env(safe-area-inset-bottom, 0px));
      display: flex;
      flex-direction: column;
      gap: 12px;
      text-align: center;
      background: #ffffff;
      border-top: 1px solid #eef1f6;
    }
    .drawer-login-btn {
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      text-decoration: none;
      padding: 12px;
      border-radius: 9999px;
      font-weight: 800;
      font-size: 15px;
      display: block;
      box-shadow: 0 4px 14px rgba(197, 160, 89, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .drawer-login-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(197, 160, 89, 0.45);
    }
    .drawer-download-link { color: #261d3b; font-size: 13px; font-weight: 700; }

    /* ขนาดปุ่ม hamburger (index.php มีกฎของตัวเองที่ชนะได้ง่าย — ทำซ้ำด้วย !important) */
    .mobile-hamburger-btn {
      display: none;
      align-items: center;
      justify-content: center;
      width: 40px !important;
      height: 40px !important;
      padding: 0 !important;
      border: 1px solid #dce2e9;
      border-radius: 10px;
      background: #fff;
      color: #0B1C33;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
    }

    /* ซ่อนเมนูแนวนอนและแสดงปุ่ม hamburger บนจอเล็ก */
    @media (max-width: 900px) {
      .site-header .site-nav { display: none; }
      .mobile-hamburger-btn { display: inline-flex !important; }
    }

    /* index.php ตั้ง .kasa-logo-img--dark{display:none} แบบเจาะจง — ต้องบังคับอีกชั้น */
    .mobile-drawer .drawer-header .kasa-logo-img--dark { display: block !important; }
    .mobile-drawer .drawer-header .kasa-logo-img--light { display: none !important; }
    @media (min-width: 901px) {
      .mobile-drawer,
      .mobile-drawer-overlay { display: none !important; }
    }
  </style>
  <script>
  (function () {
    function initMobileNav() {
      var drawer = document.getElementById('mobileDrawer');
      var overlay = document.getElementById('drawerOverlay');
      var openBtn = document.getElementById('openDrawerBtn');
      var closeBtn = document.getElementById('closeDrawerBtn');
      if (!drawer || !overlay || !openBtn) return false;
      if (drawer.dataset.mobileNavReady === '1') return true;
      drawer.dataset.mobileNavReady = '1';

      function open() {
        drawer.classList.add('active');
        overlay.classList.add('active');
        drawer.setAttribute('aria-hidden', 'false');
        openBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        if (closeBtn) closeBtn.focus();
      }
      function close() {
        drawer.classList.remove('active');
        overlay.classList.remove('active');
        drawer.setAttribute('aria-hidden', 'true');
        openBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        openBtn.focus();
      }

      openBtn.addEventListener('click', function (e) { e.preventDefault(); open(); });
      if (closeBtn) closeBtn.addEventListener('click', close);
      overlay.addEventListener('click', close);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('active')) close();
      });
      return true;
    }

    if (!initMobileNav()) {
      document.addEventListener('DOMContentLoaded', initMobileNav);
    }
  })();
  </script>
        <?php
    }
}

if (!function_exists('render_mobile_nav')) {
    /** ปุ่ม hamburger + drawer markup — วางไว้หลัง </header> (หรือใน header ก็ได้) */
    function render_mobile_nav(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $loggedIn  = !empty($_SESSION['user_id']);
        $isAdmin   = $loggedIn && (($_SESSION['role'] ?? '') === 'admin');
        $dashUrl   = $isAdmin ? 'admin/index.php' : 'dashboard.php';
        ?>
<button type="button" class="mobile-hamburger-btn" id="openDrawerBtn" aria-label="<?= e(t('เปิดเมนู', 'Open menu')) ?>" aria-expanded="false" aria-controls="mobileDrawer">☰</button>

<div class="mobile-drawer-overlay" id="drawerOverlay"></div>
<aside class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
  <div>
    <div class="drawer-header">
      <?= render_logo_html('index.php') ?>
      <button type="button" class="drawer-close-btn" id="closeDrawerBtn" aria-label="<?= e(t('ปิดเมนู', 'Close menu')) ?>">✕</button>
    </div>

    <ul class="drawer-menu-list">
      <li>
        <a href="reels.php" class="drawer-menu-item" style="color:#ffffff !important; font-weight:800; background:linear-gradient(135deg, #0B1C33 0%, #172D4D 45%, #C5A059 100%) !important; border:1px solid rgba(248, 216, 136, 0.75) !important; border-radius:12px; margin:6px 14px 4px; box-shadow:0 4px 14px rgba(197,160,89,0.35);">
          <span style="display:inline-flex; align-items:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
            <span><?= e(t('Property Reels (คลิปสั้นพาทัวร์)', 'Property Reels (Short Video Tours)')) ?></span>
          </span>
          <span class="arrow" style="color:#F8D888;">›</span>
        </a>
      </li>
      <li>
        <a href="ai-search.php" class="drawer-menu-item" style="color:#0B1C33 !important; font-weight:800; background:rgba(197,160,89,0.14); border:1px solid rgba(197, 160, 89, 0.45); border-radius:12px; margin:0 14px 10px;">
          <span style="display:inline-flex; align-items:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
            <span><?= e(t('ค้นหาด้วย AI', 'AI Deep Search')) ?></span>
          </span>
          <span class="arrow" style="color:#A8843F;">›</span>
        </a>
      </li>
      <li>
        <a href="submit-reel.php" class="drawer-menu-item" style="color:#F8D888 !important; font-weight:800; background:rgba(11,28,51,0.06); border:1px solid rgba(197, 160, 89, 0.4); border-radius:12px; margin:0 14px 10px;">
          <span style="display:inline-flex; align-items:center; gap:8px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
            <span><?= e(t('โพสต์คลิป Reels ด่วน (30 วิ)', 'Quick Post Reel (30s)')) ?></span>
          </span>
          <span class="arrow" style="color:#C5A059;">›</span>
        </a>
      </li>
      <li>
        <a href="search.php?type=sale" class="drawer-menu-item">
          <span><?= e(t('ซื้อ', 'Buy')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <li>
        <a href="search.php?type=rent" class="drawer-menu-item">
          <span><?= e(t('เช่า', 'Rent')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <li>
        <a href="category.php?page=house" class="drawer-menu-item">
          <span><?= e(t('บ้านเดี่ยว', 'Houses')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="search.php?category=commercial" class="drawer-menu-item">
          <span><?= e(t('พาณิชย์', 'Commercial')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="project.php" class="drawer-menu-item">
          <span><?= e(t('โครงการใหม่', 'New Projects')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="advertise.php" class="drawer-menu-item">
          <span><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="submit.php" class="drawer-menu-item">
          <span><?= e(t('ลงประกาศ', 'Post a listing')) ?></span>
          <span class="arrow">⌄</span>
        </a>
      </li>
      <?php if ($loggedIn): ?>
        <li>
          <a href="<?= e($dashUrl) ?>" class="drawer-menu-item">
            <span><?= e(t('แดชบอร์ด', 'Dashboard')) ?></span>
            <span class="arrow">›</span>
          </a>
        </li>
      <?php endif; ?>
      <li><?php render_lang_toggle(); ?></li>
    </ul>
  </div>

  <div class="drawer-footer">
    <?php if ($loggedIn): ?>
      <a href="<?= e($dashUrl) ?>" class="drawer-login-btn"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
      <a href="logout.php" class="drawer-download-link"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
    <?php else: ?>
      <a href="login.php" class="drawer-login-btn"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
    <?php endif; ?>
    <a href="submit.php" class="drawer-download-link"><?= e(t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')) ?></a>
  </div>
</aside>
        <?php
    }
}
