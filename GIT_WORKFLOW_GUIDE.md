# 🛡️ คู่มือการป้องกันไม่ให้ config.php และ uploads โดนเขียนทับ

เมื่อคุณพัฒนาโค้ดบน branch `test` แล้วต้องการดึงไปรวมกับ `main` โดย**ไม่ต้องการให้ config.php หรือรูปใน uploads ของเครื่องหลัก/เซิร์ฟเวอร์โดนทับ**

---

## ⚡ วิธีที่ง่ายและเร็วที่สุด (ใช้คำสั่งล็อค)

Git มีฟังก์ชันสั่งให้ "มองข้ามไฟล์นี้เฉพาะเครื่องนี้" โดยที่ไฟล์จริงยังอยู่และทำงานได้ 100%

### 1. ล็อคไฟล์ config.php ไม่ให้ Git แตะต้อง
เปิด Terminal (PowerShell) แล้วพิมพ์คำสั่ง:
```powershell
git update-index --assume-unchanged includes/config.php
```
*(หรือรันสคริปต์ที่เตรียมไว้ให้: `.\protect-config.ps1 -Action lock`)*

**ผลลัพธ์:**
- คุณจะแก้ `DB_HOST`, `DB_NAME`, `DB_PASS` ในเครื่องนี้ยังไงก็ได้
- เมื่อสั่ง `git status`, `git add .`, หรือ `git commit` Git จะทำเป็นมองไม่เห็นการเปลี่ยนแปลงของไฟล์นี้
- เวลา Merge จาก `test` ไป `main` **จะไม่เกิด Conflict และไม่เอาค่า config ของเครื่องนี้ไปทับเด็ดขาด**

---

### 2. ถ้าวันไหนอยากปลดล็อคให้ Git กลับมาติดตาม:
```powershell
git update-index --no-assume-unchanged includes/config.php
```
*(หรือรัน `.\protect-config.ps1 -Action unlock`)*

---

## 🚀 ขั้นตอนการทำงานปกติ (Workflow)

1. **ทำงานบน branch `test`:**
   - พัฒนาโค้ด แก้ไขฟีเจอร์ต่างๆ
   - สั่ง `git add .` แล้ว `git commit -m "update feature"`
   *(ไฟล์ config.php จะไม่ติดเข้าไป)*

2. **เอางานไปรวมเข้า `main`:**
   ```powershell
   git checkout main
   git merge test
   git push origin main
   ```
   *(โค้ดทุกอย่างจะไปรวมที่ main แต่ config.php และโฟลเดอร์ uploads ของเครื่อง/เซิร์ฟเวอร์จะคงเดิม ปลอดภัย 100%)*

---

## 📁 ไฟล์ Template สำรอง
มีไฟล์ **`includes/config.example.php`** เตรียมไว้ให้แล้ว หากในอนาคตติดตั้งโปรเจกต์ในเครื่องใหม่ เพียงแค่ Copy ไฟล์นี้แล้วเปลี่ยนชื่อเป็น `config.php` แล้วใส่รหัสผ่านฐานข้อมูลได้ทันทีครับ
