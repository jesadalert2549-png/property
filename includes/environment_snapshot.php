<?php
/** Visual environment snapshot for a property with precise coordinates. */
if (!function_exists('render_environment_snapshot')) {
    function render_environment_snapshot(array $property): void
    {
        $lat = filter_var($property['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($property['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || abs((float)$lat) > 90 || abs((float)$lng) > 180) return;
        $lat = (float)$lat;
        $lng = (float)$lng;
        $location = (string)($property['location'] ?? '');
        ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.env-snapshot{position:relative;isolation:isolate;overflow:hidden;border:1px solid rgba(255,255,255,.85);border-radius:28px;background:linear-gradient(145deg,#fff 0%,#f6f8fb 58%,#edf2f8 100%);box-shadow:0 18px 50px rgba(11,28,51,.09),inset 0 1px 0 #fff}
.env-snapshot,.env-snapshot *{box-sizing:border-box}
.env-snapshot p,.env-snapshot small{color:#475569}
.env-snapshot .text-slate-400{color:#526176!important}.env-snapshot .text-slate-500{color:#43546a!important}.env-snapshot .text-slate-600{color:#334155!important}
.env-snapshot .text-\[10px\]{font-size:12px!important;line-height:1.5}.env-snapshot .text-\[11px\]{font-size:13px!important;line-height:1.5}.env-snapshot .text-xs{font-size:13px!important;line-height:1.5}
.env-snapshot .env-stat-label{font-size:12px;color:#40516a}.env-snapshot .env-live{font-size:11px;color:#216344}
.env-snapshot .env-section-caption{font-size:13px;line-height:1.5;color:#43546a}
.env-snapshot .env-footnote{font-size:12px;line-height:1.55;color:#526176}
.env-snapshot:before{content:"";position:absolute;z-index:-1;width:280px;height:280px;right:-110px;top:-170px;border-radius:50%;background:radial-gradient(circle,rgba(197,160,89,.18),transparent 70%);pointer-events:none}
.env-snapshot-card{min-width:0;padding:18px;border:1px solid rgba(226,232,240,.85);border-radius:22px;background:rgba(255,255,255,.82);box-shadow:0 8px 24px rgba(11,28,51,.055),inset 0 1px 0 #fff;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.env-snapshot .env-stat{padding:12px;border-radius:17px;background:#f6f8fb}.env-snapshot .env-stat-value{font-size:26px;line-height:1;font-weight:850;letter-spacing:-.04em;color:#0b1c33}.env-snapshot .env-stat-label{margin-top:7px;color:#64748b;font-size:11px;font-weight:700}
.env-snapshot .env-live{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #dce9e2;border-radius:999px;background:#f2faf5;color:#397456;font-size:10px;font-weight:800;white-space:nowrap}.env-snapshot .env-live i{width:7px;height:7px;border-radius:50%;background:#22a06b;box-shadow:0 0 0 3px rgba(34,160,107,.12)}
.env-snapshot .env-sun-svg{display:block;width:100%;max-width:280px;height:auto;margin:4px auto 0}.env-snapshot .env-route-map{display:block;width:100%;height:250px;border:1px solid #d7e0e9;border-radius:16px;background:#eaf1f5;overflow:hidden;z-index:0}.env-snapshot .leaflet-container{font:12px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;color:#24364b}.env-snapshot .leaflet-control-attribution{font-size:10px;background:rgba(255,255,255,.88)}.env-snapshot .leaflet-control-attribution a{color:#205b86}.env-snapshot .env-route-path{fill:none;stroke:#fff;stroke-width:8;stroke-linecap:round;stroke-linejoin:round}.env-snapshot .env-route-start{fill:#0b1c33;stroke:#fff;stroke-width:3}.env-snapshot .env-route-end{fill:#c5a059;stroke:#fff;stroke-width:3}
.env-snapshot .env-poi-panel{margin-top:12px;padding:12px;border:1px solid #e5eaf0;border-radius:15px;background:#f8fafc}.env-snapshot .env-poi-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:9px}.env-snapshot .env-poi-item{display:flex;min-width:0;align-items:center;gap:8px;border:1px solid #e7edf3;border-radius:12px;background:#fff;padding:8px 10px;color:#26374b;text-decoration:none}.env-snapshot .env-poi-item:hover{border-color:#9fc7e9;background:#f5faff}.env-snapshot .env-poi-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.env-snapshot .env-poi-item small{margin-left:auto;flex:0 0 auto;color:#64748b;font-size:11px}.env-snapshot .env-poi-dot{flex:0 0 9px;width:9px;height:9px;border-radius:50%;background:#2583c5;box-shadow:0 0 0 3px #e0f1ff}
.env-snapshot .env-route-input{min-width:0;width:100%;border:1px solid #dbe2eb;border-radius:14px;padding:11px 13px;color:#0b1c33;background:#fff;outline:none}.env-snapshot .env-route-input:focus{border-color:#c5a059;box-shadow:0 0 0 3px rgba(197,160,89,.15)}
.env-snapshot .env-route-submit{display:inline-flex;flex:0 0 auto;align-items:center;justify-content:center;min-width:64px;min-height:44px;padding:0 14px;border:0;border-radius:14px;background:linear-gradient(135deg,#0b1c33,#203a5c);color:#fff;font-size:13px;font-weight:800;line-height:1;white-space:nowrap;box-shadow:0 6px 16px rgba(11,28,51,.2);cursor:pointer}.env-snapshot .env-route-submit:disabled{opacity:.55;cursor:wait}
.env-snapshot .env-route-form{display:flex;align-items:stretch;gap:8px}.env-snapshot .env-route-form .env-route-input{flex:1 1 auto;width:1%;min-width:0}.env-snapshot .env-route-form .env-route-submit{width:auto}
.env-snapshot .env-sun-svg text{text-anchor:middle}.env-snapshot .env-sun-svg text:first-of-type{text-anchor:start}.env-snapshot .env-sun-svg text:last-of-type{text-anchor:end}
.env-snapshot .env-preset{padding:7px 10px;border:1px solid #e0e6ed;border-radius:999px;background:#fff;color:#45566b;font-size:11px;font-weight:700;cursor:pointer}.env-snapshot .env-preset:hover{border-color:#c5a059;color:#795d2d}
.env-snapshot .env-info-tile{display:flex;align-items:center;gap:11px;min-width:0;padding:13px;border:1px solid #e6ebf1;border-radius:17px;background:rgba(255,255,255,.84)}
.env-snapshot .env-info-icon{display:grid;flex:0 0 40px;width:40px;height:40px;place-items:center;border-radius:14px;background:#f1f5f9;color:#516174}.env-snapshot .env-info-icon svg{width:20px;height:20px}
@media(max-width:640px){.env-snapshot{border-radius:22px}.env-snapshot-card{padding:15px;border-radius:19px}.env-snapshot .env-route-map{height:240px}.env-snapshot .env-poi-list{grid-template-columns:1fr}.env-snapshot .env-stat-label{font-size:12px;color:#40516a}.env-snapshot .env-footnote{font-size:12px}}
</style>
<section class="env-snapshot mt-10 p-4 sm:p-7" id="environmentSnapshot" data-lat="<?= e((string)$lat) ?>" data-lng="<?= e((string)$lng) ?>" data-location="<?= e($location) ?>">
  <div class="relative mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><p class="text-[11px] font-black uppercase tracking-[.18em] text-[#A8843F]"><?= e(t('มองทำเลให้เห็นภาพ', 'See the neighbourhood clearly')) ?></p><h3 class="mt-1 text-xl font-extrabold tracking-tight text-ink sm:text-2xl"><?= e(t('ภาพรวมสภาพแวดล้อม', 'Environment snapshot')) ?></h3><p class="mt-1 text-xs text-slate-500"><?= e(t('ข้อมูลจริงเท่าที่ตรวจสอบได้ · ไม่แสดงคะแนนคาดเดา', 'Verified information only · no invented scores')) ?></p></div>
    <span class="env-live"><i></i><?= e(t('อากาศปัจจุบัน', 'Current weather')) ?></span>
  </div>
  <div class="grid gap-4 lg:grid-cols-[.92fr_1.08fr]">
    <article class="env-snapshot-card">
      <div class="flex items-center justify-between gap-2"><div><h4 class="text-sm font-extrabold text-ink"><?= e(t('แดดและอากาศตอนนี้', 'Sun & weather now')) ?></h4><p class="mt-1 text-[11px] text-slate-500"><?= e(t('คำนวณจากพิกัดประกาศ', 'Calculated from listing coordinates')) ?></p></div><span id="envWeatherIcon" class="grid h-11 w-11 place-items-center rounded-2xl bg-amber-50 text-2xl">☀️</span></div>
      <div class="env-stat-grid mt-5"><div class="env-stat"><div class="env-stat-value"><span id="envTemp">—</span><span class="ml-0.5 text-base">°</span></div><div class="env-stat-label"><?= e(t('อุณหภูมิ', 'Temperature')) ?></div></div><div class="env-stat"><div id="envUv" class="env-stat-value">—</div><div class="env-stat-label">UV</div></div><div class="env-stat"><div id="envWind" class="env-stat-value">—</div><div class="env-stat-label"><?= e(t('ลม กม./ชม.', 'Wind km/h')) ?></div></div></div>
      <svg class="env-sun-svg" viewBox="0 0 280 160" role="img" aria-label="Sun path diagram"><defs><linearGradient id="envSunGrad" x1="0" x2="1"><stop offset="0" stop-color="#cbd5e1"/><stop offset=".5" stop-color="#f2c66d"/><stop offset="1" stop-color="#cbd5e1"/></linearGradient><filter id="envSunGlow"><feGaussianBlur stdDeviation="4" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs><path d="M30 126 A110 100 0 0 1 250 126" fill="none" stroke="#e8edf3" stroke-width="12" stroke-linecap="round"/><path d="M30 126 A110 100 0 0 1 250 126" fill="none" stroke="url(#envSunGrad)" stroke-width="3" stroke-linecap="round" stroke-dasharray="4 7"/><line x1="140" y1="128" x2="140" y2="24" stroke="#d9e0e8" stroke-width="1.5" stroke-dasharray="4 5"/><circle cx="140" cy="128" r="15" fill="#0b1c33"/><path d="M132 132V126L140 119L148 126V132H143V127H137V132Z" fill="#f8d888"/><g id="envSunMarker" transform="translate(140 25)" filter="url(#envSunGlow)"><circle r="13" fill="#f7c85e"/><path d="M0-21v-4M0 21v4M-21 0h-4M21 0h4M-15-15l-3-3M15 15l3 3M15-15l3-3M-15 15l-3 3" stroke="#e6a936" stroke-width="2" stroke-linecap="round"/></g><text x="24" y="151" fill="#64748b" font-size="10" font-weight="700"><?= e(t('เช้า', 'AM')) ?></text><text x="140" y="151" text-anchor="middle" fill="#64748b" font-size="10" font-weight="700"><?= e(t('เที่ยง', 'NOON')) ?></text><text x="228" y="151" fill="#64748b" font-size="10" font-weight="700"><?= e(t('เย็น', 'PM')) ?></text></svg>
      <div class="mt-1 flex items-center justify-between rounded-xl bg-amber-50/80 px-3 py-2 text-xs"><span id="envSunSummary" class="font-bold text-slate-600"><?= e(t('กำลังคำนวณตำแหน่งดวงอาทิตย์…', 'Calculating sun position…')) ?></span><span class="font-extrabold text-amber-800"><span id="envSunrise">—</span> – <span id="envSunset">—</span></span></div>
      <p class="mt-2 text-[10px] leading-relaxed text-slate-400"><?= e(t('แสดงตำแหน่งดวงอาทิตย์ปัจจุบัน ไม่ใช่ทิศหน้าบ้าน ต้องยืนยันแนวอาคารก่อนประเมินแดดเข้าตัวบ้าน', 'Shows current sun position, not building orientation. Confirm the building facing direction before assessing indoor sunlight.')) ?></p>
    </article>
    <article class="env-snapshot-card">
      <div class="flex items-start justify-between gap-3"><div><h4 class="text-sm font-extrabold text-ink"><?= e(t('ระยะเดินทางจากที่นี่', 'Route from this property')) ?></h4><p class="mt-1 text-[11px] text-slate-500"><?= e(t('ใส่ปลายทางเพื่อดูเส้นทางและถนนรอบข้างบนแผนที่', 'Enter a destination to see the route and surrounding streets on a map')) ?></p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-blue-50 text-lg">🧭</span></div>
      <form id="envRouteForm" class="env-route-form mt-4"><label class="sr-only" for="envDestination"><?= e(t('ปลายทาง', 'Destination')) ?></label><input id="envDestination" class="env-route-input" type="text" autocomplete="street-address" placeholder="<?= e(t('เช่น สยามพารากอน', 'e.g. Siam Paragon')) ?>"><button id="envRouteSubmit" class="env-route-submit" type="submit"><?= e(t('ดูทาง', 'Route')) ?></button></form>
      <div class="env-presets mt-3"><button type="button" class="env-preset" data-destination="<?= e(t('สถานีรถไฟฟ้าใกล้ฉัน', 'nearest train station')) ?>">🚆 <?= e(t('รถไฟฟ้า', 'Transit')) ?></button><button type="button" class="env-preset" data-destination="<?= e(t('โรงพยาบาลใกล้ฉัน', 'nearest hospital')) ?>">🏥 <?= e(t('โรงพยาบาล', 'Hospital')) ?></button><button type="button" class="env-preset" data-destination="<?= e(t('สนามบินสุวรรณภูมิ', 'Suvarnabhumi Airport')) ?>">✈️ <?= e(t('สนามบิน', 'Airport')) ?></button></div>
      <div id="envRouteMap" class="env-route-map mt-4" role="img" aria-label="แผนที่ถนนและพื้นที่รอบเส้นทาง"></div>
      <section class="env-poi-panel" aria-labelledby="envPoiHeading"><div class="flex items-center justify-between gap-2"><h5 id="envPoiHeading" class="text-xs font-extrabold text-ink"><?= e(t('จุดอำนวยความสะดวกรอบบ้าน', 'Nearby amenities')) ?></h5><span id="envPoiCount" class="text-[11px] font-bold text-slate-500"></span></div><p id="envPoiStatus" class="mt-1 text-xs text-slate-500" aria-live="polite"><?= e(t('กำลังค้นหาโรงพยาบาล ร้านค้า สวนสาธารณะ และจุดเดินทางใกล้เคียง…', 'Searching nearby hospitals, shops, parks and transit…')) ?></p><div id="envPoiList" class="env-poi-list"></div></section>
      <div id="envRouteResult" class="mt-4 hidden" aria-live="polite"><div class="flex items-end justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500"><?= e(t('ระยะทางถนน', 'Road distance')) ?></p><p class="mt-1 text-xl font-black text-ink"><span id="envRouteDistance">—</span> <span class="text-xs font-bold text-slate-600">km</span></p></div><div class="text-right"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500"><?= e(t('เวลาโดยประมาณ', 'Estimated time')) ?></p><p class="mt-1 text-xl font-black text-ink"><span id="envRouteDuration">—</span> <span class="text-xs font-bold text-slate-600"><?= e(t('นาที', 'min')) ?></span></p></div></div></div>
      <p id="envRouteMessage" class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500" aria-live="polite"><?= e(t('ระยะทางและเวลาเป็นค่าประมาณจากเส้นทางถนน ไม่ใช่ข้อมูลจราจรสด', 'Distance and duration are road-route estimates, not live traffic data.')) ?></p>
      <a class="mt-3 inline-flex text-xs font-bold text-[#8C6B32] hover:underline" id="envOpenRoute" href="https://www.google.com/maps/dir/?api=1&origin=<?= e($lat . ',' . $lng) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('เปิดเส้นทางใน Google Maps ↗', 'Open directions in Google Maps ↗')) ?></a>
    </article>
  </div>
  <div class="env-info-grid mt-4">
    <div class="env-info-tile"><span class="env-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 18c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 7 0M3 12c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 7 0M12 3v5"/></svg></span><span><strong class="block text-xs text-ink"><?= e(t('ความเสี่ยงน้ำท่วม', 'Flood exposure')) ?></strong><small class="mt-1 block text-[10px] leading-snug text-slate-500"><?= e(t('ยังไม่มีประวัติระดับพื้นที่ที่ยืนยันได้', 'Verified local flood history is not connected')) ?></small></span></div>
    <div class="env-info-tile"><span class="env-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17h18M5 17l2-6h10l2 6M8 11l2-4h4l2 4M8 20h.01M16 20h.01"/></svg></span><span><strong class="block text-xs text-ink"><?= e(t('รถติดและเสียงรบกวน', 'Traffic & noise')) ?></strong><small class="mt-1 block text-[10px] leading-snug text-slate-500"><?= e(t('ไม่มีข้อมูลสด — แนะนำดูพื้นที่ช่วงเวลาเร่งด่วน', 'No live feed; visit at peak hours to assess')) ?></small></span></div>
    <a class="env-info-tile no-underline transition hover:border-amber-300" href="https://www.google.com/maps/search/?api=1&query=<?= e($lat . ',' . $lng) ?>" target="_blank" rel="noopener noreferrer"><span class="env-info-icon bg-amber-50 text-amber-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.3 7-12a7 7 0 1 0-14 0c0 6.7 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span><span><strong class="block text-xs text-ink"><?= e(t('สำรวจถนนรอบบ้าน', 'Explore nearby streets')) ?></strong><small class="mt-1 block text-[10px] text-slate-500"><?= e(t('เปิดแผนที่/ภาพถนน ↗', 'Open map/street imagery ↗')) ?></small></span></a>
  </div>
  <p class="env-footnote mt-3 text-[10px] text-slate-400"><?= e(t('อากาศจาก Open-Meteo · แผนที่และเส้นทางจาก OpenStreetMap/OSRM · เวลาไม่รวมรถติดสด · ความเสี่ยงน้ำท่วมและเสียงยังไม่มีฐานข้อมูลยืนยัน', 'Weather via Open-Meteo · maps and routes via OpenStreetMap/OSRM · duration excludes live traffic · verified flood/noise datasets are not connected yet')) ?></p>
</section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(() => {
  const root = document.getElementById('environmentSnapshot');
  if (!root || root.dataset.ready === '1') return;
  root.dataset.ready = '1';
  const lat = Number(root.dataset.lat), lon = Number(root.dataset.lng);
  const $ = id => root.querySelector('#' + id);
  const summary = $('envSunSummary');
  const fmt = new Intl.DateTimeFormat(document.documentElement.lang.toLowerCase().startsWith('th') ? 'th-TH' : 'en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
  const weatherUrl = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,wind_speed_10m,wind_direction_10m&hourly=uv_index&daily=sunrise,sunset,uv_index_max&timezone=auto&forecast_days=1`;
  fetch(weatherUrl, { signal: AbortSignal.timeout(9000) }).then(r => { if (!r.ok) throw new Error('weather'); return r.json(); }).then(data => {
    const c = data.current || {}, d = data.daily || {};
    $('envTemp').textContent = Number.isFinite(c.temperature_2m) ? Math.round(c.temperature_2m) : '—';
    $('envWind').textContent = Number.isFinite(c.wind_speed_10m) ? Math.round(c.wind_speed_10m) : '—';
    const uv = data.hourly?.uv_index || [];
    const now = new Date(c.time || Date.now());
    const hourIndex = Math.max(0, uv.findIndex((_, i) => data.hourly.time[i] >= (c.time || '')));
    $('envUv').textContent = Number.isFinite(uv[hourIndex]) ? Number(uv[hourIndex]).toFixed(1) : (d.uv_index_max?.[0] ?? '—');
    const code = Number(c.weather_code);
    $('envWeatherIcon').textContent = c.is_day === 0 ? '🌙' : (code >= 95 ? '⛈️' : code >= 51 ? '🌧️' : code >= 2 ? '⛅' : '☀️');
    const sunrise = d.sunrise?.[0] ? new Date(d.sunrise[0]) : null;
    const sunset = d.sunset?.[0] ? new Date(d.sunset[0]) : null;
    if (sunrise && sunset) {
      $('envSunrise').textContent = fmt.format(sunrise);
      $('envSunset').textContent = fmt.format(sunset);
      const progress = Math.max(0, Math.min(1, (now - sunrise) / (sunset - sunrise)));
      const theta = Math.PI * (1 - progress);
      const x = 140 + 110 * Math.cos(theta), y = 126 - 100 * Math.sin(theta);
      $('envSunMarker').setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)})`);
      summary.textContent = c.is_day === 0 ? (document.documentElement.lang.toLowerCase().startsWith('th') ? 'ดวงอาทิตย์ลับขอบฟ้าแล้ว' : 'Sun is below the horizon') : (document.documentElement.lang.toLowerCase().startsWith('th') ? 'ตำแหน่งดวงอาทิตย์วันนี้' : 'Sun position today');
    }
  }).catch(() => { summary.textContent = document.documentElement.lang.toLowerCase().startsWith('th') ? 'โหลดอากาศไม่สำเร็จ · ลองใหม่ภายหลัง' : 'Weather unavailable · try again later'; });

  const form = $('envRouteForm'), input = $('envDestination'), button = $('envRouteSubmit');
  const message = $('envRouteMessage'), result = $('envRouteResult');
  let routeMap = null, routeLayer = null, poiLayer = null;
  const setMessage = text => { message.textContent = text; };
  const loadNearbyAmenities = async () => {
    const status = $('envPoiStatus'), list = $('envPoiList'), count = $('envPoiCount');
    const isThai = document.documentElement.lang.toLowerCase().startsWith('th');
    const amenityLabels = { hospital: isThai ? 'โรงพยาบาล' : 'Hospital', clinic: isThai ? 'คลินิก' : 'Clinic', doctors: isThai ? 'คลินิกแพทย์' : 'Doctor', dentist: isThai ? 'ทันตแพทย์' : 'Dentist', pharmacy: isThai ? 'ร้านขายยา' : 'Pharmacy', veterinary: isThai ? 'คลินิกสัตว์' : 'Veterinary', school: isThai ? 'โรงเรียน' : 'School', kindergarten: isThai ? 'อนุบาล' : 'Kindergarten', childcare: isThai ? 'ศูนย์ดูแลเด็ก' : 'Childcare', university: isThai ? 'มหาวิทยาลัย' : 'University', bank: isThai ? 'ธนาคาร' : 'Bank', marketplace: isThai ? 'ตลาด' : 'Market', library: isThai ? 'ห้องสมุด' : 'Library', community_centre: isThai ? 'ศูนย์ชุมชน' : 'Community centre', police: isThai ? 'สถานีตำรวจ' : 'Police station', fire_station: isThai ? 'สถานีดับเพลิง' : 'Fire station', toilets: isThai ? 'ห้องน้ำสาธารณะ' : 'Public toilets', drinking_water: isThai ? 'จุดน้ำดื่ม' : 'Drinking water', post_box: isThai ? 'ตู้ไปรษณีย์' : 'Post box', recycling: isThai ? 'จุดรีไซเคิล' : 'Recycling point', supermarket: isThai ? 'ซูเปอร์มาร์เก็ต' : 'Supermarket', convenience: isThai ? 'ร้านสะดวกซื้อ' : 'Convenience store', bakery: isThai ? 'ร้านเบเกอรี่' : 'Bakery', mall: isThai ? 'ห้างสรรพสินค้า' : 'Shopping mall', department_store: isThai ? 'ห้างสรรพสินค้า' : 'Department store', clothes: isThai ? 'ร้านเสื้อผ้า' : 'Clothing store', butcher: isThai ? 'ร้านเนื้อ' : 'Butcher', hairdresser: isThai ? 'ร้านทำผม' : 'Hairdresser', park: isThai ? 'สวนสาธารณะ' : 'Park', playground: isThai ? 'สนามเด็กเล่น' : 'Playground', sports_centre: isThai ? 'ศูนย์กีฬา' : 'Sports centre', swimming_pool: isThai ? 'สระว่ายน้ำ' : 'Swimming pool', cinema: isThai ? 'โรงภาพยนตร์' : 'Cinema', theatre: isThai ? 'โรงละคร' : 'Theatre', cafe: isThai ? 'คาเฟ่' : 'Cafe', restaurant: isThai ? 'ร้านอาหาร' : 'Restaurant', fast_food: isThai ? 'อาหารจานด่วน' : 'Fast food', atm: isThai ? 'ตู้เอทีเอ็ม' : 'ATM', fuel: isThai ? 'ปั๊มน้ำมัน' : 'Fuel station', charging_station: isThai ? 'สถานีชาร์จรถไฟฟ้า' : 'EV charging', parking: isThai ? 'ที่จอดรถ' : 'Parking', post_office: isThai ? 'ไปรษณีย์' : 'Post office', place_of_worship: isThai ? 'ศาสนสถาน' : 'Place of worship', station: isThai ? 'สถานีขนส่ง' : 'Transit station', stop_position: isThai ? 'จุดจอดขนส่ง' : 'Transit stop', platform: isThai ? 'ชานชาลา' : 'Platform', bus_stop: isThai ? 'ป้ายรถเมล์' : 'Bus stop', tram_stop: isThai ? 'ป้ายรถราง' : 'Tram stop', aerodrome: isThai ? 'สนามบิน' : 'Airport' };
    const distance = (a, b) => { const rad = value => value * Math.PI / 180; const dLat = rad(b[0] - lat), dLon = rad(b[1] - lon), x = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat)) * Math.cos(rad(b[0])) * Math.sin(dLon / 2) ** 2; return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x)); };
    try {
      let elements = [];
      try {
        const nearbyUrl = new URL('api/nearby_amenities.php', window.location.href);
        nearbyUrl.searchParams.set('lat', lat);
        nearbyUrl.searchParams.set('lng', lon);
        const response = await fetch(nearbyUrl, { signal: AbortSignal.timeout(15000) });
        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data.elements)) elements = data.elements;
        }
      } catch (_) { /* Use the public geocoder fallback below if the local proxy is unavailable. */ }
      const places = elements.map(item => {
        const point = item.type === 'node' ? [item.lat, item.lon] : [item.center?.lat, item.center?.lon];
        const tags = item.tags || {}, kind = tags.amenity || tags.shop || tags.leisure || tags.public_transport || tags.highway || tags.railway || tags.aeroway;
        return { name: tags.name || amenityLabels[kind] || (isThai ? 'สถานที่ใกล้เคียง' : 'Nearby place'), type: amenityLabels[kind] || (isThai ? 'สถานที่' : 'Place'), point, km: point.every(Number.isFinite) ? distance(point, [lat, lon]) : Infinity };
      }).filter(place => Number.isFinite(place.km)).sort((a, b) => a.km - b.km).slice(0, 20);
      if (!places.length) {
        const photonResponse = await fetch(`https://photon.komoot.io/reverse?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}&limit=30`, { signal: AbortSignal.timeout(10000) });
        if (photonResponse.ok) {
          const photonData = await photonResponse.json();
          const photonPlaces = (photonData.features || []).map(feature => {
            const coordinates = feature.geometry?.coordinates || [], properties = feature.properties || {};
            const point = [Number(coordinates[1]), Number(coordinates[0])];
            const kind = properties.type || properties.category || 'place';
            return { name: properties.name || properties.street || (isThai ? 'สถานที่ใกล้เคียง' : 'Nearby place'), type: amenityLabels[kind] || kind, point, km: point.every(Number.isFinite) ? distance(point, [lat, lon]) : Infinity };
          }).filter(place => Number.isFinite(place.km) && place.km <= 5).sort((a, b) => a.km - b.km).slice(0, 20);
          places.push(...photonPlaces);
        }
      }
      if (!places.length) { status.textContent = isThai ? 'ยังไม่พบจุดอำนวยความสะดวกในรัศมี 5 กม.' : 'No nearby places found within 5 km.'; return; }
      status.textContent = isThai ? 'สถานที่จาก OpenStreetMap · ระยะเส้นตรงโดยประมาณ' : 'From OpenStreetMap · approximate straight-line distance';
      count.textContent = `${places.length} ${isThai ? 'จุด' : 'places'}`;
      places.forEach(place => {
        const item = document.createElement('a'); item.className = 'env-poi-item'; item.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${place.name} ${place.point[0]},${place.point[1]}`)}`; item.target = '_blank'; item.rel = 'noopener noreferrer';
        const dot = document.createElement('i'); dot.className = 'env-poi-dot';
        const label = document.createElement('span'); label.textContent = `${place.name} · ${place.type}`;
        const distanceLabel = document.createElement('small'); distanceLabel.textContent = place.km < 1 ? `${Math.round(place.km * 1000)} m` : `${place.km.toFixed(1)} km`;
        item.append(dot, label, distanceLabel); list.append(item);
        if (poiLayer) { const marker = L.circleMarker(place.point, { radius: 6, color: '#fff', weight: 2, fillColor: '#2583c5', fillOpacity: 1 }); const popup = document.createElement('div'); const title = document.createElement('strong'); title.textContent = place.name; const detail = document.createElement('div'); detail.textContent = `${place.type} · ${distanceLabel.textContent}`; popup.append(title, detail); marker.bindPopup(popup).addTo(poiLayer); }
      });
    } catch (error) { status.textContent = isThai ? 'โหลดจุดใกล้เคียงไม่สำเร็จในขณะนี้' : 'Nearby places are unavailable right now.'; }
  };
  if (window.L) {
    routeMap = L.map($('envRouteMap'), { scrollWheelZoom: false, zoomControl: true }).setView([lat, lon], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>' }).addTo(routeMap);
    L.circleMarker([lat, lon], { radius: 8, color: '#fff', weight: 3, fillColor: '#0b1c33', fillOpacity: 1 }).addTo(routeMap).bindTooltip(document.documentElement.lang.toLowerCase().startsWith('th') ? 'ที่ตั้งอสังหาริมทรัพย์' : 'Property location');
    poiLayer = L.layerGroup().addTo(routeMap);
    window.setTimeout(() => routeMap.invalidateSize(), 120);
    loadNearbyAmenities();
  } else { $('envPoiStatus').textContent = document.documentElement.lang.toLowerCase().startsWith('th') ? 'แผนที่ไม่พร้อมใช้งาน จึงแสดงจุดใกล้เคียงไม่ได้' : 'Map unavailable; nearby places cannot be shown.'; }
  const route = async destination => {
    destination = String(destination || '').trim();
    if (!destination) { input.focus(); return; }
    button.disabled = true;
    setMessage(document.documentElement.lang.toLowerCase().startsWith('th') ? 'กำลังค้นหาปลายทางและคำนวณเส้นทาง…' : 'Finding destination and calculating route…');
    try {
      const isThai = document.documentElement.lang.toLowerCase().startsWith('th');
      const location = root.dataset.location || '';
      const queries = [...new Set([`${destination} ${location}`, destination].map(value => value.trim()).filter(Boolean))];
      let found = [];
      for (const query of queries) {
        const geoResponse = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(query)}`, { headers: { 'Accept-Language': isThai ? 'th,en' : 'en,th' }, signal: AbortSignal.timeout(10000) });
        if (!geoResponse.ok) throw new Error('geocode');
        found = await geoResponse.json();
        if (found.length) break;
      }
      if (!found.length) throw new Error('notfound');
      const destLat = Number(found[0].lat), destLon = Number(found[0].lon);
      const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${lon},${lat};${destLon},${destLat}?overview=full&geometries=geojson`, { signal: AbortSignal.timeout(12000) });
      if (!response.ok) throw new Error('route');
      const payload = await response.json(), r = payload.routes?.[0];
      if (!r) throw new Error('route');
      const coords = r.geometry.coordinates;
      if (!window.L) throw new Error('map-unavailable');
      result.classList.remove('hidden');
      const latLngs = coords.map(([routeLon, routeLat]) => [routeLat, routeLon]);
      if (!routeMap) throw new Error('map-unavailable');
      if (routeLayer) routeMap.removeLayer(routeLayer);
      routeLayer = L.featureGroup([
        L.polyline(latLngs, { color: '#fff', weight: 9, opacity: .95 }),
        L.polyline(latLngs, { color: '#c58b2a', weight: 5, opacity: 1 }),
        L.circleMarker(latLngs[0], { radius: 7, color: '#fff', weight: 3, fillColor: '#0b1c33', fillOpacity: 1 }),
        L.circleMarker(latLngs[latLngs.length - 1], { radius: 7, color: '#fff', weight: 3, fillColor: '#c58b2a', fillOpacity: 1 })
      ]).addTo(routeMap);
      routeMap.fitBounds(routeLayer.getBounds(), { padding: [24, 24], maxZoom: 15 });
      window.setTimeout(() => routeMap.invalidateSize(), 80);
      $('envRouteDistance').textContent = (r.distance / 1000).toFixed(1);
      $('envRouteDuration').textContent = Math.max(1, Math.round(r.duration / 60));
      result.classList.remove('hidden');
      const gmaps = `https://www.google.com/maps/dir/?api=1&origin=${lat},${lon}&destination=${destLat},${destLon}&travelmode=driving`;
      $('envOpenRoute').href = gmaps;
      setMessage(document.documentElement.lang.toLowerCase().startsWith('th') ? `เส้นทางถนนถึง “${found[0].name || destination}” · เวลาเป็นค่าประมาณ ไม่รวมจราจรสด` : `Road route to “${found[0].name || destination}” · estimate excludes live traffic`);
    } catch (error) {
      setMessage(error.message === 'notfound' ? (document.documentElement.lang.toLowerCase().startsWith('th') ? 'ไม่พบปลายทาง ลองใส่ชื่อสถานที่หรือเขต/จังหวัดให้ชัดขึ้น' : 'Destination not found. Try adding a district or city.') : (document.documentElement.lang.toLowerCase().startsWith('th') ? 'เชื่อมต่อบริการแผนที่ไม่ได้ในขณะนี้ · ลองเปิด Google Maps แทน' : 'Map service unavailable · use Google Maps instead'));
    } finally { button.disabled = false; }
  };
  form.addEventListener('submit', event => { event.preventDefault(); route(input.value); });
  root.querySelectorAll('.env-preset').forEach(preset => preset.addEventListener('click', () => { input.value = preset.dataset.destination; route(input.value); }));
})();
</script>
<style>
/* Utility-class fallbacks for this component */
.env-snapshot .env-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.env-snapshot .env-presets{display:flex;flex-wrap:wrap;gap:8px}
.env-snapshot .env-info-grid{display:grid;grid-template-columns:1fr;gap:12px}
@media(min-width:640px){.env-snapshot .env-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:640px){.env-snapshot .env-route-map{height:240px}.env-snapshot-card{padding:15px}.env-snapshot .env-stat{padding:12px 10px}.env-snapshot .env-info-tile small{font-size:12px;line-height:1.5}}
.env-snapshot .env-header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px}
</style>
        <?php
    }
}
