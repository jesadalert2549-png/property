<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=900');

$latRaw = isset($_GET['lat']) ? trim((string)$_GET['lat']) : '';
$lngRaw = isset($_GET['lng']) ? trim((string)$_GET['lng']) : '';
$lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
$lng = filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = (float)$lat;
$lng = (float)$lng;
$radius = 2000;
$query = '[out:json][timeout:8];(nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[amenity~"^(hospital|clinic|doctors|dentist|pharmacy|school|kindergarten|childcare|university|bank|marketplace|library|community_centre|police|fire_station|cafe|restaurant|fast_food|atm|fuel|charging_station|parking|post_office|place_of_worship|bus_station)$"];nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[shop~"^(supermarket|convenience|bakery|mall|department_store|clothes|chemist|books|hardware)$"];nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[leisure~"^(park|playground|sports_centre|swimming_pool|fitness_centre)$"];nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[public_transport~"^(station|stop_position|platform)$"];nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[railway=station];nwr(around:' . $radius . ',' . $lat . ',' . $lng . ')[highway=bus_stop];);out center tags;';
$body = http_build_query(['data' => $query]);
$endpoints = [
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass-api.de/api/interpreter',
];

// Circuit breaker: remember Overpass failures so later requests skip the dead mirrors
$overpassCooldown = sys_get_temp_dir() . '/kasatest_overpass_down.flag';
if (file_exists($overpassCooldown) && (time() - filemtime($overpassCooldown)) < 300) {
    $endpoints = [];
}

// Only give the mirrors one shot: if the first one times out we assume the
// network blocks Overpass and fall through to Photon instead of waiting twice.
$endpoints = array_slice($endpoints, 0, 1);

foreach ($endpoints as $endpoint) {
    $responseBody = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: PropertyGO/1.0 (nearby amenities)'],
        ]);
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($responseBody !== false && $status >= 200 && $status < 300) {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded) && isset($decoded['elements'])) {
                echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: PropertyGO/1.0 (nearby amenities)\r\n",
            'content' => $body,
            'timeout' => 6,
            'ignore_errors' => true,
        ]]);
        $responseBody = @file_get_contents($endpoint, false, $context);
        if ($responseBody !== false) {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded) && isset($decoded['elements'])) {
                echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
    }
}

if ($endpoints !== false) {
    @touch($overpassCooldown);
}

// Fallback: Photon reverse lookup (works when Overpass is unreachable)
$photonUrl = 'https://photon.komoot.io/reverse?' . http_build_query([
    'lat' => $lat,
    'lon' => $lng,
    'limit' => 30,
]);
$photonRaw = false;
if (function_exists('curl_init')) {
    $curl = curl_init($photonUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['User-Agent: PropertyGO/1.0 (nearby amenities)'],
    ]);
    $photonRaw = curl_exec($curl);
    $photonStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($photonStatus < 200 || $photonStatus >= 300) {
        $photonRaw = false;
    }
} else {
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: PropertyGO/1.0 (nearby amenities)\r\n",
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $photonRaw = @file_get_contents($photonUrl, false, $context);
}

if ($photonRaw !== false) {
    $photon = json_decode($photonRaw, true);
    if (is_array($photon) && !empty($photon['features'])) {
        $elements = [];
        foreach ($photon['features'] as $feature) {
            $props = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : [];
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }
            $name = $props['name'] ?? null;
            if ($name === null || $name === '') {
                $name = $props['street'] ?? ($props['city'] ?? null);
            }
            if ($name === null || $name === '') {
                continue;
            }
            $category = $props['osm_value'] ?? ($props['type'] ?? 'place');
            $key = $props['osm_key'] ?? 'place';
            $elements[] = [
                'type' => 'node',
                'id' => $props['osm_id'] ?? null,
                'lat' => (float)$coords[1],
                'lon' => (float)$coords[0],
                'tags' => [
                    'name' => $name,
                    $key => $category,
                ],
            ];
        }
        if ($elements) {
            echo json_encode([
                'source' => 'photon',
                'elements' => $elements,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

http_response_code(502);
echo json_encode(['error' => 'amenities_unavailable'], JSON_UNESCAPED_UNICODE);
