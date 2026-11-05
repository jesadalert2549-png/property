<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_schema.php';
require_admin();
$pdo = db();
ensure_profile_schema($pdo);
if (request_is_post()) {
    csrf_check();
    $userId = (int)($_POST['user_id'] ?? 0);
    $decision = ($_POST['decision'] ?? '') === 'approve' ? 'verified' : 'rejected';
    $stmt = $pdo->prepare("UPDATE users SET agent_status = ?, agent_reviewed_at = NOW() WHERE id = ? AND role = 'user' AND agent_status = 'pending'");
    $stmt->execute([$decision, $userId]);
    set_flash($stmt->rowCount() ? ($decision === 'verified' ? 'รับรอง Agent แล้ว เครื่องหมายจะแสดงในบัญชีผู้ใช้' : 'บันทึกผลตรวจแล้ว') : 'รายการนี้ถูกตรวจไปแล้วหรือไม่พบคำขอ');
    redirect('agents.php');
}
$pending = $pdo->query("SELECT id, username, full_name, email, phone, agent_license, agent_agency, agent_document, created_at FROM users WHERE role = 'user' AND agent_status = 'pending' ORDER BY created_at")->fetchAll();
$flash = get_flash();
$token = csrf_token();
?>
<!doctype html><html lang="<?= e(html_lang_attr()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ตรวจรับรอง Agent | Standard Property</title><link rel="stylesheet" href="admin.css?v=<?= e((string)filemtime(__DIR__ . '/admin.css')) ?>"><?php site_controls_head(); ?></head><body class="admin-body"><?php require __DIR__ . '/_sidebar.php'; ?><main class="agent-review-page"><header><span>TRUST & SAFETY</span><h1>ตรวจรับรอง Agent</h1><p>ตรวจหลักฐานและข้อมูลใบอนุญาตก่อนอนุมัติเครื่องหมายที่แสดงต่อผู้ใช้</p></header><?php if ($flash): ?><div class="admin-profile-alert success"><?= e($flash) ?></div><?php endif; ?><?php if (!$pending): ?><section class="agent-review-empty"><strong>ไม่มีคำขอที่รอตรวจ</strong><p>คำขอใหม่จากสมาชิกจะแสดงในหน้านี้</p></section><?php else: ?><div class="agent-review-list"><?php foreach ($pending as $agent): ?><article class="agent-review-card"><div class="agent-review-card-head"><div><small>คำขอจาก <?= e($agent['username']) ?> · <?= e(date('d/m/Y', strtotime($agent['created_at']))) ?></small><h2><?= e($agent['full_name'] ?: $agent['username']) ?></h2></div><span class="agent-status-label pending">รอตรวจสอบ</span></div><dl><div><dt>บริษัท/สังกัด</dt><dd><?= e($agent['agent_agency']) ?></dd></div><div><dt>เลขใบอนุญาต/ทะเบียน</dt><dd><?= e($agent['agent_license']) ?></dd></div><div><dt>อีเมลและโทรศัพท์</dt><dd><?= e($agent['email']) ?><?= $agent['phone'] ? ' · ' . e($agent['phone']) : '' ?></dd></div></dl><?php if (!empty($agent['agent_document'])): ?><a class="agent-document-link" href="agent-document.php?id=<?= (int)$agent['id'] ?>" target="_blank" rel="noopener">เปิดดูหลักฐานที่แนบ ↗</a><?php endif; ?><form method="post" class="agent-review-actions"><input type="hidden" name="csrf" value="<?= e($token) ?>"><input type="hidden" name="user_id" value="<?= (int)$agent['id'] ?>"><button name="decision" value="approve" class="agent-approve">✓ รับรอง Agent</button><button name="decision" value="reject" class="agent-reject">ไม่รับรอง</button></form></article><?php endforeach; ?></div><?php endif; ?></main></body></html>
