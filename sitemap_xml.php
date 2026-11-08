<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/seo_provinces.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$urls = [
    ['loc' => page_url(''), 'lastmod' => null],
    ['loc' => page_url('search.php'), 'lastmod' => null],
    ['loc' => page_url('project.php'), 'lastmod' => null],
    ['loc' => page_url('advertise.php'), 'lastmod' => null],
    ['loc' => page_url('sitemap.php'), 'lastmod' => null],
];

foreach (PROPERTY_CATEGORIES as $category => $label) {
    if ($category !== 'other') {
        $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode($category)), 'lastmod' => null];
    }
}
foreach (SEO_CATEGORIES as $slug => $config) {
    $urls[] = ['loc' => category_url($slug), 'lastmod' => null];
}

// 77 จังหวัดประเทศไทยสำหรับ Google & AI Crawler
foreach (THAI_PROVINCES as $provName => $p) {
    if ($provName === '') continue;
    $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('ขายบ้าน' . $provName)), 'lastmod' => null];
    $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('ขายคอนโด' . $provName)), 'lastmod' => null];
    $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('ขายที่ดิน' . $provName)), 'lastmod' => null];
}

// ย่าน Prime CBD Zones & Transit Oriented Hubs ที่มีการค้นหาหนาแน่นที่สุด
if (defined('PRIME_CBD_ZONES')) {
    foreach (PRIME_CBD_ZONES as $cbdKey => $cbd) {
        $cbdName = $cbd['name_th'];
        $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('คอนโด' . $cbdName)), 'lastmod' => null];
        $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('คอนโดให้เช่า' . $cbdName)), 'lastmod' => null];
        $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('บ้าน' . $cbdName)), 'lastmod' => null];
        foreach (array_slice($cbd['sub_districts'], 0, 3) as $subD) {
            $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('คอนโด' . $subD)), 'lastmod' => null];
            $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode('คอนโดให้เช่า' . $subD)), 'lastmod' => null];
        }
    }
}

// คำค้นหาเจาะจงช่วงงบประมาณยอดนิยม (High-intent Budget Keywords)
$budgetSlugs = [
    'คอนโดราคาไม่เกิน1ล้าน', 'คอนโดราคาไม่เกิน2ล้าน', 'คอนโดราคาไม่เกิน3ล้าน', 'คอนโดราคาไม่เกิน5ล้าน',
    'บ้านราคาไม่เกิน2ล้าน', 'บ้านราคาไม่เกิน3ล้าน', 'บ้านราคาไม่เกิน5ล้าน', 'บ้านราคาไม่เกิน10ล้าน',
    'ทาวน์โฮมราคาไม่เกิน2ล้าน', 'ทาวน์โฮมราคาไม่เกิน3ล้าน',
    'ที่ดินราคาถูกไม่เกิน1ล้าน',
    'คอนโดให้เช่าราคาไม่เกิน10000', 'คอนโดให้เช่าราคาไม่เกิน15000', 'บ้านให้เช่าราคาไม่เกิน20000',
];
foreach ($budgetSlugs as $bSlug) {
    $urls[] = ['loc' => page_url('category.php?page=' . rawurlencode($bSlug)), 'lastmod' => null];
}

$rows = db()->query(
    "SELECT id, updated_at
     FROM properties
     WHERE approval = 'approved'
     ORDER BY id"
)->fetchAll();
foreach ($rows as $row) {
    $urls[] = [
        'loc' => page_url('property.php?id=' . (int)$row['id']),
        'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
    ];
}

$projectRows = db()->query(
    'SELECT id, updated_at FROM development_projects WHERE is_published = 1 ORDER BY id'
)->fetchAll();
foreach ($projectRows as $row) {
    $urls[] = [
        'loc' => page_url('project.php?id=' . (int)$row['id']),
        'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($url['lastmod'] !== null) {
        echo '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
