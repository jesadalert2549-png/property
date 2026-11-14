<?php
/**
 * PropertyGO AI Deep Search — Natural Language Parser (prototype / rule-based)
 *
 * แปลงข้อความภาษาไทยแบบภาษาพูดเป็นโครงสร้างเงื่อนไขค้นหา
 * ไฟล์นี้ไม่แตะฐานข้อมูลและไม่แตะระบบเดิม ทำงานแบบ pure function เท่านั้น
 *
 * ผลลัพธ์ที่คืนกลับ (schema กลางที่ LLM หรือ parser ใด ๆ ก็ต้องผลิตแบบเดียวกัน):
 * [
 *   'type'         => 'sale'|'rent'|null,
 *   'category'     => 'condo'|'house'|'townhouse'|'land'|'commercial'|'warehouse'|null,
 *   'budget_max'   => int|null,       // บาท (ราคาขาย) หรือ บาท/เดือน (เช่า)
 *   'budget_min'   => int|null,
 *   'locations'    => string[],       // คำทำเลที่จับได้ (เก็บคำเดิมไว้แสดงผล)
 *   'bedrooms_min' => int|null,
 *   'bathrooms_min'=> int|null,
 *   'parking_min'  => int|null,
 *   'area_min'     => float|null,     // ตร.ม.
 *   'amenities'    => string[],       // คีย์ amenity ที่ระบบรู้จัก
 *   'transit'      => bool,           // ต้องการใกล้รถไฟฟ้า/รถไฟ
 *   'soft'         => string[],       // ความต้องการรอง (ไม่ใช่ hard filter)
 *   'unparsed'     => string,         // ข้อความที่ยังไม่ถูกตีความ
 * ]
 */

if (!function_exists('ai_search_amenity_catalog')) {
    /**
     * รายการสิ่งอำนวยความสะดวกที่ระบบรู้จัก พร้อมคำพ้องภาษาไทย/อังกฤษ
     * ใช้จับคู่กับคอลัมน์ amenities (ข้อความอิสระ) ในฐานข้อมูล
     */
    function ai_search_amenity_catalog(): array
    {
        return [
            'fitness'  => ['ฟิตเนส', 'ฟิตเนต', 'ฟิตเน็ต', 'fitness', 'gym', 'ยิม'],
            'pool'     => ['สระว่ายน้ำ', 'สระ', 'pool', 'swimming'],
            'park'     => ['ที่จอดรถ', 'ที่จอด', 'parking', 'จอดรถ'],
            'security' => ['รปภ', 'รปภ.', 'security', 'รักษาความปลอดภัย'],
            'cctv'     => ['กล้องวงจรปิด', 'cctv'],
            'garden'   => ['สวน', 'garden'],
            'wifi'     => ['อินเทอร์เน็ต', 'เน็ต', 'wifi', 'wi-fi', 'internet'],
            'elevator' => ['ลิฟต์', 'ลิฟท์', 'elevator', 'lift'],
            'furniture'=> ['เฟอร์นิเจอร์', 'เฟอร์', 'furnished', 'พร้อมอยู่'],
            'aircon'   => ['แอร์', 'เครื่องปรับอากาศ', 'air'],
        ];
    }
}

if (!function_exists('ai_search_category_keywords')) {
    function ai_search_category_keywords(): array
    {
        return [
            'condo'      => ['คอนโด', 'คอนโดมิเนียม', 'condo', 'apartment', 'อพาร์ทเม้นท์', 'อพาร์ทเมนท์'],
            'house'      => ['บ้านเดี่ยว', 'บ้าน', 'house', 'villa', 'วิลล่า'],
            'townhouse'  => ['ทาวน์โฮม', 'ทาวน์เฮ้าส์', 'ทาวน์เฮาส์', 'ทาวน์', 'townhouse', 'townhome'],
            'land'       => ['ที่ดิน', 'land', 'แปลง'],
            'commercial' => ['อาคารพาณิชย์', 'ตึกแถว', 'commercial', 'โชว์รูม', 'showroom', 'สำนักงาน', 'ออฟฟิศ', 'office', 'ออฟฟิศให้เช่า', 'co-working', 'coworking'],
            'warehouse'  => ['โกดัง', 'โรงงาน', 'warehouse', 'factory'],
        ];
    }
}

if (!function_exists('ai_search_known_locations')) {
    /**
     * พจนานุกรมทำเล (ย่าน/สถานี/จังหวัด) ที่ใช้จับคำในข้อความ
     * เรียงคำยาวก่อนเพื่อให้ "สุขุมวิท 117" ถูกจับได้ก่อน "สุขุมวิท"
     */
    function ai_search_known_locations(): array
    {
        return [
            // กรุงเทพฯ เขตหลัก (รองรับทั้ง "เขตวัฒนา" และ "วัฒนา")
            'วัฒนา', 'คลองเตย', 'คลองสาน', 'คลองสามวา', 'คลองหลวง',
            'สาทร', 'บางรัก', 'ปทุมวัน', 'ดินแดง', 'ราชเทวี', 'พญาไท',
            'ห้วยขวาง', 'จตุจักร', 'บางเขน', 'หลักสี่', 'ดอนเมือง', 'สายไหม',
            'บึงกุ่ม', 'คันนายาว', 'สะพานสูง', 'ประเวศ', 'สวนหลวง',
            'พระโขนง', 'วัฒนา', 'ธนบุรี', 'บางกอกน้อย', 'ตลิ่งชัน',
            'ภาษีเจริญ', 'บางแค', 'หนองแขม', 'จอมทอง', 'ราษฎร์บูรณะ',
            'กรุงเทพ', 'กรุงเทพมหานคร', 'bangkok',
            // กรุงเทพฯ ย่านหลัก
            'สุขุมวิท 117', 'สุขุมวิท', 'อโศก', 'อโศกมนตรี', 'นานา', 'เพลินจิต', 'ชิดลม', 'พร้อมพงษ์',
            'ทองหล่อ', 'เอกมัย', 'พระโขนง', 'อ่อนนุช', 'บางนา', 'ศรีนครินทร์', 'แหลมทอง', 'ศรีนครินทร์',
            'ลาดพร้าว', 'รัชดา', 'รัชดาภิเษก', 'ห้วยขวาง', 'จตุจักร', 'บางซื่อ', 'อารีย์', 'พหลโยธิน',
            'สาทร', 'สีลม', 'สุรศักดิ์', 'บางรัก', 'จรัญสนิทวงศ์', 'ตลาดพลู', 'บางขุนนท์',
            'บางกะปิ', 'รามคำแหง', 'รามอินทรา', 'มีนบุรี', 'ลาดกระบัง', 'รังสิต', 'ดอนเมือง',
            'ปากน้ำ', 'สมุทรปราการ', 'บางพลี', 'บางเสาธง', 'เทพารักษ์', 'สำโรง', 'แบริ่ง', 'ปู่เจ้าสมิงพราย',
            'นนทบุรี', 'บางใหญ่', 'แคราย', 'ปากเกร็ด', 'นนทบุรี',
            'นครปฐม', 'พุทธมณฑล', 'ศาลายา',
            // ต่างจังหวัด
            'เชียงใหม่', 'เชียงราย', 'ขอนแก่น', 'อุดรธานี', 'อุบลราชธานี', 'นครราชสีมา', 'โคราช',
            'พัทยา', 'หัวหิน', 'ชะอำ', 'ระยอง', 'จันทบุรี', 'ตราด', 'ภูเก็ต', 'สุราษฎร์ธานี', 'เกาะสมุย',
            'หาดใหญ่', 'สงขลา', 'ตรัง', 'กระบี่', 'พังงา', 'สกลนคร', 'ร้อยเอ็ด', 'มหาสารคาม',
            'ลพบุรี', 'สระบุรี', 'ราชบุรี', 'กาญจนบุรี', 'เพชรบุรี', 'อ่างทอง', 'พระนครศรีอยุธยา', 'อยุธยา',
        ];
    }
}

if (!function_exists('ai_search_normalize_th')) {
    /** ทำความสะอาดข้อความ: แปลงอังกฤษเป็นตัวพิมพ์เล็ก ลบช่องว่างซ้ำ */
    function ai_search_normalize_th(string $text): string
    {
        $text = str_replace(["\u{200B}", "\u{00A0}"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        return mb_strtolower($text);
    }
}

if (!function_exists('ai_search_parse_number')) {
    /** แปลงตัวเลขในข้อความ (รองรับ 2.5, 2,500,000, ๒) */
    function ai_search_parse_number(string $raw): ?float
    {
        $thaiDigits = ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9'];
        $raw = strtr($raw, $thaiDigits);
        $raw = str_replace([',', ' '], '', $raw);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        return (float)$raw;
    }
}

if (!function_exists('ai_search_parse_budget')) {
    /**
     * ดึงงบประมาณจากข้อความ (คืน [min, max] ในหน่วยบาท)
     * รองรับ: "2 ล้าน", "2.5 ล้าน", "3,000,000", "500k", "ไม่เกิน 2 ล้าน", "งบ 2-3 ล้าน", "ราคาไม่แพง"
     */
    function ai_search_parse_budget(string $text): array
    {
        $budgetMin = null;
        $budgetMax = null;

        // ตัวคูณ: ล้าน / แสน / พัน / k / m
        $unitMap = [
            'ล้าน' => 1000000, 'ล.' => 1000000, 'm' => 1000000, 'mb' => 1000000,
            'แสน' => 100000, 'พัน' => 1000, 'k' => 1000, 'บาท' => 1,
        ];

        $phpNum = '(\d+(?:[.,]\d+)?)';

        // รูปแบบช่วง: 2-3 ล้าน / 2 ถึง 3 ล้าน / 2.5-3 ล้าน
        if (preg_match('/' . $phpNum . '\s*(?:-|–|ถึง|ไปจนถึง|~)\s*' . $phpNum . '\s*(ล้าน|แสน|พัน|k|m|ล\.)/iu', $text, $m)) {
            $unitCurrency = $m[3];
            $mult = $unitMap[mb_strtolower($unitCurrency)] ?? 1;
            $low = ai_search_parse_number($m[1]);
            $high = ai_search_parse_number($m[2]);
            if ($low !== null && $high !== null) {
                $budgetMin = (int)round($low * $mult);
                $budgetMax = (int)round($high * $mult);
            }
        }

        // รูปแบบเดี่ยว + หน่วย: 2 ล้าน / 2.5 ล้านบาท / 800k / 2m
        if ($budgetMax === null
            && preg_match('/' . $phpNum . '\s*(ล้านบาท|ล้าน|แสนบาท|แสน|พันบาท|พัน|k|m|ล\.)/iu', $text, $m)) {
            $value = ai_search_parse_number($m[1]);
            $unitCurrency = mb_strtolower($m[2]);
            $mult = $unitMap[$unitCurrency] ?? null;
            if ($mult === null) {
                // เผื่อหน่วยผสม เช่น "ล้านบาท" ให้ตรวจอีกครั้ง
                foreach ($unitMap as $key => $candidate) {
                    if (mb_strpos($unitCurrency, $key) !== false) {
                        $mult = $candidate;
                        break;
                    }
                }
            }
            if ($value !== null && $mult !== null) {
                $budgetMax = (int)round($value * $mult);
            }
        }

        // รูปแบบเลขเต็ม: 2,500,000 / 2500000 บาท / 15000 บาท (ค่าเช่า)
        if ($budgetMax === null && preg_match('/(\d{1,3}(?:,\d{3})+|\d{5,})\s*(?:บาท|บ\.)?/u', $text, $m)) {
            $value = ai_search_parse_number($m[1]);
            if ($value !== null && $value >= 1000) {
                $budgetMax = (int)$value;
            }
        }

        // คำว่า "ไม่แพง" / "ราคาถูก" / "งบจำกัด" => กำหนดเพดานแบบผ่อนปรน
        if ($budgetMax === null && preg_match('/(ไม่แพง|ราคาถูก|งบจำกัด|ประหยัด)/u', $text)) {
            $budgetMax = 3000000;
        }

        // "ไม่เกิน 2 ล้าน" => ถือเป็นเพดานเดียว
        if ($budgetMax !== null && $budgetMin !== null && $budgetMax < $budgetMin) {
            [$budgetMin, $budgetMax] = [$budgetMax, null];
        }

        return [$budgetMin, $budgetMax];
    }
}

if (!function_exists('ai_search_parse_rooms')) {
    /** ดึงจำนวนห้องนอน/ห้องน้ำ/ที่จอดรถ (ค่าแบบ "ขั้นต่ำ") */
    function ai_search_parse_rooms(string $text): array
    {
        $bedrooms = null;
        $bathrooms = null;
        $parking = null;
        $area = null;

        if (preg_match('/(\d+)\s*(?:ห้องนอน|นอน|bedroom|bed|br)(?![a-z])/iu', $text, $m)) {
            $bedrooms = (int)$m[1];
        }
        if (preg_match('/(\d+)\s*(?:ห้องน้ำ|น้ำ|bathroom|bath|wc)(?![a-z])/iu', $text, $m)) {
            $bathrooms = (int)$m[1];
        }
        if (preg_match('/(\d+)\s*(?:ที่จอด|cัน|คันที่จอด|parking)/iu', $text, $m)) {
            $parking = (int)$m[1];
        } elseif (preg_match('/(?:มี)?\s*(?:ที่จอดรถ|ที่จอด|parking)/iu', $text)) {
            $parking = 1;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:ตร\.?\s?ม|ตารางเมตร|sqm|sq\.?\s?m)/iu', $text, $m)) {
            $area = (float)$m[1];
        }
        // "ห้องใหญ่" => พื้นที่ขั้นต่ำประมาณ 50 ตร.ม.
        if ($area === null && preg_match('/(ห้องใหญ่|พื้นที่เยอะ|กว้าง)/u', $text)) {
            $area = 50.0;
        }

        return [$bedrooms, $bathrooms, $parking, $area];
    }
}

if (!function_exists('ai_search_parse_category')) {
    function ai_search_parse_category(string $text): ?string
    {
        foreach (ai_search_category_keywords() as $key => $words) {
            foreach ($words as $word) {
                if (mb_strpos($text, mb_strtolower($word)) !== false) {
                    return $key;
                }
            }
        }
        return null;
    }
}

if (!function_exists('ai_search_parse_type')) {
    function ai_search_parse_type(string $text): ?string
    {
        $rentWords = ['ให้เช่า', 'เช่า', ' rents', 'rent ', 'rental', 'ผ่อนเช่า', 'รายเดือน'];
        $saleWords = ['ขาย', 'ซื้อ', 'ซื้อขาย', 'sale', 'for sale', 'มือสอง'];
        if (preg_match('/(ให้เช่า|เช่า|rent)/iu', $text)) {
            return 'rent';
        }
        foreach ($saleWords as $word) {
            if (mb_strpos($text, $word) !== false) {
                return 'sale';
            }
        }
        return null;
    }
}

if (!function_exists('ai_search_parse_locations')) {
    function ai_search_parse_locations(string $text): array
    {
        // ตัดคำนำหน้าที่ผู้ใช้มักพิมพ์ (จังหวัด/อำเภอ/เขต/ตำบล) ออกก่อนจับคำ
        // เช่น "จังหวัดภูเก็ต" -> "ภูเก็ต", "อำเภอเมืองภูเก็ต" -> "เมืองภูเก็ต"
        $needleText = preg_replace('/(จังหวัด|จ\.|อำเภอ|อ\.|เขต|ตำบล|ต\.|แขวง)\s*/u', '', $text) ?? $text;

        $found = [];
        foreach (ai_search_known_locations() as $place) {
            $needle = mb_strtolower($place);
            if (mb_strpos($text, $needle) !== false || mb_strpos($needleText, $needle) !== false) {
                $found[] = $place;
            }
        }
        // ลบคำที่ซ้ำซ้อน (เช่น "สุขุมวิท" เมื่อจับได้ "สุขุมวิท 117" แล้ว)
        $filtered = [];
        foreach ($found as $place) {
            $covered = false;
            foreach ($found as $other) {
                if ($other !== $place && mb_strlen($other) > mb_strlen($place) && mb_strpos($other, $place) !== false) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $filtered[] = $place;
            }
        }
        return array_values(array_unique($filtered));
    }
}

if (!function_exists('ai_search_parse_amenities')) {
    function ai_search_parse_amenities(string $text): array
    {
        $hits = [];
        foreach (ai_search_amenity_catalog() as $key => $words) {
            foreach ($words as $word) {
                if (mb_strpos($text, mb_strtolower($word)) !== false) {
                    $hits[] = $key;
                    break;
                }
            }
        }
        // ที่จอดรถถูกจับเป็น amenity ด้วย แต่มีคอลัมน์เฉพาะ (parking_spaces) แล้ว
        return array_values(array_unique($hits));
    }
}

if (!function_exists('ai_search_parse_transit')) {
    function ai_search_parse_transit(string $text): bool
    {
        return (bool)preg_match('/(ใกล้รถไฟฟ้า|ติดรถไฟฟ้า|รถไฟฟ้า|bts|mrt|ใกล้สถานี|ติดสถานี|ใกล้รถไฟ|airport rail|arl)/iu', $text);
    }
}

if (!function_exists('ai_search_parse_query')) {
    /**
     * Entry point: แปลงข้อความภาษาธรรมชาติเป็นโครงสร้างเงื่อนไข
     */
    function ai_search_parse_query(string $query): array
    {
        $text = ai_search_normalize_th($query);

        [$budgetMin, $budgetMax] = ai_search_parse_budget($text);
        [$bedrooms, $bathrooms, $parking, $area] = ai_search_parse_rooms($text);

        $soft = [];
        if (preg_match('/(บรรยากาศดี|เงียบ|สงบ|น่าอยู่)/u', $text)) {
            $soft[] = 'quiet';
        }
        if (preg_match('/(ลงทุน|ผลตอบแทน|ปล่อยเช่า|yield)/iu', $text)) {
            $soft[] = 'investment';
        }
        if (preg_match('/(พร้อมอยู่|พร้อมเข้าอยู่)/u', $text)) {
            $soft[] = 'ready_to_move';
        }

        return [
            'type'          => ai_search_parse_type($text),
            'category'      => ai_search_parse_category($text),
            'budget_max'    => $budgetMax,
            'budget_min'    => $budgetMin,
            'locations'     => ai_search_parse_locations($text),
            'bedrooms_min'  => $bedrooms,
            'bathrooms_min' => $bathrooms,
            'parking_min'   => $parking,
            'area_min'      => $area,
            'amenities'     => ai_search_parse_amenities($text),
            'transit'       => ai_search_parse_transit($text),
            'soft'          => $soft,
            'raw'           => trim($query),
        ];
    }
}

if (!function_exists('ai_search_criteria_has_content')) {
    /** ตรวจว่าผู้ใช้ระบุเงื่อนไขที่นำไปค้นหาได้จริงอย่างน้อยหนึ่งอย่าง */
    function ai_search_criteria_has_content(array $criteria): bool
    {
        return !empty($criteria['type'])
            || !empty($criteria['category'])
            || !empty($criteria['budget_max'])
            || !empty($criteria['budget_min'])
            || !empty($criteria['locations'])
            || !empty($criteria['bedrooms_min'])
            || !empty($criteria['bathrooms_min'])
            || !empty($criteria['parking_min'])
            || !empty($criteria['area_min'])
            || !empty($criteria['amenities'])
            || !empty($criteria['transit']);
    }
}

if (!function_exists('ai_search_criteria_labels')) {
    /** แปลงเงื่อนไขเป็นข้อความอ่านง่ายสำหรับแสดงใน UI */
    function ai_search_criteria_labels(array $criteria): array
    {
        $labels = [];

        if (!empty($criteria['type'])) {
            $labels[] = t('ประเภทการซื้อขาย: ', 'Purpose: ') . type_label((string)$criteria['type']);
        }
        if (!empty($criteria['category'])) {
            $labels[] = t('ประเภทอสังหาฯ: ', 'Type: ') . category_label((string)$criteria['category']);
        }
        if (!empty($criteria['budget_min']) && !empty($criteria['budget_max'])) {
            $labels[] = t('งบประมาณ: ', 'Budget: ') . number_format((int)$criteria['budget_min']) . ' – ' . number_format((int)$criteria['budget_max']) . t(' บาท', ' THB');
        } elseif (!empty($criteria['budget_max'])) {
            $labels[] = t('งบไม่เกิน: ', 'Budget up to: ') . number_format((int)$criteria['budget_max']) . t(' บาท', ' THB');
        } elseif (!empty($criteria['budget_min'])) {
            $labels[] = t('งบขั้นต่ำ: ', 'Budget from: ') . number_format((int)$criteria['budget_min']) . t(' บาท', ' THB');
        }
        if (!empty($criteria['locations'])) {
            $labels[] = t('ทำเล: ', 'Location: ') . implode(', ', $criteria['locations']);
        }
        if (!empty($criteria['bedrooms_min'])) {
            $labels[] = t('ห้องนอนขั้นต่ำ: ', 'Min bedrooms: ') . (int)$criteria['bedrooms_min'];
        }
        if (!empty($criteria['bathrooms_min'])) {
            $labels[] = t('ห้องน้ำขั้นต่ำ: ', 'Min bathrooms: ') . (int)$criteria['bathrooms_min'];
        }
        if (!empty($criteria['parking_min'])) {
            $labels[] = t('ที่จอดรถขั้นต่ำ: ', 'Min parking: ') . (int)$criteria['parking_min'];
        }
        if (!empty($criteria['area_min'])) {
            $labels[] = t('พื้นที่ขั้นต่ำ: ', 'Min area: ') . (float)$criteria['area_min'] . ' ตร.ม.';
        }
        if (!empty($criteria['amenities'])) {
            $catalog = ai_search_amenity_catalog();
            $names = [];
            foreach ($criteria['amenities'] as $key) {
                $names[] = t($catalog[$key][0] ?? $key, $key);
            }
            $labels[] = t('สิ่งอำนวยความสะดวก: ', 'Amenities: ') . implode(', ', $names);
        }
        if (!empty($criteria['transit'])) {
            $labels[] = t('ใกล้ระบบราง: ใช่', 'Near transit: yes');
        }

        return $labels;
    }
}
