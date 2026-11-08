/*!
 * Instant Navigation & Speculative Preloader
 * เมื่อผู้ใช้เลื่อนเมาส์ผ่านลิงก์หรือแตะบนมือถือ บราวเซอร์จะ prefetch หน้านั้นล่วงหน้าทันที
 * ช่วยลดอาการดีเลย์ระหว่างเปลี่ยนหน้าเว็บได้มากกว่า 200-400ms
 */
(function () {
  'use strict';
  if (!('fetch' in window) || navigator.connection && (navigator.connection.saveData || /(2|3)g/.test(navigator.connection.effectiveType))) {
    return;
  }

  const prefetched = new Set();
  const currentHost = window.location.host;

  function canPrefetch(url) {
    if (!url) return false;
    try {
      const parsed = new URL(url, window.location.href);
      if (parsed.host !== currentHost) return false;
      if (parsed.pathname === window.location.pathname && parsed.search === window.location.search) return false;
      // ข้ามลิงก์ที่ไม่ควร prefetch (ออกจากระบบ, จัดการ, ลบ, หรือไฟล์ดาวน์โหลด)
      if (/(logout|delete|clear|action=|\.pdf|\.zip|\.sql)/i.test(parsed.href)) return false;
      return true;
    } catch (e) {
      return false;
    }
  }

  function prefetch(url) {
    if (!canPrefetch(url)) return;
    const cleanUrl = new URL(url, window.location.href).href;
    if (prefetched.has(cleanUrl)) return;
    prefetched.add(cleanUrl);

    // ใช้วิธี <link rel="prefetch"> เพื่อให้บราวเซอร์แคชหน้าล่วงหน้าแบบ low-priority
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = cleanUrl;
    link.as = 'document';
    document.head.appendChild(link);

    // เสริมด้วย fetch high-speed cache สำหรับเบราว์เซอร์ยุคใหม่
    if (window.fetch) {
      fetch(cleanUrl, { priority: 'low', credentials: 'same-origin' }).catch(() => {});
    }
  }

  let delayTimer;

  // บนคอมพิวเตอร์: เมื่อเมาส์ชี้เกิน 65ms หรือ hover
  document.addEventListener('mouseover', function (e) {
    const a = e.target.closest('a');
    if (!a || !a.href) return;
    delayTimer = setTimeout(function () {
      prefetch(a.href);
    }, 65);
  }, { passive: true });

  document.addEventListener('mouseout', function (e) {
    if (delayTimer) clearTimeout(delayTimer);
  }, { passive: true });

  // บนมือถือ: เมื่อนิ้วแตะเริ่มสัมผัส (touchstart เกิดก่อน click ~100-300ms)
  document.addEventListener('touchstart', function (e) {
    const a = e.target.closest('a');
    if (a && a.href) {
      prefetch(a.href);
    }
  }, { passive: true });

  // เมื่อแท็บลิงก์โฟกัสด้วยคีย์บอร์ด
  document.addEventListener('focusin', function (e) {
    const a = e.target.closest('a');
    if (a && a.href) {
      prefetch(a.href);
    }
  }, { passive: true });
})();
