<?php
/**
 * iOS-Style Bottom Navigation Bar (iPhone / iPad, ≤1199px)
 *
 * - แสดงอัตโนมัติทุกหน้าที่เรียก render_site_footer() (ผูกไว้ใน includes/site_shell.php)
 * - หน้าที่ไม่มี site footer (เช่น reels.php) เรียก render_ios_bottom_nav() เองก่อน </body>
 * - เรียกซ้ำได้ปลอดภัย (static guard) — แสดงครั้งเดียวต่อหน้า
 *
 * UX:
 *   - แตะแล้วไฮไลต์ย้ายไปเมนูที่เลือกทันที (ไม่ต้องรอหน้าใหม่โหลด)
 *   - เลื่อนลงแล้วแถบซ่อนตัว เลื่อนขึ้นแล้วกลับมา → ไม่บังเนื้อหา
 * Performance:
 *   - CSS/JS inline ขนาดเล็ก (~4 KB) ไม่มี request เพิ่ม ไม่ block render
 *   - prefetch หน้าปลายทางตอนเริ่มแตะ (pointerdown) → เปลี่ยนหน้าเร็วขึ้น 100-300ms
 *   - scroll listener แบบ passive + requestAnimationFrame
 *
 * $active: 'home' | 'buy' | 'rent' | 'reels' | 'post' (ว่าง = ตรวจจากหน้าปัจจุบันอัตโนมัติ)
 */

if (!function_exists('ios_bottom_nav_detect_active')) {
    function ios_bottom_nav_detect_active(): string
    {
        $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'index.php'));
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';

        if (in_array($page, ['index.php', 'dashboard.php', 'member.php'], true)) return 'home';
        if ($page === 'reels.php') return 'reels';
        if (in_array($page, ['submit.php', 'submit-reel.php', 'submit-project.php', 'edit-listing.php'], true)) return 'post';
        if ($page === 'search.php' || $page === 'category.php') {
            if ($type === 'rent') return 'rent';
            if ($type === 'sale') return 'buy';
            // หน้าหมวด SEO ที่ชื่อมีคำว่า "เช่า" ให้ไฮไลต์เมนูเช่า
            $slug = is_string($_GET['page'] ?? null) ? $_GET['page'] : '';
            if ($slug !== '' && mb_strpos($slug, 'เช่า') !== false) return 'rent';
            return 'buy';
        }
        return '';
    }
}

if (!function_exists('render_ios_bottom_nav')) {
    function render_ios_bottom_nav(string $active = ''): void
    {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;

        if ($active === 'dashboard') $active = 'home';
        if ($active === '') $active = ios_bottom_nav_detect_active();

        // หน้าที่อยู่ในโฟลเดอร์ย่อย (admin/staff/api) ต้องเติม ../ ให้ลิงก์
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        $prefix = preg_match('#/(admin|staff|api)$#', $scriptDir) ? '../' : '';

        // reels.php เป็นหน้าเต็มจอ — แถบลอยทับวิดีโอ ไม่เว้น padding และไม่ซ่อนตอนเลื่อน
        $isOverlayPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'reels.php';

        $loggedIn  = !empty($_SESSION['user_id']);
        $homeUrl   = $loggedIn ? 'dashboard.php' : 'index.php';
        $homeLabel = $loggedIn ? t('แดชบอร์ด', 'Dashboard') : t('หน้าแรก', 'Home');

        $items = [
            'home'  => [$homeUrl, $homeLabel, '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'],
            'buy'   => ['search.php?type=sale', t('ซื้อ', 'Buy'), '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>'],
            'rent'  => ['search.php?type=rent', t('เช่า', 'Rent'), '<rect x="3" y="8" width="18" height="10" rx="2"/><path d="M7 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><path d="M7 13h2m3 0h2m3 0h.5"/>'],
            'reels' => ['reels.php', 'Reels', '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>'],
            'post'  => ['submit.php', t('ลงประกาศ', 'Post'), '<path d="M12 5v14M5 12h14"/>'],
        ];
        ?>
<style>
.ios-bottom-nav{display:none}
@media (max-width:1199px){
  body.has-ios-nav{padding-bottom:calc(76px + env(safe-area-inset-bottom,0px))}
  body.has-ios-nav.ios-nav-overlay{padding-bottom:0}
  .glass-footnav,.reels-floating-fab{display:none!important}
  .to-top{bottom:calc(88px + env(safe-area-inset-bottom,0px))!important}

  .ios-bottom-nav{
    position:fixed;left:10px;right:10px;bottom:calc(8px + env(safe-area-inset-bottom,0px));
    z-index:1100;display:grid;grid-template-columns:repeat(5,1fr);gap:4px;
    max-width:560px;margin:0 auto;padding:6px;border-radius:24px;
    /* เข้มขึ้น: พื้น navy ทึบกว่าเดิม เพื่อให้ไอคอน/ตัวอักษรตัดกับพื้นหลังชัด */
    background:rgba(9,20,36,.92);
    -webkit-backdrop-filter:blur(18px) saturate(1.6);backdrop-filter:blur(18px) saturate(1.6);
    border:.5px solid rgba(255,255,255,.22);
    box-shadow:0 1px 0 rgba(255,255,255,.14) inset,0 10px 30px rgba(0,0,0,.38);
    transform:translateY(0);transition:transform .28s cubic-bezier(.32,.72,0,1);
    contain:layout paint;
  }
  .ios-bottom-nav.is-hidden{transform:translateY(calc(100% + 16px + env(safe-area-inset-bottom,0px)))}
  /* ซ่อนแถบเมื่อเปิด drawer/คีย์บอร์ด เพื่อไม่ให้ซ้อนกัน */
  body.site-shell-drawer-open .ios-bottom-nav{transform:translateY(calc(100% + 16px + env(safe-area-inset-bottom,0px)))}

  .ios-nav-item{
    position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:2px;min-height:48px;padding:5px 2px;border-radius:18px;
    color:#E9EFF7!important;-webkit-text-fill-color:#E9EFF7!important;text-decoration:none!important;
    -webkit-tap-highlight-color:transparent;touch-action:manipulation;user-select:none;
    transition:background-color .18s ease,box-shadow .18s ease,transform .12s ease;
  }

  .ios-nav-item svg{width:22px;height:22px;color:#F4F7FC;transition:color .18s ease,transform .18s ease}
  .ios-nav-item span{
    font:700 10.5px/1.15 'Prompt',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    letter-spacing:-.01em;white-space:nowrap;text-shadow:0 1px 3px rgba(0,0,0,.7);
  }
  /* ปุ่มที่ยังไม่ active: พื้นจาง + ขอบบาง ช่วยให้แต่ละช่องแยกจากกันชัด */
  .ios-nav-item:not(.active):not(.post){
    background:rgba(255,255,255,.06);
    box-shadow:0 0 0 .5px rgba(255,255,255,.10) inset;
  }
  .ios-nav-item:not(.active):not(.post) svg{color:#EAF0F8}
  .ios-nav-item.active{
    background:linear-gradient(180deg,rgba(255,255,255,.24),rgba(255,255,255,.09));
    box-shadow:0 1px 0 rgba(255,255,255,.35) inset,0 0 0 1px rgba(248,216,136,.38);
    color:#fff!important;-webkit-text-fill-color:#fff!important;
  }
  .ios-nav-item.active svg{color:#FFE7A6;transform:scale(1.08)}
  .ios-nav-item:active{transform:scale(.94)}

  .ios-nav-item.post{
    background:linear-gradient(135deg,#EBCE88 0%,#D4B76A 50%,#C5A059 100%);
    border:.5px solid rgba(255,255,255,.5);
    box-shadow:0 1px 0 rgba(255,255,255,.4) inset,0 3px 10px rgba(197,160,89,.45);
    color:#14273F!important;-webkit-text-fill-color:#14273F!important;
  }
  .ios-nav-item.post svg{color:#0B1C33}
  .ios-nav-item.post span{text-shadow:none}

  .ios-nav-item.post.active{box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 0 0 2px rgba(255,231,166,.9),0 3px 12px rgba(197,160,89,.55)}

  /* reels.php: ยกปุ่มด้านขวาและข้อความขึ้นพ้นแถบเมนู */
  body.ios-nav-overlay .reel-actions-col{bottom:calc(150px + env(safe-area-inset-bottom,0px))}
  body.ios-nav-overlay .reel-bottom-card{padding-bottom:calc(84px + env(safe-area-inset-bottom,0px))!important}
}
@media (max-width:360px){
  .ios-nav-item span{font-size:9.5px}
  .ios-nav-item svg{width:20px;height:20px}
}
@media (prefers-reduced-motion:reduce){
  .ios-bottom-nav,.ios-nav-item,.ios-nav-item svg{transition:none!important}
}
</style>
<nav class="ios-bottom-nav" id="iosBottomNav" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
<?php foreach ($items as $key => [$href, $label, $icon]):
    $isActive = $active === $key; ?>
  <a href="<?= e($prefix . $href) ?>" data-key="<?= e($key) ?>" class="ios-nav-item<?= $key === 'post' ? ' post' : '' ?><?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="<?= $key === 'post' ? '2.4' : '2' ?>" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
    <span><?= e($label) ?></span>
  </a>
<?php endforeach; ?>
</nav>
<script>
(function(){
  var nav=document.getElementById('iosBottomNav');
  if(!nav)return;
  var body=document.body;
  body.classList.add('has-ios-nav');
  var overlay=<?= $isOverlayPage ? 'true' : 'false' ?>;
  if(overlay)body.classList.add('ios-nav-overlay');
  var items=nav.querySelectorAll('.ios-nav-item');
  var initial=nav.querySelector('.ios-nav-item.active');

  function select(el){
    for(var i=0;i<items.length;i++){
      items[i].classList.toggle('active',items[i]===el);
      if(items[i]===el)items[i].setAttribute('aria-current','page');else items[i].removeAttribute('aria-current');
    }
  }
  /* prefetch ตอนเริ่มแตะ: เริ่มโหลดหน้าก่อนนิ้วยก ~100-300ms */
  var done={};
  function prefetch(a){
    var url=a.href;
    if(done[url]||a.classList.contains('active'))return;
    done[url]=1;
    if(document.querySelector('link[rel="prefetch"][href="'+url+'"]'))return;
    var l=document.createElement('link');l.rel='prefetch';l.href=url;l.as='document';
    document.head.appendChild(l);
  }
  nav.addEventListener('pointerdown',function(e){
    var a=e.target.closest('.ios-nav-item');if(a)prefetch(a);
  },{passive:true});
  /* ไฮไลต์ย้ายไปเมนูที่แตะทันที */
  nav.addEventListener('click',function(e){
    var a=e.target.closest('.ios-nav-item');if(!a)return;
    if(e.metaKey||e.ctrlKey||e.shiftKey||e.button===1)return;
    select(a);
  });
  /* กด Back แล้วกลับมาจาก bfcache → คืนไฮไลต์ของหน้านี้ */
  addEventListener('pageshow',function(e){
    if(e.persisted){select(initial);nav.classList.remove('is-hidden');}
  });

  if(overlay)return;
  /* เลื่อนลง = ซ่อน, เลื่อนขึ้น/ใกล้บนสุด/ถึงท้ายหน้า = แสดง */
  var lastY=scrollY,ticking=false;
  function update(){
    ticking=false;
    var y=scrollY,dy=y-lastY;
    var atBottom=innerHeight+y>=document.documentElement.scrollHeight-40;
    if(y<80||atBottom){nav.classList.remove('is-hidden');}
    else if(Math.abs(dy)>10){nav.classList.toggle('is-hidden',dy>0);}
    else return;
    lastY=y;
  }
  addEventListener('scroll',function(){if(!ticking){ticking=true;requestAnimationFrame(update);}},{passive:true});
  /* โฟกัสช่องกรอก (คีย์บอร์ดขึ้น) → ซ่อนแถบไม่ให้ลอยทับฟอร์ม */
  document.addEventListener('focusin',function(e){
    if(e.target.matches&&e.target.matches('input,textarea,select,[contenteditable]'))nav.classList.add('is-hidden');
  });
  document.addEventListener('focusout',function(){nav.classList.remove('is-hidden');});
})();
</script>
        <?php
    }
}
