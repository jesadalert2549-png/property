<?php
/**
 * ระบบวิเคราะห์ประกาศสำหรับสมาชิก (User Property Analytics)
 * --------------------------------------------------------------------------
 * รวมฟังก์ชันคำนวณทั้งหมดไว้ที่เดียว โดยใช้ข้อมูลที่มีอยู่แล้วในฐานข้อมูล:
 *   • visits              — page / property views + device + referrer + เวลา
 *   • properties          — ราคา, ทำเล, จำนวนห้อง, รูป, วิดีโอ, คำอธิบาย
 *   • property_images     — จำนวนรูป
 *   • search_queries      — คำค้นหาที่คนใช้ (ใช้ดูความต้องการของตลาด)
 *   • property_favorites  — ผู้ที่กดหัวใจ
 *
 * ไม่อาศัยตารางใหม่ ไม่แก้ schema เดิม
 */

if (!function_exists('analytics_user_property_ids')) {
    /** คืน id ของประกาศทั้งหมดที่ผู้ใช้รายนี้เป็นเจ้าของ */
    function analytics_user_property_ids(PDO $pdo, int $userId): array
    {
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        $st = $pdo->prepare('SELECT id FROM properties WHERE user_id = ?');
        $st->execute([$userId]);
        return $cache[$userId] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('analytics_user_summary')) {
    /**
     * สรุปภาพรวมของผู้ใช้ ช่วง N วัน เทียบกับ N วันก่อนหน้า
     * คืนค่า: views, views_prev, clicks, clicks_prev, favorites, ctr, ...
     */
    function analytics_user_summary(PDO $pdo, int $userId, int $days = 7): array
    {
        $ids = analytics_user_property_ids($pdo, $userId);
        $empty = [
            'views' => 0, 'views_prev' => 0, 'clicks' => 0, 'clicks_prev' => 0,
            'favorites' => 0, 'favorites_prev' => 0, 'ctr' => 0.0,
            'listings' => 0, 'approved' => 0, 'unique_visitors' => 0,
        ];
        if (!$ids) {
            return $empty;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $days = max(1, min(365, $days));

        // นับการเข้าชมหน้ากระทบ (event_type=property)
        $sql = "SELECT
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS cur,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        AND created_at <  DATE_SUB(NOW(), INTERVAL ? DAY)) AS prev
                FROM visits
                WHERE event_type = 'property' AND property_id IN ($in)";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$days, $days * 2, $days], $ids));
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $views = (int)($row['cur'] ?? 0);
        $viewsPrev = (int)($row['prev'] ?? 0);

        // ผู้เข้าชมไม่ซ้ำ (นับจาก visitor_hash)
        $st = $pdo->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM visits
                             WHERE event_type = 'property' AND property_id IN ($in)
                               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $st->execute(array_merge($ids, [$days]));
        $unique = (int)$st->fetchColumn();

        // การคลิก (เก็บใน clicks_count ของตาราง properties)
        $st = $pdo->prepare("SELECT COALESCE(SUM(clicks_count),0) FROM properties WHERE id IN ($in)");
        $st->execute($ids);
        $clicks = (int)$st->fetchColumn();

        // รายการโปรด
        $st = $pdo->prepare("SELECT COUNT(*) FROM property_favorites WHERE property_id IN ($in)");
        $st->execute($ids);
        $favs = (int)$st->fetchColumn();

        // สถานะประกาศ
        $st = $pdo->prepare("SELECT COUNT(*) AS total, SUM(approval = 'approved') AS approved
                             FROM properties WHERE id IN ($in)");
        $st->execute($ids);
        $st2 = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'approved' => 0];

        $ctr = $views > 0 ? round(($clicks / $views) * 100, 1) : 0.0;

        return [
            'views'           => $views,
            'views_prev'      => $viewsPrev,
            'clicks'          => $clicks,
            'clicks_prev'     => 0,
            'favorites'       => $favs,
            'favorites_prev'  => 0,
            'ctr'             => $ctr,
            'unique_visitors' => $unique,
            'listings'        => (int)$st2['total'],
            'approved'        => (int)$st2['approved'],
        ];
    }
}

if (!function_exists('analytics_pct_change')) {
    /** เปอร์เซ็นต์เปลี่ยนแปลง (คืน null ถ้าไม่มีฐานให้เทียบ) */
    function analytics_pct_change($current, $previous): ?float
    {
        $current = (float)$current;
        $previous = (float)$previous;
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}

if (!function_exists('analytics_daily_views')) {
    /**
     * จำนวนการดูรายวัน (เติมวันที่ไม่มีข้อมูลให้เป็น 0 เพื่อกราฟต่อเนื่อง)
     * คืน array: [['date' => 'YYYY-MM-DD', 'label' => '14 พ.ย.', 'count' => 5], ...]
     */
    function analytics_daily_views(PDO $pdo, int $userId, int $days = 14): array
    {
        $ids = analytics_user_property_ids($pdo, $userId);
        $days = max(1, min(90, $days));

        $map = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) c
                                 FROM visits
                                 WHERE event_type = 'property' AND property_id IN ($in)
                                   AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                                 GROUP BY DATE(created_at)");
            $st->execute(array_merge($ids, [$days]));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[$r['d']] = (int)$r['c'];
            }
        }

        $today = new DateTimeImmutable('today');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify("-{$i} day");
            $key = $day->format('Y-m-d');
            $out[] = [
                'date'  => $key,
                'label' => $day->format('j/n'),
                'count' => $map[$key] ?? 0,
            ];
        }
        return $out;
    }
}

if (!function_exists('analytics_top_properties')) {
    /** ประกาศของผู้ใช้ที่ได้ผลดีที่สุด จัดอันดับด้วยจำนวนการดู */
    function analytics_top_properties(PDO $pdo, int $userId, int $days = 30, int $limit = 5): array
    {
        $ids = analytics_user_property_ids($pdo, $userId);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $limit = max(1, min(20, $limit));

        $sql = "SELECT p.id, p.title, p.price, p.type, p.property_category, p.location,
                       p.views_count, p.clicks_count, p.approval,
                       (SELECT COUNT(*) FROM visits v
                        WHERE v.event_type = 'property' AND v.property_id = p.id
                          AND v.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS recent_views,
                       (SELECT COUNT(*) FROM property_favorites f WHERE f.property_id = p.id) AS favs
                FROM properties p
                WHERE p.id IN ($in)
                ORDER BY recent_views DESC, p.created_at DESC
                LIMIT " . (int)$limit;
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$days], $ids));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('analytics_audience')) {
    /** ผู้ชมมาจากอุปกรณ์อะไร และมาจากช่องทางไหน */
    function analytics_audience(PDO $pdo, int $userId, int $days = 30): array
    {
        $ids = analytics_user_property_ids($pdo, $userId);
        $result = [
            'devices'   => ['mobile' => 0, 'desktop' => 0],
            'referrers' => [],
            'total'     => 0,
        ];
        if (!$ids) {
            return $result;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $days = max(1, min(365, $days));

        // อุปกรณ์
        $st = $pdo->prepare("SELECT device, COUNT(*) c FROM visits
                             WHERE event_type = 'property' AND property_id IN ($in)
                               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                             GROUP BY device");
        $st->execute(array_merge($ids, [$days]));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['device'] === 'mobile' ? 'mobile' : 'desktop';
            $result['devices'][$key] += (int)$r['c'];
            $result['total'] += (int)$r['c'];
        }

        // ช่องทาง (จัดกลุ่มแบบง่าย)
        $st = $pdo->prepare("SELECT referrer, COUNT(*) c FROM visits
                             WHERE event_type = 'property' AND property_id IN ($in)
                               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                             GROUP BY referrer");
        $st->execute(array_merge($ids, [$days]));

        $groups = ['search' => 0, 'social' => 0, 'direct' => 0, 'other' => 0];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ref = strtolower((string)$r['referrer']);
            $c = (int)$r['c'];
            if ($ref === '' || $ref === 'localhost' || strpos($ref, 'kasatest') !== false) {
                $groups['direct'] += $c;
            } elseif (preg_match('/google|bing|yahoo|duckduckgo|search/', $ref)) {
                $groups['search'] += $c;
            } elseif (preg_match('/facebook|line|instagram|twitter|x\.com|tiktok|youtube/', $ref)) {
                $groups['social'] += $c;
            } else {
                $groups['other'] += $c;
            }
        }
        $result['referrers'] = $groups;

        return $result;
    }
}

if (!function_exists('listing_health_score')) {
    /**
     * ให้คะแนนสุขภาพประกาศ 0–100 พร้อมเหตุผลและคำแนะนำ
     * ใช้ช่วยผู้ใช้รู้ว่าควรปรับอะไรเพื่อให้ประกาศได้ผลขึ้น
     */
    function listing_health_score(PDO $pdo, array $property): array
    {
        $id = (int)($property['id'] ?? 0);
        $score = 0;
        $checks = [];   // ['key','label','ok','points','tip']

        // 1) รูปภาพ ≥ 3
        $imgCount = 0;
        if ($id) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM property_images WHERE property_id = ?');
            $st->execute([$id]);
            $imgCount = (int)$st->fetchColumn();
        }
        $hasImg = $imgCount >= 3;
        $score += $hasImg ? 20 : ($imgCount > 0 ? 10 : 0);
        $checks[] = [
            'key' => 'images', 'label' => 'รูปภาพ', 'ok' => $hasImg,
            'detail' => $imgCount . ' รูป',
            'tip' => $imgCount === 0 ? 'เพิ่มรูปอย่างน้อย 3 รูป — ประกาศมีรูปได้ความสนใจมากกว่าถึง 5 เท่า'
                 : ($imgCount < 3 ? 'เพิ่มรูปให้ครบอย่างน้อย 3 รูป' : ''),
        ];

        // 2) คำอธิบาย ≥ 150 ตัวอักษร
        $descLen = mb_strlen(trim((string)($property['description'] ?? '')));
        $hasDesc = $descLen >= 150;
        $score += $hasDesc ? 15 : ($descLen > 40 ? 8 : 0);
        $checks[] = [
            'key' => 'description', 'label' => 'คำอธิบาย', 'ok' => $hasDesc,
            'detail' => $descLen . ' ตัวอักษร',
            'tip' => $descLen < 150 ? 'เขียนคำอธิบายอย่างน้อย 150 ตัวอักษร เช่น ทำเล จุดเด่น สิ่งอำนวยความสะดวก' : '',
        ];

        // 3) พิกัดแผนที่
        $hasMap = !empty($property['lat']) && !empty($property['lng']);
        $score += $hasMap ? 15 : 0;
        $checks[] = [
            'key' => 'map', 'label' => 'แผนที่', 'ok' => $hasMap,
            'detail' => $hasMap ? 'ระบุแล้ว' : 'ยังไม่มี',
            'tip' => $hasMap ? '' : 'ปักหมุดแผนที่ ช่วยให้ผู้ซื้อเห็นทำเลจริงและตัดสินใจเร็วขึ้น',
        ];

        // 4) ข้อมูลห้อง/พื้นที่
        $hasRooms = !empty($property['bedrooms']) || !empty($property['bathrooms']);
        $hasArea  = !empty($property['area_sqm']) || !empty($property['land_area']);
        $score += $hasRooms ? 10 : 0;
        $score += $hasArea ? 10 : 0;
        $checks[] = [
            'key' => 'rooms', 'label' => 'จำนวนห้อง', 'ok' => $hasRooms,
            'detail' => ($property['bedrooms'] ?? '-') . ' นอน / ' . ($property['bathrooms'] ?? '-') . ' น้ำ',
            'tip' => $hasRooms ? '' : 'ระบุจำนวนห้องนอน/ห้องน้ำ — ผู้ซื้อใช้ตัวกรองนี้บ่อยที่สุด',
        ];
        $checks[] = [
            'key' => 'area', 'label' => 'พื้นที่', 'ok' => $hasArea,
            'detail' => !empty($property['area_sqm']) ? $property['area_sqm'] . ' ตร.ม.' : 'ยังไม่มี',
            'tip' => $hasArea ? '' : 'ระบุขนาดพื้นที่ใช้สอย',
        ];

        // 5) สิ่งอำนวยความสะดวก
        $amen = (string)($property['amenities'] ?? '');
        $hasAmen = $amen !== '' && $amen !== '[]';
        $score += $hasAmen ? 10 : 0;
        $checks[] = [
            'key' => 'amenities', 'label' => 'สิ่งอำนวยความสะดวก', 'ok' => $hasAmen,
            'detail' => $hasAmen ? 'ระบุแล้ว' : 'ยังไม่มี',
            'tip' => $hasAmen ? '' : 'ระบุสิ่งอำนวยความสะดวก เช่น ที่จอดรถ ฟิตเนส รปภ.',
        ];

        // 6) วิดีโอ
        $hasVideo = !empty($property['video_file']);
        $score += $hasVideo ? 10 : 0;
        $checks[] = [
            'key' => 'video', 'label' => 'วิดีโอทัวร์', 'ok' => $hasVideo,
            'detail' => $hasVideo ? 'มีแล้ว' : 'ยังไม่มี',
            'tip' => $hasVideo ? '' : 'เพิ่มวิดีโอทัวร์สั้น ๆ — เพิ่มเวลาดูต่อหน้าได้ชัดเจน',
        ];

        // 7) เปรียบเทียบราคาตลาด
        $market = listing_market_price(
            $pdo,
            (string)($property['property_category'] ?? ''),
            (string)($property['type'] ?? ''),
            (string)($property['location'] ?? ''),
            $id
        );
        $price = (int)($property['price'] ?? 0);
        $priceOk = true;
        $priceTip = '';
        if ($market['median'] > 0 && $price > 0) {
            $diff = (($price - $market['median']) / $market['median']) * 100;
            if ($diff > 25) {
                $priceOk = false;
                $priceTip = sprintf('ราคาสูงกว่าค่ากลางย่านนี้ %.0f%% (ค่ากลาง ฿%s)', $diff, number_format($market['median']));
            } elseif ($diff > 10) {
                $priceOk = false;
                $priceTip = sprintf('สูงกว่าค่ากลางเล็กน้อย %.0f%% — ลองพิจารณาปรับ', $diff);
            }
        }
        $score += $priceOk ? 10 : 0;

        $score = max(0, min(100, $score));

        if ($score >= 80) {
            $verdict = 'ดีมาก';
            $tone = 'great';
        } elseif ($score >= 60) {
            $verdict = 'ดี';
            $tone = 'good';
        } elseif ($score >= 40) {
            $verdict = 'พอใช้';
            $tone = 'ok';
        } else {
            $verdict = 'ควรปรับ';
            $tone = 'low';
        }

        $tips = [];
        foreach ($checks as $c) {
            if (!$c['ok'] && $c['tip'] !== '') {
                $tips[] = $c['tip'];
            }
        }
        if ($priceTip !== '') {
            $tips[] = $priceTip;
        }

        return [
            'score'    => $score,
            'verdict'  => $verdict,
            'tone'     => $tone,
            'checks'   => $checks,
            'tips'     => array_slice($tips, 0, 3),
            'market'   => $market,
            'images'   => $imgCount,
        ];
    }
}

if (!function_exists('listing_market_price')) {
    /**
     * ค่ากลาง (median) ของราคาในกลุ่มเดียวกัน: หมวด + ซื้อ/เช่า + จังหวัด
     * ไม่ใช้ค่าเฉลี่ยเพราะถูกค่าสุดโต่งดึงไป
     */
    function listing_market_price(PDO $pdo, string $category, string $type, string $location, int $excludeId = 0): array
    {
        $empty = ['median' => 0, 'count' => 0];
        if ($category === '' || $type === '') {
            return $empty;
        }

        // ดึงจังหวัดจากข้อความทำเล (คำสุดท้ายหลังเครื่องหมายจุลภาค หรือคำที่ขึ้นต้นด้วย จังหวัด)
        $province = '';
        if (preg_match('/(กรุงเทพฯ|กรุงเทพมหานคร|เชียงใหม่|ภูเก็ต|ชลบุรี|นนทบุรี|สมุทรปราการ|ปทุมธานี|ระยอง|พัทยา|ขอนแก่น|หาดใหญ่|สุราษฎร์ธานี|เชียงราย|นครราชสีมา)/u', $location, $m)) {
            $province = $m[1];
        }

        $sql = "SELECT price FROM properties
                WHERE approval = 'approved' AND price > 0 AND price < 2147483000
                  AND property_category = ? AND type = ?";
        $params = [$category, $type];
        if ($province !== '') {
            $sql .= ' AND location LIKE ?';
            $params[] = '%' . $province . '%';
        }
        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY price ASC LIMIT 400';

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $prices = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            return $empty;
        }

        if (count($prices) < 2) {
            return ['median' => 0, 'count' => count($prices)];
        }
        $n = count($prices);
        $mid = intdiv($n, 2);
        $median = ($n % 2 === 0) ? (int)round(($prices[$mid - 1] + $prices[$mid]) / 2) : $prices[$mid];

        return ['median' => $median, 'count' => $n];
    }
}

if (!function_exists('analytics_bar_percent')) {
    /** คำนวณความยาวแท่งกราฟเป็นเปอร์เซ็นต์ เทียบกับค่าสูงสุด */
    function analytics_bar_percent($value, $max): int
    {
        $max = (float)$max;
        if ($max <= 0) {
            return 0;
        }
        return (int)max(2, min(100, round(((float)$value / $max) * 100)));
    }
}
