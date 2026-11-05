<?php
// ── ระบบสองภาษา (ไทย / English) ──────────────────────────────
// ใช้: t('ข้อความไทย', 'English text')
// สลับภาษา: ปุ่ม ไทย/EN ในเมนู หรือ ?lang=en / ?lang=th (จำไว้ใน session + cookie)

// เริ่ม session ก่อนอ่านภาษา (ถ้ายังไม่เริ่ม) — ปลอดภัยต่อการเรียกก่อน start_session()
function i18n_boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $isSecure,
        ]);
        @session_start();
    }
}

function set_lang(string $lang): string
{
    $lang = ($lang === 'en') ? 'en' : 'th';
    i18n_boot_session();
    $_SESSION['lang'] = $lang;
    $_COOKIE['sp_lang'] = $lang;
    if (!headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('sp_lang', $lang, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'samesite' => 'Lax',
            'secure'   => $isSecure,
            'httponly' => false,
        ]);
    }
    current_lang($lang);
    return $lang;
}

function current_lang(?string $override = null): string
{
    static $resolved = null;
    if ($override !== null) {
        $resolved = in_array($override, ['th', 'en'], true) ? $override : 'th';
        return $resolved;
    }
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['th', 'en'], true)) {
        $resolved = set_lang((string)$_GET['lang']);
        return $resolved;
    }
    if ($resolved !== null) {
        return $resolved;
    }

    i18n_boot_session();
    $lang = 'th';
    // 1) cookie ที่จดจำไว้
    if (isset($_COOKIE['sp_lang']) && in_array($_COOKIE['sp_lang'], ['th', 'en'], true)) {
        $lang = $_COOKIE['sp_lang'];
    }
    // 2) session
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['th', 'en'], true)) {
        $lang = $_SESSION['lang'];
    }

    $resolved = $lang;
    return $resolved;
}

function is_en(): bool
{
    return current_lang() === 'en';
}

// แปลคู่ข้อความ ไทย/อังกฤษ
function t(string $th, string $en): string
{
    return is_en() ? $en : $th;
}

// แอตทริบิวต์ lang ของ <html>
function html_lang_attr(): string
{
    return current_lang();
}

// ลิงก์สลับภาษา คง URL เดิมไว้
function render_lang_toggle(): void
{
    $target = is_en() ? 'th' : 'en';
    $label = is_en() ? 'ไทย' : 'EN';
    $title = is_en() ? 'เปลี่ยนเป็นภาษาไทย (Switch to Thai)' : 'Switch to English (เปลี่ยนเป็นภาษาอังกฤษ)';
    ?>
    <a class="lang-toggle" href="<?= e(lang_switch_url($target)) ?>" hreflang="<?= e($target) ?>" rel="alternate"
       data-lang-target="<?= e($target) ?>"
       title="<?= e($title) ?>"
       aria-label="<?= e($title) ?>"><span class="lang-globe-icon" aria-hidden="true">🌐</span> <?= e($label) ?></a>
    <?php
}

// ── ชื่อหมวดอสังหาริมทรัพย์แบบสองภาษา (URL slug คงค่าเดิมของระบบ) ──
function property_category_en_label(string $key): ?string
{
    $map = [
        'condo'      => 'Condo',
        'house'      => 'House',
        'townhouse'  => 'Townhouse',
        'land'       => 'Land',
        'commercial' => 'Commercial building',
        'warehouse'  => 'Warehouse & Factory',
        'other'      => 'Other',
    ];
    return $map[$key] ?? null;
}

// label หมวดตามภาษาปัจจุบัน (ใช้ทุกที่ที่แสดง PROPERTY_CATEGORIES)
function category_label_bi(string $key): string
{
    $label = category_label($key);
    return is_en() ? (property_category_en_label($key) ?? $label) : $label;
}

// ข้อความเมนู dropdown ซื้อ/เช่า — inline สองภาษาทั้งชุด
function menu_bi(): array
{
    return [
        'buy'      => t('ซื้อ', 'Buy'),
        'rent'     => t('เช่า', 'Rent'),
        'by_type'  => t('ตามประเภททรัพย์', 'By property type'),
        'by_bts'   => t('ติดรถไฟฟ้า', 'Near transit'),
        'by_budget'=> t('ตามงบประมาณ', 'By budget'),
        'hot_area' => t('ย่านยอดนิยม', 'Popular areas'),
        'all_sale' => t('ดูประกาศขายทั้งหมด', 'View all listings for sale'),
        'all_rent' => t('ดูประกาศเช่าทั้งหมด', 'View all rental listings'),
        'cheap_rent_condo' => t('คอนโดเช่าไม่เกิน 10,000', 'Condo rentals under 10,000'),
        'cheap_rent_house' => t('บ้านเช่าไม่เกิน 15,000', 'House rentals under 15,000'),
        'rent_bts' => t('เช่าคอนโดติด BTS', 'Condo rentals near BTS'),
        'bkk_all'  => t('กรุงเทพฯ ทั้งหมด', 'All of Bangkok'),
    ];
}

// CSS/JS เล็ก ๆ ของปุ่มสลับภาษา (เรียกครั้งเดียวใน head)
function lang_toggle_head(): void
{
    ?>
    <style>
      .lang-toggle {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        color: #2b2150;
        background: #fff;
        cursor: pointer;
        transition: border-color .2s ease, color .2s ease, background .2s ease, transform .25s cubic-bezier(.22,1,.36,1);
      }
      .lang-toggle:hover { border-color: #7955d9; color: #7955d9; background: #f6f2fd; transform: translateY(-1px); }
      .lang-toggle .lang-globe-icon { font-size: 14px; line-height: 1; }
      .site-nav .lang-toggle { margin-left: 6px; }
      .site-shell-actions .lang-toggle { align-self: center; }
      .dd-navbar .lang-toggle { margin-left: 10px; }
      .drawer-menu-item.lang-toggle, .site-shell-drawer .lang-toggle { margin: 10px 20px; align-self: flex-start; }

      /* ซ่อน floating duplicate หากมี .site-controls เพื่อไม่ให้ทับซ้อนกัน */
      .site-controls ~ .lang-float-toggle,
      body:has(.site-controls) .lang-float-toggle,
      body.has-site-controls .lang-float-toggle,
      .lang-float-toggle {
        display: none !important;
      }
      @media (prefers-reduced-motion: reduce) {
        .lang-toggle { transition: none; }
      }
    </style>
    <?php
}

// ปุ่มลอยมุมจอ (fallback เมื่อปุ่มในเมนูถูกซ่อนด้วย responsive CSS)
function render_lang_float_toggle(): void
{
    $target = is_en() ? 'th' : 'en';
    $label = is_en() ? 'ไทย' : 'EN';
    $globe = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
    ?>
    <a class="lang-float-toggle" href="<?= e(lang_switch_url($target)) ?>" hreflang="<?= e($target) ?>" rel="alternate"
       aria-label="<?= is_en() ? 'Switch to Thai' : 'Switch to English' ?>"><?= $globe ?><?= e($label) ?></a>
    <?php
}

// ลิงก์ปัจจุบันพร้อมสลับภาษา (คงพารามิเตอร์อื่น ๆ ใน query ไว้อย่างถูกต้อง)
function lang_switch_url(string $target): string
{
    $target = ($target === 'en') ? 'en' : 'th';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    parse_str($parts['query'] ?? '', $query);
    $query['lang'] = $target;
    $newQuery = http_build_query($query);
    return ($parts['path'] ?? '/') . ($newQuery !== '' ? '?' . $newQuery : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

// เริ่มต้นอ่านค่าภาษาทันทีเมื่อโหลดไฟล์
current_lang();

