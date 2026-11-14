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
    /* ปุ่ม hamburger: 44×44 ตามขนาดแตะขั้นต่ำของ Apple (HIG)
       ใช้ !important เพราะ index.php มีกฎขนาดของตัวเองที่ชนะ selector ทั่วไปได้ */
    .mobile-hamburger-btn {
      display: none;
      align-items: center;
      justify-content: center;
      width: 44px !important;
      height: 44px !important;
      padding: 0 !important;
      border: 1px solid #dce2e9;
      border-radius: 12px;
      background: #fff;
      color: #0B1C33;
      font-size: 21px;
      line-height: 1;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
      transition: border-color .2s ease, background .2s ease, transform .12s ease;
    }
    .mobile-hamburger-btn:active { background: #f1f4f9 !important; transform: scale(.94); }
    .mobile-hamburger-btn:focus-visible { outline: 3px solid #C5A059; outline-offset: 2px; }
    @media (hover: hover) { .mobile-hamburger-btn:hover { border-color: #C5A059; background: #fffdf7; } }

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
      width: 84vw;
      max-width: 360px;
      height: 100vh;
      height: 100dvh;
      background: #ffffff;
      z-index: 2001;
      box-shadow: -8px 0 32px rgba(11, 28, 51, .18);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      /* curve แบบ iOS sheet */
      transition: transform .34s cubic-bezier(.32, .72, 0, 1);
      visibility: hidden;
      border-radius: 0;
      overflow: hidden;
    }

    @media (prefers-reduced-motion: reduce) {
      .mobile-drawer,
      .mobile-drawer-overlay { transition-duration: .01ms !important; }
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
    /* หัว drawer: เว้น safe area ด้านบน (notch / Dynamic Island) */
    .drawer-header {
      flex: 0 0 auto;
      padding: calc(14px + env(safe-area-inset-top, 0px)) 16px 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #eef1f6;
    }
    /* ปุ่มปิด: วงกลม 36px แตะง่าย ตามแบบ iOS sheet */
    .drawer-close-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      width: 36px;
      height: 36px;
      padding: 0;
      border: none;
      border-radius: 50%;
      background: #f1f4f9;
      color: #5b6b80;
      font-size: 17px;
      line-height: 1;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
      transition: background-color .15s ease, transform .1s ease;
    }
    .drawer-close-btn:active { background: #e4eaf2; transform: scale(.92); }
    @media (hover: hover) { .drawer-close-btn:hover { background: #e9eef5; color: #0B1C33; } }

    .drawer-menu-list {
      list-style: none;
      padding: 10px 0 6px;
      margin: 0;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      flex: 1 1 auto;
      min-height: 0;
    }
    /* ---------- รายการเมนู: iOS-style list ─────────────────────────────
       แถวสูง ≥48px ตาม Apple HIG (ขั้นต่ำ 44pt) และมีระยะห่างระหว่างกลุ่ม
       ---------------------------------------------------------------- */
    .drawer-menu-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      min-height: 52px;
      padding: 12px 20px;
      color: #0B1C33;
      text-decoration: none;
      font-size: 16px;
      font-weight: 600;
      letter-spacing: -0.01em;
      border-radius: 12px;
      margin: 0 12px 2px;
      -webkit-tap-highlight-color: transparent;
      transition: background-color .15s ease, color .15s ease, transform .1s ease;
    }
    .drawer-menu-item:active {
      background: #f1f4f9;
      transform: scale(.985);
    }
    @media (hover: hover) {
      .drawer-menu-item:hover { background: #f6f8fb; color: #A8843F; }
    }
    .drawer-menu-item span.arrow { color: #c3ccd8; font-size: 18px; font-weight: 400; }

    /* ---------- 3 ปุ่มเด่น: การ์ดไอคอนในวงกลม ---------- */
    .drawer-hero-item {
      align-items: center !important;
      min-height: 68px !important;
      padding: 12px 14px !important;
      margin: 0 12px 10px !important;
      border: 1px solid transparent !important;
      box-shadow: 0 2px 10px rgba(11, 28, 51, .05) !important;
      gap: 10px !important;
      border-radius: 12px !important;
    }
    .drawer-item-main { display: flex !important; align-items: center !important; gap: 12px !important; min-width: 0 !important; }
    .drawer-item-icon {
      flex: 0 0 auto !important;
      display: grid !important;
      place-items: center !important;
      width: 42px !important;
      height: 42px !important;
      border-radius: 13px !important;
    }
    .drawer-item-icon svg { width: 21px !important; height: 21px !important; display: block !important; }
    .drawer-item-label {
      display: flex !important;
      flex-direction: column !important;
      min-width: 0 !important;
      font-size: 15.5px !important;
      font-weight: 800 !important;
      line-height: 1.25 !important;
      letter-spacing: -0.01em !important;
      text-align: left !important;
      color: inherit !important;
    }
    .drawer-item-label small {
      display: block !important;
      margin-top: 2px !important;
      font-size: 11.5px !important;
      font-weight: 600 !important;
      opacity: .72 !important;
      letter-spacing: 0 !important;
      white-space: nowrap !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
    }

    /* Primary (Reels) — เข้มทอง */
    .drawer-hero-item--primary {
      background: linear-gradient(135deg, #0B1C33 0%, #172D4D 55%, #A8843F 100%);
      color: #fff;
      border-color: rgba(248, 216, 136, .55);
      box-shadow: 0 6px 18px rgba(11, 28, 51, .22);
    }
    .drawer-hero-item--primary .drawer-item-icon { background: rgba(248, 216, 136, .16); color: #F8D888; }
    .drawer-hero-item--primary .arrow { color: #F8D888; }
    .drawer-hero-item--primary:active { background: linear-gradient(135deg, #0B1C33 0%, #172D4D 55%, #A8843F 100%); }

    /* AI — ทองอ่อน */
    .drawer-hero-item--ai {
      background: #FFF9EE;
      color: #0B1C33;
      border-color: rgba(197, 160, 89, .35);
    }
    .drawer-hero-item--ai .drawer-item-icon { background: rgba(197, 160, 89, .18); color: #A8843F; }
    .drawer-hero-item--ai .arrow { color: #A8843F; }

    /* Quick post — เทาอ่อน */
    .drawer-hero-item--quick { background: #F5F7FA; color: #0B1C33; }
    .drawer-hero-item--quick .drawer-item-icon { background: #e8edf4; color: #44566d; }

    @media (hover: hover) {
      .drawer-hero-item:hover { filter: brightness(.985); }
      .drawer-hero-item--primary:hover { filter: brightness(1.06); }
    }
    .drawer-hero-item:active { transform: scale(.985); }

    /* เส้นคั้นระหว่างกลุ่มเมนู */
    .drawer-divider {
      height: 1px;
      margin: 4px 22px 10px;
      background: #eef1f6;
    }

    /* ---------- Footer: ปลอดภัยกับ safe area / home indicator ของ iPhone --- */
    .drawer-footer {
      flex: 0 0 auto;
      padding: 14px 16px calc(14px + env(safe-area-inset-bottom, 0px));
      display: flex;
      flex-direction: column;
      gap: 10px;
      text-align: center;
      background: #fff;
      border-top: 1px solid #eef1f6;
      box-shadow: 0 -6px 20px rgba(11, 28, 51, 0.06);
    }

    /* ปุ่มหลัก: สูง 50px แตะง่าย นิ้วโป้งถึง */
    .drawer-login-btn {
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 50px !important;
      height: 50px !important;
      padding: 0 20px !important;
      border-radius: 14px !important;
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%) !important;
      color: #0B1C33 !important;
      text-decoration: none !important;
      font-weight: 800;
      font-size: 16px;
      letter-spacing: -0.01em;
      box-shadow: 0 6px 18px rgba(197, 160, 89, 0.32);
      -webkit-tap-highlight-color: transparent;
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }
    .drawer-login-btn:active {
      transform: scale(.97);
      box-shadow: 0 3px 10px rgba(197, 160, 89, 0.3);
      filter: brightness(.96);
    }
    @media (hover: hover) {
      .drawer-login-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(197, 160, 89, 0.42); }
    }

    /* ลิงก์รอง: เดิมสูงแค่ 21px และมีขีดเส้นใต้ -> ทำให้เป็นปุ่มแตะได้
       สุง ≥44px พื้นจาง โทนเดียวกับปุ่มหลัก */
    .drawer-download-link {
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 44px !important;
      height: 44px !important;
      padding: 0 16px !important;
      border-radius: 12px !important;
      background: #f5f7fa !important;
      color: #33465c !important;
      text-decoration: none !important;
      border-bottom: none !important;
      font-size: 14px !important;
      font-weight: 700 !important;
      -webkit-tap-highlight-color: transparent;
      transition: background-color .15s ease, transform .1s ease;
    }
    .drawer-download-link:active { background: #e9eef5; transform: scale(.985); }
    @media (hover: hover) { .drawer-download-link:hover { background: #eef2f7; } }

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
      <?php /* 3 ปุ่มเด่น: แสดงเป็น card ไอคอนในวงกลม (สไตล์ iOS) */ ?>
      <li>
        <a href="reels.php" class="drawer-menu-item drawer-hero-item drawer-hero-item--primary">
          <span class="drawer-item-main">
            <span class="drawer-item-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
            </span>
            <span class="drawer-item-label"><?= e(t('Property Reels', 'Property Reels')) ?><small><?= e(t('คลิปสั้นพาทัวร์', 'Short video tours')) ?></small></span>
          </span>
          <span class="arrow" aria-hidden="true">›</span>
        </a>
      </li>
      <li>
        <a href="ai-search.php" class="drawer-menu-item drawer-hero-item drawer-hero-item--ai">
          <span class="drawer-item-main">
            <span class="drawer-item-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.2 13.6 8l4.8 1.6-4.8 1.6L12 16l-1.6-4.8L5.6 9.6 10.4 8Z"></path><path d="M18 15.5l.7 2.1 2.1.7-2.1.7-.7 2.1-.7-2.1-2.1-.7 2.1-.7Z"></path></svg>
            </span>
            <span class="drawer-item-label"><?= e(t('ค้นหาด้วย AI', 'AI Deep Search')) ?><small><?= e(t('พิมพ์เป็นภาษาพูดได้', 'Describe it in plain words')) ?></small></span>
          </span>
          <span class="arrow" aria-hidden="true">›</span>
        </a>
      </li>
      <li>
        <a href="submit-reel.php" class="drawer-menu-item drawer-hero-item drawer-hero-item--quick">
          <span class="drawer-item-main">
            <span class="drawer-item-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
            </span>
            <span class="drawer-item-label"><?= e(t('โพสต์คลิปด่วน', 'Quick Post Reel')) ?><small><?= e(t('30 วินาทีเสร็จ', 'Ready in 30 seconds')) ?></small></span>
          </span>
          <span class="arrow" aria-hidden="true">›</span>
        </a>
      </li>

      <li class="drawer-divider" aria-hidden="true"></li>
      <li>
        <a href="search.php?type=sale" class="drawer-menu-item">
          <span><?= e(t('ซื้อ', 'Buy')) ?></span>
          <span class="arrow">›</span>
        </a>
      </li>
      <li>
        <a href="search.php?type=rent" class="drawer-menu-item">
          <span><?= e(t('เช่า', 'Rent')) ?></span>
          <span class="arrow">›</span>
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
