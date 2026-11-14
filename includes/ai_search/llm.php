<?php
/**
 * PropertyGO AI Deep Search — LLM interpretation layer (optional)
 *
 * แยกส่วน "AI จริง" ออกจาก "prototype" อย่างชัดเจน:
 *   - ถ้าตั้งค่า AI_API_KEY ผ่าน environment -> เรียก LLM แปลงข้อความเป็น JSON เงื่อนไข
 *   - ถ้าไม่ตั้งค่า หรือเรียกไม่สำเร็จ -> fallback ไปใช้ rule-based parser (prototype)
 *
 * ความปลอดภัย:
 *   - API Key อ่านจาก environment variable เท่านั้น (ไม่ hardcode, ไม่ส่งไป frontend)
 *   - LLM ไม่เคยสร้างประกาศ — ทำได้แค่แปลงข้อความเป็นเงื่อนไข
 *   - ผลลัพธ์จาก LLM ถูก sanitize/validate ทุกฟิลด์ก่อนใช้
 */

require_once __DIR__ . '/parser.php';

if (!function_exists('ai_search_llm_config')) {
    /**
     * อ่านการตั้งค่า AI จาก environment
     * รองรับ: OPENAI_API_KEY, AI_API_KEY + AI_BASE_URL, AI_MODEL
     */
    function ai_search_llm_config(): array
    {
        $apiKey = getenv('OPENAI_API_KEY') ?: getenv('AI_API_KEY') ?: '';
        $baseUrl = getenv('AI_BASE_URL') ?: 'https://api.openai.com/v1';
        $model = getenv('AI_MODEL') ?: 'gpt-4o-mini';

        return [
            'enabled'  => $apiKey !== '',
            'api_key'  => $apiKey,
            'base_url' => rtrim($baseUrl, '/'),
            'model'    => $model,
        ];
    }
}

if (!function_exists('ai_search_llm_available')) {
    function ai_search_llm_available(): bool
    {
        $cfg = ai_search_llm_config();
        return $cfg['enabled'] && function_exists('curl_init');
    }
}

if (!function_exists('ai_search_llm_system_prompt')) {
    /**
     * System prompt: บังคับให้ LLM ตอบเป็น JSON ตาม schema ที่กำหนดเท่านั้น
     */
    function ai_search_llm_system_prompt(): string
    {
        $categories = implode(', ', array_keys(ai_search_category_keywords()));
        return <<<PROMPT
You convert Thai natural-language property search requests into a strict JSON object.

Return ONLY minified JSON with exactly these keys:
{"type":"sale"|"rent"|null,
 "category":"{$categories}"|null,
 "budget_max":integer|null,
 "budget_min":integer|null,
 "locations":string[],
 "bedrooms_min":integer|null,
 "bathrooms_min":integer|null,
 "parking_min":integer|null,
 "area_min":number|null,
 "amenities":string[],
 "transit":boolean}

Rules:
- budget values are in THB. "2 ล้าน" = 2000000. "2.5 ล้าน" = 2500000. "800k" = 800000.
- If the user does not specify something, output null (or [] for arrays). NEVER invent constraints.
- amenities must come only from: fitness, pool, security, cctv, garden, wifi, elevator, furniture, aircon.
- parking is captured by parking_min, not by amenities.
- transit=true only when the user mentions BTS/MRT/รถไฟฟ้า/ใกล้สถานี.
- locations: keep the user's original Thai wording (e.g. "สุขุมวิท", "ปากน้ำ", "บางนา").
PROMPT;
    }
}

if (!function_exists('ai_search_llm_parse_query')) {
    /**
     * เรียก LLM เพื่อแปลงข้อความ -> เงื่อนไข
     * คืน null หากไม่พร้อมใช้งานหรือเกิดข้อผิดพลาด (ให้ caller fallback)
     */
    function ai_search_llm_parse_query(string $query, int $timeoutSeconds = 12): ?array
    {
        if (!ai_search_llm_available()) {
            return null;
        }
        $cfg = ai_search_llm_config();

        $payload = [
            'model' => $cfg['model'],
            'temperature' => 0,
            'max_tokens' => 400,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => ai_search_llm_system_prompt()],
                ['role' => 'user', 'content' => $query],
            ],
        ];

        $ch = curl_init($cfg['base_url'] . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['api_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string)$raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            return null;
        }

        return ai_search_llm_sanitize($parsed, $query);
    }
}

if (!function_exists('ai_search_llm_sanitize')) {
    /**
     * ตรวจสอบและจำกัดค่าที่ได้จาก LLM (defensive — ไม่เชื่อ LLM โดยตรง)
     */
    function ai_search_llm_sanitize(array $parsed, string $originalQuery): array
    {
        $allowedCategories = array_keys(ai_search_category_keywords());
        $allowedAmenities = array_keys(ai_search_amenity_catalog());

        $type = $parsed['type'] ?? null;
        $type = in_array($type, ['sale', 'rent'], true) ? $type : null;

        $category = $parsed['category'] ?? null;
        $category = (is_string($category) && in_array($category, $allowedCategories, true)) ? $category : null;

        $toInt = static function ($v): ?int {
            if ($v === null || $v === '' || !is_numeric($v)) {
                return null;
            }
            $n = (int)round((float)$v);
            return $n > 0 ? $n : null;
        };

        $locations = [];
        if (!empty($parsed['locations']) && is_array($parsed['locations'])) {
            foreach ($parsed['locations'] as $loc) {
                if (is_string($loc) && trim($loc) !== '' && mb_strlen($loc) <= 60) {
                    $locations[] = trim($loc);
                }
            }
        }

        $amenities = [];
        if (!empty($parsed['amenities']) && is_array($parsed['amenities'])) {
            foreach ($parsed['amenities'] as $a) {
                if (is_string($a) && in_array($a, $allowedAmenities, true)) {
                    $amenities[] = $a;
                }
            }
        }

        $area = $parsed['area_min'] ?? null;
        $area = (is_numeric($area) && (float)$area > 0) ? round((float)$area, 2) : null;

        return [
            'type'          => $type,
            'category'      => $category,
            'budget_max'    => $toInt($parsed['budget_max'] ?? null),
            'budget_min'    => $toInt($parsed['budget_min'] ?? null),
            'locations'     => array_values(array_unique($locations)),
            'bedrooms_min'  => $toInt($parsed['bedrooms_min'] ?? null),
            'bathrooms_min' => $toInt($parsed['bathrooms_min'] ?? null),
            'parking_min'   => $toInt($parsed['parking_min'] ?? null),
            'area_min'      => $area,
            'amenities'     => array_values(array_unique($amenities)),
            'transit'       => (bool)($parsed['transit'] ?? false),
            'soft'          => [],
            'raw'           => $originalQuery,
        ];
    }
}

if (!function_exists('ai_search_interpret_query')) {
    /**
     * Entry point หลัก: แปลงข้อความ -> เงื่อนไข
     * ใช้ LLM ก่อน (ถ้ามี) แล้ว fallback เป็น rule-based prototype
     *
     * คืนค่า: [criteria, engine] โดย engine = 'llm' หรือ 'prototype'
     */
    function ai_search_interpret_query(string $query): array
    {
        $llm = ai_search_llm_parse_query($query);
        if (is_array($llm)) {
            return ['criteria' => $llm, 'engine' => 'llm'];
        }
        return ['criteria' => ai_search_parse_query($query), 'engine' => 'prototype'];
    }
}
