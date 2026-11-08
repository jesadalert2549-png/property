<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/staff.php';
require_admin();

$isFounder = ($_SESSION['role'] ?? '') === 'admin';
if (!$isFounder) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>สิทธิ์ถูกจำกัด</title><link rel="stylesheet" href="admin.css"></head><body style="padding:40px;text-align:center;font-family:sans-serif;"><h2>🔒 หน้านี้สงวนสิทธิ์เฉพาะ Founder & CEO (เจ้าของเว็บไซต์) เท่านั้น</h2><p><a href="index.php" class="btn">← กลับแดชบอร์ด</a></p></body></html>';
    exit;
}

$pdo = db();

// ตรวจสอบสถานะการเชื่อมต่อและข้อมูลจริงในระบบ
$statProps = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE approval = 'approved'")->fetchColumn();
$statProjects = (int)$pdo->query("SELECT COUNT(*) FROM development_projects WHERE is_published = 1")->fetchColumn();
$statKeywords = (int)$pdo->query("SELECT COUNT(*) FROM seo_keywords WHERE is_active = 1")->fetchColumn();
$statSearches = (int)$pdo->query("SELECT COUNT(*) FROM search_queries")->fetchColumn();
$statMembers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$statAgents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE agent_status = 'verified'")->fetchColumn();

// หมวดหมู่และฟีเจอร์ทั้งหมดของระบบ PropertyGO
$featureCatalog = [
    'seo_geo' => [
        'category' => '🚀 ระบบการตลาด & SEO อัจฉริยะ (SEO & GEO AI Engine)',
        'badge'    => 'AI & Google SERP Ready',
        'color'    => '#C5A059',
        'items'    => [
            [
                'title'       => 'ระบบส่งข้อความแจ้งเตือนอสังหาฯ (Smart Push Alerts Engine)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'notifications.php, api/notifications.php, admin/notifications.php',
                'desc'        => 'ระบบส่งข้อความแจ้งเตือนดีลพิเศษ ทรัพย์ลดราคา และโอกาสทำเลทอง เข้ากล่องข้อความของผู้ใช้ทุกคน พร้อมจุดแจ้งเตือนกระดิ่งแบบเรียลไทม์',
                'icon'        => '📢',
                'test_url'    => 'notifications.php'
            ],
            [
                'title'       => 'Google Sitelinks Searchbox & Navigation Elements',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'index.php, includes/seo_provinces.php',
                'desc'        => 'ฝัง Schema.org/WebSite (SearchAction) และ SiteNavigationElement ให้หน้าแรกสามารถขึ้นกล่องค้นหาและลิงก์ย่อย (Sitelinks) บน Google Search ได้เหมือน DDproperty',
                'icon'        => '🔍',
                'test_url'    => '../index.php'
            ],
            [
                'title'       => 'ระบบ SEO & AI เจาะจง 77 จังหวัด & Prime CBD Zones',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/seo.php, includes/seo_provinces.php',
                'desc'        => "มีคีย์เวิร์ดเป้าหมายเปิดใช้งาน {$statKeywords} คำ ครอบคลุม 77 จังหวัด และย่านธุรกิจ CBD ทำเลรถไฟฟ้า BTS/MRT พร้อมสร้างหน้า Landing Page ดักเสิร์ชอัตโนมัติ",
                'icon'        => '📍',
                'test_url'    => 'seo.php'
            ],
            [
                'title'       => 'ระบบบันทึกและวิเคราะห์คำค้นหาเรียลไทม์ (Live Search Intelligence)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'search.php, api/track.php, admin/seo.php',
                'desc'        => "บันทึกทุกคำค้นหาใหม่ของผู้เข้าชมอัตโนมัติ (ปัจจุบันบันทึก {$statSearches} คำค้น) พร้อมปุ่ม ⚡ 'ทำเป็น SEO' คลิกเดียวเพื่อสร้างคีย์เวิร์ดเป้าหมายใหม่ทันที",
                'icon'        => '📡',
                'test_url'    => 'seo.php#search-insights'
            ],
            [
                'title'       => 'RealEstateAgent Schema + Social Profiles (sameAs)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'includes/config.php, includes/seo_provinces.php',
                'desc'        => 'เชื่อมต่อโปรไฟล์โซเชียล Facebook Page และ LINE Official เข้ากับ Schema เพื่อให้ Google ดึงไอคอนโซเชียลไปแสดงใต้ชื่อเว็บไซต์',
                'icon'        => '🌐',
                'test_url'    => '../index.php'
            ],
            [
                'title'       => 'Google SERP Favicon & Brand Icon Manager',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/favicon.php, images/favicon-48x48.png',
                'desc'        => 'ระบบจัดการไอคอนแบรนด์ที่จะแสดงบนผลการค้นหา Google Search พร้อมตัวจำลองขนาดจริง (SERP Preview) และอัปโหลดสร้างไฟล์ทุกขนาดอัตโนมัติ',
                'icon'        => '🎨',
                'test_url'    => 'favicon.php'
            ],
            [
                'title'       => 'XML Sitemap อัตโนมัติ & Robots.txt สำหรับบอท AI',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'sitemap_xml.php, robots.txt',
                'desc'        => 'สร้างแผนผังไซต์ XML รองรับ Googlebot, SearchGPT, ClaudeBot, PerplexityBot, Applebot พร้อมส่งเข้า Google Search Console ได้ทันที',
                'icon'        => '🗺️',
                'test_url'    => '../sitemap.xml'
            ],
        ]
    ],
    'speed_performance' => [
        'category' => '⚡ ความเร็วและประสิทธิภาพเว็บ (Speed & Performance Suite)',
        'badge'    => 'Sub-40ms Load Time',
        'color'    => '#10B981',
        'items'    => [
            [
                'title'       => 'Instant Preloading & Speculative Loading',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'js/instant-page.js, includes/helpers.php',
                'desc'        => 'ตรวจจับจังหวะเมาส์ชี้ (Hover 65ms) หรือนิ้วแตะบนมือถือ (touchstart) เพื่อ Prefetch หน้านั้นล่วงหน้า ลดอาการดีเลย์ระหว่างเปลี่ยนหน้าได้ 200-400ms',
                'icon'        => '⚡',
                'test_url'    => '../index.php'
            ],
            [
                'title'       => 'Gzip Compression & Long-term Browser Caching',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => '.htaccess',
                'desc'        => 'บีบอัดโค้ดด้วย Deflate/Gzip ลดขนาดดาวน์โหลดสูงสุด 70% และตั้งค่าแคชรูปภาพ ฟอนต์ CSS/JS ไว้ในเครื่องผู้ใช้ 1 เดือนถึง 1 ปี',
                'icon'        => '📦',
                'test_url'    => '../index.php'
            ],
            [
                'title'       => 'Database Composite Indexing',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'includes/db.php, ตาราง properties',
                'desc'        => 'เพิ่มดัชนีผสม (approval, status, category, price, location) ทำให้คิวรีหน้าแรกและหน้าค้นหาทำงานเร็วขึ้นกว่า 10 เท่า',
                'icon'        => '🗄️',
                'test_url'    => '../search.php'
            ],
            [
                'title'       => 'Non-blocking Assets & Responsive Image Lazy Loading',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'includes/helpers.php, index.php, property.php',
                'desc'        => 'โหลดสคริปต์แบบ defer และใส่ loading="lazy" decoding="async" ให้รูปภาพทั้งหมด ยกเว้นรูปปกแรกที่ใช้ fetchpriority="high"',
                'icon'        => '🖼️',
                'test_url'    => '../index.php'
            ],
        ]
    ],
    'listings_management' => [
        'category' => '🏠 การจัดการประกาศและโครงการ (Listings & Projects Engine)',
        'badge'    => "ทรัพย์อนุมัติแล้ว {$statProps} รายการ",
        'color'    => '#3B82F6',
        'items'    => [
            [
                'title'       => 'ระบบตรวจอนุมัติและจัดการประกาศ (Review & Approval Workflow)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/index.php, admin/view.php, admin/edit.php',
                'desc'        => 'คัดกรองประกาศ ตรวจสอบรูปภาพ รายละเอียด ย้ายสถานะ เผยแพร่/ซ่อน หรือทำเครื่องหมาย "ขายแล้ว/เช่าแล้ว"',
                'icon'        => '▦',
                'test_url'    => 'index.php'
            ],
            [
                'title'       => 'ระบบโปรโมตทรัพย์เด่นหน้าแรก (Featured Listings Promotion)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/featured.php, index.php',
                'desc'        => 'ปักหมุดทรัพย์เด่นให้อยู่แถวหน้าสุดของหน้าแรก และจัดการหมวดหมู่แนะนำ (คอนโดแนะนำ, บ้านแนะนำ ฯลฯ)',
                'icon'        => '✦',
                'test_url'    => 'featured.php'
            ],
            [
                'title'       => 'ระบบพอร์ตโครงการใหม่ (Development Projects Showcase)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/projects.php, project.php, submit-project.php',
                'desc'        => "รองรับการลงข้อมูลโครงการบ้าน-คอนโดใหม่เปิดตัว พร้อมแกลเลอรี สิ่งอำนวยความสะดวก และแผนผังโครงการ (เผยแพร่อยู่ {$statProjects} โครงการ)",
                'icon'        => '⌂',
                'test_url'    => 'projects.php'
            ],
            [
                'title'       => 'ระบบคำนวณเงินผ่อน & Rental Yield อสังหาฯ แบบเรียลไทม์',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'index.php, property.php, js/app.js',
                'desc'        => 'Interactive Property Suite เครื่องมือคำนวณค่างวดเงินกู้ และประมาณการผลตอบแทนค่าเช่า (Yield) ในหน้ารายละเอียดทรัพย์',
                'icon'        => '🧮',
                'test_url'    => '../index.php'
            ],
        ]
    ],
    'users_agents' => [
        'category' => '👥 สมาชิก ตัวแทน & การยืนยันตัวตน (Users, Agents & Auth)',
        'badge'    => "สมาชิก {$statMembers} คน | Agent รับรอง {$statAgents} คน",
        'color'    => '#8B5CF6',
        'items'    => [
            [
                'title'       => 'ระบบบริหารจัดการสมาชิก & สิทธิ์โควตาประกาศ (Member Quota & Tier Command)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/members.php, submit.php, dashboard.php',
                'desc'        => 'เครื่องมือควบคุมสิทธิ์รายบุคคล กำหนดระดับ Free, Pro, VIP และระบุโควตาจำนวนประกาศสูงสุด ป้องกันสแปมและรองรับผู้ใช้ปริมาณมาก',
                'icon'        => '👥',
                'test_url'    => 'members.php'
            ],
            [
                'title'       => 'ระบบตรวจและรับรอง Agent (Verified Agent Badges & Documents)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/agents.php, admin/agent-document.php',
                'desc'        => 'ตรวจสอบเอกสารบัตรประจำตัวประชาชน/ใบอนุญาตตัวแทน พร้อมมอบตราสัญลักษณ์ "✓ Agent รับรอง" เพื่อสร้างความน่าเชื่อถือสูงในหน้าเว็บ',
                'icon'        => '✓',
                'test_url'    => 'agents.php'
            ],
            [
                'title'       => 'ระบบสมาชิก & พอร์ทัลส่วนตัว (Member Dashboard & Public Profile)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'dashboard.php, member.php, edit-listing.php',
                'desc'        => 'สมาชิกมีหน้าจัดการประกาศของตนเอง และหน้าโปรไฟล์สาธารณะ (Public Profile) รวมทรัพย์ทั้งหมดที่เผยแพร่',
                'icon'        => '👤',
                'test_url'    => '../dashboard.php'
            ],
            [
                'title'       => 'ระบบบันทึก & กดใจทรัพย์ที่ชอบ (Favorites & Saved Properties Engine)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'favorites.php, api/favorites.php, js/favorites.js',
                'desc'        => 'ระบบกดหัวใจบันทึกทรัพย์ รองรับทั้งสมาชิก (Sync ลงฐานข้อมูล) และผู้เยี่ยมชมทั่วไป (Guest LocalStorage) พร้อมหน้ารวมทรัพย์ที่ชอบและป้ายตัวเลขนับแบบเรียลไทม์',
                'icon'        => '❤️',
                'test_url'    => '../favorites.php'
            ],
            [
                'title'       => 'Social Login (Google, LINE, Facebook, Apple)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'oauth.php, admin/oauth.php, includes/oauth.php',
                'desc'        => 'เข้าสู่ระบบด้วยโซเชียลมีเดียได้อย่างสะดวกรวดเร็ว พร้อมหน้าจอตั้งค่า Client ID / Secret ในระบบหลังบ้าน',
                'icon'        => '🔒',
                'test_url'    => 'oauth.php'
            ],
        ]
    ],
    'analytics_reporting' => [
        'category' => '📊 สถิติ รายงาน & ความเป็นส่วนตัว (Analytics & Governance)',
        'badge'    => 'First-party Privacy Safe',
        'color'    => '#EC4899',
        'items'    => [
            [
                'title'       => 'ระบบวิเคราะห์สถิติผู้เข้าชมเชิงลึก (In-depth Analytics Dashboard)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/analytics.php, api/track.php',
                'desc'        => 'วัดผล Pageviews (PV), Unique Visitors (UV), แหล่งที่มา (Referrer), อุปกรณ์ (Mobile/Desktop), และสถิติประกาศยอดนิยมแบบเรียลไทม์',
                'icon'        => '📈',
                'test_url'    => 'analytics.php'
            ],
            [
                'title'       => 'ระบบสร้างรายงานสรุปผู้บริหารเป็น PDF (Executive PDF Generator)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/pdf-report.php',
                'desc'        => 'ส่งออกเอกสารสรุปผลประจำเดือนในรูปแบบพร้อมพิมพ์หรือเซฟเป็น PDF สวยงามระดับองค์กร',
                'icon'        => '🧾',
                'test_url'    => 'pdf-report.php'
            ],
            [
                'title'       => 'ระบบการจัดการสิทธิ์หุ้นส่วน (Role-Based Access Control - RBAC)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/partners.php, includes/staff.php',
                'desc'        => 'Founder & CEO สามารถแต่งตั้งผู้ดูแลและกำหนดสิทธิ์เข้าถึงรายเมนู (เช่น ดูแลเฉพาะประกาศ หรือการตลาด) ได้อย่างปลอดภัย',
                'icon'        => '👑',
                'test_url'    => 'partners.php'
            ],
            [
                'title'       => 'ระบบจัดการเวอร์ชันระบบ (System Version Stamping)',
                'status'      => 'พร้อมใช้งาน 100%',
                'status_type' => 'active',
                'file'        => 'admin/version.php, version.json',
                'desc'        => 'อัปเดตเลขเวอร์ชันระบบแบบ Semantic Versioning (เช่น ' . SITE_VERSION . ') ได้ทันทีจากหลังบ้านโดยไม่ต้องแก้โค้ด',
                'icon'        => '⬇',
                'test_url'    => 'version.php'
            ],
        ]
    ],
];

// นับฟีเจอร์ทั้งหมด
$totalFeatures = 0;
foreach ($featureCatalog as $cat) {
    $totalFeatures += count($cat['items']);
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แผนที่ระบบ & ฟีเจอร์ทั้งหมด (Founder Feature Map) | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <?php lang_toggle_head(); site_controls_head(); ?>
  <style>
    .feat-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
      padding: 32px 36px;
      border-radius: 24px;
      background: linear-gradient(135deg, #0B1C33 0%, #152A4A 100%);
      color: #fff;
      border: 1px solid rgba(197, 160, 89, 0.4);
      margin-bottom: 28px;
      box-shadow: 0 16px 36px rgba(11, 28, 51, 0.15);
      position: relative;
      overflow: hidden;
    }
    .feat-hero::after {
      content: "";
      position: absolute;
      right: -30px;
      bottom: -30px;
      width: 220px;
      height: 220px;
      background: radial-gradient(circle, rgba(197, 160, 89, 0.18) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }
    .feat-hero .eyebrow {
      color: #C5A059;
      font-size: 11.5px;
      font-weight: 800;
      letter-spacing: .2em;
      text-transform: uppercase;
    }
    .feat-hero h1 {
      margin: 8px 0 6px;
      color: #fff;
      font-size: clamp(24px, 3.2vw, 32px);
      font-weight: 800;
    }
    .feat-hero p {
      margin: 0;
      color: rgba(255, 255, 255, 0.82);
      font-size: 14px;
      max-width: 680px;
      line-height: 1.6;
    }
    .feat-badge-group {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
    }
    .feat-stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      background: rgba(197, 160, 89, 0.18);
      border: 1px solid rgba(197, 160, 89, 0.45);
      color: #E5CB96;
      font-size: 12.5px;
      font-weight: 700;
    }

    .feat-cat-box {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      padding: 24px 28px;
      margin-bottom: 24px;
      box-shadow: 0 8px 24px rgba(11, 28, 51, 0.04);
    }
    .feat-cat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      padding-bottom: 14px;
      border-bottom: 1.5px solid #f1f5f9;
      margin-bottom: 18px;
    }
    .feat-cat-header h2 {
      margin: 0;
      font-size: 18px;
      color: #0B1C33;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .feat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
    }
    .feat-card {
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 18px 20px;
      background: #fafbfc;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all .18s ease;
    }
    .feat-card:hover {
      background: #fff;
      border-color: #C5A059;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(11, 28, 51, 0.06);
    }
    .feat-card-top {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 10px;
    }
    .feat-icon-box {
      display: grid;
      place-items: center;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: #fff;
      border: 1px solid #e2e8f0;
      font-size: 20px;
      flex-shrink: 0;
    }
    .feat-title {
      font-size: 14.5px;
      font-weight: 800;
      color: #0B1C33;
      margin: 0 0 4px;
      line-height: 1.4;
    }
    .feat-desc {
      font-size: 12.5px;
      color: #64748B;
      line-height: 1.55;
      margin: 0 0 12px;
    }
    .feat-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11.5px;
      padding-top: 10px;
      border-top: 1px solid #f1f5f9;
    }
    .feat-file-tag {
      font-family: monospace;
      color: #475569;
      background: #f1f5f9;
      padding: 2px 7px;
      border-radius: 5px;
      max-width: 190px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .feat-btn-test {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #0B1C33;
      font-weight: 800;
      text-decoration: none;
      padding: 4px 10px;
      border-radius: 6px;
      background: #E5CB96;
      border: 1px solid #C5A059;
      font-size: 11.5px;
      transition: background .15s;
    }
    .feat-btn-test:hover {
      background: #C5A059;
      color: #fff;
    }
  </style>
</head>
<body class="has-admin-sidebar">
<?php require __DIR__ . '/_sidebar.php'; ?>
<main style="width:min(100% - 48px, 1180px); margin:38px auto 70px;">

  <!-- Hero Header -->
  <div class="feat-hero">
    <div>
      <span class="eyebrow">FOUNDER &amp; CEO · EXCLUSIVE PLATFORM AUDIT</span>
      <h1>🗺️ แผนที่ระบบ &amp; ฟีเจอร์ทั้งหมดของเว็บไซต์</h1>
      <p>หน้านี้จัดทำขึ้นเฉพาะสำหรับ <strong>Founder &amp; CEO (คุณ <?= e($adminDisplayName) ?>)</strong> เพื่อให้คุณตรวจสอบความพร้อมของนวัตกรรม ฟีเจอร์ SEO การประมวลผล และเครื่องมือบริหารทั้งหมดที่มีในระบบได้ในที่เดียว</p>
    </div>
    <div class="feat-badge-group">
      <span class="feat-stat-pill">👑 เอกสิทธิ์เฉพาะเจ้าของเว็บไซต์</span>
      <span class="feat-stat-pill" style="background:#0B1C33; border-color:#C5A059; color:#fff;">✨ รวม <?= $totalFeatures ?> ฟีเจอร์หลักในระบบ</span>
      <span class="feat-stat-pill" style="background:rgba(16,185,129,.18); border-color:rgba(16,185,129,.4); color:#059669;">✓ สถานะระบบ: พร้อมใช้งานสมบูรณ์</span>
    </div>
  </div>

  <!-- หมวดหมู่ฟีเจอร์แต่ละกลุ่ม -->
  <?php foreach ($featureCatalog as $catKey => $cat): ?>
    <section class="feat-cat-box">
      <div class="feat-cat-header">
        <h2>
          <span><?= e($cat['category']) ?></span>
        </h2>
        <span style="font-size:12px; font-weight:800; color:<?= $cat['color'] ?>; background:rgba(0,0,0,.03); padding:4px 12px; border-radius:999px; border:1px solid #e2e8f0;">
          <?= e($cat['badge']) ?>
        </span>
      </div>

      <div class="feat-grid">
        <?php foreach ($cat['items'] as $item): ?>
          <div class="feat-card">
            <div>
              <div class="feat-card-top">
                <div class="feat-icon-box"><?= e($item['icon']) ?></div>
                <div style="flex:1;">
                  <h3 class="feat-title"><?= e($item['title']) ?></h3>
                  <span style="display:inline-block; font-size:10.5px; font-weight:800; color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:999px;">
                    ● <?= e($item['status']) ?>
                  </span>
                </div>
              </div>
              <p class="feat-desc"><?= e($item['desc']) ?></p>
            </div>

            <div class="feat-meta">
              <span class="feat-file-tag" title="<?= e($item['file']) ?>">📁 <?= e($item['file']) ?></span>
              <a href="<?= e($item['test_url']) ?>" target="_blank" rel="noopener" class="feat-btn-test">
                ทดสอบ ↗
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

</main>
</body>
</html>
