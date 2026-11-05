<?php
require_once __DIR__ . '/helpers.php';

function save_profile_image(array $file, string $folder): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('รูปภาพมีขนาดเกิน 3 MB หรืออัปโหลดไม่สำเร็จ');
    }
    $image = @getimagesize($file['tmp_name'] ?? '');
    $mime = $image['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG หรือ WebP');

    $directory = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('ไม่สามารถเตรียมพื้นที่จัดเก็บรูปได้');
    }
    if (!is_file($directory . '/.htaccess')) {
        file_put_contents($directory . '/.htaccess', $folder === 'agent-documents'
            ? "Options -Indexes\nRequire all denied\n"
            : "Options -Indexes\n<FilesMatch ".'"\\.(php|phtml|phar)$"'.">\nRequire all denied\n</FilesMatch>\n");
    }
    $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
    return $name;
}

function delete_profile_image(string $name, string $folder): void
{
    if ($name !== '' && basename($name) === $name) {
        $path = __DIR__ . '/../uploads/' . $folder . '/' . $name;
        if (is_file($path)) @unlink($path);
    }
}
