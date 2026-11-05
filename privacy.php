<?php
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
current_lang();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('นโยบายความเป็นส่วนตัว', 'Privacy Policy')) ?> | <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= e((string)filemtime(__DIR__ . '/css/style.css')) ?>">
  <link rel="stylesheet" href="css/home.css?v=<?= e((string)filemtime(__DIR__ . '/css/home.css')) ?>">
  <?php site_controls_head(); lang_toggle_head(); ?>
  <style>
    .privacy-page{min-height:65vh;padding:48px 20px 64px}.privacy-card{max-width:820px;margin:auto;padding:clamp(24px,5vw,48px);border:1px solid #e5e9ee;border-radius:22px;background:#fff;box-shadow:0 14px 40px rgba(24,50,75,.08)}
    .privacy-card h1{font-size:clamp(27px,4vw,36px);margin:0 0 10px}.privacy-card h2{margin:28px 0 8px;font-size:19px}.privacy-card p,.privacy-card li{color:#536273;line-height:1.85}.privacy-updated{color:#8491a0!important;font-size:13px}.privacy-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}
  </style>
</head>
<body>
<main class="privacy-page"><article class="privacy-card">
  <span class="listing-eyebrow">ACCOUNT &amp; PRIVACY</span>
  <h1><?= e(t('นโยบายความเป็นส่วนตัว', 'Privacy Policy')) ?></h1>
  <p class="privacy-updated"><?= e(t('ข้อมูลสำหรับการสมัคร การเข้าสู่ระบบ และสถิติการเข้าชมเว็บไซต์ ', 'Information used for registration, login and visitor statistics of ') . SITE_NAME) ?></p>
  <h2><?= e(t('ข้อมูลที่เราใช้', 'Information we use')) ?></h2>
  <p><?= e(t('เมื่อสมัครด้วยอีเมล เราจัดเก็บชื่อ อีเมล ชื่อผู้ใช้ และข้อมูลติดต่อที่คุณกรอก เมื่อสมัครหรือเข้าสู่ระบบด้วย Google, Facebook หรือ Apple เราได้รับรหัสบัญชีเฉพาะจากผู้ให้บริการ รวมถึงชื่อและอีเมลที่ผู้ให้บริการส่งให้ เพื่อสร้างบัญชีและยืนยันการเข้าสู่ระบบ ระบบบันทึกเวลาเข้าสู่ระบบสำเร็จล่าสุดของบัญชีเพื่อช่วยดูแลบัญชีและตรวจสอบการใช้งาน โดยไม่บันทึกรหัสผ่านหรือ IP address', 'When you register with email, we store your name, email, username and the contact details you provide. When you register or log in with Google, Facebook or Apple, we receive a unique account ID from the provider, along with the name and email it shares, to create the account and verify the login. The system records the latest successful login time of each account to help maintain and audit usage, without storing passwords or IP addresses.')) ?></p>
  <p><?= e(t('สถิติการเข้าชมเว็บไซต์เป็นทางเลือกและเริ่มเก็บหลังจากคุณยินยอมเท่านั้น ข้อมูลประกอบด้วยหน้าที่เข้าชม ประกาศที่ดู ประเภทอุปกรณ์และเบราว์เซอร์ และโดเมนที่ส่งต่อมายังเว็บไซต์ ระบบใช้รหัสสุ่มเพื่อแยกผู้เข้าชมซ้ำ โดยไม่เก็บ IP address คุณเปลี่ยนใจได้จากปุ่มตั้งค่าคุกกี้ และเมื่อปฏิเสธ ระบบจะลบข้อมูลสถิติที่เชื่อมกับรหัสผู้เข้าชมของเบราว์เซอร์นั้น', 'Visitor statistics are optional and only start collecting after you consent. The data includes visited pages, viewed listings, device and browser types, and referring domains. The system uses a random ID to distinguish repeat visitors without storing IP addresses. You can change your mind from the cookie settings button — when declined, statistics tied to that browser\'s visitor ID are deleted.')) ?></p>
  <h2><?= e(t('การจัดเก็บและการใช้งาน', 'Storage & usage')) ?></h2>
  <p><?= e(t('ข้อมูลบัญชีจะจัดเก็บในฐานข้อมูลของเว็บไซต์เพื่อจัดการบัญชีและประกาศของคุณ เราไม่ขอหรือจัดเก็บรหัสผ่าน Google, Facebook หรือ Apple การยืนยันรหัสผ่านดำเนินการโดยผู้ให้บริการแต่ละราย', 'Account information is stored in the website database to manage your account and listings. We never request or store Google, Facebook or Apple passwords — password verification is handled by each provider.')) ?></p>
  <h2><?= e(t('การเชื่อมโยงบัญชี', 'Account linking')) ?></h2>
  <p><?= e(t('บัญชีโซเชียลที่ใช้สมัครครั้งแรกจะเชื่อมกับบัญชีเว็บไซต์ตามรหัสบัญชีของผู้ให้บริการ หากอีเมลมีบัญชีอยู่แล้ว ระบบจะขอให้เข้าสู่ระบบด้วยวิธีเดิมก่อน เพื่อป้องกันการผูกบัญชีโดยไม่ได้รับอนุญาต', 'The social account used at first registration is linked to the website account via the provider\'s account ID. If that email already has an account, the system asks you to log in with the original method first, to prevent unauthorised account linking.')) ?></p>
  <h2><?= e(t('ทางเลือกของคุณ', 'Your choices')) ?></h2>
  <p><?= e(t('คุณเลือกสมัครด้วยอีเมลและรหัสผ่านแทนได้ การใช้บัญชีภายนอกอยู่ภายใต้เงื่อนไขและนโยบายของผู้ให้บริการนั้นด้วย', 'You may register with an email and password instead. Using an external account is also subject to that provider\'s terms and policies.')) ?></p>
  <p><?= e(t('เมื่อคุณเข้าสู่ระบบและยินยอมคุกกี้สถิติ เราจะเชื่อมหน้าที่เข้าชมกับบัญชีของคุณ เพื่อแสดงสถิติการใช้งานแก่ผู้ดูแลเว็บไซต์ ระบบจะไม่เก็บ IP address และคุณเปลี่ยนหรือถอนความยินยอมได้ที่การตั้งค่าคุกกี้', 'When you log in and consent to statistics cookies, visited pages are linked with your account so usage statistics can be shown to the website administrator. The system never stores IP addresses, and you can change or withdraw consent in the cookie settings.')) ?></p>
  <div class="privacy-actions"><?php render_lang_toggle(); ?><a class="btn" href="register.php"><?= e(t('กลับไปสมัครสมาชิก', 'Back to registration')) ?></a><a class="chip" href="login.php"><?= e(t('เข้าสู่ระบบ', 'Log in')) ?></a></div>
</article></main>
<?php render_site_footer(); ?>
</body>
</html>
