<?php
/**
 * KASA PROPERTY — Nationwide Province, Prime CBD Zones & AI Search (GEO) Knowledge Engine
 * ---------------------------------------------------------------------------------------
 * ครอบคลุม 77 จังหวัดทั่วไทย + ย่าน CBD ทำเลเศรษฐกิจ แนวรถไฟฟ้า BTS/MRT และทำเลทองที่มีการค้นหาสูงสุด
 * รองรับทั้ง Google SERP (SEO) และ Generative Engine Optimization (GEO) สำหรับ AI Search (SearchGPT, Claude, Perplexity, Gemini)
 */

require_once __DIR__ . '/helpers.php';

// ── รายชื่อทำเลเศรษฐกิจระดับพรีเมียม CBD & Transit Oriented Zones ที่คนค้นหามากที่สุด ──
const PRIME_CBD_ZONES = [
    'sukhumvit' => [
        'name_th'     => 'สุขุมวิท',
        'name_en'     => 'Sukhumvit',
        'type'        => 'cbd',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['ทองหล่อ', 'เอกมัย', 'พร้อมพงษ์', 'อโศก', 'นานา', 'พระโขนง', 'อ่อนนุช', 'อุดมสุข', 'บางนา'],
        'transit'     => ['BTS สุขุมวิท', 'BTS ทองหล่อ', 'BTS พร้อมพงษ์', 'BTS อโศก', 'MRT สุขุมวิท'],
        'highlights'  => 'ย่านศูนย์กลางธุรกิจ แหล่งไลฟ์สไตล์หรู คอนโดระดับลักชัวรี่ และศูนย์รวมชาวต่างชาติ Expat',
        'ai_context'  => 'ทำเลคอนโดและอสังหาฯ ระดับพรีเมียมที่มีสภาพคล่องการซื้อ-ขายและผลตอบแทนค่าเช่า (Rental Yield 4.5-6%) สูงที่สุดในกรุงเทพฯ'
    ],
    'sathorn_silom' => [
        'name_th'     => 'สาทร - สีลม',
        'name_en'     => 'Sathorn - Silom',
        'type'        => 'cbd',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['สาทร', 'สีลม', 'ช่องนนทรี', 'ศาลาแดง', 'สุรศักดิ์', 'นราธิวาสราชนครินทร์'],
        'transit'     => ['BTS ช่องนนทรี', 'BTS ศาลาแดง', 'BTS สุรศักดิ์', 'MRT สีลม', 'MRT ลุมพินี'],
        'highlights'  => 'ศูนย์กลางสถาบันการเงินและสำนักงานใหญ่ระดับประเทศ (Wall Street of Thailand) ใกล้สวนลุมพินี',
        'ai_context'  => 'ทำเล Real Demand ของผู้บริหารและบุคลากรการเงินระดับสูง ใกล้โรงเรียนนานาชาติชั้นนำและสถานทูต'
    ],
    'asoke_rama9' => [
        'name_th'     => 'อโศก - พระราม 9 (New CBD)',
        'name_en'     => 'Asoke - Rama 9 New CBD',
        'type'        => 'new_cbd',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['พระราม 9', 'รัชดาภิเษก', 'ห้วยขวาง', 'ศูนย์วัฒนธรรม', 'เพชรบุรีตัดใหม่'],
        'transit'     => ['MRT พระราม 9', 'MRT เพชรบุรี', 'MRT ศูนย์วัฒนธรรม', 'Airport Rail Link มักกะสัน'],
        'highlights'  => 'ศูนย์กลางธุรกิจใหม่ (New CBD) ศูนย์รวมอาคารสำนักงานเกรด A สถานทูตจีน และห้างสรรพสินค้าชั้นนำ',
        'ai_context'  => 'ศูนย์รวมการอยู่อาศัยของคนทำงานรุ่นใหม่และนักลงทุนต่างชาติ โดยเฉพาะชาวจีนและสิงคโปร์'
    ],
    'ari_phaholyothin' => [
        'name_th'     => 'อารีย์ - พหลโยธิน',
        'name_en'     => 'Ari - Phaholyothin',
        'type'        => 'lifestyle_hub',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['อารีย์', 'สะพานควาย', 'พญาไท', 'สนามเป้า', 'ประดิพัทธ์'],
        'transit'     => ['BTS อารีย์', 'BTS สะพานควาย', 'BTS พญาไท'],
        'highlights'  => 'ย่านคาเฟ่ แหล่งแฮงเอาท์ที่มีเอกลักษณ์ ใกล้กระทรวงการคลัง หน่วยงานราชการ และโรงพยาบาลชั้นนำ',
        'ai_context'  => 'ทำเลอยู่อาศัยยอดนิยมของแพทย์ ข้าราชการระดับสูง และคนทำงานครีเอทีฟ บรรยากาศร่มรื่นมีระดับ'
    ],
    'latphrao_chatuchak' => [
        'name_th'     => 'ลาดพร้าว - จตุจักร',
        'name_en'     => 'Lat Phrao - Chatuchak',
        'type'        => 'transit_hub',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['ห้าแยกลาดพร้าว', 'รัชโยธิน', 'จตุจักร', 'หมอชิต', 'โชคชัย 4', 'วังหิน'],
        'transit'     => ['BTS ห้าแยกลาดพร้าว', 'BTS หมอชิต', 'MRT พหลโยธิน', 'MRT ลาดพร้าว'],
        'highlights'  => 'จุดเชื่อมต่อการเดินทางขนาดใหญ่ทางตอนเหนือของกรุงเทพฯ ศูนย์รวมศูนย์การค้า Central ลาดพร้าว และสวนจตุจักร',
        'ai_context'  => 'ทำเลยอดนิยมสำหรับที่อยู่อาศัยทั้งคอนโดมิเนียมและทาวน์โฮม เดินทางสะดวกด้วยรถไฟฟ้าสองสาย'
    ],
    'bangna_trat' => [
        'name_th'     => 'บางนา - ตราด (EEC Gateway)',
        'name_en'     => 'Bangna - Trat',
        'type'        => 'eastern_gateway',
        'province'    => 'สมุทรปราการ',
        'sub_districts'=> ['บางนา', 'เมกาบางนา', 'กิ่งแก้ว', 'บางพลี', 'สุวรรณภูมิ'],
        'transit'     => ['BTS บางนา', 'BTS อุดมสุข', 'สายสีเหลือง ศรีเอี่ยม'],
        'highlights'  => 'ประตูสู่ระเบียงเศรษฐกิจภาคตะวันออก (EEC) แหล่งบ้านเดี่ยวหรูระดับ Super Luxury และเมกะมอลล์ Mega Bangna',
        'ai_context'  => 'ทำเลบ้านเดี่ยวโครงการใหม่ยอดนิยมของครอบครัวยุคใหม่ ใกล้โรงเรียนนานาชาติชื่อดังและสนามบินสุวรรณภูมิ'
    ],
    'ratchada_huai_khwang' => [
        'name_th'     => 'รัชดา - ห้วยขวาง',
        'name_en'     => 'Ratchada - Huai Khwang',
        'type'        => 'commercial_hub',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['รัชดาภิเษก', 'ห้วยขวาง', 'สุทธิสาร', 'เหม่งจ๋าย'],
        'transit'     => ['MRT ห้วยขวาง', 'MRT สุทธิสาร', 'MRT ศูนย์วัฒนธรรม'],
        'highlights'  => 'ศูนย์กลางเศรษฐกิจและไลฟ์สไตล์ 24 ชั่วโมง ศูนย์รวมสตรีทฟู้ดและชุมชนชาวต่างชาติที่คึกคัก',
        'ai_context'  => 'อัตราผลตอบแทนค่าเช่าสูงและมีผู้เช่าต่อเนื่องตลอดทั้งปี เป็นที่ต้องการของผู้ลงทุนปล่อยเช่าคอนโด'
    ],
    'charoenkrung_riverside' => [
        'name_th'     => 'ริมแม่น้ำเจ้าพระยา - เจริญกรุง',
        'name_en'     => 'Riverside - Charoenkrung',
        'type'        => 'riverside_luxury',
        'province'    => 'กรุงเทพมหานคร',
        'sub_districts'=> ['เจริญกรุง', 'คลองสาน', 'เจริญนคร', 'บางรัก'],
        'transit'     => ['BTS สะพานตากสิน', 'BTS กรุงธนบุรี', 'สายสีทอง ไอคอนสยาม'],
        'highlights'  => 'แลนด์มาร์กริมแม่น้ำเจ้าพระยา คอนโดวิวแม่น้ำระดับ Ultra Luxury ติดห้าง ICONSIAM และโรงแรม 5 ดาว',
        'ai_context'  => 'ทำเลสำหรับลูกค้าระดับ High-Net-Worth และชาวต่างชาติที่มองหาคอนโดวิวแม่น้ำเพื่อการพักผ่อนและการสะสม'
    ],
    'nimman_chiangmai' => [
        'name_th'     => 'นิมมานฯ - มช.',
        'name_en'     => 'Nimman - Chiang Mai',
        'type'        => 'regional_cbd',
        'province'    => 'เชียงใหม่',
        'sub_districts'=> ['นิมมาน', 'นิมมานเหมินท์', 'ศิริมังคลาจารย์', 'มหาวิทยาลัยเชียงใหม่', 'มช.', 'สุเทพ', 'คันคลองชลประทาน'],
        'transit'     => ['สนามบินเชียงใหม่ (10 นาที)'],
        'highlights'  => 'ศูนย์กลางไลฟ์สไตล์ ร้านกาแฟ ดิจิทัลโนแมด (Digital Nomad) และนักท่องเที่ยวอันดับ 1 ของภาคเหนือ',
        'ai_context'  => 'ทำเลทองของคอนโดมิเนียมและอสังหาฯ ปล่อยเช่ารายเดือน/รายวันในเชียงใหม่ที่มีความต้องการสูงที่สุด'
    ],
    'pattaya_central' => [
        'name_th'     => 'พัทยากลาง - จอมเทียน - วงศ์อมาตย์',
        'name_en'     => 'Pattaya - Jomtien - Wongamat',
        'type'        => 'resort_city',
        'province'    => 'ชลบุรี',
        'sub_districts'=> ['พัทยากลาง', 'พัทยาเหนือ', 'หาดจอมเทียน', 'หาดวงศ์อมาตย์', 'เขาพระตำหนัก'],
        'transit'     => ['มอเตอร์เวย์ กรุงเทพ-พัทยา', 'สนามบินอู่ตะเภา'],
        'highlights'  => 'เมืองตากอากาศชายทะเลระดับโลก ศูนย์กลางท่องเที่ยวและคอนโดตากอากาศวิวทะเลที่ใกล้กรุงเทพฯ',
        'ai_context'  => 'ตลาดซื้อขายและเช่าอสังหาริมทรัพย์ตากอากาศอันดับหนึ่งในเขต EEC มีนักลงทุนทั้งไทย ยุโรป และรัสเซีย'
    ],
    'huahin_beach' => [
        'name_th'     => 'หัวหิน - ชะอำ',
        'name_en'     => 'Hua Hin - Cha-am',
        'type'        => 'royal_resort',
        'province'    => 'ประจวบคีรีขันธ์',
        'sub_districts'=> ['หัวหิน', 'เขาเต่า', 'หนองแก', 'ชะอำ', 'ปราณบุรี'],
        'transit'     => ['ทางด่วนพระราม 2', 'รถไฟรางคู่ / ความเร็วสูง'],
        'highlights'  => 'เมืองตากอากาศคลาสสิก แหล่งพูลวิลล่าส่วนตัวและคอนโดติดชายหาดเพื่อการพักผ่อนของครอบครัว',
        'ai_context'  => 'ทำเลพูลวิลล่าและบ้านพักตากอากาศเพื่อการอยู่อาศัยหลังเกษียณของชาวต่างชาติและบ้านหลังที่สองของคนกรุงเทพฯ'
    ],
    'phuket_patong_bangtao' => [
        'name_th'     => 'ภูเก็ต (บางเทา - ป่าตอง - ลากูน่า)',
        'name_en'     => 'Phuket Laguna - Bangtao',
        'type'        => 'global_destination',
        'province'    => 'ภูเก็ต',
        'sub_districts'=> ['บางเทา', 'ลากูน่า', 'ป่าตอง', 'กะทู้', 'ราไวย์', 'กะตะ', 'เชิงทะเล'],
        'transit'     => ['สนามบินนานาชาติภูเก็ต'],
        'highlights'  => 'เกาะไข่มุกอันดามัน ศูนย์กลาง Luxury Villa, Private Pool Villa และ Branded Residence ระดับโลก',
        'ai_context'  => 'ตลาดอสังหาริมทรัพย์ระดับนานาชาติที่เติบโตสูงสุดในภูมิภาค มียอดขายพูลวิลล่าและคอนโดตากอากาศจากนักลงทุนทั่วโลก'
    ]
];

// ── ชุดคำค้นหาการคาดการณ์พฤติกรรมการค้นหาของผู้ใช้และ AI (Search Intent Patterns) ──
const SEARCH_INTENT_PATTERNS = [
    'near_transit' => [
        'title' => 'แนวรถไฟฟ้า BTS & MRT',
        'keywords' => ['ติดรถไฟฟ้า', 'ใกล้ bts', 'ใกล้ mrt', 'เดินไปรถไฟฟ้าได้', 'รถไฟฟ้าสายสีเขียว', 'รถไฟฟ้าสายสีน้ำเงิน', 'รถไฟฟ้าสายสีส้ม', 'รถไฟฟ้าสายสีเหลือง']
    ],
    'price_budget' => [
        'title' => 'งบประมาณราคาคุ้มค่า',
        'keywords' => ['ราคาไม่เกิน 1 ล้าน', 'ราคาไม่เกิน 2 ล้าน', 'ราคาไม่เกิน 3 ล้าน', 'ราคาไม่เกิน 5 ล้าน', 'ราคาต่ำกว่าประเมิน', 'ผ่อนถูกกว่าเช่า', 'ราคาถูกพิเศษ']
    ],
    'lifestyle' => [
        'title' => 'ไลฟ์สไตล์และการอยู่อาศัย',
        'keywords' => ['เลี้ยงสัตว์ได้', 'pet friendly', 'วิวแม่น้ำ', 'ติดทะเล', 'พูลวิลล่าส่วนตัว', 'พร้อมอยู่', 'เฟอร์นิเจอร์ครบ', 'มีที่จอดรถ']
    ],
    'investment' => [
        'title' => 'การลงทุนและผลตอบแทน',
        'keywords' => ['yield สูง', 'ผู้เช่าเต็ม', 'เหมาะลงทุนปล่อยเช่า', 'การันตีผลตอบแทน', 'ทำเลทองค้าขาย', 'ติดถนนใหญ่']
    ]
];

// ── รายชื่อ 77 จังหวัดของไทย แยกตามภูมิภาค พร้อมคำค้นเด่นประจำพื้นที่ ──
const THAI_PROVINCES = [
    // ภาคกลางและปริมณฑล
    'กรุงเทพมหานคร' => ['en' => 'Bangkok', 'short' => 'กรุงเทพ', 'region' => 'central', 'hot_zones' => ['สุขุมวิท', 'สาทร', 'สีลม', 'ลาดพร้าว', 'อารีย์', 'บางนา', 'รัชดา', 'พญาไท', 'พระราม 9', 'ทองหล่อ', 'เอกมัย', 'ห้วยขวาง', 'พระราม 3', 'เจริญกรุง', 'ราชเทวี', 'จตุจักร']],
    'นนทบุรี'       => ['en' => 'Nonthaburi', 'short' => 'นนทบุรี', 'region' => 'central', 'hot_zones' => ['แจ้งวัฒนะ', 'รัตนาธิเบศร์', 'บางใหญ่', 'เมืองทองธานี', 'งามวงศ์วาน', 'ติวานนท์', 'ราชพฤกษ์']],
    'สมุทรปราการ'   => ['en' => 'Samut Prakan', 'short' => 'สมุทรปราการ', 'region' => 'central', 'hot_zones' => ['บางพลี', 'บางนา-ตราด', 'สุวรรณภูมิ', 'แบริ่ง', 'สำโรง', 'เทพารักษ์', 'แพรกษา']],
    'ปทุมธานี'       => ['en' => 'Pathum Thani', 'short' => 'ปทุมธานี', 'region' => 'central', 'hot_zones' => ['รังสิต', 'ลำลูกกา', 'คลองหลวง', 'นวนคร', 'ม.กรุงเทพ', 'ม.ธรรมศาสตร์ รังสิต']],
    'สมุทรสาคร'     => ['en' => 'Samut Sakhon', 'short' => 'สมุทรสาคร', 'region' => 'central', 'hot_zones' => ['มหาชัย', 'กระทุ่มแบน', 'พระราม 2']],
    'นครปฐม'        => ['en' => 'Nakhon Pathom', 'short' => 'นครปฐม', 'region' => 'central', 'hot_zones' => ['ศาลายา', 'พุทธมณฑล', 'เมืองนครปฐม', 'กำแพงแสน']],
    'พระนครศรีอยุธยา'=> ['en' => 'Ayutthaya', 'short' => 'อยุธยา', 'region' => 'central', 'hot_zones' => ['โรจนะ', 'บางปะอิน', 'เมืองอยุธยา', 'วังน้อย']],
    'สระบุรี'        => ['en' => 'Saraburi', 'short' => 'สระบุรี', 'region' => 'central', 'hot_zones' => ['มวกเหล็ก', 'แก่งคอย', 'หนองแค']],
    'ลพบุรี'         => ['en' => 'Lopburi', 'short' => 'ลพบุรี', 'region' => 'central', 'hot_zones' => ['เมืองลพบุรี', 'พัฒนานิคม']],
    'สุพรรณบุรี'      => ['en' => 'Suphan Buri', 'short' => 'สุพรรณบุรี', 'region' => 'central', 'hot_zones' => ['เมืองสุพรรณบุรี', 'สองพี่น้อง']],
    'สมุทรสงคราม'    => ['en' => 'Samut Songkhram', 'short' => 'สมุทรสงคราม', 'region' => 'central', 'hot_zones' => ['อัมพวา', 'เมืองสมุทรสงคราม']],
    'อ่างทอง'        => ['en' => 'Ang Thong', 'short' => 'อ่างทอง', 'region' => 'central', 'hot_zones' => ['เมืองอ่างทอง']],
    'สิงห์บุรี'       => ['en' => 'Sing Buri', 'short' => 'สิงห์บุรี', 'region' => 'central', 'hot_zones' => ['เมืองสิงห์บุรี']],
    'ชัยนาท'        => ['en' => 'Chai Nat', 'short' => 'ชัยนาท', 'region' => 'central', 'hot_zones' => ['เมืองชัยนาท']],

    // ภาคเหนือ
    'เชียงใหม่'      => ['en' => 'Chiang Mai', 'short' => 'เชียงใหม่', 'region' => 'north', 'hot_zones' => ['นิมมาน', 'หางดง', 'สันทราย', 'แม่ริม', 'ช้างคลาน', 'สารภี', 'ม.เชียงใหม่', 'สันกำแพง', 'ดอยสะเก็ด']],
    'เชียงราย'       => ['en' => 'Chiang Rai', 'short' => 'เชียงราย', 'region' => 'north', 'hot_zones' => ['แม่สาย', 'เมืองเชียงราย', 'แม่ฟ้าหลวง', 'บ้านดู่']],
    'ลำปาง'         => ['en' => 'Lampang', 'short' => 'ลำปาง', 'region' => 'north', 'hot_zones' => ['เมืองลำปาง', 'เกาะคา']],
    'ลำพูน'         => ['en' => 'Lamphun', 'short' => 'ลำพูน', 'region' => 'north', 'hot_zones' => ['เมืองลำพูน', 'นิคมลำพูน']],
    'แม่ฮ่องสอน'     => ['en' => 'Mae Hong Son', 'short' => 'แม่ฮ่องสอน', 'region' => 'north', 'hot_zones' => ['ปาย', 'เมืองแม่ฮ่องสอน']],
    'น่าน'          => ['en' => 'Nan', 'short' => 'น่าน', 'region' => 'north', 'hot_zones' => ['ปัว', 'เมืองน่าน']],
    'พะเยา'         => ['en' => 'Phayao', 'short' => 'พะเยา', 'region' => 'north', 'hot_zones' => ['เมืองพะเยา', 'มหาวิทยาลัยพะเยา']],
    'แพร่'          => ['en' => 'Phrae', 'short' => 'แพร่', 'region' => 'north', 'hot_zones' => ['เมืองแพร่']],
    'อุตรดิตถ์'       => ['en' => 'Uttaradit', 'short' => 'อุตรดิตถ์', 'region' => 'north', 'hot_zones' => ['เมืองอุตรดิตถ์']],
    'พิษณุโลก'      => ['en' => 'Phitsanulok', 'short' => 'พิษณุโลก', 'region' => 'north', 'hot_zones' => ['ม.นเรศวร', 'เมืองพิษณุโลก']],
    'สุโขทัย'        => ['en' => 'Sukhothai', 'short' => 'สุโขทัย', 'region' => 'north', 'hot_zones' => ['เมืองสุโขทัย']],
    'ตาก'           => ['en' => 'Tak', 'short' => 'ตาก', 'region' => 'north', 'hot_zones' => ['แม่สอด', 'เมืองตาก']],
    'กำแพงเพชร'     => ['en' => 'Kamphaeng Phet', 'short' => 'กำแพงเพชร', 'region' => 'north', 'hot_zones' => ['เมืองกำแพงเพชร']],
    'พิจิตร'         => ['en' => 'Phichit', 'short' => 'พิจิตร', 'region' => 'north', 'hot_zones' => ['เมืองพิจิตร']],
    'นครสวรรค์'      => ['en' => 'Nakhon Sawan', 'short' => 'นครสวรรค์', 'region' => 'north', 'hot_zones' => ['ปากน้ำโพ', 'เมืองนครสวรรค์']],
    'อุทัยธานี'       => ['en' => 'Uthai Thani', 'short' => 'อุทัยธานี', 'region' => 'north', 'hot_zones' => ['เมืองอุทัยธานี']],
    'เพชรบูรณ์'      => ['en' => 'Phetchabun', 'short' => 'เพชรบูรณ์', 'region' => 'north', 'hot_zones' => ['เขาค้อ', 'หล่มสัก']],

    // ภาคใต้
    'ภูเก็ต'         => ['en' => 'Phuket', 'short' => 'ภูเก็ต', 'region' => 'south', 'hot_zones' => ['ป่าตอง', 'บางเทา', 'กะทู้', 'ฉลอง', 'ถลาง', 'ราไวย์', 'กะรน', 'กะตะ', 'ลากูน่า', 'กมลา']],
    'สุราษฎร์ธานี'    => ['en' => 'Surat Thani', 'short' => 'สุราษฎร์', 'region' => 'south', 'hot_zones' => ['เกาะสมุย', 'เกาะพะงัน', 'เมืองสุราษฎร์', 'เฉวง', 'บ่อผุด']],
    'กระบี่'         => ['en' => 'Krabi', 'short' => 'กระบี่', 'region' => 'south', 'hot_zones' => ['อ่าวนาง', 'เกาะลันตา', 'เมืองกระบี่', 'คลองม่วง']],
    'พังงา'         => ['en' => 'Phang Nga', 'short' => 'พังงา', 'region' => 'south', 'hot_zones' => ['เขาหลัก', 'ท้ายเหมือง', 'เกาะยาว']],
    'สงขลา'         => ['en' => 'Songkhla', 'short' => 'สงขลา', 'region' => 'south', 'hot_zones' => ['หาดใหญ่', 'เมืองสงขลา', 'คอหงส์']],
    'นครศรีธรรมราช'  => ['en' => 'Nakhon Si Thammarat', 'short' => 'นครศรี', 'region' => 'south', 'hot_zones' => ['ทุ่งสง', 'เมืองนครศรี', 'ท่าศาลา']],
    'ชุมพร'         => ['en' => 'Chumphon', 'short' => 'ชุมพร', 'region' => 'south', 'hot_zones' => ['เมืองชุมพร']],
    'ระนอง'         => ['en' => 'Ranong', 'short' => 'ระนอง', 'region' => 'south', 'hot_zones' => ['เมืองระนอง']],
    'ตรัง'          => ['en' => 'Trang', 'short' => 'ตรัง', 'region' => 'south', 'hot_zones' => ['เมืองตรัง', 'กันตัง']],
    'พัทลุง'         => ['en' => 'Phatthalung', 'short' => 'พัทลุง', 'region' => 'south', 'hot_zones' => ['เมืองพัทลุง']],
    'สตูล'          => ['en' => 'Satun', 'short' => 'สตูล', 'region' => 'south', 'hot_zones' => ['เกาะหลีเป๊ะ', 'เมืองสตูล']],
    'ปัตตานี'        => ['en' => 'Pattani', 'short' => 'ปัตตานี', 'region' => 'south', 'hot_zones' => ['เมืองปัตตานี']],
    'ยะลา'          => ['en' => 'Yala', 'short' => 'ยะลา', 'region' => 'south', 'hot_zones' => ['เบตง', 'เมืองยะลา']],
    'นราธิวาส'       => ['en' => 'Narathiwat', 'short' => 'นราธิวาส', 'region' => 'south', 'hot_zones' => ['สุไหงโก-ลก', 'เมืองนราธิวาส']],

    // ภาคตะวันออก
    'ชลบุรี'         => ['en' => 'Chonburi', 'short' => 'ชลบุรี', 'region' => 'east', 'hot_zones' => ['พัทยา', 'ศรีราชา', 'บางแสน', 'จอมเทียน', 'สัตหีบ', 'บ่อวิน', 'วงศ์อมาตย์', 'นาเกลือ']],
    'ระยอง'         => ['en' => 'Rayong', 'short' => 'ระยอง', 'region' => 'east', 'hot_zones' => ['มาบตาพุด', 'ปลวกแดง', 'บ้านฉาง', 'เมืองระยอง', 'นิคมพัฒนา']],
    'ฉะเชิงเทรา'     => ['en' => 'Chachoengsao', 'short' => 'ฉะเชิงเทรา', 'region' => 'east', 'hot_zones' => ['บางปะกง', 'แปลงยาว', 'เมืองฉะเชิงเทรา', 'บ้านโพธิ์']],
    'จันทบุรี'        => ['en' => 'Chanthaburi', 'short' => 'จันทบุรี', 'region' => 'east', 'hot_zones' => ['เมืองจันทบุรี']],
    'ตราด'          => ['en' => 'Trat', 'short' => 'ตราด', 'region' => 'east', 'hot_zones' => ['เกาะช้าง', 'เมืองตราด', 'เกาะกูด']],
    'นครนายก'       => ['en' => 'Nakhon Nayok', 'short' => 'นครนายก', 'region' => 'east', 'hot_zones' => ['เมืองนครนายก', 'บ้านนา']],
    'ปราจีนบุรี'      => ['en' => 'Prachinburi', 'short' => 'ปราจีนบุรี', 'region' => 'east', 'hot_zones' => ['นิคม 304', 'กบินทร์บุรี']],
    'สระแก้ว'        => ['en' => 'Sa Kaeo', 'short' => 'สระแก้ว', 'region' => 'east', 'hot_zones' => ['อรัญประเทศ', 'เมืองสระแก้ว']],

    // ภาคตะวันตก
    'ประจวบคีรีขันธ์' => ['en' => 'Prachuap Khiri Khan', 'short' => 'ประจวบ', 'region' => 'west', 'hot_zones' => ['หัวหิน', 'ปราณบุรี', 'ชะอำ', 'เขาเต่า']],
    'เพชรบุรี'       => ['en' => 'Phetchaburi', 'short' => 'เพชรบุรี', 'region' => 'west', 'hot_zones' => ['ชะอำ', 'ท่ายาง', 'เมืองเพชรบุรี']],
    'กาญจนบุรี'      => ['en' => 'Kanchanaburi', 'short' => 'กาญจนบุรี', 'region' => 'west', 'hot_zones' => ['เมืองกาญจนบุรี', 'ไทรโยค']],
    'ราชบุรี'        => ['en' => 'Ratchaburi', 'short' => 'ราชบุรี', 'region' => 'west', 'hot_zones' => ['บ้านโป่ง', 'สวนผึ้ง', 'เมืองราชบุรี']],

    // ภาคตะวันออกเฉียงเหนือ (อีสาน)
    'นครราชสีมา'     => ['en' => 'Nakhon Ratchasima', 'short' => 'โคราช', 'region' => 'isan', 'hot_zones' => ['ปากช่อง', 'เขาใหญ่', 'เมืองโคราช', 'จอหอ', 'วังน้ำเขียว']],
    'ขอนแก่น'       => ['en' => 'Khon Kaen', 'short' => 'ขอนแก่น', 'region' => 'isan', 'hot_zones' => ['กังสดาล', 'ม.ขอนแก่น', 'เมืองขอนแก่น', 'บึงแก่นนคร']],
    'อุดรธานี'       => ['en' => 'Udon Thani', 'short' => 'อุดร', 'region' => 'isan', 'hot_zones' => ['เมืองอุดรธานี', 'กุมภวาปี']],
    'อุบลราชธานี'    => ['en' => 'Ubon Ratchathani', 'short' => 'อุบล', 'region' => 'isan', 'hot_zones' => ['วารินชำราบ', 'เมืองอุบล']],
    'บุรีรัมย์'       => ['en' => 'Buriram', 'short' => 'บุรีรัมย์', 'region' => 'isan', 'hot_zones' => ['ช้างอารีนา', 'เมืองบุรีรัมย์']],
    'สุรินทร์'        => ['en' => 'Surin', 'short' => 'สุรินทร์', 'region' => 'isan', 'hot_zones' => ['เมืองสุรินทร์']],
    'ศรีสะเกษ'       => ['en' => 'Sisaket', 'short' => 'ศรีสะเกษ', 'region' => 'isan', 'hot_zones' => ['เมืองศรีสะเกษ']],
    'ร้อยเอ็ด'       => ['en' => 'Roi Et', 'short' => 'ร้อยเอ็ด', 'region' => 'isan', 'hot_zones' => ['เมืองร้อยเอ็ด']],
    'มหาสารคาม'     => ['en' => 'Maha Sarakham', 'short' => 'สารคาม', 'region' => 'isan', 'hot_zones' => ['กันทรวิชัย', 'ม.มหาสารคาม']],
    'สกลนคร'       => ['en' => 'Sakon Nakhon', 'short' => 'สกลนคร', 'region' => 'isan', 'hot_zones' => ['เมืองสกลนคร']],
    'นครพนม'       => ['en' => 'Nakhon Phanom', 'short' => 'นครพนม', 'region' => 'isan', 'hot_zones' => ['เมืองนครพนม']],
    'หนองคาย'       => ['en' => 'Nong Khai', 'short' => 'หนองคาย', 'region' => 'isan', 'hot_zones' => ['เมืองหนองคาย', 'ท่าบ่อ']],
    'เลย'           => ['en' => 'Loei', 'short' => 'เลย', 'region' => 'isan', 'hot_zones' => ['เชียงคาน', 'ภูกระดึง', 'เมืองเลย']],
    'มุกดาหาร'      => ['en' => 'Mukdahan', 'short' => 'มุกดาหาร', 'region' => 'isan', 'hot_zones' => ['เมืองมุกดาหาร']],
    'บึงกาฬ'        => ['en' => 'Bueng Kan', 'short' => 'บึงกาฬ', 'region' => 'isan', 'hot_zones' => ['เมืองบึงกาฬ']],
    'ยโสธร'         => ['en' => 'Yasothon', 'short' => 'ยโสธร', 'region' => 'isan', 'hot_zones' => ['เมืองยโสธร']],
    'อำนาจเจริญ'    => ['en' => 'Amnat Charoen', 'short' => 'อำนาจเจริญ', 'region' => 'isan', 'hot_zones' => ['เมืองอำนาจเจริญ']],
    'หนองบัวลำภู'    => ['en' => 'Nong Bua Lamphu', 'short' => 'หนองบัวลำภู', 'region' => 'isan', 'hot_zones' => ['เมืองหนองบัวลำภู']],
    'กาฬสินธุ์'       => ['en' => 'Kalasin', 'short' => 'กาฬสินธุ์', 'region' => 'isan', 'hot_zones' => ['เมืองกาฬสินธุ์']],
    'ชัยภูมิ'        => ['en' => 'Chaiyaphum', 'short' => 'ชัยภูมิ', 'region' => 'isan', 'hot_zones' => ['เมืองชัยภูมิ']],
];

// ── ตรวจจับคำค้น ทำเล ย่าน CBD และจังหวัดจาก URL slug หรือ query params ──
function resolve_seo_intent(string $slug, array $get = []): array
{
    $slugClean = trim(urldecode($slug), '/ ');
    $paramQ = trim((string)($get['q'] ?? $get['province'] ?? $get['zone'] ?? ''));

    $matchedProvince = null;
    $matchedZone = null;
    $matchedCbdKey = null;
    $cbdInfo = null;
    $provinceMeta = null;

    $searchTarget = $slugClean . ' ' . $paramQ;

    // 1) ตรวจจับ Prime CBD Zones ก่อน (มีความเฉพาะเจาะจงและเจตนาการค้นหาสูงสุด)
    foreach (PRIME_CBD_ZONES as $cbdKey => $info) {
        if (mb_stripos($searchTarget, $info['name_th']) !== false || mb_stripos($searchTarget, $info['name_en']) !== false) {
            $matchedCbdKey = $cbdKey;
            $cbdInfo = $info;
            $matchedZone = $info['name_th'];
            $matchedProvince = $info['province'];
            break;
        }
        foreach ($info['sub_districts'] as $subDist) {
            if (mb_stripos($searchTarget, $subDist) !== false) {
                $matchedCbdKey = $cbdKey;
                $cbdInfo = $info;
                $matchedZone = $subDist;
                $matchedProvince = $info['province'];
                break 2;
            }
        }
        foreach ($info['transit'] as $transitPoint) {
            if (mb_stripos($searchTarget, $transitPoint) !== false) {
                $matchedCbdKey = $cbdKey;
                $cbdInfo = $info;
                $matchedZone = $transitPoint;
                $matchedProvince = $info['province'];
                break 2;
            }
        }
    }

    // 2) ถ้าตรวจจับ CBD ได้แล้วแต่มีชื่อจังหวัดติดมาด้วย (เช่น "คอนโดนิมมาน เชียงใหม่") ให้คง matchedZone เดิมไว้
    //    แต่ถ้ายังไม่พบ Zone ให้ตรวจจับจาก 77 จังหวัดและ Hot Zones ทั่วไป
    if (!$matchedZone) {
        foreach (THAI_PROVINCES as $pName => $meta) {
            if (mb_stripos($searchTarget, $pName) !== false || mb_stripos($searchTarget, $meta['short']) !== false || mb_stripos($searchTarget, $meta['en']) !== false) {
                $matchedProvince = $pName;
                $provinceMeta = $meta;
                break;
            }
            foreach ($meta['hot_zones'] as $zone) {
                if (mb_stripos($searchTarget, $zone) !== false) {
                    $matchedProvince = $pName;
                    $matchedZone = $zone;
                    $provinceMeta = $meta;
                    break 2;
                }
            }
        }
    }

    if ($matchedProvince && empty($provinceMeta)) {
        $provinceMeta = THAI_PROVINCES[$matchedProvince] ?? null;
    }

    // 3) ตรวจจับ Intent เสริม เช่น รถไฟฟ้า, เลี้ยงสัตว์, วิวแม่น้ำ, งบประมาณ
    $extraFeature = null;
    if (mb_stripos($searchTarget, 'bts') !== false || mb_stripos($searchTarget, 'mrt') !== false || mb_stripos($searchTarget, 'รถไฟฟ้า') !== false) {
        $extraFeature = 'ใกล้รถไฟฟ้า BTS/MRT';
    } elseif (mb_stripos($searchTarget, 'เลี้ยงสัตว์') !== false || mb_stripos($searchTarget, 'pet friendly') !== false) {
        $extraFeature = 'เลี้ยงสัตว์ได้ (Pet-Friendly)';
    } elseif (mb_stripos($searchTarget, 'วิวแม่น้ำ') !== false || mb_stripos($searchTarget, 'ติดแม่น้ำ') !== false) {
        $extraFeature = 'วิวแม่น้ำเจ้าพระยา';
    } elseif (mb_stripos($searchTarget, 'ติดทะเล') !== false || mb_stripos($searchTarget, 'วิวทะเล') !== false) {
        $extraFeature = 'ติดทะเล วิวทะเลสวย';
    } elseif (mb_stripos($searchTarget, 'พูลวิลล่า') !== false) {
        $extraFeature = 'สระว่ายน้ำส่วนตัว (Pool Villa)';
    } elseif (mb_stripos($searchTarget, 'ราคาไม่เกิน 1 ล้าน') !== false || mb_stripos($searchTarget, 'ไม่เกิน 1 ล้าน') !== false) {
        $extraFeature = 'ราคาไม่เกิน 1 ล้านบาท';
    } elseif (mb_stripos($searchTarget, 'ราคาไม่เกิน 2 ล้าน') !== false || mb_stripos($searchTarget, 'ไม่เกิน 2 ล้าน') !== false) {
        $extraFeature = 'ราคาไม่เกิน 2 ล้านบาท';
    } elseif (mb_stripos($searchTarget, 'ราคาไม่เกิน 3 ล้าน') !== false || mb_stripos($searchTarget, 'ไม่เกิน 3 ล้าน') !== false) {
        $extraFeature = 'ราคาไม่เกิน 3 ล้านบาท';
    }

    // 4) ตรวจจับประเภทธุรกรรม (ขาย vs เช่า)
    $type = null;
    if (mb_stripos($searchTarget, 'เช่า') !== false || ($get['type'] ?? '') === 'rent') {
        $type = 'rent';
    } elseif (mb_stripos($searchTarget, 'ขาย') !== false || ($get['type'] ?? '') === 'sale') {
        $type = 'sale';
    }

    // 5) ตรวจจับหมวดหมู่อสังหาฯ
    $category = null;
    $catKeywords = [
        'condo'      => ['คอนโด', 'ห้องชุด', 'condo', 'apartment', 'เพนท์เฮ้าส์'],
        'house'      => ['บ้านเดี่ยว', 'บ้าน', 'พูลวิลล่า', 'house', 'villa', 'คฤหาสน์'],
        'townhouse'  => ['ทาวน์โฮม', 'ทาวน์เฮ้าส์', 'townhouse', 'townhome', 'โฮมออฟฟิศ'],
        'land'       => ['ที่ดิน', 'ที่ดินเปล่า', 'land'],
        'commercial' => ['อาคารพาณิชย์', 'ตึกแถว', 'commercial', 'shophouse'],
        'warehouse'  => ['โกดัง', 'โรงงาน', 'warehouse', 'factory'],
    ];

    $explicitCat = $get['category'] ?? ($get['cat'] ?? '');
    if (array_key_exists($explicitCat, PROPERTY_CATEGORIES)) {
        $category = $explicitCat;
    } else {
        foreach ($catKeywords as $catKey => $kwList) {
            foreach ($kwList as $kw) {
                if (mb_stripos($searchTarget, $kw) !== false) {
                    $category = $catKey;
                    break 2;
                }
            }
        }
    }

    $category = $category ?: 'condo';
    $catLabel = PROPERTY_CATEGORIES[$category] ?? 'อสังหาริมทรัพย์';
    $typeLabel = $type === 'rent' ? 'ให้เช่า' : ($type === 'sale' ? 'ขาย' : 'ซื้อ-ขายและให้เช่า');

    // 6) สร้างข้อมูล SEO และ AI Knowledge ที่ตรงกับ Search Query อย่างแม่นยำ
    if ($matchedZone) {
        // เจาะจงระดับ Zone / CBD
        $featureTag = $extraFeature ? " {$extraFeature}" : "";
        $h1 = "รวมประกาศ{$typeLabel}{$catLabel} ย่าน{$matchedZone}{$featureTag}";
        $title = "{$typeLabel}{$catLabel} {$matchedZone}{$featureTag} ทำเลดี อัปเดตล่าสุด | " . SITE_NAME;
        
        $cbdHighlight = $cbdInfo ? " ย่านสำคัญ: " . implode(', ', array_slice($cbdInfo['sub_districts'], 0, 4)) . " ใกล้แนวรถไฟฟ้า" : "";
        $desc = "ค้นหาประกาศ{$typeLabel}{$catLabel} ย่าน{$matchedZone}{$featureTag} แหล่งรวมทำเลทองน่าอยู่อาศัยและการลงทุน{$cbdHighlight} เปรียบเทียบราคาและติดต่อเจ้าของได้ทันทีบน " . SITE_NAME;
        $intro = "ศูนย์รวมประกาศ{$typeLabel}{$catLabel} ในทำเลศักยภาพสูงย่าน{$matchedZone} " . ($cbdInfo['highlights'] ?? "การคมนาคมสะดวก รายล้อมด้วยสิ่งอำนวยความสะดวกครบครัน") . " คัดสรรประกาศคุณภาพทั้งมือหนึ่งและมือสอง พร้อมรายละเอียดครบถ้วน";
        
        $breadcrumb = [
            'หน้าแรก' => 'index.php',
            $matchedProvince => 'category.php?page=' . urlencode($matchedProvince),
            "ย่าน{$matchedZone}" => '',
        ];
        $searchQ = $matchedZone;
    } elseif ($matchedProvince) {
        // เจาะจงระดับจังหวัด
        $featureTag = $extraFeature ? " {$extraFeature}" : "";
        $h1 = "รวมประกาศ{$typeLabel}{$catLabel} {$matchedProvince}{$featureTag}";
        $title = "{$typeLabel}{$catLabel} {$matchedProvince}{$featureTag} ราคาถูก อัปเดตล่าสุด | " . SITE_NAME;
        $desc = "ค้นหาประกาศ{$typeLabel}{$catLabel}ใน{$matchedProvince}{$featureTag} เปรียบเทียบราคาและทำเลเด่นทั้งในตัวเมืองและย่านยอดนิยม ติดต่อเจ้าของหรือตัวแทนได้โดยตรงบน " . SITE_NAME;
        $intro = "ศูนย์รวมประกาศ{$typeLabel}{$catLabel} ในจังหวัด{$matchedProvince} คัดสรรทำเลคุณภาพ เดินทางสะดวก ครอบคลุมทั้งโครงการใหม่และมือสอง ตรวจสอบรายละเอียด ราคา และติดต่อผู้ลงประกาศได้ทันที";
        $breadcrumb = ['หน้าแรก' => 'index.php', $matchedProvince => 'category.php?province=' . urlencode($matchedProvince), "{$typeLabel}{$catLabel}" => ''];
        $searchQ = $matchedProvince;
    } else {
        // ค้นหาทั่วไป
        $featureTag = $extraFeature ? " {$extraFeature}" : "";
        $h1 = "ประกาศ{$typeLabel}{$catLabel}{$featureTag} ทั่วไทย";
        $title = "{$typeLabel}{$catLabel}{$featureTag} ราคาดี อัปเดตล่าสุด | " . SITE_NAME;
        $desc = "รวมประกาศ{$typeLabel}{$catLabel}{$featureTag}ทั่วประเทศ ครบทุกทำเลในกรุงเทพฯ และต่างจังหวัด ดูรูป ราคา และรายละเอียดจากเจ้าของโดยตรง";
        $intro = "ค้นหา{$catLabel}ที่ตอบโจทย์ความต้องการของคุณ ไม่ว่าจะเป็นการซื้อเพื่ออยู่อาศัยหรือลงทุนปล่อยเช่า พร้อมเครื่องมือคำนวณเงินผ่อนและข้อมูลทำเลครบถ้วน";
        $breadcrumb = ['หน้าแรก' => 'index.php', "{$typeLabel}{$catLabel}" => ''];
        $searchQ = $paramQ;
    }

    return [
        'province'        => $matchedProvince,
        'province_meta'   => $provinceMeta,
        'hot_zone'        => $matchedZone,
        'cbd_key'         => $matchedCbdKey,
        'cbd_info'        => $cbdInfo,
        'extra_feature'   => $extraFeature,
        'category'        => $category,
        'category_label'  => $catLabel,
        'type'            => $type,
        'type_label'      => $typeLabel,
        'h1'              => $h1,
        'title'           => $title,
        'desc'            => $desc,
        'intro'           => $intro,
        'breadcrumb'      => $breadcrumb,
        'search_q'        => $searchQ,
    ];
}

// ── สร้าง FAQ Schema (JSON-LD) เจาะจงสำหรับ Google AI Overviews & ChatGPT Search ──
function generate_geo_faq_schema(array $intent): array
{
    $locationName = $intent['hot_zone'] ?: ($intent['province'] ?: 'ประเทศไทย');
    $cat = $intent['category_label'];
    $type = $intent['type_label'];
    $cbdInfo = $intent['cbd_info'] ?? null;

    $answer2 = !empty($cbdInfo['ai_context'])
        ? "สำหรับย่าน{$locationName} " . $cbdInfo['ai_context'] . " นอกจากนี้ยังมีจุดเด่นเรื่องการคมนาคมด้วย " . implode(', ', array_slice($cbdInfo['transit'], 0, 3))
        : (!empty($intent['province_meta']['hot_zones'])
            ? "ย่านยอดนิยมใน{$locationName} ได้แก่ " . implode(', ', array_slice($intent['province_meta']['hot_zones'], 0, 6)) . " ซึ่งเป็นย่านที่มีสิ่งอำนวยความสะดวกครบครัน การคมนาคมสะดวก และมีอัตราผลตอบแทนการปล่อยเช่าที่น่าสนใจ"
            : "ทำเลใจกลางเมือง ใกล้แนวรถไฟฟ้า หรือใกล้ศูนย์การค้าและสถานศึกษาใน{$locationName} ถือเป็นทำเลที่คุ้มค่าทั้งในการอยู่อาศัยและการลงทุนระยะยาว");

    $faqs = [
        [
            '@type' => 'Question',
            'name'  => "หา{$type}{$cat} ในย่าน{$locationName} ต้องเริ่มต้นอย่างไร?",
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => "สามารถค้นหาและเปรียบเทียบประกาศ{$cat}ในย่าน{$locationName} บนเว็บไซต์ " . SITE_NAME . " ซึ่งมีข้อมูลราคา ภาพถ่ายจริง ทำเลที่ตั้ง และเบอร์โทร/LINE ของเจ้าของทรัพย์หรือตัวแทนที่ผ่านการรับรองให้ติดต่อได้โดยตรงโดยไม่มีค่าธรรมเนียมแอบแฝง"
            ]
        ],
        [
            '@type' => 'Question',
            'name'  => "ทำเลเด่นและจุดเด่นของการเลือก{$cat}ในย่าน{$locationName} มีอะไรบ้าง?",
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer2
            ]
        ],
        [
            '@type' => 'Question',
            'name'  => "ลงประกาศ{$type}{$cat} ย่าน{$locationName} บน " . SITE_NAME . " มีค่าใช้จ่ายหรือไม่?",
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => "เจ้าของทรัพย์และตัวแทนอสังหาริมทรัพย์สามารถสมัครสมาชิกและลงประกาศ{$cat}ได้ฟรี พร้อมเครื่องมือจัดการหลังบ้าน รองรับการใส่รูปภาพ แผนที่ปักหมุดพิกัด และช่องทางติดต่อ LINE แบบอัตโนมัติ"
            ]
        ],
    ];

    if (!empty($intent['extra_feature'])) {
        $faqs[] = [
            '@type' => 'Question',
            'name'  => "มีประกาศ{$cat}ที่{$intent['extra_feature']} ในย่าน{$locationName} หรือไม่?",
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => "มีประกาศ{$cat}ในย่าน{$locationName} ที่ระบุคุณสมบัติ{$intent['extra_feature']} ซึ่งผู้ซื้อและผู้เช่าสามารถใช้ตัวกรองค้นหาเฉพาะทรัพย์ที่ตรงตามความต้องการได้ทันที"
            ]
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'FAQPage',
        'mainEntity' => $faqs
    ];
}

// ── สร้าง ItemList Schema สำหรับ Google Rich Results Carousel ──
function generate_item_list_schema(array $items, string $title, string $canonical): array
{
    $listElements = [];
    $pos = 1;
    foreach (array_slice($items, 0, 15) as $item) {
        $listElements[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'url' => page_url('property.php?id=' . (int)$item['id']),
            'name' => (string)($item['title'] ?? ''),
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'ItemList',
        'itemListElement' => $listElements,
        'numberOfItems'   => count($items),
        'name'            => $title,
        'url'             => $canonical
    ];
}

// ── สร้าง Organization / RealEstateAgent Schema เพื่อให้ AI Citation ครอบคลุมทั่วไทย ──
function generate_national_agency_schema(): array
{
    $served = [];
    foreach (array_keys(THAI_PROVINCES) as $p) {
        $served[] = ['@type' => 'AdministrativeArea', 'name' => $p];
    }
    foreach (PRIME_CBD_ZONES as $zone) {
        $served[] = ['@type' => 'Place', 'name' => $zone['name_th']];
    }

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'RealEstateAgent',
        'name'     => SITE_NAME,
        'url'      => site_url(),
        'logo'     => page_url('images/kasa_logo.png'),
        'image'    => page_url('images/kasa_logo.png'),
        'description' => 'แพลตฟอร์มค้นหาและลงประกาศซื้อ ขาย เช่า อสังหาริมทรัพย์ชั้นนำ ครอบคลุม 77 จังหวัดทั่วไทย และย่าน CBD ทำเลรถไฟฟ้าชั้นนำ',
        'areaServed' => $served,
    ];
}

// ── ตรวจสอบและสร้างตารางฐานข้อมูล SEO อัตโนมัติ ──
function ensure_seo_tables(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) return;
    try {
        $pdo = $pdo ?: db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seo_keywords (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                keyword VARCHAR(255) NOT NULL,
                keyword_type ENUM('short_tail', 'long_tail') NOT NULL DEFAULT 'short_tail',
                category VARCHAR(50) NOT NULL DEFAULT '',
                province VARCHAR(100) NOT NULL DEFAULT '',
                target_intent VARCHAR(255) NOT NULL DEFAULT '',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_prov (province),
                KEY idx_type (keyword_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seo_areas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                province VARCHAR(100) NOT NULL,
                district VARCHAR(100) NOT NULL DEFAULT '',
                region VARCHAR(50) NOT NULL DEFAULT 'central',
                custom_title VARCHAR(255) NOT NULL DEFAULT '',
                custom_desc TEXT DEFAULT NULL,
                hot_keywords TEXT DEFAULT NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_area_prov (province)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        // Silently continue if database is not reachable yet
    }
}
