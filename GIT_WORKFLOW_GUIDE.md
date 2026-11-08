# 🛡️ คู่มือการ Deploy และป้องกันไม่ให้ config.php และ uploads โดนเขียนทับ (แก้ปัญหา HTTP 500)

---

## ⚠️ สาเหตุที่ Deploy แล้วขึ้น HTTP ERROR 500
บนเซิร์ฟเวอร์จริง (Hostinger `standardproperty.cloud`) การเชื่อมต่อ MySQL ใช้ชื่อฐานข้อมูลและรหัสผ่านจริง 
เมื่อคุณสั่ง `git pull` บนเซิร์ฟเวอร์ ถ้าไฟล์ `includes/config.php` ใน Git เป็นค่า localhost (`property_test`, `root`, `''`) ตัว Git จะไปเขียนทับไฟล์บนเซิร์ฟเวอร์ ทำให้ต่อ Database ไม่ได้และเกิด **HTTP ERROR 500**

---

## ⚡ วิธีแก้ปัญหาถาวร 100% (ระบบ config.local.php)

ระบบได้ถูกตั้งค่าให้รองรับ **`includes/config.local.php`** เรียบร้อยแล้ว:

1. **บน Hostinger (เซิร์ฟเวอร์จริง):**
   - ไปที่ Hostinger File Manager เข้าโฟลเดอร์ `includes/`
   - สร้างไฟล์ชื่อ **`config.local.php`** (มีไฟล์ตัวอย่างให้ดูที่ `includes/config.local.example.php`)
   - ใส่รหัสผ่านฐานข้อมูลจริงของ Hostinger:
     ```php
     <?php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'ชื่อ_DB_บน_hostinger');
     define('DB_USER', 'ชื่อ_User_บน_hostinger');
     define('DB_PASS', 'รหัสผ่าน_บน_hostinger');
     ```
2. **ทำไมวิธีนี้ถึงปลอดภัย 100%?**
   - ไฟล์ `config.local.php` อยู่ใน `.gitignore` เรียบร้อยแล้ว
   - **Git จะไม่ยุ่งกับไฟล์นี้เด็ดขาด** ไม่ดึงขึ้น GitHub และไม่ลบทิ้ง
   - ต่อไปนี้ไม่ว่าจะสั่ง `git pull` บน Hostinger กี่ครั้ง ตัว Git จะไม่แตะต้องรหัสผ่านจริงของเซิร์ฟเวอร์เลย ทำให้เว็บทำงานได้ตลอดเวลา **ไม่เกิด 500 error อีกแน่นอน**

3. **บนเครื่อง Localhost (XAMPP):**
   - `includes/config.php` จะใช้ค่าเริ่มต้น (`localhost`, `property_test`, `root`, `''`) ให้โดยอัตโนมัติ ใช้งานได้ทันทีไม่ต้องแก้อะไร

---

## 🚀 ขั้นตอนการนำงานขึ้น Deploy (Workflow)

1. บันทึกโค้ดบนเครื่องทดสอบ:
   ```powershell
   git add .
   git commit -m "อัปเดตระบบ"
   git push origin main
   ```
2. ดึงโค้ดลง Hostinger (Deploy):
   ```bash
   git pull origin main
   ```
   *(โค้ดฟีเจอร์และดีไซน์ใหม่ทั้งหมดจะอัปเดตทันที โดยฐานข้อมูลจริงและรูปใน uploads ไม่โดนเขียนทับครับ)*
