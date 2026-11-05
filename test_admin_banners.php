 <?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

ob_start();
try {
    include __DIR__ . '/admin/banners.php';
    $html = ob_get_clean();
    echo "admin/banners.php render: " . strlen($html) . " bytes [PASS]\n";
    $checks = [
        'wireframe-container',
        'previewBox',
        'previewMediaFile',
        'selectSlot',
        'filterBanners',
        'home_hero_top',
        'property_detail_top',
        'property_sidebar',
        'category_top',
    ];
    foreach ($checks as $item) {
        echo " - $item: " . (strpos($html, $item) !== false ? "[OK]" : "[MISSING]") . "\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
}
