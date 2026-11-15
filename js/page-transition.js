/**
 * Page Transition Effect — iOS-Style Slide Animation (Mobile Only)
 * เลื่อนหน้าแบบ iOS เฉพาะมือถือ/iPad (≤1199px)
 * Desktop (≥1200px) เปิดหน้าปกติ (ไม่มี transition)
 */
(function () {
  'use strict';

  // ตรวจสอบว่าบราว์เซอร์รองรับ
  if (!('replaceState' in history) || !document.body.animate) return;

  // ตรวจสอบว่าเป็น mobile/tablet หรือไม่
  function isMobile() {
    return window.innerWidth <= 1199;
  }

  const duration = 280; // milliseconds (เร็วขึ้น แต่ยังนุ่มนวล)
  const easing = 'cubic-bezier(0.32, 0.72, 0, 1)'; // iOS slide easing

  let isTransitioning = false;

  // Slide in เมื่อโหลดหน้าใหม่เสร็จ (เฉพาะ mobile)
  function slideIn() {
    if (!isMobile()) return;
    
    document.body.animate([
      { transform: 'translateX(100%)', opacity: 0.95 },
      { transform: 'translateX(0)', opacity: 1 }
    ], {
      duration: duration,
      easing: easing,
      fill: 'forwards'
    });
  }

  // Slide out ก่อนเปลี่ยนหน้า (เฉพาะ mobile)
  function slideOut(callback) {
    if (!isMobile()) {
      // Desktop: ไปหน้าใหม่ทันที ไม่มี animation
      if (callback) callback();
      return;
    }

    isTransitioning = true;

    const anim = document.body.animate([
      { transform: 'translateX(0)', opacity: 1 },
      { transform: 'translateX(-25%)', opacity: 0.85 }
    ], {
      duration: duration,
      easing: easing,
      fill: 'forwards'
    });

    anim.onfinish = function () {
      if (callback) callback();
    };
  }

  // เริ่มต้น: slide in หน้าปัจจุบัน (เฉพาะ mobile)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', slideIn);
  } else {
    slideIn();
  }

  // Intercept ลิงก์ทั้งหมด
  document.addEventListener('click', function (e) {
    if (isTransitioning) return; // ป้องกันคลิกซ้ำระหว่าง transition

    const link = e.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');

    // ข้ามลิงก์พิเศษ
    if (!href ||
      href.startsWith('#') ||
      href.startsWith('javascript:') ||
      href.startsWith('tel:') ||
      href.startsWith('mailto:') ||
      link.target === '_blank' ||
      link.download ||
      link.hasAttribute('data-no-transition') ||
      e.ctrlKey ||
      e.metaKey ||
      e.shiftKey ||
      e.button !== 0) {
      return;
    }

    // ตรวจสอบว่าเป็น same-origin
    try {
      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;

      // ข้าม logout, download, API, รูปภาพ, admin actions
      if (/(logout|download|delete|api\/|\.(pdf|zip|jpg|png|webp|svg)$)/i.test(url.pathname)) return;

    } catch (err) {
      return;
    }

    // Desktop: เปิดหน้าปกติ ไม่ต้อง preventDefault
    if (!isMobile()) return;

    // Mobile: ป้องกัน default navigation + slide out
    e.preventDefault();

    slideOut(function () {
      window.location.href = href;
    });

  }, false);

  // รองรับ bfcache (กด Back/Forward) - เฉพาะ mobile
  window.addEventListener('pageshow', function (e) {
    if (e.persisted && isMobile()) {
      // มาจาก bfcache — slide in จากซ้าย (Back)
      document.body.animate([
        { transform: 'translateX(-25%)', opacity: 0.85 },
        { transform: 'translateX(0)', opacity: 1 }
      ], {
        duration: duration,
        easing: easing,
        fill: 'forwards'
      });
    }
    isTransitioning = false;
  });

  // Reset transform ก่อน unload
  window.addEventListener('pagehide', function () {
    if (isMobile()) {
      document.body.style.transform = 'translateX(0)';
      document.body.style.opacity = '1';
    }
    isTransitioning = false;
  });

  // Reset เมื่อ resize (เปลี่ยนจาก mobile → desktop หรือกลับกัน)
  let resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      document.body.style.transform = '';
      document.body.style.opacity = '';
    }, 250);
  }, { passive: true });

})();
