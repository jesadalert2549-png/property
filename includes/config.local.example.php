<?php
// ตัวอย่างไฟล์ตั้งค่าเฉพาะเครื่อง (Localhost / Production Hostinger Override)
// บนเซิร์ฟเวอร์จริง (Hostinger): ก๊อปปี้ไฟล์นี้แล้วตั้งชื่อเป็น config.local.php
// จากนั้นใส่ชื่อ DB, User, Password จริงของ Hostinger
// Git จะไม่ยุ่งกับไฟล์ config.local.php เลย ทำให้ deploy ได้ตลอดเวลาโดยไม่เกิด 500 error อีกต่อไป

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
