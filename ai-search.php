<?php
/**
 * PropertyGO AI Deep Search — หน้าค้นหาด้วยภาษาธรรมชาติ
 *
 * ใช้ประกาศจริงจากฐานข้อมูลเท่านั้น (ผ่าน api/ai-search.php)
 * ไม่แตะระบบ Login / Admin / ลงประกาศ เดิม
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/price_helpers.php';
require_once __DIR__ . '/includes/ai_search/parser.php';
require_once __DIR__ . '/includes/ai_search/llm.php';
require_once __DIR__ . '/includes/ai_search/ranker.php';
current_lang();

$rawQuery = is_string($_GET['q'] ?? null) ? trim((string)$_GET['q']) : '';
if (mb_strlen($rawQuery) > 300) {
    $rawQuery = mb_substr($rawQuery, 0, 300);
}

// รับค่าจาก Quick AI Search บนหน้าแรก (checkbox สิ่งอำนวยความสะดวก + ประเภท)
// รองรับ transit (ใกล้รถไฟฟ้า) และ quiet (บรรยากาศเงียบ) ซึ่งไม่ใช่คีย์ใน catalog
$quickAmenityCatalog = ai_search_amenity_catalog();
$quickExtraKeys = ['transit' => t('ใกล้รถไฟฟ้า', 'Near transit'), 'quiet' => t('บรรยากาศเงียบ', 'Quiet area')];
$quickAmenities = [];
if (!empty($_GET['am']) && is_array($_GET['am'])) {
    foreach ($_GET['am'] as $amKey) {
        if (!is_string($amKey) || in_array($amKey, $quickAmenities, true)) {
            continue;
        }
        if (isset($quickAmenityCatalog[$amKey]) || isset($quickExtraKeys[$amKey])) {
            $quickAmenities[] = $amKey;
        }
    }
}
$quickCategory = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
if (!array_key_exists($quickCategory, PROPERTY_CATEGORIES)) {
    $quickCategory = '';
}
$quickType = is_string($_GET['type'] ?? null) && in_array($_GET['type'], ['sale', 'rent'], true) ? $_GET['type'] : '';

$engineLabel = ai_search_llm_available()
    ? t('AI ทำงานแบบเชื่อมต่อโมเดลภาษา', 'AI powered by language model')
    : t('โหมดทดลอง: ตัวแปลงภาษาธรรมชาติในเครื่อง (ไม่ใช้ API ภายนอก)', 'Prototype mode: on-server natural-language parser (no external API)');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php
    seo_head(
        t('AI Deep Search — ค้นหาบ้านและคอนโดด้วยภาษาธรรมชาติ | ', 'AI Deep Search — Natural Language Property Search | ') . SITE_NAME,
        t('พิมพ์สิ่งที่คุณต้องการเป็นภาษาพูด เช่น "คอนโด 2 ล้านแถวสุขุมวิท 2 ห้องนอน" แล้วระบบจะค้นหาประกาศจริงพร้อมเหตุผลการจับคู่', 'Describe what you want in plain language and PropertyGO matches real listings with explanations.'),
        page_url('ai-search.php'),
        false
    );
  ?>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string) filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/site-shell.css?v=<?= e((string) filemtime(__DIR__ . '/css/site-shell.css')) ?>">
  <?php lang_toggle_head(); ?>
  <link rel="stylesheet" href="css/ai-search.css?v=<?= e((string) filemtime(__DIR__ . '/css/ai-search.css')) ?>">
</head>
<body class="ai-search-page">

<?php render_site_header(); ?>

<main class="ai-main">
  <section class="ai-hero">
    <div class="ai-hero-inner">
      <span class="ai-eyebrow"><?= e(t('PropertyGO AI', 'PropertyGO AI')) ?></span>
      <h1><?= e(t('บอกเราว่าคุณกำลังมองหาอะไร', 'Tell us what you are looking for')) ?></h1>
      <p><?= e(t('พิมพ์เป็นภาษาพูดได้เลย ระบบจะค้นจากประกาศจริงใน PropertyGO พร้อมบอกเหตุผลว่าทำไมถึงเลือกแต่ละรายการ', 'Type naturally. We search real PropertyGO listings and explain why each one matches.')) ?></p>

      <form class="ai-search-form" id="aiSearchForm" action="ai-search.php" method="get" role="search">
        <label class="sr-only" for="aiSearchInput"><?= e(t('ค้นหาอสังหาริมทรัพย์ด้วยภาษาธรรมชาติ', 'Natural language property search')) ?></label>
        <div class="ai-search-box">
          <svg class="ai-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
          </svg>
          <input
            id="aiSearchInput"
            name="q"
            type="text"
            autocomplete="off"
            maxlength="300"
            value="<?= e($rawQuery) ?>"
            placeholder="<?= e(t('ลองบอกบ้านหรือคอนโดที่คุณกำลังมองหา...', 'Describe the home or condo you are looking for...')) ?>">
          <button type="submit" class="ai-search-submit" id="aiSearchSubmit">
            <span class="ai-btn-label"><?= e(t('ค้นหา', 'Search')) ?></span>
            <span class="ai-btn-spinner" aria-hidden="true"></span>
          </button>
        </div>
      </form>

      <?php if ($quickAmenities || $quickCategory !== '' || $quickType !== ''): ?>
        <?php
          $amCatalog = ai_search_amenity_catalog();
          $activeFilterChips = [];
          $quickTransit = in_array('transit', $quickAmenities, true);
          foreach ($quickAmenities as $amKey) {
              if ($amKey === 'transit') continue; // แสดงเป็น chip แยกด้านล่าง
              $label = isset($quickExtraKeys[$amKey])
                  ? $quickExtraKeys[$amKey]
                  : t($amCatalog[$amKey][0] ?? $amKey, $amKey);
              $activeFilterChips[] = [
                  'label' => $label,
                  'remove' => array_values(array_diff($quickAmenities, [$amKey])),
              ];
          }
        ?>
        <div class="ai-active-filters" aria-label="<?= e(t('ตัวกรองที่เลือกไว้', 'Selected filters')) ?>">
          <?php if ($quickCategory !== ''): ?>
            <span class="ai-active-chip is-static">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/></svg>
              <?= e(category_label($quickCategory)) ?>
            </span>
          <?php endif; ?>
          <?php if ($quickType !== ''): ?>
            <span class="ai-active-chip is-static"><?= e(type_label($quickType)) ?></span>
          <?php endif; ?>
          <?php
            // สร้าง URL "ถอดตัวกรอง" แบบ am[]=... (http_build_query ให้ am[0]= ซึ่งอ่านไม่สวย)
            $buildFilterUrl = static function (array $remaining) use ($rawQuery, $quickCategory, $quickType): string {
                $parts = [];
                if ($rawQuery !== '') { $parts[] = 'q=' . rawurlencode($rawQuery); }
                if ($quickCategory !== '') { $parts[] = 'category=' . rawurlencode($quickCategory); }
                if ($quickType !== '') { $parts[] = 'type=' . rawurlencode($quickType); }
                foreach ($remaining as $am) {
                    $parts[] = 'am%5B%5D=' . rawurlencode((string)$am);
                }
                return 'ai-search.php' . ($parts ? '?' . implode('&', $parts) : '');
            };
          ?>
          <?php if (!empty($quickTransit)): ?>
            <?php $transitRemoveUrl = $buildFilterUrl(array_values(array_diff($quickAmenities, ['transit']))); ?>
            <a class="ai-active-chip" href="<?= e($transitRemoveUrl) ?>" title="<?= e(t('เอาตัวกรองนี้ออก', 'Remove this filter')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="3" width="14" height="13" rx="3"/><path d="M5 10h14M9 16l-2 4M15 16l2 4"/></svg>
              <?= e(t('ใกล้รถไฟฟ้า', 'Near transit')) ?>
              <svg class="ai-chip-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </a>
          <?php endif; ?>
          <?php foreach ($activeFilterChips as $chip): ?>
            <?php $removeUrl = $buildFilterUrl($chip['remove']); ?>
            <a class="ai-active-chip" href="<?= e($removeUrl) ?>" title="<?= e(t('เอาตัวกรองนี้ออก', 'Remove this filter')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
              <?= e($chip['label']) ?>
              <svg class="ai-chip-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="ai-examples" aria-label="<?= e(t('ตัวอย่างคำค้นหา', 'Example searches')) ?>">
        <span class="ai-examples-label"><?= e(t('ลองแบบนี้:', 'Try:')) ?></span>
        <?php
        $examples = [
            t('หาคอนโด 2 ล้านแถวสุขุมวิท 2 ห้องนอน', 'Condo under 2M near Sukhumvit, 2 bedrooms'),
            t('อยากได้บ้านแถวปากน้ำ งบ 3 ล้าน มีที่จอดรถ', 'House near Pak Nam, 3M budget, with parking'),
            t('หาทาวน์โฮมใกล้รถไฟฟ้า ไม่เกิน 2.5 ล้าน', 'Townhouse near BTS/MRT, up to 2.5M'),
            t('หาคอนโดห้องใหญ่ๆ แถวบางนา ราคาไม่แพง', 'Large condo in Bang Na, affordable price'),
        ];
        foreach ($examples as $example): ?>
          <button type="button" class="ai-example-chip" data-query="<?= e($example) ?>"><?= e($example) ?></button>
        <?php endforeach; ?>
      </div>

      <p class="ai-engine-note">
        <span class="ai-engine-dot" aria-hidden="true"></span>
        <?= e($engineLabel) ?>
      </p>
    </div>
  </section>

  <section class="ai-results" id="aiResults" aria-live="polite" aria-busy="false">
    <div class="ai-results-inner" id="aiResultsInner">
      <?php if ($rawQuery === ''): ?>
        <div class="ai-placeholder">
          <div class="ai-placeholder-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V20h14V9.5"></path><path d="M10 20v-5h4v5"></path>
            </svg>
          </div>
          <h2><?= e(t('เริ่มต้นด้วยการพิมพ์ความต้องการของคุณ', 'Start by describing what you need')) ?></h2>
          <p><?= e(t('ระบบจะอ่านข้อความ ค้นประกาศจริง และอธิบายเหตุผลการจับคู่ให้คุณเห็นทุกครั้ง', 'We read your message, search real listings, and explain every match.')) ?></p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php render_page_cta([
    'title'   => t('มีทรัพย์ให้ขายหรือให้เช่า?', 'Have a property to sell or rent?'),
    'text'    => t('ลงประกาศฟรีบน ' . SITE_NAME . ' เพื่อให้ผู้ซื้อที่กำลังค้นหาด้วย AI เจอคุณ', 'List for free on ' . SITE_NAME . ' and be found by buyers using AI search.'),
    'primary' => ['submit.php', t('ลงประกาศฟรี', 'Post a free listing')],
    'ghost'   => ['search.php', t('ใช้ตัวกรองแบบเดิม', 'Use classic filters')],
]); ?>

<?php render_to_top(); render_ux_scripts(); render_site_footer(); ?>

<script>
  window.AI_SEARCH_CONFIG = {
    endpoint: 'api/ai-search.php',
    initialQuery: <?= json_encode($rawQuery, JSON_UNESCAPED_UNICODE) ?>,
    quickFilters: <?= json_encode([
        'amenities' => array_values($quickAmenities),
        'category'  => $quickCategory,
        'type'      => $quickType,
    ], JSON_UNESCAPED_UNICODE) ?>,
    labels: {
      searching: <?= json_encode(t('กำลังวิเคราะห์ความต้องการของคุณ...', 'Understanding your request...'), JSON_UNESCAPED_UNICODE) ?>,
      results: <?= json_encode(t('พบ %d รายการที่ใกล้เคียง', 'Found %d matching listings'), JSON_UNESCAPED_UNICODE) ?>,
      more: <?= json_encode(t('ดูผลลัพธ์เพิ่มเติม', 'Show more results'), JSON_UNESCAPED_UNICODE) ?>,
      fewer: <?= json_encode(t('ย่อรายการ', 'Show fewer'), JSON_UNESCAPED_UNICODE) ?>,
      error: <?= json_encode(t('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', 'Something went wrong. Please try again.'), JSON_UNESCAPED_UNICODE) ?>,
      noResult: <?= json_encode(t('ไม่พบประกาศที่ตรงกับเงื่อนไข', 'No listings matched your criteria'), JSON_UNESCAPED_UNICODE) ?>,
      matched: <?= json_encode(t('ตรงกับที่ต้องการ', 'Matches'), JSON_UNESCAPED_UNICODE) ?>,
      mismatch: <?= json_encode(t('ยังไม่ตรง', 'Does not match'), JSON_UNESCAPED_UNICODE) ?>,
      unknown: <?= json_encode(t('ข้อมูลที่ยังไม่มี', 'Not available'), JSON_UNESCAPED_UNICODE) ?>,
      score: <?= json_encode(t('คะแนนความเหมาะสม', 'Match score'), JSON_UNESCAPED_UNICODE) ?>
    }
  };
</script>
<script src="js/ai-search.js?v=<?= e((string) filemtime(__DIR__ . '/js/ai-search.js')) ?>" defer></script>
</body>
</html>
