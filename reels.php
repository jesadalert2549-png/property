<?php
/**
 * Property Reels (Kasa Reels / Short-form Video Feed Prototype)
 * พัฒนาสำหรับดูคลิปสั้นพาทัวร์บ้านและคอนโดแบบเต็มจอ เลื่อนปัดได้ไม่รู้จบ
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/price_helpers.php';
current_lang();

$pdo = db();

// รองรับ AJAX API สำหรับโหลดคอมเมนต์ และโพสต์คอมเมนต์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'get_comments') {
        $propId = max(0, (int)($_POST['property_id'] ?? 0));
        $cStmt = $pdo->prepare(
            "SELECT c.id, c.author_name, c.comment, c.image_path, c.created_at, c.user_id,
                    (SELECT u.avatar_path FROM users u WHERE u.id = c.user_id LIMIT 1) AS user_avatar
             FROM property_comments c
             WHERE c.property_id = ?
             ORDER BY c.id DESC
             LIMIT 50"
        );
        $cStmt->execute([$propId]);
        $comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'add_comment') {
        $propId = max(0, (int)($_POST['property_id'] ?? 0));
        $author = trim((string)($_POST['author_name'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if (!empty($_SESSION['user_id'])) {
            $uStmt = $pdo->prepare("SELECT full_name, username, avatar_path FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                $author = trim((string)($uRow['full_name'] ?? '')) ?: ($uRow['username'] ?? 'สมาชิก');
            }
        }
        if ($author === '') {
            $author = 'ผู้เยี่ยมชม ' . rand(100, 999);
        }

        // จัดการอัปโหลดรูปภาพแนบในคอมเมนต์ (ถ้ามี)
        $uploadedCommentImage = null;
        if (!empty($_FILES['comment_image']['tmp_name']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['comment_image']['tmp_name'];
            $origName = $_FILES['comment_image']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $maxSize = 8 * 1024 * 1024; // 8MB

            if (in_array($ext, $allowedExts, true) && filesize($tmp) <= $maxSize) {
                $commentUploadDir = __DIR__ . '/uploads/comments/';
                if (!is_dir($commentUploadDir)) {
                    mkdir($commentUploadDir, 0755, true);
                }
                $newImgName = 'cm_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $commentUploadDir . $newImgName)) {
                    $uploadedCommentImage = 'comments/' . $newImgName;
                }
            }
        }

        if ($propId > 0 && ($comment !== '' || $uploadedCommentImage !== null)) {
            $ins = $pdo->prepare("INSERT INTO property_comments (property_id, user_id, author_name, comment, image_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $ins->execute([$propId, $userId, mb_substr($author, 0, 80), mb_substr($comment, 0, 1000), $uploadedCommentImage]);
            $newId = $pdo->lastInsertId();

            $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM property_comments WHERE property_id = $propId")->fetchColumn();

            // ดึง avatar ล่าสุดของผู้คอมเมนต์
            $authorAvatar = null;
            if ($userId) {
                $authorAvatar = $pdo->query("SELECT avatar_path FROM users WHERE id = $userId")->fetchColumn();
            }

            echo json_encode([
                'success' => true,
                'total_count' => $totalCount,
                'comment' => [
                    'id' => $newId,
                    'user_id' => $userId,
                    'author_name' => htmlspecialchars($author),
                    'user_avatar' => $authorAvatar ? htmlspecialchars($authorAvatar) : null,
                    'comment' => htmlspecialchars($comment),
                    'image_path' => $uploadedCommentImage ? htmlspecialchars($uploadedCommentImage) : null,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อความหรือแนบรูปภาพ']);
        exit;
    }
}

// รับค่าค้นหา Reels จาก GET parameter 'q'
$reelsSearchQuery = trim((string)($_GET['q'] ?? ''));

// ดึงประกาศทั้งหมดที่มีไฟล์วิดีโอ พร้อมนับจำนวนคอมเมนต์ และพิกัดแผนที่ (lat, lng)
$sqlReels = "SELECT p.id, p.title, p.type, p.price, p.original_price, p.location, p.lat, p.lng, p.property_category, p.video_file, p.phone, p.line_id, p.description,
            (SELECT COUNT(*) FROM property_comments c WHERE c.property_id = p.id) AS comments_count,
            u.full_name AS publisher_name,
            u.avatar_path AS publisher_avatar,
            u.agent_status AS publisher_agent_status,
            (SELECT i.filename FROM property_images i WHERE i.property_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
     FROM properties p
     LEFT JOIN users u ON u.id = p.user_id
     WHERE p.approval = 'approved' AND p.video_file IS NOT NULL AND p.video_file != ''";

$sqlParams = [];
if ($reelsSearchQuery !== '') {
    $sqlReels .= " AND (p.title LIKE ? OR p.location LIKE ? OR p.description LIKE ? OR p.property_category LIKE ?)";
    $likeQ = '%' . $reelsSearchQuery . '%';
    $sqlParams = [$likeQ, $likeQ, $likeQ, $likeQ];
}

$sqlReels .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sqlReels);
$stmt->execute($sqlParams);
$reels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบข้อมูลผู้ใช้ที่กำลังล็อกอินอยู่
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $uStmt = $pdo->prepare("SELECT id, username, full_name, avatar_path, agent_status, role FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([(int)$_SESSION['user_id']]);
    $currentUser = $uStmt->fetch(PDO::FETCH_ASSOC);
}

$flash = get_flash();

$pageTitle = t('Property Reels | คลิปสั้นพาทัวร์บ้าน & คอนโด', 'Property Reels | Short Video Home Tours') . ' - ' . SITE_NAME;
$pageDesc  = t('รับชมคลิปวิดีโอสั้นพาทัวร์บ้าน คอนโด ที่ดิน อสังหาริมทรัพย์สวยๆ ปัดดูเพลินได้ไม่รู้จบ', 'Watch short video tours of luxury homes, condos, and properties.');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <?php seo_head($pageTitle, $pageDesc, site_url() . '/reels.php', false); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <?php lang_toggle_head(); ?>
  <style>
    * { box-sizing: border-box; }
    /* Hide global injected site-controls on Reels page so it doesn't block the video */
    .site-controls,
    [data-back-button],
    .site-control-button {
      display: none !important;
    }

    :root {
      --font: 'Prompt', 'Noto Sans Thai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
      --font-editorial: 'Prompt', 'Noto Sans Thai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    }

    html, body,
    .reels-viewport,
    .reel-slide,
    .reel-bottom-card,
    .reel-bottom-card * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Prompt', 'Noto Sans Thai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Reels Viewport Container */
    .reels-viewport {
      width: 100%;
      height: 100vh;
      height: 100dvh;
      overflow-y: scroll;
      scroll-snap-type: y mandatory;
      scrollbar-width: none; /* Firefox */
      -ms-overflow-style: none; /* IE/Edge */
    }
    .reels-viewport::-webkit-scrollbar { display: none; }

    /* Each Slide Item */
    .reel-slide {
      position: relative;
      width: 100%;
      height: 100vh;
      height: 100dvh;
      scroll-snap-align: start;
      scroll-snap-stop: always;
      display: flex;
      justify-content: center;
      align-items: center;
      background: #0B1C33;
      overflow: hidden;
    }

    /* Backdrop Blur for Desktop */
    .reel-backdrop {
      position: absolute;
      inset: -20px;
      background-size: cover;
      background-position: center;
      filter: blur(28px) brightness(0.35);
      transform: scale(1.1);
      z-index: 1;
      opacity: 0.8;
      pointer-events: none;
    }

    /* Video Frame (Mobile & Desktop: completely borderless and seamless) */
    .reel-video-container {
      position: relative;
      width: 100%;
      height: 100%;
      max-width: 500px;
      z-index: 2;
      background: #000;
      box-shadow: 0 16px 50px rgba(0, 0, 0, 0.7);
      overflow: hidden;
      border: none !important;
      outline: none !important;
    }
    @media (min-width: 768px) {
      .reel-video-container {
        height: 96vh;
        max-height: 900px;
        border-radius: 20px;
        border: none !important;
        outline: none !important;
      }
    }

    .reel-video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      cursor: pointer;
    }

    /* Top Floating Controls */
    .reel-top-bar {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      padding: env(safe-area-inset-top, 14px) 14px 14px;
      padding-top: max(14px, env(safe-area-inset-top));
      display: flex;
      align-items: center;
      gap: 8px;
      z-index: 10;
      background: linear-gradient(180deg, rgba(6, 15, 28, 0.85) 0%, rgba(6, 15, 28, 0.4) 60%, rgba(0,0,0,0) 100%);
      pointer-events: none;
    }
    .reel-top-bar > * { pointer-events: auto; }

    .reel-top-left-actions {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }

    .reel-back-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      height: 36px;
      padding: 0 12px;
      border-radius: 999px;
      background: rgba(11, 28, 51, 0.85);
      border: 1px solid rgba(197, 160, 89, 0.45);
      color: #FFFFFF;
      text-decoration: none;
      font-size: 12.5px;
      font-weight: 700;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      transition: all .2s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }
    .reel-back-btn:hover {
      background: #0B1C33;
      border-color: #C5A059;
      color: #F8D888;
      transform: translateY(-1px);
    }

    .reel-post-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      height: 36px;
      padding: 0 12px;
      border-radius: 999px;
      background: linear-gradient(135deg, #0B1C33 0%, #152E4D 50%, #C5A059 100%);
      border: 1.5px solid rgba(248, 216, 136, 0.75);
      color: #FFFFFF;
      text-decoration: none;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .01em;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 2px 10px rgba(197, 160, 89, 0.3);
      transition: all .2s ease;
      white-space: nowrap;
    }
    .reel-post-btn:hover {
      border-color: #FFFFFF;
      background: linear-gradient(135deg, #C5A059 0%, #A8843F 100%);
      color: #0B1C33;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(197, 160, 89, 0.5);
    }

    .reel-badge-feed {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(239,68,68,0.9), rgba(197,160,89,0.9));
      color: #fff;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
      box-shadow: 0 4px 14px rgba(239,68,68,0.4);
      animation: pulseReels 2s infinite ease-in-out;
    }
    @keyframes pulseReels {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.04); }
    }

    /* Luxury Search Bar in Top Bar */
    .reel-search-form {
      position: relative;
      flex: 1;
      max-width: 280px;
      margin: 0;
    }
    .reel-search-wrap {
      display: flex;
      align-items: center;
      background: rgba(11, 28, 51, 0.85);
      border: 1.5px solid rgba(197, 160, 89, 0.5);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 999px;
      padding: 0 12px;
      height: 36px;
      transition: all .2s ease;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
    }
    .reel-search-wrap:hover {
      border-color: rgba(248, 216, 136, 0.8);
      background: rgba(11, 28, 51, 0.95);
    }
    .reel-search-wrap:focus-within {
      background: #0B1C33;
      border-color: #F8D888;
      box-shadow: 0 0 14px rgba(197, 160, 89, 0.45);
    }
    .reel-search-btn-submit {
      background: transparent;
      border: none;
      padding: 0;
      margin: 0 6px 0 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #F8D888;
    }
    .reel-search-icon {
      width: 15px;
      height: 15px;
      fill: currentColor;
      flex-shrink: 0;
    }
    .reel-search-input {
      background: transparent;
      border: none;
      outline: none;
      color: #FFFFFF;
      font-size: 12.5px;
      font-family: 'Prompt', 'Inter', sans-serif;
      font-weight: 500;
      width: 100%;
    }
    .reel-search-input::placeholder {
      color: rgba(255, 255, 255, 0.65);
      font-weight: 400;
    }
    .reel-search-clear {
      color: rgba(255, 255, 255, 0.6);
      text-decoration: none;
      font-size: 13px;
      line-height: 1;
      padding: 2px 5px;
      border-radius: 50%;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      transition: color .15s;
    }
    .reel-search-clear:hover {
      color: #F8D888;
    }

    .reel-sound-toggle-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(11, 28, 51, 0.85);
      border: 1px solid rgba(197, 160, 89, 0.4);
      color: #FFFFFF;
      display: grid;
      place-items: center;
      cursor: pointer;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
      flex-shrink: 0;
      transition: all .2s ease;
    }
    .reel-sound-toggle-btn:hover {
      border-color: #F8D888;
      color: #F8D888;
      transform: scale(1.05);
    }

    /* Mobile (iPhone / Mobile screen <= 540px) */
    @media (max-width: 540px) {
      .reel-top-bar {
        gap: 6px;
        padding: max(10px, env(safe-area-inset-top)) 10px 10px;
      }
      .reel-back-btn .btn-text {
        display: none;
      }
      .reel-back-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 50%;
      }
      .reel-post-btn .btn-text {
        display: none;
      }
      .reel-post-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 50%;
      }
      .reel-search-form {
        max-width: none;
        flex: 1;
      }
      .reel-search-wrap {
        height: 34px;
        padding: 0 10px;
      }
      .reel-search-input {
        font-size: 12px;
      }
      .reel-sound-toggle-btn {
        width: 34px;
        height: 34px;
      }
    }

    /* Kasa Reels Right Action Sidebar */
    .reel-actions-col {
      position: absolute;
      right: 14px;
      bottom: 100px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      z-index: 10;
      pointer-events: auto;
    }

    .reel-action-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
      padding: 0;
      font-family: 'Prompt', -apple-system, sans-serif !important;
      transition: transform .22s cubic-bezier(0.34, 1.56, 0.64, 1);
      text-decoration: none;
      width: 48px;
    }
    .reel-action-btn:hover {
      transform: scale(1.08);
    }
    .reel-action-btn:active { transform: scale(0.92); }
    .reel-action-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.45); /* Soft, blurred, seamless glass */
      border: 1px solid rgba(255, 255, 255, 0.22);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      display: grid;
      place-items: center;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
      transition: all .2s;
      color: #ffffff;
    }
    .reel-action-btn:hover .reel-action-icon {
      background: rgba(11, 28, 51, 0.85);
      border-color: #F8D888;
      color: #F8D888;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
    }
    .reel-action-label {
      font-size: 11.5px;
      font-weight: 700;
      color: #ffffff;
      text-shadow: 0 1px 4px rgba(0, 0, 0, 0.95);
      letter-spacing: 0.02em;
      white-space: nowrap;
      text-align: center;
    }

    /* Bottom CTA Buttons Row - Elegant Glassmorphism & High-end Harmony */
    .reel-cta-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    /* Primary Call Button: Crisp White Pill with Navy Icon & Text (Ultra Clear, High Contrast, Zero Glare) */
    .reel-btn-call {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 15px;
      border-radius: 999px;
      background: #ffffff;
      color: #0B1C33;
      font-size: 12px;
      font-weight: 850;
      text-decoration: none;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
      border: 1px solid rgba(255, 255, 255, 0.9);
      transition: all .22s cubic-bezier(0.34, 1.56, 0.64, 1);
      white-space: nowrap;
    }
    .reel-btn-call svg {
      color: #059669; /* Soft emerald accent on the phone icon */
    }
    .reel-btn-call:hover {
      transform: translateY(-2px) scale(1.06);
      background: #f8fafc;
      box-shadow: 0 6px 20px rgba(255, 255, 255, 0.45);
      color: #0B1C33;
    }
    .reel-btn-call:active { transform: scale(0.96); }

    /* Map Navigation Button: Frosted Glass Pill with Subtle Sky Accent (Eye-friendly, Modern) */
    .reel-btn-map {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.75);
      color: #ffffff;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.28);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      transition: all .22s cubic-bezier(0.34, 1.56, 0.64, 1);
      white-space: nowrap;
    }
    .reel-btn-map svg {
      color: #38bdf8; /* Soft cyan map pin */
    }
    .reel-btn-map:hover {
      transform: translateY(-2px) scale(1.06);
      background: rgba(30, 41, 59, 0.9);
      border-color: #38bdf8;
      color: #ffffff;
      box-shadow: 0 6px 20px rgba(56, 189, 248, 0.25);
    }
    .reel-btn-map:active { transform: scale(0.96); }

    /* Details Button: Frosted Glass with Warm Gold Accent */
    .reel-btn-details {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.75);
      border: 1px solid rgba(197, 160, 89, 0.55);
      color: #F8D888;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
      transition: all .22s cubic-bezier(0.34, 1.56, 0.64, 1);
      white-space: nowrap;
    }
    .reel-btn-details:hover {
      transform: translateY(-2px) scale(1.06);
      background: #C5A059;
      border-color: #C5A059;
      color: #0B1C33;
      box-shadow: 0 6px 20px rgba(197, 160, 89, 0.35);
    }
    .reel-btn-details:active { transform: scale(0.96); }

    /* Kasa Reels Community Comments Modal Drawer (Kasa Reels Light Sheet Style) */
    .reels-comments-drawer-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      z-index: 9998;
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
    }
    .reels-comments-drawer-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    .reels-comments-drawer,
    .reels-comments-drawer * {
      font-family: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    }

    .reels-comments-drawer {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translate(-50%, 100%);
      width: 100%;
      max-width: 480px;
      height: 72vh;
      max-height: 640px;
      background: #ffffff; /* Kasa Reels White Background */
      color: #0f1419;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-bottom: none;
      box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.35);
      z-index: 9999;
      display: flex;
      flex-direction: column;
      transition: transform .32s cubic-bezier(0.25, 1, 0.5, 1);
      overflow: hidden;
    }
    .reels-comments-drawer.active {
      transform: translate(-50%, 0);
    }

    .reels-comments-header {
      padding: 14px 20px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #edf2f7;
      background: #ffffff;
      position: relative;
    }
    /* Kasa Reels top pull handle */
    .reels-comments-handle {
      position: absolute;
      top: 6px;
      left: 50%;
      transform: translateX(-50%);
      width: 36px;
      height: 4px;
      border-radius: 999px;
      background: #e2e8f0;
    }
    .reels-comments-title {
      font-size: 14.5px;
      font-weight: 700;
      color: #0f1419;
      display: flex;
      align-items: center;
      gap: 7px;
      margin-top: 4px;
      letter-spacing: -0.01em;
    }
    .reels-comments-close {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: #f1f5f9;
      border: none;
      color: #64748b;
      font-size: 14px;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: all .2s;
    }
    .reels-comments-close:hover {
      background: #e2e8f0;
      color: #0f1419;
    }

    .reels-comments-list {
      flex: 1;
      overflow-y: auto;
      padding: 16px 20px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      background: #ffffff;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .reels-comments-list::-webkit-scrollbar {
      width: 5px;
    }
    .reels-comments-list::-webkit-scrollbar-track {
      background: transparent;
    }
    .reels-comments-list::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 999px;
    }

    .reel-comment-item {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding-bottom: 14px;
      border-bottom: 1px solid #f8fafc;
    }
    .reel-comment-item:last-child {
      border-bottom: none;
      padding-bottom: 4px;
    }
    .reel-comment-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
      color: #334155;
      font-weight: 700;
      font-size: 14px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .reel-comment-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .reel-comment-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .reel-comment-meta {
      display: flex;
      align-items: center;
      gap: 7px;
      flex-wrap: wrap;
    }
    .reel-comment-author {
      font-size: 13.5px;
      font-weight: 700;
      color: #0f1419;
      letter-spacing: -0.01em;
      line-height: 1.3;
    }
    .reel-comment-author-badge {
      font-size: 10px;
      font-weight: 700;
      color: #0284c7;
      background: #e0f2fe;
      padding: 1px 6px;
      border-radius: 4px;
    }
    .reel-comment-dot {
      color: #cbd5e1;
      font-size: 10px;
      line-height: 1;
    }
    .reel-comment-time {
      font-size: 11px;
      color: #94a3b8;
      font-weight: 400;
    }
    .reel-comment-text {
      font-size: 13.5px;
      font-weight: 400;
      color: #1e293b;
      line-height: 1.6;
      margin: 0;
      word-break: break-word;
      letter-spacing: 0.005em;
    }
    /* Clickable Link inside comment */
    .reel-comment-link {
      color: #0284c7;
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 2px;
      word-break: break-all;
      transition: color .15s;
    }
    .reel-comment-link:hover {
      color: #0369a1;
      text-decoration: none;
    }
    /* Attached Photo in comment */
    .reel-comment-photo-wrap {
      margin-top: 8px;
      border-radius: 12px;
      overflow: hidden;
      width: fit-content;
      max-width: 220px;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .reel-comment-photo-wrap a {
      display: block;
      line-height: 0;
    }
    .reel-comment-photo-wrap img {
      width: auto;
      max-width: 100%;
      height: auto;
      max-height: 180px;
      object-fit: contain;
      display: block;
      cursor: zoom-in;
      transition: transform .22s ease;
      background: #fff;
    }
    .reel-comment-photo-wrap img:hover {
      transform: scale(1.02);
    }

    .reels-comments-empty {
      text-align: center;
      padding: 50px 20px;
      color: #94a3b8;
    }
    .reels-comments-empty svg {
      width: 44px;
      height: 44px;
      margin-bottom: 8px;
      color: #cbd5e1;
    }

    /* Kasa Reels Bottom Sticky Comment Bar (Clean White) */
    .reels-comments-footer {
      padding: 10px 14px;
      background: #ffffff;
      border-top: 1px solid #f1f5f9;
      display: flex;
      flex-direction: column;
      gap: 8px;
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.04);
    }
    .reels-user-indicator {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 11.5px;
      color: #64748b;
    }
    .reels-user-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 700;
      color: #0f1419;
    }
    .reels-user-chip-avatar {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #eee8fa;
      color: #6044a6;
      font-size: 10px;
      font-weight: 900;
      display: grid;
      place-items: center;
      overflow: hidden;
    }
    .reels-user-chip-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .reels-comment-input-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    /* Photo attachment button */
    .reels-attach-photo-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      color: #475569;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: all .2s;
      flex-shrink: 0;
    }
    .reels-attach-photo-btn:hover {
      background: #e2e8f0;
      color: #0B1C33;
      transform: scale(1.05);
    }
    .reels-attach-photo-btn.has-file {
      background: #e0f2fe;
      color: #0284c7;
      border-color: #38bdf8;
    }
    .reels-comment-input {
      flex: 1;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      border-radius: 999px;
      padding: 9px 16px;
      font-size: 13.5px;
      font-weight: 500;
      color: #0f1419;
      outline: none;
      font-family: inherit;
      transition: all .2s;
    }
    .reels-comment-input:focus {
      background: #ffffff;
      border-color: #C5A059;
      box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.18);
    }
    .reels-comment-input::placeholder {
      color: #94a3b8;
    }
    .reels-comment-send-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0B1C33, #172D4D);
      border: none;
      color: #F8D888;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: transform .18s, background .18s;
      flex-shrink: 0;
    }
    .reels-comment-send-btn:hover {
      background: #C5A059;
      color: #0B1C33;
      transform: scale(1.08);
    }
    .reels-comment-send-btn:active { transform: scale(0.92); }

    /* Photo Upload Preview Chip */
    .reels-photo-preview-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 4px 8px;
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 10px;
      font-size: 11.5px;
      color: #334155;
    }
    .reels-photo-preview-thumb {
      width: 28px;
      height: 28px;
      border-radius: 6px;
      object-fit: cover;
      border: 1px solid #e2e8f0;
    }
    .reels-photo-remove-btn {
      margin-left: auto;
      background: none;
      border: none;
      color: #ef4444;
      font-size: 12px;
      font-weight: 800;
      cursor: pointer;
      padding: 2px 6px;
    }

    .reels-author-input {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      font-size: 11px;
      color: #0f1419;
      padding: 3px 8px;
      outline: none;
      font-family: inherit;
      width: 140px;
    }

    /* Specific Action Highlights */
    .reel-action-btn.is-fav .reel-action-icon {
      color: #ef4444;
      background: #fff;
      border-color: #ef4444;
    }
    .reel-action-btn.line-btn .reel-action-icon {
      background: #06c755;
      border-color: #06c755;
      color: #fff;
    }
    .reel-action-btn.call-btn .reel-action-icon {
      background: #C5A059;
      border-color: #F8D888;
      color: #0B1C33;
    }

    /* Bottom Info Card - Seamless Full-Width Vignette (No Vertical Seams) */
    .reel-bottom-card {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      width: 100%;
      padding: 36px 16px env(safe-area-inset-bottom, 20px);
      padding-right: 76px; /* Keeps text gracefully away from right action icons */
      padding-bottom: max(20px, env(safe-area-inset-bottom));
      z-index: 9;
      background: linear-gradient(0deg, rgba(0,0,0,0.92) 0%, rgba(0,0,0,0.65) 50%, rgba(0,0,0,0) 100%);
      pointer-events: none;
    }
    .reel-bottom-card > * {
      pointer-events: auto;
    }

    .reel-badges-row {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 6px;
      flex-wrap: wrap;
    }
    .reel-tag-type {
      padding: 2.5px 8px;
      border-radius: 6px;
      font-size: 10.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .05em;
      background: #15803d;
      color: #fff;
    }
    .reel-tag-type.rent { background: #0284c7; }
    .reel-tag-cat {
      padding: 2.5px 8px;
      border-radius: 6px;
      font-size: 10.5px;
      font-weight: 800;
      background: rgba(255,255,255,0.2);
      backdrop-filter: blur(4px);
      color: #fff;
    }

    .reel-price {
      font-size: clamp(20px, 3.5vw, 26px);
      font-weight: 900;
      color: #F8D888;
      margin: 4px 0 2px;
      text-shadow: 0 2px 10px rgba(0,0,0,0.6);
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .reel-price-orig {
      font-size: 13px;
      color: rgba(255,255,255,0.6);
      text-decoration: line-through;
      font-weight: 600;
    }
    /* Eye-catching Discount Tag Badge */
    .reel-discount-badge {
      display: inline-flex;
      align-items: center;
      gap: 3.5px;
      padding: 2.5px 8px;
      border-radius: 6px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #ffffff;
      font-size: 11px;
      font-weight: 850;
      letter-spacing: 0.02em;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.35);
      animation: discountPulse 2.2s infinite ease-in-out;
    }
    @keyframes discountPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    /* In-place Expandable Caption (Kasa Reels Native Style - Video is 100% visible behind) */
    .reel-caption-wrap {
      margin: 4px 0 6px;
      position: relative;
    }
    .reel-caption-title {
      font-size: 15px;
      font-weight: 700;
      color: #ffffff;
      line-height: 1.45;
      text-shadow: 0 2px 6px rgba(0,0,0,0.9);
      letter-spacing: 0.01em;
      margin-bottom: 2px;
      word-break: break-word;
      overflow-wrap: anywhere;
    }
    .reel-caption-desc {
      font-size: 13.5px;
      font-weight: 400;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.95);
      text-shadow: 0 2px 6px rgba(0,0,0,0.9);
      word-break: break-word;
      overflow-wrap: anywhere;
      letter-spacing: 0.01em;
      transition: all .25s ease;
    }
    /* When collapsed: truncated neatly to 1-2 lines on the video background */
    .reel-caption-desc.is-collapsed {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    /* When expanded: stays completely right here on the video background with smooth scroll */
    .reel-caption-desc.is-expanded {
      display: block;
      max-height: 140px;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.4) transparent;
      padding-right: 4px;
      margin-top: 4px;
      color: #ffffff;
    }
    .reel-caption-desc.is-expanded::-webkit-scrollbar {
      width: 4px;
    }
    .reel-caption-desc.is-expanded::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.4);
      border-radius: 999px;
    }
    .reel-more-toggle {
      background: none;
      border: none;
      padding: 0;
      color: #F8D888;
      font-size: 12.5px;
      font-weight: 800;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 3px;
      margin-top: 3px;
      text-shadow: 0 1px 4px rgba(0,0,0,0.95);
      font-family: inherit;
      transition: color .15s;
    }
    .reel-more-toggle:hover {
      color: #ffffff;
      text-decoration: underline;
    }

    .reel-loc {
      font-size: 12px;
      color: #cbd5e1;
      display: flex;
      align-items: center;
      gap: 4px;
      text-shadow: 0 1px 4px rgba(0,0,0,0.8);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Desktop Navigation Controls on side */
    .desktop-nav-hint {
      display: none;
      position: absolute;
      right: 28px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 10;
      flex-direction: column;
      gap: 12px;
    }
    @media (min-width: 900px) {
      .desktop-nav-hint { display: flex; }
    }
    .desktop-arrow-btn {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(11,28,51,0.8);
      border: 1px solid rgba(197,160,89,0.5);
      color: #F8D888;
      display: grid;
      place-items: center;
      cursor: pointer;
      font-size: 20px;
      transition: all .2s;
      box-shadow: 0 4px 14px rgba(0,0,0,0.5);
    }
    .desktop-arrow-btn:hover {
      background: #C5A059;
      color: #0B1C33;
      transform: scale(1.1);
    }

    /* Center Play/Pause Ripple Indicator */
    .center-play-ripple {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.6);
      width: 68px;
      height: 68px;
      border-radius: 50%;
      background: rgba(11,28,51,0.85);
      border: 2px solid #F8D888;
      color: #F8D888;
      display: grid;
      place-items: center;
      z-index: 15;
      pointer-events: none;
      opacity: 0;
      transition: opacity .2s, transform .2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .center-play-ripple.active {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }
  </style>
</head>
<body>

<?php if (!empty($flash)): ?>
  <div id="reelsFlashNotice" style="position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:99999; background:linear-gradient(135deg,#0B1C33,#152E4D); border:1.5px solid #F8D888; color:#ffffff; padding:12px 24px; border-radius:999px; font-weight:800; font-size:13.5px; box-shadow:0 10px 30px rgba(0,0,0,0.7); display:flex; align-items:center; gap:8px; animation:reelsSlideDown .4s ease;">
    <span><?= e($flash) ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#F8D888; font-size:16px; cursor:pointer; padding:0 0 0 6px;">✕</button>
  </div>
  <style>
    @keyframes reelsSlideDown {
      from { opacity:0; transform:translate(-50%, -20px); }
      to { opacity:1; transform:translate(-50%, 0); }
    }
  </style>
  <script>
    setTimeout(() => {
      const el = document.getElementById('reelsFlashNotice');
      if (el) {
        el.style.transition = 'opacity .5s, transform .5s';
        el.style.opacity = '0';
        el.style.transform = 'translate(-50%, -20px)';
        setTimeout(() => el.remove(), 500);
      }
    }, 4500);
  </script>
<?php endif; ?>

<?php if (empty($reels)): ?>
  <div style="height:100vh; display:grid; place-content:center; text-align:center; padding:20px;">
    <div style="font-size:50px; margin-bottom:12px;">🎬</div>
    <h2 style="font-size:22px; margin:0 0 8px;"><?= e(t('ยังไม่มีคลิปวิดีโอพาทัวร์ในขณะนี้', 'No Property Reels available yet')) ?></h2>
    <p style="color:#94a3b8; font-size:14px; margin:0 0 20px;"><?= e(t('ผู้ลงประกาศสามารถเพิ่มคลิปวิดีโอได้ในหน้าลงประกาศทรัพย์สิน', 'Listers can attach video clips in property submit form')) ?></p>
    <a href="index.php" style="display:inline-block; padding:10px 24px; border-radius:999px; background:#C5A059; color:#0B1C33; font-weight:800; text-decoration:none;">← <?= e(t('กลับสู่หน้าหลัก', 'Back to Home')) ?></a>
  </div>
<?php else: ?>

  <!-- Desktop Up/Down Navigation Floating Arrows -->
  <div class="desktop-nav-hint">
    <button type="button" class="desktop-arrow-btn" id="desktopPrevBtn" title="<?= e(t('คลิปก่อนหน้า (ลูกศรขึ้น)', 'Previous Reel (Up Arrow)')) ?>">▲</button>
    <button type="button" class="desktop-arrow-btn" id="desktopNextBtn" title="<?= e(t('คลิปถัดไป (ลูกศรลง)', 'Next Reel (Down Arrow)')) ?>">▼</button>
  </div>

  <!-- Reels Main Vertical Swiper Viewport -->
  <main class="reels-viewport" id="reelsViewport">
    <?php foreach ($reels as $index => $item):
      $videoFilename = basename((string)$item['video_file']);
      $videoUrl = 'uploads/' . rawurlencode($videoFilename);
      $coverFilename = basename((string)($item['cover'] ?? ''));
      $coverUrl = ($coverFilename && is_file(__DIR__ . '/uploads/' . $coverFilename)) ? 'uploads/' . rawurlencode($coverFilename) : '';
      $origPrice = (!empty($item['original_price']) && (int)$item['original_price'] > (int)$item['price']) ? (int)$item['original_price'] : null;
      $priceText = number_format((int)$item['price']) . ($item['type'] === 'rent' ? ' ' . t('บ./ด.', 'THB/mo') : ' ' . t('บาท', 'THB'));
    ?>
      <section class="reel-slide" data-reel-index="<?= $index ?>" data-reel-id="<?= (int)$item['id'] ?>">
        <!-- Background Ambient Blur (Desktop) -->
        <div class="reel-backdrop" style="background-image: url('<?= e($coverUrl ?: 'images/Gemini_Generated_Image_r1f2n5r1f2n5r1f2.jpg') ?>');"></div>

        <!-- Main Reels Video Player Container -->
        <div class="reel-video-container">
          <!-- Top Floating Bar -->
          <div class="reel-top-bar">
            <div class="reel-top-left-actions">
              <a href="index.php" class="reel-back-btn" title="<?= e(t('กลับหน้าหลัก', 'Back to Home')) ?>">
                <span>←</span>
                <span class="btn-text"><?= e(t('หน้าหลัก', 'Home')) ?></span>
              </a>
              <a href="submit-reel.php" class="reel-post-btn" title="<?= e(t('โพสต์คลิป Reels ของคุณด่วน', 'Post Your Reel')) ?>">
                <span>➕</span>
                <span class="btn-text"><?= e(t('โพสต์คลิป', 'Post Reel')) ?></span>
              </a>
            </div>

            <!-- Search Form (ช่องค้นหาสวยงาม เข้าถึงง่าย รองรับ iPhone, iPad, คอม, มือถือ) -->
            <form action="reels.php" method="get" class="reel-search-form" onsubmit="if(!this.q.value.trim()){return false;}">
              <div class="reel-search-wrap">
                <button type="submit" class="reel-search-btn-submit" title="<?= e(t('ค้นหาคลิป', 'Search')) ?>" aria-label="<?= e(t('ค้นหาคลิป', 'Search')) ?>">
                  <svg class="reel-search-icon" viewBox="0 0 24 24"><path d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.39a1 1 0 0 1-1.42 1.42l-5.38-5.39A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/></svg>
                </button>
                <input type="text" name="q" class="reel-search-input" value="<?= e($reelsSearchQuery) ?>" placeholder="<?= e(t('ค้นหาคลิป Reels...', 'Search reels...')) ?>" autocomplete="off">
                <?php if ($reelsSearchQuery !== ''): ?>
                  <a href="reels.php" class="reel-search-clear" title="<?= e(t('ล้างการค้นหา', 'Clear')) ?>">✕</a>
                <?php endif; ?>
              </div>
            </form>

            <button type="button" class="reel-sound-toggle-btn" title="<?= e(t('เปิด/ปิดเสียง', 'Toggle sound')) ?>">
              <svg class="sound-icon-unmuted" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
              <svg class="sound-icon-muted" style="display:none;" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
            </button>
          </div>

          <!-- Video Stream -->
          <video class="reel-video"
                 src="<?= e($videoUrl) ?>"
                 loop
                 playsinline
                 preload="auto"
                 poster="<?= e($coverUrl) ?>"></video>

          <!-- Center Play/Pause Ripple Indicator -->
          <div class="center-play-ripple" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" style="margin-left: 3px;"><path d="M8 5v14l11-7z"/></svg>
          </div>

          <!-- Kasa Reels Right Action Buttons Column -->
          <div class="reel-actions-col">
            <!-- Save / Heart Button -->
            <button type="button" class="reel-action-btn" data-fav-toggle data-fav-id="<?= (int)$item['id'] ?>" title="<?= e(t('บันทึกในรายการที่ชอบ', 'Save to favorites')) ?>">
              <span class="reel-action-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
              </span>
              <span class="reel-action-label"><?= e(t('ใจ', 'Like')) ?></span>
            </button>

            <!-- Kasa Reels Community Comments Button -->
            <button type="button" class="reel-action-btn reel-comment-trigger-btn" data-property-id="<?= (int)$item['id'] ?>" data-property-title="<?= e($item['title']) ?>" title="<?= e(t('ความคิดเห็นชุมชน', 'Community Comments')) ?>">
              <span class="reel-action-icon">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              </span>
              <span class="reel-action-label" data-count-label><?= (int)($item['comments_count'] ?? 0) ?></span>
            </button>

            <!-- LINE Contact Button -->
            <?php if (!empty($item['line_id'])): ?>
              <a href="https://line.me/R/ti/p/~<?= rawurlencode($item['line_id']) ?>" target="_blank" rel="noopener noreferrer" class="reel-action-btn line-btn" title="<?= e(t('ทัก LINE ผู้ขาย', 'Chat via LINE')) ?>">
                <span class="reel-action-icon">
                  <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M21.5 10.7c0-4.8-4.7-8.7-10.5-8.7s-10.5 3.9-10.5 8.7c0 4.3 3.8 7.9 8.9 8.6.3.1.8.2 1 .7.1.3.1.7 0 1l-.4 1.7c-.1.5-.4 1.7.7 1 1.2-.8 6.4-3.8 8.8-6.5 1.4-1.6 2-3.4 2-4.8z"/></svg>
                </span>
                <span class="reel-action-label">LINE</span>
              </a>
            <?php endif; ?>

            <!-- Share Link Button -->
            <button type="button" class="reel-action-btn reel-share-btn" data-share-url="property.php?id=<?= (int)$item['id'] ?>" data-share-title="<?= e($item['title']) ?>" title="<?= e(t('แชร์คลิปนี้', 'Share')) ?>">
              <span class="reel-action-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.5 11 7.8-4.7M8.5 13.2l7.7 4.5"/></svg>
              </span>
              <span class="reel-action-label"><?= e(t('แชร์', 'Share')) ?></span>
            </button>
          </div>

          <!-- Bottom Floating Information Card -->
          <div class="reel-bottom-card">
            <div class="reel-badges-row">
              <span class="reel-tag-type <?= $item['type'] === 'rent' ? 'rent' : '' ?>">
                <?= e(is_en() ? ($item['type'] === 'rent' ? 'For Rent' : 'For Sale') : type_label($item['type'])) ?>
              </span>
              <span class="reel-tag-cat">
                <?= e(category_label_bi($item['property_category'])) ?>
              </span>
            </div>

            <div class="reel-price">
              <span><?= e($priceText) ?></span>
              <?php if ($origPrice): ?>
                <span class="reel-price-orig">฿<?= number_format($origPrice) ?></span>
                <?php
                  $diff = $origPrice - (int)$item['price'];
                  $discountPct = round(($diff / $origPrice) * 100);
                ?>
                <span class="reel-discount-badge">
                  <span>🔥</span>
                  <span><?= e(t('ลด ฿' . number_format($diff) . ' (-' . $discountPct . '%)', 'Save ฿' . number_format($diff) . ' (-' . $discountPct . '%)')) ?></span>
                </span>
              <?php endif; ?>
            </div>

            <div class="reel-caption-wrap">
              <?php
                $hasDesc = !empty($item['description']);
              ?>
              <div class="reel-caption-title"><?= e($item['title']) ?></div>
              <?php if ($hasDesc): ?>
                <div class="reel-caption-desc is-collapsed" data-caption-desc>
                  <?= nl2br(e($item['description'])) ?>
                </div>
                <button type="button" class="reel-more-toggle" data-more-toggle>
                  <span class="more-label">...<?= e(t('ดูเพิ่มเติม', 'more')) ?></span>
                  <span class="less-label" style="display:none;"><?= e(t('▲ ย่อลง', '▲ less')) ?></span>
                </button>
              <?php endif; ?>
            </div>

            <div class="reel-loc">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
              <span><?= e($item['location']) ?></span>
            </div>

            <!-- Distinct Call, Map & Details Action Buttons at bottom -->
            <div class="reel-cta-row">
              <?php if (!empty($item['phone'])): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $item['phone'])) ?>" class="reel-btn-call" title="<?= e(t('โทรสอบถามทันที', 'Call Now')) ?>">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                  <span><?= e(t('โทรสอบถาม', 'Call Lister')) ?></span>
                </a>
              <?php endif; ?>

              <?php
                $hasMapLink = ($item['lat'] !== null && $item['lng'] !== null && is_numeric($item['lat']) && is_numeric($item['lng']));
                $mapUrl = $hasMapLink ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($item['lat'] . ',' . $item['lng']) : '';
              ?>
              <?php if ($hasMapLink): ?>
                <a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer" class="reel-btn-map" title="<?= e(t('ดูพิกัดแผนที่บน Google Maps', 'View Location on Google Maps')) ?>">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span><?= e(t('ดูแผนที่', 'View Map')) ?></span>
                </a>
              <?php endif; ?>

              <a href="property.php?id=<?= (int)$item['id'] ?>" class="reel-btn-details" title="<?= e(t('ดูรายละเอียดเต็มของประกาศนี้', 'View Details')) ?>">
                <span><?= e(t('ดูรายละเอียด', 'View Details')) ?></span>
                <span>›</span>
              </a>
            </div>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
<?php endif; ?>

<!-- Kasa Reels Community Comments Modal Drawer -->
<div class="reels-comments-drawer-overlay" id="commentsDrawerOverlay"></div>
<aside class="reels-comments-drawer" id="commentsDrawer" aria-hidden="true">
  <div class="reels-comments-header">
    <div class="reels-comments-handle" aria-hidden="true"></div>
    <div class="reels-comments-title">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      <span><?= e(t('ความคิดเห็นชุมชน', 'Comments')) ?></span>
      <span id="commentsHeaderCount" style="color:#64748b; font-size:12.5px; font-weight:700;">(0)</span>
    </div>
    <button type="button" class="reels-comments-close" id="commentsDrawerClose" aria-label="<?= e(t('ปิด', 'Close')) ?>">✕</button>
  </div>

  <div class="reels-comments-list" id="commentsList">
    <div class="reels-comments-empty" id="commentsEmpty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <p style="margin:0; font-size:13px;"><?= e(t('ยังไม่มีความคิดเห็น เป็นคนแรกที่พูดคุยหรือสอบถามเกี่ยวกับทรัพย์นี้!', 'No comments yet. Be the first to start the conversation!')) ?></p>
    </div>
  </div>

  <form class="reels-comments-footer" id="commentPostForm">
    <?php if ($currentUser): ?>
      <?php
        $userDisplayName = trim((string)($currentUser['full_name'] ?? '')) ?: $currentUser['username'];
        $userAvatar = basename((string)($currentUser['avatar_path'] ?? ''));
        $userAvatarExists = $userAvatar !== '' && is_file(__DIR__ . '/uploads/avatars/' . $userAvatar);
        $userInitial = mb_strtoupper(mb_substr($userDisplayName, 0, 1));
      ?>
      <div class="reels-user-indicator">
        <span class="reels-user-chip">
          <span class="reels-user-chip-avatar">
            <?php if ($userAvatarExists): ?>
              <img src="uploads/avatars/<?= e($userAvatar) ?>" alt="<?= e($userDisplayName) ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?= e($userInitial) ?>
            <?php endif; ?>
          </span>
          <span><?= e($userDisplayName) ?></span>
          <?php if (($currentUser['agent_status'] ?? '') === 'verified'): ?>
            <span style="color:#0284c7; font-size:11px;" title="Agent รับรอง">✓</span>
          <?php endif; ?>
        </span>
        <span style="font-size:11px; color:#15803d; font-weight:700;">● <?= e(t('เชื่อมต่อบัญชีแล้ว', 'Logged in')) ?></span>
      </div>
      <input type="hidden" id="commentAuthorName" value="<?= e($userDisplayName) ?>">
    <?php else: ?>
      <div class="reels-user-indicator">
        <span style="display:flex; align-items:center; gap:6px;">
          <span>👤 <?= e(t('ชื่อของคุณ:', 'Your Name:')) ?></span>
          <input type="text" id="commentAuthorName" class="reels-author-input" maxlength="60" placeholder="<?= e(t('ระบุชื่อของคุณ', 'Your name')) ?>">
        </span>
        <a href="login.php?return_to=reels.php" style="color:#C5A059; text-decoration:none; font-weight:700;"><?= e(t('เข้าสู่ระบบ ↗', 'Login ↗')) ?></a>
      </div>
    <?php endif; ?>

    <!-- Photo Upload Preview Bar (Hidden by default) -->
    <div id="commentPhotoPreviewBar" class="reels-photo-preview-bar" style="display:none;">
      <img id="commentPhotoPreviewImg" class="reels-photo-preview-thumb" src="" alt="preview">
      <span id="commentPhotoPreviewName" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px; font-weight:600;"></span>
      <button type="button" id="commentPhotoRemoveBtn" class="reels-photo-remove-btn" title="<?= e(t('ยกเลิกรูปนี้', 'Remove photo')) ?>">✕ ยกเลิก</button>
    </div>

    <div class="reels-comment-input-row">
      <label class="reels-attach-photo-btn" id="commentAttachLabel" title="<?= e(t('แนบรูปภาพ (JPG, PNG, WebP)', 'Attach photo')) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <input type="file" id="commentImageFile" name="comment_image" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
      </label>
      <input type="text" id="commentInputText" class="reels-comment-input" maxlength="800" placeholder="<?= e(t('พิมพ์ข้อความ, ส่งลิงก์ หรือแนบรูป...', 'Type a message, paste a link or photo...')) ?>" autocomplete="off">
      <button type="submit" class="reels-comment-send-btn" title="<?= e(t('ส่งความคิดเห็น', 'Send comment')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-left:2px;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
  </form>
</aside>

<script>
(() => {
  const viewport = document.getElementById('reelsViewport');
  if (!viewport) return;

  const slides = Array.from(document.querySelectorAll('.reel-slide'));
  let currentActiveIndex = 0;
  // ค่าเริ่มต้นพยายามเปิดเสียง (Unmuted) เสมอตามความต้องการของผู้ใช้
  let isGlobalMuted = false;

  const updateSoundIcon = (muted) => {
    document.querySelectorAll('.reel-sound-toggle-btn').forEach(btn => {
      const onIcon = btn.querySelector('.sound-icon-unmuted');
      const offIcon = btn.querySelector('.sound-icon-muted');
      if (onIcon) onIcon.style.display = muted ? 'none' : 'block';
      if (offIcon) offIcon.style.display = muted ? 'block' : 'none';
    });
  };
  updateSoundIcon(isGlobalMuted);

  // ปลดล็อกเสียงทันทีเมื่อผู้ใช้แตะหรือคลิกหน้าจอครั้งแรก (Autoplay Unmute on first user touch/click)
  const unlockAudioOnFirstInteraction = () => {
    if (isGlobalMuted) return;
    const activeVideo = slides[currentActiveIndex]?.querySelector('video.reel-video');
    if (activeVideo && activeVideo.muted) {
      activeVideo.muted = false;
      activeVideo.volume = 1.0;
      activeVideo.play().catch(() => {});
      updateSoundIcon(false);
    }
  };
  window.addEventListener('click', unlockAudioOnFirstInteraction, { once: true, passive: true });
  window.addEventListener('touchstart', unlockAudioOnFirstInteraction, { once: true, passive: true });
  window.addEventListener('keydown', unlockAudioOnFirstInteraction, { once: true, passive: true });

  // ระบบนับยอดวิวคลิป Reels แบบป้องกันการสแปมและป้องกัน Bug รีเฟรชหน้าจอ (Anti-Refresh Spam)
  let trackedReelsInSession = new Set();
  try {
    const saved = JSON.parse(sessionStorage.getItem('kasa_reels_viewed') || '[]');
    if (Array.isArray(saved)) trackedReelsInSession = new Set(saved);
  } catch (e) {}

  let reelWatchTimer = null;
  const trackReelView = (slide) => {
    if (!slide) return;
    const propId = parseInt(slide.getAttribute('data-reel-id'), 10);
    if (!propId || trackedReelsInSession.has(propId)) return;

    if (reelWatchTimer) clearTimeout(reelWatchTimer);
    // ต้องดูคลิปจริงอย่างน้อย 2.5 วินาที จึงจะบันทึกวิว (ป้องกันการเลื่อนผ่านเร็วๆ)
    reelWatchTimer = setTimeout(() => {
      if (trackedReelsInSession.has(propId)) return;
      trackedReelsInSession.add(propId);
      try {
        sessionStorage.setItem('kasa_reels_viewed', JSON.stringify(Array.from(trackedReelsInSession)));
      } catch (e) {}

      const payload = JSON.stringify({ event: 'property', property_id: propId });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('api/track.php', payload);
      } else {
        fetch('api/track.php', { method: 'POST', body: payload, keepalive: true }).catch(() => {});
      }
    }, 2500);
  };

  const playSlide = (index) => {
    slides.forEach((slide, i) => {
      const v = slide.querySelector('video.reel-video');
      if (!v) return;

      if (i === index) {
        v.volume = 1.0;
        v.muted = isGlobalMuted;
        const playPromise = v.play();
        if (playPromise !== undefined) {
          playPromise.then(() => {
            // เมื่อเล่นสำเร็จและสถานะเปิดเสียงอยู่ ให้แน่ใจว่าไอคอนตรงกับเสียงที่ดัง
            if (!v.muted) {
              isGlobalMuted = false;
              updateSoundIcon(false);
            }
          }).catch(() => {
            // หาก Browser (Safari / Chrome) มีนโยบาย Autoplay Policy บล็อกเสียงในครั้งแรกที่ยังไม่ได้คลิก
            // ให้เล่นภาพต่อทันทีแบบ muted ชั่วคราว และรอปลดเสียงเมื่อผู้ใช้แตะหน้าจอ
            v.muted = true;
            updateSoundIcon(true);
            v.play().catch(() => {});
          });
        }
        trackReelView(slide);
      } else {
        v.pause();
        v.currentTime = 0;
      }
    });
  };

  // Click on video to toggle play/pause with ripple
  slides.forEach(slide => {
    const v = slide.querySelector('video.reel-video');
    const ripple = slide.querySelector('.center-play-ripple');
    if (!v) return;

    v.addEventListener('click', () => {
      if (v.paused) {
        v.play().catch(() => {});
        if (ripple) {
          ripple.classList.remove('active');
        }
      } else {
        v.pause();
        if (ripple) {
          ripple.classList.add('active');
          setTimeout(() => ripple.classList.remove('active'), 800);
        }
      }
    });
  });

  // Sound Toggle Buttons
  document.querySelectorAll('.reel-sound-toggle-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      isGlobalMuted = !isGlobalMuted;
      updateSoundIcon(isGlobalMuted);
      const activeVideo = slides[currentActiveIndex]?.querySelector('video.reel-video');
      if (activeVideo) {
        activeVideo.muted = isGlobalMuted;
        activeVideo.volume = 1.0;
      }
    });
  });

  // IntersectionObserver to auto-play whichever slide is snapped into center
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
        const idx = parseInt(entry.target.getAttribute('data-reel-index'), 10);
        if (!isNaN(idx)) {
          currentActiveIndex = idx;
          playSlide(idx);
        }
      }
    });
  }, { threshold: [0.6] });

  slides.forEach(slide => observer.observe(slide));

  // Desktop Up / Down Arrows
  const prevBtn = document.getElementById('desktopPrevBtn');
  const nextBtn = document.getElementById('desktopNextBtn');

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      const prevIdx = Math.max(0, currentActiveIndex - 1);
      slides[prevIdx]?.scrollIntoView({ behavior: 'smooth' });
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const nextIdx = Math.min(slides.length - 1, currentActiveIndex + 1);
      slides[nextIdx]?.scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Keyboard navigation (Arrow Up, Arrow Down, Space to pause)
  window.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      const nextIdx = Math.min(slides.length - 1, currentActiveIndex + 1);
      slides[nextIdx]?.scrollIntoView({ behavior: 'smooth' });
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      const prevIdx = Math.max(0, currentActiveIndex - 1);
      slides[prevIdx]?.scrollIntoView({ behavior: 'smooth' });
    } else if (e.key === ' ' || e.code === 'Space') {
      e.preventDefault();
      const currentVideo = slides[currentActiveIndex]?.querySelector('video.reel-video');
      if (currentVideo) {
        if (currentVideo.paused) currentVideo.play().catch(() => {});
        else currentVideo.pause();
      }
    } else if (e.key === 'm' || e.key === 'M') {
      isGlobalMuted = !isGlobalMuted;
      updateSoundIcon(isGlobalMuted);
      const activeVideo = slides[currentActiveIndex]?.querySelector('video.reel-video');
      if (activeVideo) activeVideo.muted = isGlobalMuted;
    }
  });

  // Share button
  document.querySelectorAll('.reel-share-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const url = window.location.origin + window.location.pathname.replace('reels.php', '') + btn.getAttribute('data-share-url');
      const title = btn.getAttribute('data-share-title');
      if (navigator.share) {
        try { await navigator.share({ title, url }); return; } catch(err) {}
      }
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(url);
        alert('<?= e(t('คัดลอกลิงก์ประกาศเรียบร้อยแล้ว!', 'Listing link copied!')) ?>');
      }
    });
  });

  // Favorite Heart Toggle in Reels
  document.querySelectorAll('[data-fav-toggle]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const id = btn.getAttribute('data-fav-id');
      const parentBtn = btn.closest('.reel-action-btn');
      parentBtn?.classList.toggle('is-fav');
    });
  });

  // Kasa Reels Community Comments Drawer Logic
  const commentsDrawer = document.getElementById('commentsDrawer');
  const commentsOverlay = document.getElementById('commentsDrawerOverlay');
  const commentsClose = document.getElementById('commentsDrawerClose');
  const commentsList = document.getElementById('commentsList');
  const commentsEmpty = document.getElementById('commentsEmpty');
  const commentsHeaderCount = document.getElementById('commentsHeaderCount');
  const commentForm = document.getElementById('commentPostForm');
  const commentInputText = document.getElementById('commentInputText');
  const commentAuthorName = document.getElementById('commentAuthorName');

  let activeCommentPropertyId = 0;
  let activeCommentTriggerBtn = null;

  const closeComments = () => {
    if (commentsDrawer) commentsDrawer.classList.remove('active');
    if (commentsOverlay) commentsOverlay.classList.remove('active');
    commentsDrawer?.setAttribute('aria-hidden', 'true');
  };

  const openComments = async (propId, triggerBtn) => {
    activeCommentPropertyId = propId;
    activeCommentTriggerBtn = triggerBtn;

    if (commentsDrawer) commentsDrawer.classList.add('active');
    if (commentsOverlay) commentsOverlay.classList.add('active');
    commentsDrawer?.setAttribute('aria-hidden', 'false');

    commentsList.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;">กำลังโหลดความคิดเห็น...</div>';

    try {
      const formData = new FormData();
      formData.append('action', 'get_comments');
      formData.append('property_id', propId);

      const res = await fetch('reels.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success && Array.isArray(data.comments)) {
        renderComments(data.comments);
        commentsHeaderCount.textContent = `(${data.comments.length})`;
      } else {
        commentsList.innerHTML = '<div style="text-align:center; padding:30px; color:#ef4444;">ไม่สามารถโหลดความคิดเห็นได้</div>';
      }
    } catch(err) {
      commentsList.innerHTML = '<div style="text-align:center; padding:30px; color:#ef4444;">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>';
    }
  };

  // Helper to format uploads url without corrupting subdirectories like comments/
  const formatUploadUrl = (path) => {
    if (!path) return '';
    return 'uploads/' + path.split('/').map(part => encodeURIComponent(part)).join('/');
  };

  // Helper to convert URLs in comment text into clickable styled links
  const linkify = (text) => {
    if (!text) return '';
    const escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
    const urlPattern = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
    return escaped.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener noreferrer" class="reel-comment-link">$1 ↗</a>');
  };

  // Photo Attachment Handling
  const photoInput = document.getElementById('commentImageFile');
  const photoAttachLabel = document.getElementById('commentAttachLabel');
  const photoPreviewBar = document.getElementById('commentPhotoPreviewBar');
  const photoPreviewImg = document.getElementById('commentPhotoPreviewImg');
  const photoPreviewName = document.getElementById('commentPhotoPreviewName');
  const photoRemoveBtn = document.getElementById('commentPhotoRemoveBtn');

  if (photoInput) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files[0];
      if (file) {
        if (file.size > 8 * 1024 * 1024) {
          alert('ขนาดรูปภาพต้องไม่เกิน 8 MB');
          photoInput.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
          photoPreviewImg.src = e.target.result;
          photoPreviewName.textContent = file.name;
          photoPreviewBar.style.display = 'flex';
          photoAttachLabel.classList.add('has-file');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  const clearPhotoAttachment = () => {
    if (photoInput) photoInput.value = '';
    if (photoPreviewBar) photoPreviewBar.style.display = 'none';
    if (photoPreviewImg) photoPreviewImg.src = '';
    if (photoAttachLabel) photoAttachLabel.classList.remove('has-file');
  };

  if (photoRemoveBtn) {
    photoRemoveBtn.addEventListener('click', clearPhotoAttachment);
  }

  const renderComments = (comments) => {
    if (!comments || comments.length === 0) {
      commentsList.innerHTML = `
        <div class="reels-comments-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <p style="margin:0; font-size:13.5px; font-weight:600;"><?= e(t('ยังไม่มีความคิดเห็น เป็นคนแรกที่พูดคุยหรือสอบถามเกี่ยวกับทรัพย์นี้!', 'No comments yet. Be the first to start the conversation!')) ?></p>
        </div>
      `;
      return;
    }

    commentsList.innerHTML = comments.map(c => {
      const initial = (c.author_name || 'U').charAt(0).toUpperCase();
      const avatarHtml = c.user_avatar
        ? `<img src="uploads/avatars/${encodeURIComponent(c.user_avatar)}" alt="${c.author_name}">`
        : initial;

      const photoHtml = c.image_path
        ? `<div class="reel-comment-photo-wrap"><a href="${formatUploadUrl(c.image_path)}" target="_blank" rel="noopener noreferrer"><img src="${formatUploadUrl(c.image_path)}" alt="รูปภาพแนบ" loading="lazy"></a></div>`
        : '';

      const formattedText = linkify(c.comment);

      // จัดรูปแบบเวลาให้อ่านง่าย (เช่น "10 นาทีที่แล้ว", "เมื่อสักครู่", "13:35 น.")
      const formatCommentTime = (timeStr) => {
        if (!timeStr) return 'เมื่อสักครู่';
        try {
          const d = new Date(timeStr.replace(/-/g, '/'));
          if (isNaN(d.getTime())) return timeStr;
          const diffSec = Math.floor((new Date() - d) / 1000);
          if (diffSec < 60) return 'เมื่อสักครู่';
          if (diffSec < 3600) return `${Math.floor(diffSec / 60)} นาทีที่แล้ว`;
          if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} ชม. ที่แล้ว`;
          return `${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()}`;
        } catch(e) {
          return timeStr;
        }
      };

      const timeFormatted = formatCommentTime(c.created_at);

      return `
        <div class="reel-comment-item">
          <div class="reel-comment-avatar">${avatarHtml}</div>
          <div class="reel-comment-content">
            <div class="reel-comment-meta">
              <span class="reel-comment-author">${c.author_name}</span>
              <span class="reel-comment-dot">·</span>
              <span class="reel-comment-time">${timeFormatted}</span>
            </div>
            ${formattedText ? `<p class="reel-comment-text">${formattedText}</p>` : ''}
            ${photoHtml}
          </div>
        </div>
      `;
    }).join('');
  };

  document.querySelectorAll('.reel-comment-trigger-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const propId = parseInt(btn.getAttribute('data-property-id'), 10);
      if (propId > 0) openComments(propId, btn);
    });
  });

  commentsClose?.addEventListener('click', closeComments);
  commentsOverlay?.addEventListener('click', closeComments);

  // Submit new comment via AJAX
  commentForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const commentText = commentInputText.value.trim();
    const hasPhoto = photoInput && photoInput.files && photoInput.files[0];
    if ((!commentText && !hasPhoto) || !activeCommentPropertyId) return;

    const author = commentAuthorName ? commentAuthorName.value.trim() : '';
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('property_id', activeCommentPropertyId);
    formData.append('comment', commentText);
    formData.append('author_name', author);
    if (hasPhoto) {
      formData.append('comment_image', photoInput.files[0]);
    }

    try {
      const res = await fetch('reels.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success && data.comment) {
        commentInputText.value = '';
        clearPhotoAttachment();
        const emptyNotice = commentsList.querySelector('.reels-comments-empty');
        if (emptyNotice) emptyNotice.remove();

        const initial = (data.comment.author_name || 'U').charAt(0).toUpperCase();
        const avatarHtml = data.comment.user_avatar
          ? `<img src="uploads/avatars/${encodeURIComponent(data.comment.user_avatar)}" alt="${data.comment.author_name}">`
          : initial;

        const photoHtml = data.comment.image_path
          ? `<div class="reel-comment-photo-wrap"><a href="${formatUploadUrl(data.comment.image_path)}" target="_blank" rel="noopener noreferrer"><img src="${formatUploadUrl(data.comment.image_path)}" alt="รูปภาพแนบ" loading="lazy"></a></div>`
          : '';

        const formattedText = linkify(data.comment.comment);

        const newHtml = `
          <div class="reel-comment-item" style="animation:fadeIn .25s ease;">
            <div class="reel-comment-avatar">${avatarHtml}</div>
            <div class="reel-comment-content">
              <div class="reel-comment-meta">
                <span class="reel-comment-author">${data.comment.author_name}</span>
                <span class="reel-comment-dot">·</span>
                <span class="reel-comment-time">เมื่อสักครู่</span>
              </div>
              ${formattedText ? `<p class="reel-comment-text">${formattedText}</p>` : ''}
              ${photoHtml}
            </div>
          </div>
        `;
        commentsList.insertAdjacentHTML('afterbegin', newHtml);

        // Update count label on trigger button & header
        if (data.total_count !== undefined) {
          commentsHeaderCount.textContent = `(${data.total_count})`;
          if (activeCommentTriggerBtn) {
            const countLabel = activeCommentTriggerBtn.querySelector('[data-count-label]');
            if (countLabel) countLabel.textContent = data.total_count;
          }
        }
      } else {
        alert(data.error || 'เกิดข้อผิดพลาดในการส่งข้อความ');
      }
    } catch(err) {
      alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    }
  });

  // In-place Expand/Collapse Caption (Kasa Reels Native Style - Video is direct background)
  document.querySelectorAll('[data-more-toggle]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const wrap = btn.closest('.reel-caption-wrap');
      const textEl = wrap?.querySelector('[data-caption-desc]');
      const moreLabel = btn.querySelector('.more-label');
      const lessLabel = btn.querySelector('.less-label');

      if (textEl && textEl.classList.contains('is-collapsed')) {
        textEl.classList.remove('is-collapsed');
        textEl.classList.add('is-expanded');
        if (moreLabel) moreLabel.style.display = 'none';
        if (lessLabel) lessLabel.style.display = 'inline';
      } else if (textEl) {
        textEl.classList.remove('is-expanded');
        textEl.classList.add('is-collapsed');
        if (moreLabel) moreLabel.style.display = 'inline';
        if (lessLabel) lessLabel.style.display = 'none';
      }
    });
  });

  // Start with first slide
  playSlide(0);
})();
</script>
</body>
</html>
