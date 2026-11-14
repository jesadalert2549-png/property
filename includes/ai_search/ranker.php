<?php
/**
 * PropertyGO AI Deep Search — Matcher & Ranker
 *
 * หลักการสำคัญ:
 *  1) ดึงเฉพาะประกาศจริงที่อนุมัติแล้วและยัง available จากตาราง properties
 *  2) แยก "เงื่อนไขบังคับ" (hard) ออกจาก "ความต้องการรอง" (soft)
 *  3) เงื่อนไขบังคับที่ไม่ผ่าน = ตัดออก ห้ามใช้คะแนนด้านอื่นมาชดเชย
 *  4) ให้คะแนนแบบถ่วงน้ำหนัก 30/25/20/15/10 ตามที่ระบุในสเปก
 *  5) AI ห้ามสร้างข้อมูลใหม่ — ทุกฟิลด์ที่แสดงมาจากฐานข้อมูลเท่านั้น
 */

require_once __DIR__ . '/parser.php';

if (!function_exists('ai_search_default_weights')) {
    /** น้ำหนักคะแนนเริ่มต้น (เปอร์เซ็นต์) ตามสเปก */
    function ai_search_default_weights(): array
    {
        return [
            'budget'      => 30,
            'location'    => 25,
            'rooms'       => 20,
            'amenities'   => 15,
            'completeness'=> 10,
        ];
    }
}

if (!function_exists('ai_search_weights_for_criteria')) {
    /**
     * ปรับน้ำหนักตามเงื่อนไขที่ผู้ใช้ระบุจริง
     * เงื่อนไขที่ผู้ใช้ไม่ได้ระบุ จะไม่ถูกนำมาเป็นตัวถ่วง (น้ำหนัก 0)
     * หากไม่มีเงื่อนไขใดเลย ใช้ค่าเริ่มต้น
     */
    function ai_search_weights_for_criteria(array $criteria): array
    {
        $base = ai_search_default_weights();
        $active = [];

        if (!empty($criteria['budget_max']) || !empty($criteria['budget_min'])) {
            $active['budget'] = $base['budget'];
        }
        if (!empty($criteria['locations']) || !empty($criteria['transit']) || !empty($criteria['category'])) {
            $active['location'] = $base['location'];
        }
        if (!empty($criteria['bedrooms_min']) || !empty($criteria['bathrooms_min'])
            || !empty($criteria['parking_min']) || !empty($criteria['area_min'])) {
            $active['rooms'] = $base['rooms'];
        }
        $amenityWanted = array_values(array_diff($criteria['amenities'] ?? [], ['park']));
        if ($amenityWanted) {
            $active['amenities'] = $base['amenities'];
        }
        $active['completeness'] = $base['completeness'];

        // normalize ให้รวมเป็น 100
        $sum = array_sum($active);
        if ($sum <= 0) {
            return $base;
        }
        $normalized = [];
        foreach ($active as $key => $weight) {
            $normalized[$key] = $weight / $sum * 100;
        }
        return $normalized;
    }
}

if (!function_exists('ai_search_fetch_candidates')) {
    /**
     * ดึงประกาศจริงจากฐานข้อมูล (จำกัดจำนวนเพื่อความเร็ว)
     * ใช้คอลัมน์ที่มีอยู่จริงเท่านั้น ไม่แก้ schema
     */
    function ai_search_fetch_candidates(PDO $pdo, array $criteria, int $limit = 300): array
    {
        $where = ["p.approval = 'approved'", "p.status = 'available'"];
        $params = [];

        if (!empty($criteria['type'])) {
            $where[] = 'p.type = ?';
            $params[] = $criteria['type'];
        }
        if (!empty($criteria['category'])) {
            $where[] = 'p.property_category = ?';
            $params[] = $criteria['category'];
        }

        // กรองงบแบบผ่อนปรน 25% เพื่อให้ยังมีตัวเลือกให้จัดอันดับ แล้วตัดสินว่า "ตรง/ไม่ตรง" ตอนให้คะแนน
        if (!empty($criteria['budget_max'])) {
            $where[] = 'p.price <= ?';
            $params[] = (int)round($criteria['budget_max'] * 1.25);
        } elseif (!empty($criteria['budget_min'])) {
            // ไม่มีเพดานงบ -> กันประกาศที่ราคาไม่สมจริง (เช่น ค่าขยะในฐานข้อมูล) หลุดเข้ามาจัดอันดับ
            $where[] = 'p.price <= ?';
            $params[] = (int)round($criteria['budget_min'] * 6);
        }
        if (!empty($criteria['budget_min'])) {
            $where[] = 'p.price >= ?';
            $params[] = (int)round($criteria['budget_min'] * 0.8);
        }

        // กรองทำเลเบื้องต้นด้วย LIKE เพื่อลดจำนวนแถว (ถ้าจับทำเลได้)
        if (!empty($criteria['locations'])) {
            $orParts = [];
            foreach ($criteria['locations'] as $place) {
                $orParts[] = 'p.location LIKE ?';
                $params[] = '%' . addcslashes($place, '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $orParts) . ')';
        }

        $sql = 'SELECT p.id, p.title, p.type, p.property_category, p.price, p.original_price,
                       p.location, p.bedrooms, p.bathrooms, p.parking_spaces, p.area_sqm,
                       p.land_area, p.furnished, p.amenities, p.nearby_places, p.description,
                       p.lat, p.lng, p.video_file, p.views_count, p.clicks_count, p.created_at,
                       u.full_name AS publisher_name, u.avatar_path AS publisher_avatar,
                       u.agent_status AS publisher_agent_status,
                       (SELECT i.filename FROM property_images i
                        WHERE i.property_id = p.id
                        ORDER BY i.sort_order, i.id LIMIT 1) AS cover
                FROM properties p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.created_at DESC
                LIMIT ' . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('ai_search_fallback_candidates')) {
    /**
     * ดึงประกาศสำรองเมื่อไม่มีรายการผ่านตัวกรองเลย
     * เพื่อให้ UI เสนอ "ทางเลือกที่ผ่อนคลายเงื่อนไข" ตามสเปก
     */
    function ai_search_fallback_candidates(PDO $pdo, array $criteria, int $limit = 60): array
    {
        $where = ["p.approval = 'approved'", "p.status = 'available'"];
        $params = [];

        // ผ่อนแบบเป็นขั้น: เริ่มจากคงประเภทอสังหาฯ + การซื้อ/เช่าไว้ก่อน
        // ถ้ายังว่างอีก จึงค่อยปลดประเภทออก เพื่อไม่ให้หน้าผลลัพธ์ว่างเปล่า
        if (!empty($criteria['type'])) {
            $where[] = 'p.type = ?';
            $params[] = $criteria['type'];
        }

        $sql = 'SELECT p.id, p.title, p.type, p.property_category, p.price, p.original_price,
                       p.location, p.bedrooms, p.bathrooms, p.parking_spaces, p.area_sqm,
                       p.land_area, p.furnished, p.amenities, p.nearby_places, p.description,
                       p.lat, p.lng, p.video_file, p.views_count, p.clicks_count, p.created_at,
                       u.full_name AS publisher_name, u.avatar_path AS publisher_avatar,
                       u.agent_status AS publisher_agent_status,
                       (SELECT i.filename FROM property_images i
                        WHERE i.property_id = p.id
                        ORDER BY i.sort_order, i.id LIMIT 1) AS cover
                FROM properties p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE ';

        // ลองครั้งที่ 1: คงประเภทอสังหาฯ ไว้
        if (!empty($criteria['category'])) {
            $attemptWhere = $where;
            $attemptWhere[] = 'p.property_category = ?';
            $attemptParams = array_merge($params, [$criteria['category']]);
            $stmt = $pdo->prepare($sql . implode(' AND ', $attemptWhere) . '
                ORDER BY p.price ASC, p.created_at DESC LIMIT ' . (int)$limit);
            $stmt->execute($attemptParams);
            $rows = $stmt->fetchAll();
            if ($rows) {
                return $rows;
            }
        }

        // ลองครั้งที่ 2: ผ่อนประเภทอสังหาฯ ออกด้วย แต่ยังคงการซื้อ/เช่า
        // (ผู้ใช้จะเห็นข้อความแจ้งชัดว่าประเภทอสังหาฯ ไม่ตรงตามที่ขอ)
        $stmt = $pdo->prepare($sql . implode(' AND ', $where) . '
            ORDER BY p.price ASC, p.created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('ai_search_price_is_sane')) {
    /**
     * คัดกรองราคาที่ไม่สมจริงออกจากการจัดอันดับ
     * กันค่าขยะ/ค่า overflow (เช่น 4,294,967,295) ไม่ให้ขึ้นอันดับต้น ๆ
     */
    function ai_search_price_is_sane(array $row, ?int $budgetMax): bool
    {
        $price = (int)($row['price'] ?? 0);
        if ($price <= 0 || $price >= 2147483000) {
            return false;
        }
        if ($budgetMax !== null && $budgetMax > 0) {
            // เกินงบเกิน 3 เท่า = ถือว่าหลุดบริบทการค้นหา
            return $price <= $budgetMax * 3;
        }
        return true;
    }
}

if (!function_exists('ai_search_text_contains')) {
    function ai_search_text_contains(?string $haystack, string $needle): bool
    {
        if ($haystack === null || trim($haystack) === '' || $needle === '') {
            return false;
        }
        return mb_stripos($haystack, $needle) !== false;
    }
}

if (!function_exists('ai_search_match_location')) {
    /**
     * ตรวจว่าประกาศตรงทำเลใดที่ผู้ใช้ระบุ (ค้นจาก location + title + nearby_places)
     * คืนชื่อทำเลที่ตรง หรือ null
     */
    function ai_search_match_location(array $row, array $locations): ?string
    {
        if (!$locations) {
            return null;
        }
        $haystack = trim(($row['location'] ?? '') . ' ' . ($row['title'] ?? '') . ' ' . ($row['nearby_places'] ?? ''));
        foreach ($locations as $place) {
            if (ai_search_text_contains($haystack, $place)) {
                return $place;
            }
        }

        // Some listings store the road/area only (e.g. "BTS ช้างเอราวัณ" for a Sukhumvit building).
        // If the user's area name appears anywhere in the listing, accept it as a partial hit.
        foreach ($locations as $place) {
            $needle = preg_replace('/\s+/u', '', mb_strtolower($place));
            $compact = preg_replace('/\s+/u', '', mb_strtolower($haystack));
            if ($needle !== '' && mb_strpos($compact, $needle) !== false) {
                return $place;
            }
        }
        return null;
    }
}

if (!function_exists('ai_search_evaluate')) {
    /**
     * ให้คะแนนประกาศหนึ่งรายการเทียบกับเงื่อนไข
     *
     * คืนค่า:
     * [
     *   'passed'    => bool,          // ผ่าน hard filter ทั้งหมดหรือไม่
     *   'score'     => float,         // 0-100
     *   'breakdown' => [...],         // คะแนนแยกหมวด
     *   'matched'   => [..],          // จุดเด่นที่ตรง (เขียว)
     *   'mismatched'=> [..],          // เงื่อนไขไม่ตรง
     *   'unknown'   => [..],          // ข้อมูลไม่มี/ยืนยันไม่ได้
     * ]
     */
    function ai_search_evaluate(array $row, array $criteria, array $weights): array
    {
        $breakdown = ['budget' => 0.0, 'location' => 0.0, 'rooms' => 0.0, 'amenities' => 0.0, 'completeness' => 0.0];
        $matched = [];
        $mismatched = [];
        $unknown = [];
        $passed = true;
        // จำนวนเงื่อนไขที่ "ไม่ผ่านแบบชัดเจน" (ใช้จัดอันดับในโหมดผ่อนปรน)
        $hardFails = 0;

        // ── 0. ประเภทอสังหาฯ (ต้องแจ้งชัดถ้าไม่ตรง) ──
        if (!empty($criteria['category'])) {
            $actualCategory = (string)($row['property_category'] ?? '');
            if ($actualCategory === (string)$criteria['category']) {
                $matched[] = [
                    'type' => 'category',
                    'text' => t('เป็น', 'Is a ') . ' ' . category_label($actualCategory) . t(' ตามที่ต้องการ', ' as requested'),
                ];
            } else {
                $hardFails++;
                $mismatched[] = [
                    'type' => 'category',
                    'text' => t('เป็น', 'Is a ') . ' ' . category_label($actualCategory)
                        . t(' ไม่ใช่ ', ' instead of ') . category_label((string)$criteria['category']),
                ];
            }
        }

        // ── 1. งบประมาณ (hard filter) ──
        $price = (int)($row['price'] ?? 0);
        $needsBudget = !empty($criteria['budget_max']) || !empty($criteria['budget_min']);
        if ($needsBudget) {
            $max = !empty($criteria['budget_max']) ? (int)$criteria['budget_max'] : null;
            $min = !empty($criteria['budget_min']) ? (int)$criteria['budget_min'] : null;

            $withinMax = $max === null || $price <= $max;
            $withinMin = $min === null || $price >= $min;

            if ($withinMax && $withinMin) {
                $breakdown['budget'] = 1.0;
                $matched[] = [
                    'type' => 'budget',
                    'text' => t('ราคาอยู่ในงบที่กำหนด', 'Price is within your budget'),
                ];
            } elseif (!$withinMax && $max !== null) {
                $over = $price - $max;
                $overPct = $max > 0 ? ($over / $max) * 100 : 0;
                // ผ่อนปรนได้ไม่เกิน 15% เพื่อไม่ให้ตัดสินใจแทนผู้ใช้ แต่ต้องแจ้งชัดว่าเกินงบ
                if ($overPct <= 15) {
                    $breakdown['budget'] = max(0.25, 1 - ($overPct / 15) * 0.75);
                    $mismatched[] = [
                        'type' => 'budget',
                        'text' => t('ราคาเกินงบประมาณ ', 'Over budget by ') . number_format($over) . t(' บาท', ' THB'),
                    ];
                } else {
                    $passed = false;
                    $hardFails++;
                    // Hard-fail: an over-budget listing must not earn any budget credit,
                    // otherwise unrelated criteria could out-score an in-budget match.
                    $breakdown['budget'] = 0.0;
                    $mismatched[] = [
                        'type' => 'budget',
                        'text' => t('ราคาเกินงบ ', 'Over budget by ') . number_format($over) . t(' บาท (', ' THB (') . round($overPct) . '%)',
                    ];
                }
            } elseif (!$withinMin && $min !== null) {
                $hardFails++;
                $breakdown['budget'] = 0.0;
                $mismatched[] = [
                    'type' => 'budget',
                    'text' => t('ราคาต่ำกว่าช่วงงบที่กำหนด', 'Below your budget range'),
                ];
            }
        }

        // ── 2. ทำเล (คะแนนตามความแม่นยำ ; ไม่ตัดทิ้งเพียงเพราะประกาศเขียนทำเลไม่ครอบคลุม) ──
        $locations = $criteria['locations'] ?? [];
        $locationWanted = $locations || !empty($criteria['transit']);
        if ($locationWanted) {
            $hit = $locations ? ai_search_match_location($row, $locations) : null;

            // Transit requirement: look for BTS/MRT hints in location / nearby / description
            $transitHit = false;
            if (!empty($criteria['transit'])) {
                $transitHaystack = mb_strtolower(trim(($row['location'] ?? '') . ' ' . ($row['nearby_places'] ?? '') . ' ' . ($row['description'] ?? '')));
                $transitHit = (bool)preg_match('/(bts|mrt|รถไฟฟ้า|แอร์พอร์ตเรลลิงก์|arl|รถไฟใต้ดิน|สถานี)/u', $transitHaystack);
            }

            if ($locations && $hit !== null) {
                $breakdown['location'] = 1.0;
                $matched[] = [
                    'type' => 'location',
                    'text' => t('อยู่ทำเล ', 'In location ') . $hit . t(' ตามที่ต้องการ', ''),
                ];
            } elseif (!$locations && !empty($criteria['transit']) && $transitHit) {
                $breakdown['location'] = 1.0;
                $matched[] = [
                    'type' => 'location',
                    'text' => t('อยู่ใกล้ระบบรางตามที่ต้องการ', 'Close to BTS/MRT as requested'),
                ];
            } elseif (!$locations && !empty($criteria['transit'])) {
                $hardFails++;
                $breakdown['location'] = 0.0;
                $mismatched[] = [
                    'type' => 'location',
                    'text' => t('ยังไม่พบข้อมูลยืนยันว่าใกล้รถไฟฟ้า', 'No confirmation of nearby transit'),
                ];
            } else {
                // ไม่พบคำทำเลในประกาศ -> ไม่ให้เครดิตด้านทำเลเลย เพื่อไม่ให้คะแนนด้านอื่นมาชดเชย
                $hardFails++;
                $breakdown['location'] = 0.0;
                $mismatched[] = [
                    'type' => 'location',
                    'text' => t('ประกาศไม่ได้ระบุทำเลที่ต้องการ (', 'Location not confirmed (') . implode(', ', $locations) . ')',
                ];
            }
        }

        // ── 3. ห้องและพื้นที่ ──
        $roomChecks = 0;
        $roomHits = 0;

        if (!empty($criteria['bedrooms_min'])) {
            $roomChecks++;
            $value = $row['bedrooms'];
            if ($value === null || $value === '') {
                $unknown[] = t('ไม่มีข้อมูลจำนวนห้องนอน', 'Bedroom count is not available');
            } elseif ((int)$value >= (int)$criteria['bedrooms_min']) {
                $roomHits++;
                $matched[] = ['type' => 'rooms', 'text' => t('มี ', 'Has ') . (int)$value . t(' ห้องนอน ตรงตามที่ขอ', ' bedrooms as requested')];
            } else {
                $mismatched[] = ['type' => 'rooms', 'text' => t('มีเพียง ', 'Only ') . (int)$value . t(' ห้องนอน (ต้องการ ', ' bedrooms (you need ') . (int)$criteria['bedrooms_min'] . ')'];
            }
        }
        if (!empty($criteria['bathrooms_min'])) {
            $roomChecks++;
            $value = $row['bathrooms'];
            if ($value === null || $value === '') {
                $unknown[] = t('ไม่มีข้อมูลจำนวนห้องน้ำ', 'Bathroom count is not available');
            } elseif ((int)$value >= (int)$criteria['bathrooms_min']) {
                $roomHits++;
                $matched[] = ['type' => 'rooms', 'text' => t('มี ', 'Has ') . (int)$value . t(' ห้องน้ำ ตรงตามที่ขอ', ' bathrooms as requested')];
            } else {
                $mismatched[] = ['type' => 'rooms', 'text' => t('มีเพียง ', 'Only ') . (int)$value . t(' ห้องน้ำ', ' bathrooms')];
            }
        }
        if (!empty($criteria['parking_min'])) {
            $roomChecks++;
            $value = $row['parking_spaces'];
            $amenitySaysParking = ai_search_text_contains($row['amenities'] ?? '', 'จอด') || ai_search_text_contains($row['amenities'] ?? '', 'parking');
            if ($value === null || $value === '') {
                if ($amenitySaysParking) {
                    $roomHits++;
                    $matched[] = ['type' => 'rooms', 'text' => t('ระบุว่ามีที่จอดรถ', 'Parking is listed')];
                } else {
                    $unknown[] = t('ไม่มีข้อมูลจำนวนที่จอดรถ', 'Parking count is not available');
                }
            } elseif ((int)$value >= (int)$criteria['parking_min']) {
                $roomHits++;
                $matched[] = ['type' => 'rooms', 'text' => t('มีที่จอดรถ ', 'Has ') . (int)$value . t(' คัน', ' parking spaces')];
            } else {
                $mismatched[] = ['type' => 'rooms', 'text' => t('ที่จอดรถไม่พอ (มี ', 'Not enough parking (') . (int)$value . ')'];
            }
        }
        if (!empty($criteria['area_min'])) {
            $roomChecks++;
            $value = $row['area_sqm'];
            if ($value === null || (float)$value <= 0) {
                $unknown[] = t('ไม่มีข้อมูลพื้นที่ใช้สอย', 'Usable area is not available');
            } elseif ((float)$value >= (float)$criteria['area_min']) {
                $roomHits++;
                $matched[] = ['type' => 'rooms', 'text' => t('พื้นที่ ', 'Area ') . (float)$value . t(' ตร.ม. ตรงตามที่ขอ', ' sqm as requested')];
            } else {
                $mismatched[] = ['type' => 'rooms', 'text' => t('พื้นที่ ', 'Area ') . (float)$value . t(' ตร.ม. เล็กกว่าที่ขอ', ' sqm is smaller than requested')];
            }
        }

        if ($roomChecks > 0) {
            $breakdown['rooms'] = $roomHits / $roomChecks;
        }

        // ── 4. สิ่งอำนวยความสะดวก ──
        $wantedAmenities = array_values(array_diff($criteria['amenities'] ?? [], ['park']));
        if ($wantedAmenities) {
            $catalog = ai_search_amenity_catalog();
            $amenityHaystack = trim(($row['amenities'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . ($row['nearby_places'] ?? ''));
            $hits = 0;
            foreach ($wantedAmenities as $key) {
                $words = $catalog[$key] ?? [$key];
                $found = false;
                foreach ($words as $word) {
                    if (ai_search_text_contains($amenityHaystack, $word)) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $hits++;
                    $matched[] = ['type' => 'amenities', 'text' => t('มี', 'Has ') . ($words[0] ?? $key)];
                } else {
                    $mismatched[] = ['type' => 'amenities', 'text' => t('ไม่พบข้อมูล', 'Not found: ') . ' ' . ($words[0] ?? $key)];
                }
            }
            $breakdown['amenities'] = $hits / count($wantedAmenities);
        }

        // ── 5. ความครบถ้วนของประกาศ ──
        $completenessParts = 0;
        $completenessHits = 0;
        foreach (['cover', 'bedrooms', 'bathrooms', 'area_sqm', 'location'] as $field) {
            $completenessParts++;
            $v = $row[$field] ?? null;
            if (($field === 'area_sqm' || $field === 'bedrooms' || $field === 'bathrooms') && $v !== null) {
                $completenessHits++;
            } elseif (!in_array($field, ['area_sqm', 'bedrooms', 'bathrooms'], true) && trim((string)$v) !== '') {
                $completenessHits++;
            }
        }
        if (trim((string)($row['description'] ?? '')) !== '') {
            $completenessParts++;
            $completenessHits++;
        }
        $breakdown['completeness'] = $completenessParts > 0 ? $completenessHits / $completenessParts : 0;

        // ── คะแนนรวมแบบถ่วงน้ำหนัก ──
        $score = 0.0;
        $weightSum = 0.0;
        foreach ($breakdown as $key => $value) {
            $w = (float)($weights[$key] ?? 0);
            $score += $value * $w;
            $weightSum += $w;
        }
        if ($weightSum > 0) {
            $score = $score / $weightSum * 100;
        }

        if (!empty($unknown)) {
            $mismatched = array_merge($mismatched, []);
        }

        return [
            'passed'     => $passed,
            'hard_fails' => $hardFails,
            'score'      => round($score, 1),
            'breakdown'  => $breakdown,
            'matched'    => $matched,
            'mismatched' => $mismatched,
            'unknown'    => array_values(array_unique($unknown)),
        ];
    }
}

if (!function_exists('ai_search_run')) {
    /**
     * ค้นหา + จัดอันดับ + สรุปผล
     *
     * คืนค่า:
     * [
     *   'criteria' => array,
     *   'weights'  => array,
     *   'top'      => array,   // 5 อันดับแรก (หรือตาม limit)
     *   'more'     => array,   // รายการที่เหลือ (สำหรับ "ดูเพิ่มเติม")
     *   'total'    => int,
     *   'relaxed'  => bool,    // ใช้ทางเลือกผ่อนปรนหรือไม่
     *   'relax_note' => string,
     * ]
     */
    function ai_search_run(PDO $pdo, array $criteria, int $topLimit = 5): array
    {
        $weights = ai_search_weights_for_criteria($criteria);
        $rows = ai_search_fetch_candidates($pdo, $criteria);

        $scored = [];
        $budgetMax = !empty($criteria['budget_max']) ? (int)$criteria['budget_max'] : null;

        foreach ($rows as $row) {
            if (!ai_search_price_is_sane($row, $budgetMax)) {
                continue;
            }
            $eval = ai_search_evaluate($row, $criteria, $weights);
            if (!$eval['passed']) {
                continue;
            }
            $scored[] = ['row' => $row] + $eval;
        }

        // เรียงจากมากไปน้อย; ถ้าคะแนนเท่ากันให้เรียงตามวันที่สร้างล่าสุด
        usort($scored, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return strcmp((string)($b['row']['created_at'] ?? ''), (string)($a['row']['created_at'] ?? ''));
            }
            return $a['score'] < $b['score'] ? 1 : -1;
        });

        $relaxed = false;
        $relaxNote = '';

        if (!$scored) {
            // ไม่มีรายการผ่านเงื่อนไขบังคับ -> เสนอทางเลือกที่ผ่อนคลาย
            $relaxed = true;
            $fallbackRows = ai_search_fallback_candidates($pdo, $criteria);
            foreach ($fallbackRows as $row) {
                if (!ai_search_price_is_sane($row, $budgetMax)) {
                    continue;
                }
                $eval = ai_search_evaluate($row, $criteria, $weights);
                $scored[] = ['row' => $row] + $eval;
            }

            // ในโหมดผ่อนปรน "ใกล้เคียงที่สุด" = จำนวนเงื่อนไขที่ยังไม่ตรงน้อยที่สุดก่อน
            // แล้วค่อยเทียบด้วยคะแนน (กันไม่ให้คะแนนด้านอื่นมาชดเชยเงื่อนไขสำคัญที่พลาด)
            usort($scored, static function (array $a, array $b): int {
                $aFails = (int)($a['hard_fails'] ?? 0);
                $bFails = (int)($b['hard_fails'] ?? 0);
                if ($aFails !== $bFails) {
                    return $aFails <=> $bFails;
                }
                if ($a['score'] !== $b['score']) {
                    return $a['score'] < $b['score'] ? 1 : -1;
                }
                return strcmp((string)($b['row']['created_at'] ?? ''), (string)($a['row']['created_at'] ?? ''));
            });

            $relaxedParts = [];
            if (!empty($criteria['budget_max'])) {
                $relaxedParts[] = t('งบประมาณ', 'budget');
            }
            if (!empty($criteria['locations'])) {
                $relaxedParts[] = t('ทำเล', 'location');
            }
            if (!empty($criteria['bedrooms_min']) || !empty($criteria['bathrooms_min'])) {
                $relaxedParts[] = t('จำนวนห้อง', 'room count');
            }
            if ($relaxedParts) {
                $relaxNote = t(
                    'ยังไม่พบประกาศที่ตรงทุกเงื่อนไข จึงผ่อนคลายเงื่อนไข: ' . implode(', ', $relaxedParts) . ' และเรียงรายการที่ใกล้เคียงที่สุดให้คุณ',
                    'No listing matched every condition, so we relaxed: ' . implode(', ', $relaxedParts) . ' and sorted the closest matches.'
                );
            } else {
                $relaxNote = t('ยังไม่พบประกาศที่ตรงเงื่อนไข ลองระบุทำเลหรืองบประมาณเพิ่มเติม', 'No listings matched. Try adding a location or budget.');
            }
        }

        $total = count($scored);
        $top = array_slice($scored, 0, $topLimit);
        $more = array_slice($scored, $topLimit);

        return [
            'criteria'   => $criteria,
            'weights'    => $weights,
            'top'        => $top,
            'more'       => $more,
            'total'      => $total,
            'relaxed'    => $relaxed,
            'relax_note' => $relaxNote,
        ];
    }
}

if (!function_exists('ai_search_result_to_public')) {
    /**
     * แปลงผลลัพธ์เป็นโครงสร้างที่ส่งออก JSON ได้ (หลีกเลี่ยงฟิลด์ภายใน)
     */
    function ai_search_result_to_public(array $item, int $rank): array
    {
        $row = $item['row'];
        $cover = '';
        $coverName = basename((string)($row['cover'] ?? ''));
        if ($coverName !== '' && is_file(UPLOAD_DIR . $coverName)) {
            $cover = 'uploads/' . rawurlencode($coverName);
        }

        $videoUrl = '';
        $videoName = basename((string)($row['video_file'] ?? ''));
        if ($videoName !== '' && is_file(UPLOAD_DIR . $videoName)) {
            $videoUrl = 'uploads/' . rawurlencode($videoName);
        }

        return [
            'rank'        => $rank,
            'id'          => (int)$row['id'],
            'title'       => (string)$row['title'],
            'url'         => 'property.php?id=' . (int)$row['id'],
            'type'        => (string)$row['type'],
            'type_label'  => type_label((string)$row['type']),
            'category'    => (string)$row['property_category'],
            'category_label' => category_label((string)$row['property_category']),
            'price'       => (int)$row['price'],
            'price_label' => price_label((string)$row['type'], (int)$row['price']),
            'original_price' => $row['original_price'] !== null ? (int)$row['original_price'] : null,
            'location'    => (string)($row['location'] ?? ''),
            'bedrooms'    => $row['bedrooms'] !== null ? (int)$row['bedrooms'] : null,
            'bathrooms'   => $row['bathrooms'] !== null ? (int)$row['bathrooms'] : null,
            'parking'     => $row['parking_spaces'] !== null ? (int)$row['parking_spaces'] : null,
            'area_sqm'    => $row['area_sqm'] !== null ? (float)$row['area_sqm'] : null,
            'cover'       => $cover,
            'video'       => $videoUrl,
            'publisher'   => (string)($row['publisher_name'] ?? ''),
            'verified'    => (($row['publisher_agent_status'] ?? '') === 'verified'),
            'score'       => $item['score'],
            'breakdown'   => $item['breakdown'],
            'matched'     => $item['matched'],
            'mismatched'  => $item['mismatched'],
            'unknown'     => $item['unknown'],
        ];
    }
}
