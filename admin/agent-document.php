<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT agent_document FROM users WHERE id = ? AND role = 'user' LIMIT 1");
$stmt->execute([$id]);
$file = (string)($stmt->fetchColumn() ?: '');
$path = __DIR__ . '/../uploads/agent-documents/' . basename($file);
$image = $file !== '' && basename($file) === $file ? @getimagesize($path) : false;
if (!$image || !is_file($path)) { http_response_code(404); exit('ไม่พบหลักฐาน'); }
header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="agent-evidence.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
