<?php
// Footnavbar — แถบเมนูล่างสไตล์ iOS Liquid Glass (มือถือ/iPad เท่านั้น, ≤1199px)
//
// วิธีใช้: วาง render_glass_footnav(); ก่อน </body> ของแต่ละหน้า
// แต่ละหน้าต้องมี <style> ของ footnav (render_glass_footnav_assets) ใน <head>
// หรือเรียก assets ไปพร้อม markup ก็ได้ (ฟังก์ชันนี้พิมพ์ style แนบมาให้ครั้งเดียว)

if (!function_exists('render_glass_footnav_assets')) {
    /** CSS ของ footnavbar — เรียกใน <head> (หรือปล่อยให้ render_glass_footnav พิมพ์เอง) */
    function render_glass_footnav_assets(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        ?>
<style>
  /* ========================= Glass Footnavbar ========================= */
  .glass-footnav {
    display: none;
  }
  @media (max-width: 1199px) {
    .glass-footnav {
      position: fixed;
      left: 12px;
      right: 12px;
      bottom: calc(10px + env(safe-area-inset-bottom, 0px));
      z-index: 1100;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      align-items: stretch;
      padding: 7px 10px;
      border-radius: 26px;
      isolation: isolate;
      overflow: hidden;
      border: 0.5px solid rgba(255, 255, 255, 0.55);
      /* พื้นเข้มโปร่ง เพื่อให้ไอคอน+ตัวอักษรตัดกับพื้นหลังชัดทุกสภาพแสง */
      background-color: rgba(15, 30, 52, 0.62);
      background-image: linear-gradient(180deg,
        rgba(255, 255, 255, 0.16) 0%,
        rgba(255, 255, 255, 0.03) 52%,
        rgba(255, 255, 255, 0.07) 100%);
      -webkit-backdrop-filter: blur(24px) saturate(1.8);
      backdrop-filter: blur(24px) saturate(1.8);
      box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.35) inset,
        0 0 0 1px rgba(11, 28, 51, 0.35),
        0 10px 30px rgba(6, 15, 28, 0.5);
    }
    /* เว้นท้ายหน้าให้แถบเมนูล่างไม่บังเนื้อหา */
    body { padding-bottom: calc(78px + env(safe-area-inset-bottom, 0px)); }
    /* Reels FAB ถูกแทนด้วยปุ่ม Reels ใน footnavbar แล้ว — ซ่อนบน iPhone/mobile */
    .reels-floating-fab { display: none !important; }
  }
  .gf-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 2px;
    border-radius: 16px;
    color: #F3F6FB !important;
    -webkit-text-fill-color: #F3F6FB !important;
    font-family: 'Prompt', -apple-system, sans-serif;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none !important;
    text-shadow: 0 1px 3px rgba(6, 15, 28, 0.6);
    -webkit-tap-highlight-color: transparent;
    transition: background-color .18s ease, transform .12s cubic-bezier(.32,.72,0,1);
  }
  .gf-item svg {
    width: 23px;
    height: 23px;
    color: #F8D888;
    filter: drop-shadow(0 1px 2px rgba(6, 15, 28, 0.55));
  }
  .gf-item:active { transform: scale(.94); background: rgba(255,255,255,.14); }
  .gf-item.gf-active {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.26), rgba(255, 255, 255, 0.10));
    box-shadow:
      0 1px 0 rgba(255,255,255,.4) inset,
      0 0 0 1px rgba(248, 216, 136, 0.4);
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
  }
  .gf-item.gf-active svg { color: #FFE7A6; }
  .gf-item.gf-post {
    background: linear-gradient(135deg, #F3DFA9 0%, #DDBE74 55%, #C5A059 100%);
    border: 0.5px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 3px 10px rgba(197, 160, 89, 0.55), 0 1px 0 rgba(255,255,255,.45) inset;
  }
  .gf-item.gf-post svg { color: #0B1C33; filter: none; }
  .gf-item.gf-post span { color: #14273F !important; -webkit-text-fill-color: #14273F !important; text-shadow: none; }
  @media (min-width: 1200px) {
    .glass-footnav { display: none !important; }
  }
  /* เลื่อน to-top ขึ้นเหนือ footnavbar */
  @media (max-width: 1199px) {
    .to-top { bottom: 96px !important; }
  }
</style>
        <?php
    }
}

if (!function_exists('render_glass_footnav')) {
    /**
     * Footnavbar markup + assets (ครั้งเดียวต่อหน้า)
     * $active: 'home' | 'buy' | 'rent' | 'reels' | 'post' | 'dashboard'
     */
    function render_glass_footnav(string $active = ''): void
    {
        // ใช้แถบเมนูล่าง iOS ตัวใหม่ (includes/ios_bottom_nav.php) แทนทุกหน้า
        // เพื่อให้หน้าตาและพฤติกรรมเหมือนกันทั้งเว็บ และไม่แสดงซ้อน 2 แถบ
        require_once __DIR__ . '/ios_bottom_nav.php';
        render_ios_bottom_nav($active);
        return;

        render_glass_footnav_assets();
        $self = basename($_SERVER['PHP_SELF']);
        if ($active === '' && $self === 'index.php') $active = 'home';
        if ($active === '' && in_array($self, ['dashboard.php', 'member.php'], true)) $active = 'dashboard';
        $cls = static fn(string $key) => 'gf-item' . ($active === $key ? ' gf-active' : '');
        $loggedIn = !empty($_SESSION['user_id']);
        $homeTarget = $loggedIn ? 'dashboard.php' : 'index.php';
        $homeKey = $loggedIn ? 'dashboard' : 'home';
        ?>
<nav class="glass-footnav" aria-label="<?= e(t('เมนูด่วนด้านล่าง', 'Quick bottom menu')) ?>">
  <a href="<?= e($homeTarget) ?>" class="gf-item<?= $active === $homeKey ? ' gf-active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path></svg>
    <span><?= e($loggedIn ? t('แดชบอร์ด', 'Dashboard') : t('หน้าแรก', 'Home')) ?></span>
  </a>
  <a href="search.php?type=sale" class="<?= $cls('buy') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9.5 21v-6h5v6"></path></svg>
    <span><?= e(t('ซื้อ', 'Buy')) ?></span>
  </a>
  <a href="search.php?type=rent" class="<?= $cls('rent') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="10" rx="2"></rect><path d="M7 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"></path><path d="M7 13h2m3 0h2m3 0h.5"></path></svg>
    <span><?= e(t('เช่า', 'Rent')) ?></span>
  </a>
  <a href="reels.php" class="<?= $cls('reels') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
    <span><?= e(t('Reels', 'Reels')) ?></span>
  </a>
  <a href="submit.php" class="<?= $cls('post') ?> gf-post">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
    <span><?= e(t('ลงประกาศ', 'Post')) ?></span>
  </a>
</nav>
        <?php
    }
}
