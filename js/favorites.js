/**
 * PropertyGO — Universal Favorites & Wishlist Engine
 * รองรับการกดหัวใจทั้งสมาชิก (บันทึก sync เข้า DB) และผู้เยี่ยมชมทั่วไป (บันทึก LocalStorage)
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'sp_guest_favorites';

  function getGuestFavorites() {
    try {
      const data = localStorage.getItem(STORAGE_KEY);
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  function setGuestFavorites(ids) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
      updateHeartBadgeCount(ids.length);
    } catch (e) { }
  }

  function updateHeartBadgeCount(count) {
    document.querySelectorAll('.sp-fav-count-badge').forEach(b => {
      b.textContent = count > 0 ? count : '';
      b.style.display = count > 0 ? 'inline-flex' : 'none';
    });
  }

  function getApiEndpoint(file) {
    return window.location.pathname.includes('/admin/') ? '../' + file : file;
  }

  window.spToggleFavorite = async function (propertyId, btnEl) {
    propertyId = parseInt(propertyId, 10);
    if (!propertyId || propertyId <= 0) return;

    if (btnEl) {
      btnEl.classList.add('heart-animating');
      setTimeout(() => btnEl.classList.remove('heart-animating'), 300);
    }

    try {
      const resp = await fetch(getApiEndpoint('api/favorites.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ property_id: propertyId })
      });
      const data = await resp.json();

      if (data.ok) {
        if (data.is_logged_in) {
          // สมาชิกที่ล็อกอินแล้ว
          applyFavState(propertyId, data.is_favorite);
          updateHeartBadgeCount(data.total);
          showToast(data.is_favorite ? 'บันทึกทรัพย์ที่ชอบแล้ว ❤️' : 'ลบออกจากรายการที่ชอบแล้ว 🤍');
        } else {
          // ผู้เยี่ยมชมทั่วไป (Guest Mode บันทึก LocalStorage)
          let guestFavs = getGuestFavorites();
          const idx = guestFavs.indexOf(propertyId);
          let isFav = false;
          if (idx >= 0) {
            guestFavs.splice(idx, 1);
            isFav = false;
          } else {
            guestFavs.push(propertyId);
            isFav = true;
          }
          setGuestFavorites(guestFavs);
          applyFavState(propertyId, isFav);
          showToast(isFav ? 'บันทึกทรัพย์ที่ชอบแล้ว ❤️' : 'ลบออกจากรายการที่ชอบแล้ว 🤍');
        }
      }
    } catch (e) {
      console.error('Favorite toggle failed', e);
    }
  };

  function applyFavState(propertyId, isFav) {
    document.querySelectorAll(`[data-fav-id="${propertyId}"]`).forEach(btn => {
      btn.setAttribute('data-is-fav', isFav ? '1' : '0');
      btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
      if (isFav) {
        btn.classList.add('is-favorited');
      } else {
        btn.classList.remove('is-favorited');
      }

      const svgPath = btn.querySelector('.heart-svg path');
      const svgEl = btn.querySelector('.heart-svg');
      if (svgEl) {
        if (isFav) {
          svgEl.setAttribute('fill', '#ef4444');
          svgEl.setAttribute('stroke', '#ef4444');
        } else {
          svgEl.setAttribute('fill', 'none');
          svgEl.setAttribute('stroke', 'currentColor');
        }
      }

      if (btn.classList.contains('sp-btn-save-detail')) {
        const textSpan = btn.querySelector('.fav-btn-text');
        if (textSpan) textSpan.textContent = isFav ? 'บันทึกแล้ว' : 'บันทึก';
      }
    });
  }

  function showToast(msg) {
    let t = document.getElementById('spFavToast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'spFavToast';
      t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#0B1C33;color:#fff;padding:10px 22px;border-radius:999px;font-size:13.5px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:99999;opacity:0;transition:opacity .25s,transform .25s;pointer-events:none;border:1px solid rgba(197,160,89,.5);display:flex;align-items:center;gap:8px;';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(-4px)';
    clearTimeout(window._favToastTimer);
    window._favToastTimer = setTimeout(() => {
      t.style.opacity = '0';
      t.style.transform = 'translateX(-50%)';
    }, 2200);
  }

  // ซิงก์สถานะตอนโหลดหน้าเว็บ
  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const resp = await fetch(getApiEndpoint('api/favorites.php'));
      const data = await resp.json();
      if (data.ok) {
        if (data.is_logged_in) {
          // หากผู้ใช้มี Guest Favorites ที่เคยกดไว้ก่อนล็อกอิน ให้ส่งไป Sync เข้า Database ทันที
          const guestFavs = getGuestFavorites();
          if (guestFavs.length > 0) {
            try {
              const syncResp = await fetch(getApiEndpoint('api/favorites.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'sync_guest', guest_ids: guestFavs })
              });
              const syncData = await syncResp.json();
              if (syncData.ok) {
                localStorage.removeItem(STORAGE_KEY);
                if (syncData.favorites) {
                  data.favorites = syncData.favorites;
                  data.total = syncData.total;
                }
              }
            } catch (e) {}
          }

          const userFavs = data.favorites || [];
          userFavs.forEach(id => applyFavState(id, true));
          updateHeartBadgeCount(userFavs.length);
        } else {
          const guestFavs = getGuestFavorites();
          guestFavs.forEach(id => applyFavState(id, true));
          updateHeartBadgeCount(guestFavs.length);
        }
      }
    } catch (e) { }

    // Event delegation สำหรับปุ่มหัวใจ
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-fav-toggle]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const id = btn.getAttribute('data-fav-id');
      if (id) {
        window.spToggleFavorite(id, btn);
      }
    });
  });
})();


// PropertyGO — Live Notification Badge Synchronizer
(function() {
  'use strict';
  async function syncNotifications() {
    try {
      const apiPath = window.location.pathname.includes('/admin/') ? '../api/notifications.php' : 'api/notifications.php';
      const resp = await fetch(apiPath);
      const data = await resp.json();
      if (data.ok && typeof data.unread_count === 'number') {
        const c = data.unread_count;
        document.querySelectorAll('.sp-notif-badge').forEach(b => {
          b.textContent = c > 0 ? c : '';
          b.style.display = c > 0 ? 'inline-flex' : 'none';
        });
      }
    } catch(e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncNotifications);
  } else {
    syncNotifications();
  }
})();


// PropertyGO — Property Card Click Tracker
(function() {
  'use strict';
  document.addEventListener('click', function(e) {
    const link = e.target.closest('a[href*="property.php?id="]');
    if (!link) return;
    const url = new URL(link.href, window.location.href);
    const pid = url.searchParams.get('id');
    if (!pid || parseInt(pid, 10) <= 0) return;

    // ส่งสัญญาณนับคลิกแบบไม่บล็อกการเปลี่ยนหน้า (Beacon / keepalive)
    const payload = JSON.stringify({ property_id: parseInt(pid, 10) });
    if (navigator.sendBeacon) {
      navigator.sendBeacon('api/click.php', payload);
    } else {
      fetch('api/click.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true
      }).catch(function() {});
    }
  }, { passive: true });
})();
