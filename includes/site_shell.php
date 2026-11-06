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
function render_ux_scripts(): void
{
    ?>
    <script>
      (() => {
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
          <div class="nav-dropdown">
            <a href="search.php?type=sale" class="nav-dropdown-toggle"><?= e(t('ซื้อ', 'Buy')) ?><span class="nav-caret" aria-hidden="true"></span></a>
            <div class="nav-dropdown-panel" role="menu">
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ตามประเภททรัพย์</p>
                <?php foreach (['condo' => 'คอนโด', 'house' => 'บ้านเดี่ยว', 'townhouse' => 'ทาวน์เฮ้าส์', 'land' => 'ที่ดิน', 'commercial' => 'อาคารพาณิชย์'] as $catKey => $catLabel): ?>
                  <a href="search.php?type=sale&category=<?= e($catKey) ?>"><?= e($catLabel) ?></a>
                <?php endforeach; ?>
                <a href="search.php?type=sale">ดูประกาศขายทั้งหมด</a>
              </div>
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ติดรถไฟฟ้า</p>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดติดบีทีเอส')) ?>">คอนโดติด BTS</a>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดติดเอ็มอาร์ที')) ?>">คอนโดติด MRT</a>
                <p class="nav-dropdown-heading">ตามงบประมาณ</p>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดราคาไม่เกินหนึ่งล้าน')) ?>">คอนโดไม่เกิน 1 ล้าน</a>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดราคาไม่เกินสามล้าน')) ?>">คอนโดไม่เกิน 3 ล้าน</a>
                <a href="category.php?page=<?= e(rawurlencode('บ้านราคาไม่เกินสองล้าน')) ?>">บ้านไม่เกิน 2 ล้าน</a>
                <a href="category.php?page=<?= e(rawurlencode('ที่ดินราคาถูก')) ?>">ที่ดินราคาถูก</a>
              </div>
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ย่านยอดนิยม</p>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดย่านสุขุมวิท')) ?>">ย่านสุขุมวิท</a>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดย่านลาดพร้าว')) ?>">ย่านลาดพร้าว</a>
                <a href="category.php?page=<?= e(rawurlencode('บ้านย่านรามอินทรา')) ?>">ย่านรามอินทรา</a>
                <a href="search.php?type=sale&q=<?= rawurlencode('กรุงเทพ') ?>">กรุงเทพฯ ทั้งหมด</a>
              </div>
            </div>
          </div>
          <div class="nav-dropdown">
            <a href="search.php?type=rent" class="nav-dropdown-toggle"><?= e(t('เช่า', 'Rent')) ?><span class="nav-caret" aria-hidden="true"></span></a>
            <div class="nav-dropdown-panel" role="menu">
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ตามประเภททรัพย์</p>
                <?php foreach (['condo' => 'คอนโด', 'house' => 'บ้านเดี่ยว', 'townhouse' => 'ทาวน์เฮ้าส์', 'commercial' => 'อาคารพาณิชย์'] as $catKey => $catLabel): ?>
                  <a href="search.php?type=rent&category=<?= e($catKey) ?>"><?= e($catLabel) ?></a>
                <?php endforeach; ?>
                <a href="search.php?type=rent">ดูประกาศเช่าทั้งหมด</a>
              </div>
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ราคาถูก</p>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดให้เช่าราคาถูก')) ?>">คอนโดเช่าไม่เกิน 10,000</a>
                <a href="category.php?page=<?= e(rawurlencode('บ้านให้เช่าราคาถูก')) ?>">บ้านเช่าไม่เกิน 15,000</a>
                <p class="nav-dropdown-heading">ติดรถไฟฟ้า</p>
                <a href="category.php?page=<?= e(rawurlencode('คอนโดให้เช่าติดบีทีเอส')) ?>">เช่าคอนโดติด BTS</a>
              </div>
              <div class="nav-dropdown-col">
                <p class="nav-dropdown-heading">ย่านยอดนิยม</p>
                <a href="search.php?type=rent&q=<?= rawurlencode('สุขุมวิท') ?>">สุขุมวิท</a>
                <a href="search.php?type=rent&q=<?= rawurlencode('ลาดพร้าว') ?>">ลาดพร้าว</a>
                <a href="search.php?type=rent&q=<?= rawurlencode('รัชดา') ?>">รัชดาภิเษก</a>
                <a href="search.php?type=rent&q=<?= rawurlencode('เชียงใหม่') ?>">เชียงใหม่</a>
              </div>
            </div>
          </div>
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
          <strong style="color:#C5A059; letter-spacing:0.04em;">🏢 ทำเลเศรษฐกิจชั้นนำ Prime CBD &amp; รถไฟฟ้า BTS/MRT</strong>
          <span style="color:#94a3b8; font-size:11px;">ย่านการอยู่อาศัยและการลงทุนอสังหาฯ ผลตอบแทนสูง</span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:6px 12px; line-height:2; margin-bottom:14px;">
          <?php
          $footerCbdHotspots = [
              'สุขุมวิท'     => 'สุขุมวิท',
              'ทองหล่อ'      => 'ทองหล่อ',
              'เอกมัย'       => 'เอกมัย',
              'พร้อมพงษ์'    => 'พร้อมพงษ์',
              'สาทร'         => 'สาทร',
              'สีลม'         => 'สีลม',
              'พระราม 9'     => 'พระราม 9 (New CBD)',
              'รัชดาภิเษก'   => 'รัชดาฯ - ห้วยขวาง',
              'อารีย์'        => 'อารีย์ - พญาไท',
              'ลาดพร้าว'     => 'ห้าแยกลาดพร้าว',
              'บางนา'        => 'บางนา - EEC',
              'เจริญกรุง'    => 'ริมน้ำเจ้าพระยา',
              'นิมมาน'       => 'นิมมานฯ (เชียงใหม่)',
              'พัทยา'        => 'พัทยา - จอมเทียน',
              'หัวหิน'        => 'หัวหิน - ชะอำ',
              'บางเทา'       => 'บางเทา - ลากูน่า (ภูเก็ต)',
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
              'กรุงเทพมหานคร' => 'กรุงเทพฯ',
              'เชียงใหม่'     => 'เชียงใหม่',
              'ภูเก็ต'        => 'ภูเก็ต',
              'ชลบุรี'        => 'ชลบุรี (พัทยา)',
              'นนทบุรี'       => 'นนทบุรี',
              'สมุทรปราการ'   => 'สมุทรปราการ',
              'ปทุมธานี'       => 'ปทุมธานี',
              'ระยอง'         => 'ระยอง',
              'ประจวบคีรีขันธ์' => 'ประจวบฯ (หัวหิน)',
              'นครราชสีมา'     => 'โคราช (เขาใหญ่)',
              'ขอนแก่น'       => 'ขอนแก่น',
              'สุราษฎร์ธานี'    => 'สุราษฎร์ฯ (สมุย)',
              'สงขลา'         => 'สงขลา (หาดใหญ่)',
              'เชียงราย'       => 'เชียงราย',
              'พระนครศรีอยุธยา'=> 'อยุธยา',
              'นครปฐม'        => 'นครปฐม',
              'กระบี่'         => 'กระบี่',
              'เพชรบุรี'       => 'เพชรบุรี (ชะอำ)',
              'กาญจนบุรี'      => 'กาญจนบุรี',
              'อุดรธานี'       => 'อุดรธานี',
              'อุบลราชธานี'    => 'อุบลราชธานี',
              'พิษณุโลก'      => 'พิษณุโลก',
              'ฉะเชิงเทรา'     => 'ฉะเชิงเทรา',
              'สระบุรี'        => 'สระบุรี',
              'ลำปาง'         => 'ลำปาง',
              'นครศรีธรรมราช' => 'นครศรีธรรมราช',
              'พังงา'         => 'พังงา',
              'ตรัง'          => 'ตรัง',
              'สมุทรสาคร'     => 'สมุทรสาคร',
              'ลพบุรี'        => 'ลพบุรี',
              'ราชบุรี'       => 'ราชบุรี',
              'บุรีรัมย์'      => 'บุรีรัมย์'
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
