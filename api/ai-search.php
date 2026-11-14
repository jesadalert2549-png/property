<?php
/**
 * PropertyGO AI Deep Search — JSON API
 *
 * GET  /api/ai-search.php?q=<ข้อความ>&limit=5
 * POST /api/ai-search.php  (form: q, limit)
 *
 * ตอบกลับ JSON เท่านั้น ไม่ส่งข้อมูลส่วนตัวหรือ API Key ใด ๆ
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai_search/llm.php';
require_once __DIR__ . '/../includes/ai_search/ranker.php';

start_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── rate limit แบบเบา ๆ ต่อ session (กันการยิงถี่เกินจำเป็น) ──
$now = time();
if (!isset($_SESSION['ai_search_hits']) || !is_array($_SESSION['ai_search_hits'])) {
    $_SESSION['ai_search_hits'] = [];
}
$_SESSION['ai_search_hits'] = array_values(array_filter(
    $_SESSION['ai_search_hits'],
    static fn($ts) => is_int($ts) && $ts > $now - 60
));
if (count($_SESSION['ai_search_hits']) >= 30) {
    http_response_code(429);
    echo json_encode([
        'ok'    => false,
        'error' => t('ค้นหาถี่เกินไป กรุณารอสักครู่แล้วลองใหม่', 'Too many searches. Please wait a moment.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION['ai_search_hits'][] = $now;

$q = '';
if (request_is_post()) {
    $q = is_string($_POST['q'] ?? null) ? trim((string)$_POST['q']) : '';
} else {
    $q = is_string($_GET['q'] ?? null) ? trim((string)$_GET['q']) : '';
}
$limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 5);
$limit = max(1, min(10, $limit));

// ข้อความจำกัดความยาวเพื่อความเร็วและความปลอดภัย
if (mb_strlen($q) > 300) {
    $q = mb_substr($q, 0, 300);
}

if ($q === '') {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => t('กรุณาพิมพ์สิ่งที่คุณกำลังมองหา', 'Please describe what you are looking for.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    // 1) แปลงข้อความ => เงื่อนไข
    ['criteria' => $criteria, 'engine' => $engine] = ai_search_interpret_query($q);

    // 1.1) รวมตัวกรองจาก Quick AI Search (checkbox สิ่งอำนวยความสะดวก + ประเภท + ซื้อ/เช่า)
    // transit / quiet เป็นตัวเลือกพิเศษจาก Quick Search ที่ไม่อยู่ใน catalog
    if (!empty($_GET['am']) || !empty($_POST['am'])) {
        $rawAm = $_GET['am'] ?? $_POST['am'];
        $catalog = ai_search_amenity_catalog();
        if (is_array($rawAm)) {
            foreach ($rawAm as $amKey) {
                if (!is_string($amKey) || in_array($amKey, $criteria['amenities'], true)) {
                    continue;
                }
                if ($amKey === 'transit') {
                    $criteria['transit'] = true;
                    continue;
                }
                if ($amKey === 'quiet') {
                    if (!in_array('quiet', $criteria['soft'], true)) {
                        $criteria['soft'][] = 'quiet';
                    }
                    continue;
                }
                if (isset($catalog[$amKey])) {
                    $criteria['amenities'][] = $amKey;
                }
            }
        }
    }
    $quickCategory = $_GET['category'] ?? $_POST['category'] ?? '';
    if (is_string($quickCategory) && $quickCategory !== '' && array_key_exists($quickCategory, PROPERTY_CATEGORIES)) {
        $criteria['category'] = $quickCategory;
    }
    $quickType = $_GET['type'] ?? $_POST['type'] ?? '';
    if (is_string($quickType) && in_array($quickType, ['sale', 'rent'], true)) {
        $criteria['type'] = $quickType;
    }
    // Safety net: ถ้า parser ใส่ transit/quiet ลง amenities มา ให้ย้ายไปฟิลด์ที่ถูกต้อง
    if (in_array('transit', $criteria['amenities'], true)) {
        $criteria['transit'] = true;
        $criteria['amenities'] = array_values(array_diff($criteria['amenities'], ['transit']));
    }
    if (in_array('quiet', $criteria['amenities'], true)) {
        if (!in_array('quiet', $criteria['soft'], true)) {
            $criteria['soft'][] = 'quiet';
        }
        $criteria['amenities'] = array_values(array_diff($criteria['amenities'], ['quiet']));
    }
    $criteria['amenities'] = array_values(array_unique($criteria['amenities']));

    if (!ai_search_criteria_has_content($criteria)) {
        echo json_encode([
            'ok'      => true,
            'engine'  => $engine,
            'query'   => $q,
            'criteria'=> $criteria,
            'labels'  => [],
            'message' => t('ยังไม่พบเงื่อนไขที่ชัดเจน ลองระบุประเภท งบประมาณ หรือทำเลเพิ่มเติม', 'No clear criteria found. Try adding a property type, budget, or location.'),
            'total'   => 0,
            'relaxed' => false,
            'items'   => [],
            'more'    => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2) ค้นหา + จัดอันดับจากฐานข้อมูลจริง
    $result = ai_search_run($pdo, $criteria, $limit);

    $items = [];
    foreach ($result['top'] as $i => $item) {
        $items[] = ai_search_result_to_public($item, $i + 1);
    }
    $more = [];
    foreach ($result['more'] as $i => $item) {
        $more[] = ai_search_result_to_public($item, $limit + $i + 1);
    }

    echo json_encode([
        'ok'         => true,
        'engine'     => $engine,
        'query'      => $q,
        'criteria'   => $criteria,
        'labels'     => ai_search_criteria_labels($criteria),
        'weights'    => array_map(static fn($w) => round($w, 1), $result['weights']),
        'total'      => $result['total'],
        'relaxed'    => $result['relaxed'],
        'relax_note' => $result['relax_note'],
        'items'      => $items,
        'more'       => $more,
        'message'    => $result['total'] === 0
            ? t('ไม่พบประกาศที่ตรงกับเงื่อนไขในระบบ', 'No listings matched your criteria.')
            : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => t('ระบบค้นหาขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง', 'The search service is temporarily unavailable.'),
    ], JSON_UNESCAPED_UNICODE);
}
