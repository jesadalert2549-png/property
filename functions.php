<?php
/**
 * ไฟล์ Legacy Wrapper สำหรับ Backward Compatibility
 * 
 * ⚠️ ไฟล์นี้เป็น wrapper เท่านั้น — ระบบหลักใช้ includes/helpers.php แล้ว
 * ไฟล์นี้มีไว้เผื่อมี legacy code เก่าที่อาจจะอ้างอิง functions.php โดยตรง
 * 
 * หากไม่มีไฟล์ใดใช้งาน functions.php โดยตรง สามารถลบไฟล์นี้ได้
 */

// โหลด helpers.php หลักที่มีฟังก์ชันครบถ้วนและ updated
require_once __DIR__ . '/includes/helpers.php';

// Note: ฟังก์ชันทั้งหมดถูก define ใน includes/helpers.php แล้ว
// - db()          → includes/db.php (with auto-start MySQL, timezone, schema migration)
// - e()           → includes/helpers.php
// - site_url()    → includes/helpers.php
// - type_label()  → includes/helpers.php (with i18n support)
// - price_label() → includes/helpers.php (with i18n support)