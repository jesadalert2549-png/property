<?php
// เมนูย่อย "ซื้อ/เช่า" แบบ dropdown พร้อมหน้า SEO (ติดรถไฟฟ้า / ราคาไม่เกิน / ย่านยอดนิยม)
if (!function_exists('t')) {
    require_once __DIR__ . '/i18n.php';
}
function render_nav_dropdowns(bool $accentHeading = false): void
{
    $u = static fn(string $slug) => 'category.php?page=' . rawurlencode($slug);
    $headingStyle = $accentHeading ? ' style="color:#7955d9"' : '';
    $m = menu_bi();
    ?>
    <div class="nav-dropdown">
      <a href="search.php?type=sale" class="nav-dropdown-toggle"><?= e($m['buy']) ?><span class="nav-caret" aria-hidden="true"></span></a>
      <div class="nav-dropdown-panel" role="menu">
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['by_type']) ?></p>
          <?php foreach (['condo' => ['condo','คอนโด'], 'house' => ['house','บ้านเดี่ยว'], 'townhouse' => ['townhouse','ทาวน์เฮ้าส์'], 'land' => ['land','ที่ดิน'], 'commercial' => ['commercial','อาคารพาณิชย์']] as $catKey => [$catEn, $catTh]): ?>
            <a href="search.php?type=sale&category=<?= e($catKey) ?>"><?= e(is_en() ? $catEn : $catTh) ?></a>
          <?php endforeach; ?>
          <a href="search.php?type=sale"><?= e($m['all_sale']) ?></a>
        </div>
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['by_bts']) ?></p>
          <a href="<?= e($u(t('คอนโดติดบีทีเอส', 'คอนโดติดบีทีเอส'))) ?>"><?= e(t('คอนโดติด BTS', 'Condos near BTS')) ?></a>
          <a href="<?= e($u(t('คอนโดติดเอ็มอาร์ที', 'คอนโดติดเอ็มอาร์ที'))) ?>"><?= e(t('คอนโดติด MRT', 'Condos near MRT')) ?></a>
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['by_budget']) ?></p>
          <a href="<?= e($u(t('คอนโดราคาไม่เกินหนึ่งล้าน', 'คอนโดราคาไม่เกินหนึ่งล้าน'))) ?>"><?= e(t('คอนโดไม่เกิน 1 ล้าน', 'Condos under 1M THB')) ?></a>
          <a href="<?= e($u(t('คอนโดราคาไม่เกินสามล้าน', 'คอนโดราคาไม่เกินสามล้าน'))) ?>"><?= e(t('คอนโดไม่เกิน 3 ล้าน', 'Condos under 3M THB')) ?></a>
          <a href="<?= e($u(t('บ้านราคาไม่เกินสองล้าน', 'บ้านราคาไม่เกินสองล้าน'))) ?>"><?= e(t('บ้านไม่เกิน 2 ล้าน', 'Houses under 2M THB')) ?></a>
          <a href="<?= e($u(t('ที่ดินราคาถูก', 'ที่ดินราคาถูก'))) ?>"><?= e(t('ที่ดินราคาถูก', 'Affordable land')) ?></a>
        </div>
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['hot_area']) ?></p>
          <a href="<?= e($u(t('คอนโดย่านสุขุมวิท', 'คอนโดย่านสุขุมวิท'))) ?>"><?= e(t('ย่านสุขุมวิท', 'Sukhumvit area')) ?></a>
          <a href="<?= e($u(t('คอนโดย่านลาดพร้าว', 'คอนโดย่านลาดพร้าว'))) ?>"><?= e(t('ย่านลาดพร้าว', 'Lat Phrao area')) ?></a>
          <a href="<?= e($u(t('บ้านย่านรามอินทรา', 'บ้านย่านรามอินทรา'))) ?>"><?= e(t('ย่านรามอินทรา', 'Ram Inthra area')) ?></a>
          <a href="search.php?type=sale&q=<?= rawurlencode(t('กรุงเทพ', 'กรุงเทพ')) ?>"><?= e($m['bkk_all']) ?></a>
        </div>
      </div>
    </div>
    <div class="nav-dropdown">
      <a href="search.php?type=rent" class="nav-dropdown-toggle"><?= e($m['rent']) ?><span class="nav-caret" aria-hidden="true"></span></a>
      <div class="nav-dropdown-panel" role="menu">
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['by_type']) ?></p>
          <?php foreach (['condo' => ['condo','คอนโด'], 'house' => ['house','บ้านเดี่ยว'], 'townhouse' => ['townhouse','ทาวน์เฮ้าส์'], 'commercial' => ['commercial','อาคารพาณิชย์']] as $catKey => [$catEn, $catTh]): ?>
            <a href="search.php?type=rent&category=<?= e($catKey) ?>"><?= e(is_en() ? $catEn : $catTh) ?></a>
          <?php endforeach; ?>
          <a href="search.php?type=rent"><?= e($m['all_rent']) ?></a>
        </div>
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e(t('ราคาถูก', 'Budget-friendly')) ?></p>
          <a href="<?= e($u(t('คอนโดให้เช่าราคาถูก', 'คอนโดให้เช่าราคาถูก'))) ?>"><?= e($m['cheap_rent_condo']) ?></a>
          <a href="<?= e($u(t('บ้านให้เช่าราคาถูก', 'บ้านให้เช่าราคาถูก'))) ?>"><?= e($m['cheap_rent_house']) ?></a>
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['by_bts']) ?></p>
          <a href="<?= e($u(t('คอนโดให้เช่าติดบีทีเอส', 'คอนโดให้เช่าติดบีทีเอส'))) ?>"><?= e($m['rent_bts']) ?></a>
        </div>
        <div class="nav-dropdown-col">
          <p class="nav-dropdown-heading"<?= $headingStyle ?>><?= e($m['hot_area']) ?></p>
          <a href="search.php?type=rent&q=<?= rawurlencode('สุขุมวิท') ?>"><?= e(t('สุขุมวิท', 'Sukhumvit')) ?></a>
          <a href="search.php?type=rent&q=<?= rawurlencode('ลาดพร้าว') ?>"><?= e(t('ลาดพร้าว', 'Lat Phrao')) ?></a>
          <a href="search.php?type=rent&q=<?= rawurlencode('รัชดา') ?>"><?= e(t('รัชดาภิเษก', 'Ratchada')) ?></a>
          <a href="search.php?type=rent&q=<?= rawurlencode('เชียงใหม่') ?>"><?= e(t('เชียงใหม่', 'Chiang Mai')) ?></a>
        </div>
      </div>
    </div>
    <?php
}

// ปุ่มกลับขึ้นด้านบน (แสดงเมื่อเลื่อนลง)
function render_to_top(): void
{
    $label = t('กลับขึ้นด้านบน', 'Back to top');
    echo '<button type="button" class="to-top" id="toTopBtn" aria-label="' . e($label) . '">↑</button>' . "\n";
}

// CTA ท้ายหน้า — เชื่อมลิงกระหว่างหน้า
function render_page_cta(array $cfg = []): void
{
    $title   = $cfg['title']   ?? t('มีอสังหาริมทรัพย์จะขายหรือให้เช่ากับเราฟรี', 'Have a property to sell or rent? List it with us for free');
    $text    = $cfg['text']    ?? t('ลงประกาศฟรี เข้าถึงผู้ซื้อและผู้เช่าทั่วไทย พร้อมเครื่องมือคำนวณเงินผ่อนและแผนที่ให้ครบ', 'Post a free listing and reach buyers and renters across Thailand, with mortgage calculator and maps included.');
    $primary = $cfg['primary'] ?? ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')];
    $ghost   = $cfg['ghost']   ?? ['search.php', t('เริ่มค้นหาทรัพย์', 'Start searching')];
    ?>
    <section class="page-cta">
      <div class="page-cta-box">
        <div>
          <h3><?= e($title) ?></h3>
          <p><?= e($text) ?></p>
        </div>
        <div class="page-cta-actions">
          <a class="page-cta-btn" href="<?= e($primary[0]) ?>"><?= e($primary[1]) ?> →</a>
          <a class="page-cta-btn ghost" href="<?= e($ghost[0]) ?>"><?= e($ghost[1]) ?></a>
        </div>
      </div>
    </section>
    <?php
}

// สคริปต์ UX กลาง: แถบความคืบหน้า + ปุ่มกลับขึ้นด้านบน
// + เติม skip-link / landmark ให้หน้าที่ยังไม่มี (accessibility)
function render_ux_scripts(): void
{
    ?>
    <script>
      (() => {
        /* ---- Accessibility bootstrap ----
           ทำให้ทุกหน้าได้ประโยชน์โดยไม่ต้องแก้ markup ทีละไฟล์:
           1) เพิ่ม skip link ถ้ายังไม่มี
           2) ให้ <main> มี id + tabindex=-1 เพื่อรับโฟกัสจากการกระโดด
           3) เติม role="main" ให้ main ที่ยังไม่มี landmark */
        (() => {
          /* หน้าแรก verwendet <section class="dd-hero"> เป็นเนื้อหาหลัก ไม่มี <main>
             → ตรวจ main ก่อน ถ้าไม่มีให้ใช้ element เนื้อหาหลักที่รู้จัก แล้วค่อย fallback body */
          const main = document.querySelector('main')
            || document.querySelector('.dd-hero')
            || document.querySelector('.project-main')
            || document.querySelector('.search-main, #searchResults')
            || document.body;
          if (main && !main.id) main.id = 'main-content';
          if (main && main !== document.body) {
            if (!main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');
            if (!main.getAttribute('role')) main.setAttribute('role', 'main');
          }
          if (document.querySelector('.skip-to-content')) return;
          const target = main && main.id ? '#' + main.id : '#main-content';
          const link = document.createElement('a');
          link.className = 'skip-to-content';
          link.href = target;
          link.textContent = '<?= e(t('ข้ามไปยังเนื้อหาหลัก', 'Skip to main content')) ?>';
          link.addEventListener('click', (e) => {
            const el = document.querySelector(target);
            if (!el) return;
            e.preventDefault();
            el.focus({ preventScroll: true });
            el.scrollIntoView({ block: 'start', behavior: 'auto' });
          });
          document.body.insertBefore(link, document.body.firstChild);
        })();

        /* ---- Theme toggle ----
           ลำดับความสำคัญ: ค่าที่ผู้ใช้เลือก (localStorage) → ค่าจากระบบ
           ใส่ <html data-theme> ตั้งแต่ก่อน CSS ทํางานจึงไม่กระพริบ        */
        (() => {
          const KEY = 'pg-theme';
          const root = document.documentElement;
          const sysDark = () => window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

          try {
            const saved = localStorage.getItem(KEY);
            if (saved === 'dark' || saved === 'light') root.setAttribute('data-theme', saved);
            else root.setAttribute('data-theme', sysDark() ? 'dark' : 'light');
          } catch (e) {
            root.setAttribute('data-theme', sysDark() ? 'dark' : 'light');
          }

          // ถ้าผู้ใช้ยังไม่เลือกเอง ให้ตามระบบเมื่อระบบเปลี่ยน
          if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
              try { if (localStorage.getItem(KEY)) return; } catch (e) {}
              root.setAttribute('data-theme', sysDark() ? 'dark' : 'light');
              syncBtn();
            });
          }

          const SUN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"></path></svg>';
          const MOON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"></path></svg>';

          let btn = null;
          function syncBtn() {
            if (!btn) return;
            const dark = root.getAttribute('data-theme') === 'dark';
            btn.innerHTML = (dark ? SUN : MOON) + '<span>' + (dark ? '<?= e(t('สว่าง', 'Light')) ?>' : '<?= e(t('มืด', 'Dark')) ?>') + '</span>';
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.title = dark ? '<?= e(t('สลับเป็นโหมดสว่าง', 'Switch to light mode')) ?>' : '<?= e(t('สลับเป็นโหมดมืด', 'Switch to dark mode')) ?>';
          }

          function mount() {
            if (document.querySelector('.theme-toggle')) return;
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'theme-toggle';
            btn.setAttribute('aria-label', '<?= e(t('สลับโหมดมืด/สว่าง', 'Toggle dark mode')) ?>');
            // วางใน navbar ถ้ามี ไม่งั้นลอยมุมขวาล่าง
            const slot =
              document.querySelector('.dd-nav-right') ||
              document.querySelector('.site-shell-actions') ||
              document.querySelector('.site-nav');
            if (slot) slot.insertBefore(btn, slot.firstChild);
            else document.body.appendChild(btn);
            syncBtn();
            btn.addEventListener('click', () => {
              const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
              root.classList.add('theme-switching');
              root.setAttribute('data-theme', next);
              try { localStorage.setItem(KEY, next); } catch (e) {}
              syncBtn();
              requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('theme-switching')));
            });
          }

          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
          else mount();
        })();

        const bar = document.createElement('div');
        bar.className = 'scroll-progress';
        document.body.prepend(bar);
        const top = document.getElementById('toTopBtn');
        const onScroll = () => {
          const max = document.documentElement.scrollHeight - innerHeight;
          bar.style.width = (max > 0 ? (scrollY / max) * 100 : 0) + '%';
          if (top) top.classList.toggle('show', scrollY > 480);
        };
        addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        top?.addEventListener('click', () => scrollTo({ top: 0, behavior: 'smooth' }));
      })();
    </script>
    <?php
}

function render_site_header(): void
{
    $adminLoggedIn = !empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin');
    $memberLoggedIn = !empty($_SESSION['user_id']);
    $dashboardUrl = $adminLoggedIn ? 'admin/index.php' : 'dashboard.php';
    ?>
    <header class="site-shell-nav">
      <div class="site-shell-left">
        <?= render_logo_html('index.php') ?>
        <nav class="site-shell-menu" aria-label="<?= e(t('เมนูหลัก', 'Main menu')) ?>">
          <?php render_nav_dropdowns(); ?>
          <a href="project.php"><?= e(t('โครงการใหม่', 'New Projects')) ?></a>
          <a href="advertise.php"><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></a>
          <a href="submit.php"><?= e(t('ลงประกาศกับเรา', 'List with us')) ?></a>
        </nav>
      </div>
      <div class="site-shell-actions">
        <a href="submit.php" class="site-shell-outline"><?= e(t('ลงประกาศ', 'Post a listing')) ?></a>
        <?php if ($adminLoggedIn): ?>
          <a href="admin/analytics.php" class="site-shell-outline"><?= e(t('สถิติผู้เข้าชม', 'Visitor stats')) ?></a>
        <?php endif; ?>
        <?php if ($memberLoggedIn): ?>
          <a href="<?= e($dashboardUrl) ?>" class="site-shell-primary" style="background:#7955d9"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
          <a href="logout.php" class="site-shell-outline"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
        <?php else: ?>
          <a href="login.php" class="site-shell-primary"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
        <?php endif; ?>
        <?php render_lang_toggle(); ?>
      </div>
      <button type="button" class="site-shell-menu-button" aria-label="<?= e(t('เปิดเมนู', 'Open menu')) ?>" aria-expanded="false">☰</button>
    </header>
    <aside class="site-shell-drawer" aria-hidden="true">
      <div class="site-shell-drawer-head">
        <?= render_logo_html('index.php') ?>
        <button type="button" class="site-shell-close" aria-label="<?= e(t('ปิดเมนู', 'Close menu')) ?>">✕</button>
      </div>
      <nav>
        <a href="search.php?type=sale"><span><?= e(t('ซื้อ', 'Buy')) ?></span><b>›</b></a>
        <a href="search.php?type=rent"><span><?= e(t('เช่า', 'Rent')) ?></span><b>›</b></a>
        <a href="category.php?page=<?= e(rawurlencode('คอนโดติดบีทีเอส')) ?>"><span><?= e(t('คอนโดติด BTS', 'Condos near BTS')) ?></span><b>›</b></a>
        <a href="category.php?page=<?= e(rawurlencode('คอนโดติดเอ็มอาร์ที')) ?>"><span><?= e(t('คอนโดติด MRT', 'Condos near MRT')) ?></span><b>›</b></a>
        <a href="category.php?page=<?= e(rawurlencode('คอนโดให้เช่าราคาถูก')) ?>"><span><?= e(t('คอนโดเช่าราคาถูก', 'Budget condo rentals')) ?></span><b>›</b></a>
        <a href="search.php?type=sale&category=commercial"><span><?= e(t('พาณิชย์', 'Commercial')) ?></span><b>›</b></a>
        <a href="category.php?page=house"><span><?= e(t('บ้านและโครงการ', 'Houses & projects')) ?></span><b>›</b></a>
        <a href="project.php"><span><?= e(t('โครงการใหม่', 'New Projects')) ?></span><b>›</b></a>
        <a href="advertise.php"><span><?= e(t('ติดต่อโฆษณา', 'Advertise')) ?></span><b>›</b></a>
        <a href="submit.php"><span><?= e(t('ลงประกาศ', 'Post a listing')) ?></span><b>›</b></a>
      </nav>
      <div class="site-shell-drawer-footer">
        <?php if ($memberLoggedIn): ?>
          <a href="<?= e($dashboardUrl) ?>" class="site-shell-primary" style="background:#7955d9"><?= e(t('แดชบอร์ด', 'Dashboard')) ?></a>
          <a href="logout.php" class="site-shell-outline"><?= e(t('ออกจากระบบ', 'Log out')) ?></a>
        <?php else: ?>
          <a href="login.php" class="site-shell-primary"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a>
        <?php endif; ?>
        <a href="submit.php"><?= e(t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')) ?></a>
        <?php render_lang_toggle(); ?>
      </div>
    </aside>
    <div class="site-shell-overlay"></div>
    <?php
}

function render_site_footer(): void
{
    $groups = [
        t('อสังหาฯ ประกาศขาย', 'Listings for sale') => [
            ['category.php?page=' . rawurlencode('ขายคอนโด'), t('ขายคอนโดมือสอง', 'Second-hand condos for sale')],
            ['category.php?page=' . rawurlencode('ขายบ้าน'), t('ขายบ้านเดี่ยวและบ้านมือสอง', 'Houses & second-hand homes for sale')],
            ['category.php?page=' . rawurlencode('ขายทาวน์เฮ้าส์'), t('ขายทาวน์เฮ้าส์และทาวน์โฮม', 'Townhouses for sale')],
            ['category.php?page=' . rawurlencode('ขายที่ดิน'), t('ขายที่ดิน', 'Land for sale')],
            ['search.php?type=sale&category=commercial', t('ขายอาคารพาณิชย์และโกดัง', 'Commercial buildings & warehouses for sale')],
            ['search.php?type=sale', t('ดูประกาศขายทั้งหมด', 'View all listings for sale')],
        ],
        t('อสังหาฯ สำหรับเช่า', 'Listings for rent') => [
            ['category.php?page=' . rawurlencode('คอนโดให้เช่า'), t('คอนโดให้เช่า', 'Condos for rent')],
            ['category.php?page=' . rawurlencode('บ้านให้เช่า'), t('บ้านให้เช่า', 'Houses for rent')],
            ['search.php?type=rent&category=townhouse', t('ทาวน์โฮมให้เช่า', 'Townhouses for rent')],
            ['search.php?type=rent&category=land', t('ที่ดินให้เช่า', 'Land for rent')],
            ['search.php?type=rent&category=commercial', t('อาคารพาณิชย์ให้เช่า', 'Commercial buildings for rent')],
            ['search.php?type=rent', t('ดูประกาศเช่าทั้งหมด', 'View all rental listings')],
        ],
        t('โครงการและข้อมูล', 'Projects & info') => [
            ['project.php', t('โครงการบ้านใหม่ Essentia Rama 2', 'New project Essentia Rama 2')],
            ['category.php?page=house', t('บ้านเดี่ยวและโครงการบ้าน', 'Houses & housing projects')],
            ['search.php?category=condo', t('ค้นหาประกาศคอนโด', 'Search condo listings')],
            ['search.php?category=land', t('ค้นหาประกาศที่ดิน', 'Search land listings')],
        ],
        t('ประกาศขายยอดนิยม', 'Popular sale listings') => [
            ['category.php?page=' . rawurlencode('ขายคอนโด'), t('ประกาศขายคอนโด', 'Condos for sale')],
            ['category.php?page=' . rawurlencode('ขายบ้าน'), t('ประกาศขายบ้าน', 'Houses for sale')],
            ['category.php?page=' . rawurlencode('ขายทาวน์เฮ้าส์'), t('ประกาศขายทาวน์โฮม', 'Townhouses for sale')],
            ['category.php?page=' . rawurlencode('ขายที่ดิน'), t('ประกาศขายที่ดิน', 'Land for sale')],
        ],
        t('ประกาศให้เช่ายอดนิยม', 'Popular rental listings') => [
            ['category.php?page=' . rawurlencode('คอนโดให้เช่า'), t('เช่าคอนโด', 'Rent a condo')],
            ['category.php?page=' . rawurlencode('บ้านให้เช่า'), t('เช่าบ้าน', 'Rent a house')],
            ['search.php?type=rent&category=townhouse', t('เช่าทาวน์โฮม', 'Rent a townhouse')],
            ['search.php?type=rent&category=commercial', t('เช่าอาคารพาณิชย์', 'Rent a commercial building')],
        ],
        t('ทำเลที่น่าสนใจ', 'Interesting locations') => [
            ['search.php?q=' . rawurlencode('กรุงเทพ'), t('กรุงเทพฯ', 'Bangkok')],
            ['search.php?q=' . rawurlencode('นนทบุรี'), t('นนทบุรี', 'Nonthaburi')],
            ['search.php?q=' . rawurlencode('เชียงใหม่'), t('เชียงใหม่', 'Chiang Mai')],
            ['search.php?q=' . rawurlencode('ภูเก็ต'), t('ภูเก็ต', 'Phuket')],
            ['search.php?q=' . rawurlencode('สมุทรปราการ'), t('สมุทรปราการ', 'Samut Prakan')],
        ],
        t('เครื่องมือและบริการ', 'Tools & services') => [
            ['search.php', t('ค้นหาอสังหาริมทรัพย์', 'Search properties')],
            ['submit.php', t('ลงประกาศขายหรือให้เช่า', 'Post a listing for sale or rent')],
            ['register.php', t('สมัครสมาชิกฟรี', 'Register for free')],
            ['login.php', t('เข้าสู่ระบบสมาชิก', 'Member login')],
            ['dashboard.php', t('แดชบอร์ดสมาชิก', 'Member dashboard')],
            ['sitemap.php', t('แผนผังเว็บไซต์', 'Sitemap')],
        ],
    ];
    ?>
    <footer id="footer" class="site-shell-footer seo-footer" itemscope itemtype="https://schema.org/RealEstateAgent">
      <div class="seo-footer-inner">
        <section class="seo-footer-about">
          <h2 itemprop="name"><?= e(SITE_NAME) ?></h2>
          <p itemprop="description"><?= e(t('ค้นหาประกาศซื้อ ขาย และเช่าอสังหาริมทรัพย์ทั่วไทย ทั้งคอนโด บ้านเดี่ยว ทาวน์โฮม ที่ดิน และอาคารพาณิชย์ ดูข้อมูลและติดต่อผู้ลงประกาศได้จากหน้ารายละเอียด', 'Search property listings for sale and rent across Thailand, including condos, houses, townhouses, land and commercial buildings. View details and contact listers directly.')) ?></p>
          <a href="sitemap.php"><?= e(t('แผนผังเว็บไซต์', 'Sitemap')) ?></a>
          <a href="#cookies" class="seo-footer-cookie-link" onclick="if(window.spOpenCookieConsent){window.spOpenCookieConsent();}return false;"><?= e(t('ตั้งค่าคุกกี้', 'Cookie Settings')) ?></a>
          <?php render_lang_toggle(); ?>
        </section>
        <?php foreach ($groups as $title => $links): ?>
          <nav class="seo-footer-group" aria-label="<?= e($title) ?>">
            <h2><?= e($title) ?></h2>
            <?php foreach ($links as [$url, $label]): ?>
              <a href="<?= e($url) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endforeach; ?>
      </div>
      <div class="seo-footer-provinces" style="max-width:1240px; margin:24px auto 0; padding:20px 16px 0; border-top:1px solid rgba(255,255,255,0.08); font-size:12px;">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px;">
          <strong style="color:#C5A059; letter-spacing:0.04em;">🏢 <?= e(t('ทำเลเศรษฐกิจชั้นนำ Prime CBD & รถไฟฟ้า BTS/MRT', 'Prime CBD & Transit-Oriented Hotspots (BTS/MRT)')) ?></strong>
          <span style="color:#94a3b8; font-size:11px;"><?= e(t('ย่านการอยู่อาศัยและการลงทุนอสังหาฯ ผลตอบแทนสูง', 'High-demand residential and high-yield investment zones')) ?></span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:6px 12px; line-height:2; margin-bottom:14px;">
          <?php
          $footerCbdHotspots = [
              'สุขุมวิท'     => t('สุขุมวิท', 'Sukhumvit'),
              'ทองหล่อ'      => t('ทองหล่อ', 'Thong Lo'),
              'เอกมัย'       => t('เอกมัย', 'Ekkamai'),
              'พร้อมพงษ์'    => t('พร้อมพงษ์', 'Phrom Phong'),
              'สาทร'         => t('สาทร', 'Sathorn'),
              'สีลม'         => t('สีลม', 'Silom'),
              'พระราม 9'     => t('พระราม 9 (New CBD)', 'Rama 9 (New CBD)'),
              'รัชดาภิเษก'   => t('รัชดาฯ - ห้วยขวาง', 'Ratchada - Huai Khwang'),
              'อารีย์'        => t('อารีย์ - พญาไท', 'Ari - Phayathai'),
              'ลาดพร้าว'     => t('ห้าแยกลาดพร้าว', 'Ladprao Junction'),
              'บางนา'        => t('บางนา - EEC', 'Bangna - EEC'),
              'เจริญกรุง'    => t('ริมน้ำเจ้าพระยา', 'Riverside (Charoen Krung)'),
              'นิมมาน'       => t('นิมมานฯ (เชียงใหม่)', 'Nimman (Chiang Mai)'),
              'พัทยา'        => t('พัทยา - จอมเทียน', 'Pattaya - Jomtien'),
              'หัวหิน'        => t('หัวหิน - ชะอำ', 'Hua Hin - Cha-am'),
              'บางเทา'       => t('บางเทา - ลากูน่า (ภูเก็ต)', 'Bang Tao - Laguna (Phuket)'),
          ];
          foreach ($footerCbdHotspots as $cbdSlug => $cbdTxt):
          ?>
            <a href="category.php?page=<?= urlencode('คอนโด' . $cbdSlug) ?>" style="color:#f1f5f9; font-weight:700; text-decoration:none; transition:color .15s;" onmouseover="this.style.color='#C5A059'" onmouseout="this.style.color='#f1f5f9'"><?= e($cbdTxt) ?></a>
            <span style="color:rgba(197,160,89,0.3);">|</span>
          <?php endforeach; ?>
        </div>

        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; border-top:1px dashed rgba(255,255,255,0.06); padding-top:12px;">
          <strong style="color:#C5A059; letter-spacing:0.04em;">📍 <?= e(t('อสังหาริมทรัพย์รายจังหวัดยอดนิยมทั่วไทย (77 จังหวัด)', 'Popular Property Locations by Province Across Thailand')) ?></strong>
          <span style="color:#94a3b8; font-size:11px;"><?= e(t('ค้นหา ซื้อ-ขาย-เช่า บ้าน คอนโด ที่ดิน ทั่วทุกภูมิภาค', 'Search Buy-Sell-Rent Houses, Condos, Land nationwide')) ?></span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:6px 10px; line-height:1.9;">
          <?php
          $seoFooterHotspots = [
              'กรุงเทพมหานคร' => t('กรุงเทพฯ', 'Bangkok'),
              'เชียงใหม่'     => t('เชียงใหม่', 'Chiang Mai'),
              'ภูเก็ต'        => t('ภูเก็ต', 'Phuket'),
              'ชลบุรี'        => t('ชลบุรี (พัทยา)', 'Chonburi (Pattaya)'),
              'นนทบุรี'       => t('นนทบุรี', 'Nonthaburi'),
              'สมุทรปราการ'   => t('สมุทรปราการ', 'Samut Prakan'),
              'ปทุมธานี'       => t('ปทุมธานี', 'Pathum Thani'),
              'ระยอง'         => t('ระยอง', 'Rayong'),
              'ประจวบคีรีขันธ์' => t('ประจวบฯ (หัวหิน)', 'Prachuap (Hua Hin)'),
              'นครราชสีมา'     => t('โคราช (เขาใหญ่)', 'Korat (Khao Yai)'),
              'ขอนแก่น'       => t('ขอนแก่น', 'Khon Kaen'),
              'สุราษฎร์ธานี'    => t('สุราษฎร์ฯ (สมุย)', 'Surat Thani (Samui)'),
              'สงขลา'         => t('สงขลา (หาดใหญ่)', 'Songkhla (Hat Yai)'),
              'เชียงราย'       => t('เชียงราย', 'Chiang Rai'),
              'พระนครศรีอยุธยา'=> t('อยุธยา', 'Ayutthaya'),
              'นครปฐม'        => t('นครปฐม', 'Nakhon Pathom'),
              'กระบี่'         => t('กระบี่', 'Krabi'),
              'เพชรบุรี'       => t('เพชรบุรี (ชะอำ)', 'Phetchaburi (Cha-am)'),
              'กาญจนบุรี'      => t('กาญจนบุรี', 'Kanchanaburi'),
              'อุดรธานี'       => t('อุดรธานี', 'Udon Thani'),
              'อุบลราชธานี'    => t('อุบลราชธานี', 'Ubon Ratchathani'),
              'พิษณุโลก'      => t('พิษณุโลก', 'Phitsanulok'),
              'ฉะเชิงเทรา'     => t('ฉะเชิงเทรา', 'Chachoengsao'),
              'สระบุรี'        => t('สระบุรี', 'Saraburi'),
              'ลำปาง'         => t('ลำปาง', 'Lampang'),
              'นครศรีธรรมราช' => t('นครศรีธรรมราช', 'Nakhon Si Thammarat'),
              'พังงา'         => t('พังงา', 'Phang Nga'),
              'ตรัง'          => t('ตรัง', 'Trang'),
              'สมุทรสาคร'     => t('สมุทรสาคร', 'Samut Sakhon'),
              'ลพบุรี'        => t('ลพบุรี', 'Lopburi'),
              'ราชบุรี'       => t('ราชบุรี', 'Ratchaburi'),
              'บุรีรัมย์'      => t('บุรีรัมย์', 'Buriram'),
          ];
          foreach ($seoFooterHotspots as $provFull => $provName):
          ?>
            <a href="category.php?page=<?= urlencode('ขายบ้าน' . $provFull) ?>" style="color:#cbd5e1; text-decoration:none; transition:color .15s;" onmouseover="this.style.color='#C5A059'" onmouseout="this.style.color='#cbd5e1'"><?= e($provName) ?></a>
            <span style="color:rgba(255,255,255,0.18);">•</span>
          <?php endforeach; ?>
          <a href="search.php" style="color:#C5A059; font-weight:700; text-decoration:none;"><?= e(t('ดูทั้งหมด 77 จังหวัด ›', 'View all 77 provinces ›')) ?></a>
        </div>
      </div>
      <div class="seo-footer-bottom">
        <div class="seo-footer-legal">
          <p class="seo-footer-copy">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. <?= e(t('สงวนลิขสิทธิ์ทุกประการ', 'All rights reserved.')) ?></p>
          <p class="seo-footer-disclaimer"><?= e(t('ประกาศและรายละเอียดทรัพย์สินเป็นข้อมูลจากผู้ลงประกาศ โปรดตรวจสอบข้อมูลและเงื่อนไขก่อนตัดสินใจ', 'Listing details are provided by the lister. Please verify all information and terms before making a decision.')) ?></p>
        </div>
        <div class="seo-footer-version-tag" aria-label="Platform Version">
          <span class="version-dot" aria-hidden="true"></span>
          <span class="version-text"><?= e(SITE_VERSION) ?></span>
        </div>
      </div>
    </footer>
    <?php render_lang_float_toggle(); ?>
    <script>
      (() => {
        const isAdminPage = location.pathname.split('/').includes('admin');
        if (isAdminPage) {
          document.querySelectorAll('.site-shell-nav a[href], .site-shell-drawer a[href], .site-shell-footer a[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (href && !href.startsWith('../') && !href.includes('/') && !href.startsWith('#')) {
              link.setAttribute('href', '../' + href);
            } else if (href && href.startsWith('admin/')) {
              link.setAttribute('href', '../' + href);
            }
          });
        }

        const button = document.querySelector('.site-shell-menu-button');
        const drawer = document.querySelector('.site-shell-drawer');
        const overlay = document.querySelector('.site-shell-overlay');
        const close = document.querySelector('.site-shell-close');
        if (!button || !drawer || !overlay || !close) return;
        const setOpen = open => {
          drawer.classList.toggle('open', open);
          overlay.classList.toggle('open', open);
          drawer.setAttribute('aria-hidden', String(!open));
          button.setAttribute('aria-expanded', String(open));
          document.body.classList.toggle('site-shell-drawer-open', open);
        };
        button.addEventListener('click', () => setOpen(true));
        close.addEventListener('click', () => setOpen(false));
        overlay.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });

        // เงา header เมื่อเลื่อนลง
        const nav = document.querySelector('.site-shell-nav');
        if (nav) {
          const onScroll = () => nav.classList.toggle('scrolled', scrollY > 8);
          addEventListener('scroll', onScroll, { passive: true });
          onScroll();
        }
      })();
    </script>
    <?php
}
