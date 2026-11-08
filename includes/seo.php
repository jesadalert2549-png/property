<?php
// ฟังก์ชันช่วย SEO: URL, meta tags, JSON-LD และการดึงข้อมูลหน้าหมวด
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_config.php';
require_once __DIR__ . '/site_shell.php';
start_session();

if (!function_exists('site_url')) {
    function site_url(): string
    {
        return rtrim(defined('SITE_URL') ? SITE_URL : 'https://standardproperty.cloud', '/');
    }
}

if (!function_exists('page_url')) {
    function page_url(string $path = ''): string
    {
        return site_url() . '/' . ltrim($path, '/');
    }
}

// สร้าง URL ที่ route จริงรองรับ ใช้กับ sitemap
function category_url(string $slug): string
{
    return page_url('category.php?page=' . rawurlencode($slug));
}

function seo_head(string $title, string $description, string $canonical, bool $index = true, string $image = ''): void
{
    // max-snippet:-1, max-image-preview:large, max-video-preview:-1 ช่วยให้ Google AI Overviews และ Perplexity ดึงเนื้อหาและภาพไปแสดงผลสรุปได้เต็มประสิทธิภาพ
    $robots = $index ? 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1' : 'noindex,follow';
    $image = $image !== '' ? $image : page_url('images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg');
    echo '<title>' . e($title) . "</title>\n";
    echo '<meta name="description" content="' . e($description) . "\">\n";
    echo '<meta name="robots" content="' . $robots . "\">\n";
    echo '<meta name="googlebot" content="' . $robots . "\">\n";
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . e(SITE_NAME) . "\">\n";
    echo '<meta property="og:title" content="' . e($title) . "\">\n";
    echo '<meta property="og:description" content="' . e($description) . "\">\n";
    echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    echo '<meta property="og:image" content="' . e($image) . "\">\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . e($title) . "\">\n";
    echo '<meta name="twitter:description" content="' . e($description) . "\">\n";
    echo '<meta name="twitter:image" content="' . e($image) . "\">\n";
    // Search Engine Site Verification (Bing Webmaster Tools / Copilot)
    echo '<meta name="msvalidate.01" content="9699B7C40E9B6F2EF57A18C92D40D95B">' . "\n";
    // Typography: Modern Inter & Prompt font system
    site_controls_head();
}

function json_ld(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . str_replace('</', '<\/', $json) . "</script>\n";
}

// slug ของหมวดจากคู่ (category, type) ใช้ทำลิงก์กลับหน้าหมวด
function category_slug_for(string $category, ?string $type = null): ?string
{
    if (!defined('SEO_CATEGORIES')) {
        return $category;
    }

    foreach (SEO_CATEGORIES as $slug => $cfg) {
        if ($cfg['category'] === $category) {
            if ($type === null || empty($cfg['type']) || $cfg['type'] === $type) {
                return $slug;
            }
        }
    }
    return $category;
}

// หมวดที่มีประกาศอนุมัติแล้วอย่างน้อย 1 รายการ (แก้ไขให้อ่านจากคอลัมน์ category)
function available_category_slugs(): array
{
    if (!defined('SEO_CATEGORIES')) {
        return array_keys(PROPERTY_CATEGORIES ?? []);
    }

    // ดึงหมวดหมู่ที่มีประกาศอนุมัติแล้ว (approval = 'approved')
    $rows = db()->query(
        "SELECT category, type, COUNT(*) AS c
         FROM properties WHERE approval = 'approved'
         GROUP BY category, type"
    )->fetchAll();

    $active_cats = [];
    $active_cat_types = [];
    foreach ($rows as $r) {
        $cat = $r['category'];
        $type = $r['type'];
        $active_cats[$cat] = ($active_cats[$cat] ?? 0) + (int)$r['c'];
        $active_cat_types[$cat . '|' . $type] = (int)$r['c'];
    }

    $out = [];
    foreach (SEO_CATEGORIES as $slug => $cfg) {
        $cat = $cfg['category'] ?? $slug;
        $type = $cfg['type'] ?? null;

        if ($type) {
            if (!empty($active_cat_types[$cat . '|' . $type])) {
                $out[] = $slug;
            }
        } else {
            if (!empty($active_cats[$cat])) {
                $out[] = $slug;
            }
        }
    }

    // หากยังไม่มีใน SEO_CATEGORIES ให้คืนค่า slug หมวดหมู่ที่มีในฐานข้อมูล
    if (empty($out)) {
        return array_keys($active_cats);
    }

    return $out;
}

// ดึงรายการประกาศอนุมัติแล้วตามหมวดหมู่ (แก้ไขให้ใช้คอลัมน์ category)
function category_listings(string $category, ?string $type = null): array
{
    $sql = "SELECT p.id, p.title, p.type, p.status, p.price, p.location,
                   (SELECT i.filename FROM property_images i
                    WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
            FROM properties p
            WHERE p.approval = 'approved' AND p.category = ?";
    
    $params = [$category];

    if (!empty($type)) {
        $sql .= " AND p.type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY (p.status = 'done'), p.created_at DESC LIMIT 100";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function render_not_found(): void
{
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="' . e(html_lang_attr()) . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e(t('ไม่พบหน้าที่ต้องการ', 'Page Not Found')) . ' | ' . e(SITE_NAME) . '</title>'
        . '<link rel="stylesheet" href="css/style.css">';
    site_controls_head();
    echo '</head><body>'
        . '<div class="container section center" style="padding: 60px 20px;">'
        . '<div style="margin-bottom: 24px;">' . render_logo_html('index.php') . '</div>'
        . '<h1>' . e(t('ไม่พบหน้าที่ต้องการ', 'Page Not Found')) . '</h1>'
        . '<p style="margin-top: 16px;"><a href="index.php" class="btn" style="text-decoration:none;">' . e(t('กลับหน้าแรก', 'Back to Home')) . '</a></p></div>';
    render_site_footer();
    echo '</body></html>';
    exit;
}

// ส่งสถิติการเข้าชมจากหน้า SEO
function seo_track_script(array $events): void
{
    echo '<script>window.spPendingEvents=window.spPendingEvents||[];';
    foreach ($events as $ev) {
        if (($ev['event'] ?? '') === 'page') {
            echo 'window.spPendingEvents.push(Object.assign(' . json_encode($ev) . ',{path:window.location.pathname+window.location.search}));';
        } else {
            echo 'window.spPendingEvents.push(' . json_encode($ev) . ');';
        }
    }
    echo 'if(window.spFlushTracking){window.spFlushTracking();}</script>' . "\n";
}
