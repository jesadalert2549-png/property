<?php
$files = ['sitemap.php', 'admin/members.php', 'admin/seo.php'];
$allOk = true;
foreach ($files as $f) {
    $out = shell_exec('C:\\xampp\\php\\php.exe -l ' . $f . ' 2>&1');
    if (!str_contains((string)$out, 'No syntax errors detected')) {
        echo "ERR in $f: $out\n";
        $allOk = false;
    }
}
if ($allOk) echo "SITEMAP_AND_ADMIN_LANG_OK\n";
