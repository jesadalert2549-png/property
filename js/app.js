(function () {
  'use strict';

  const TYPE_LABEL = { sale: 'ขาย', rent: 'ให้เช่า' };

  let allListings = [];
  let currentFilter = '';
  let current = null;     // ประกาศที่เปิดอยู่ใน modal
  let imageIndex = 0;

  const grid = document.getElementById('grid');
  const modal = document.getElementById('modal');

  // ===== ฟังก์ชันช่วย =====
  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function priceText(p) {
    return Number(p.price).toLocaleString('th-TH') + (p.type === 'rent' ? ' บาท/เดือน' : ' บาท');
  }

  function doneText(p) {
    return p.type === 'rent' ? 'ปล่อยเช่าแล้ว' : 'ขายแล้ว';
  }

  function telHref(phone) {
    return 'tel:' + String(phone || '').replace(/[^0-9+]/g, '');
  }

  // ส่งสถิติการเข้าชมไปที่ api/track.php
  function track(data) {
    if (data.event === 'page') {
      const current = new URL(window.location.href);
      const allowed = ['page', 'category', 'type'];
      const query = new URLSearchParams();
      allowed.forEach((key) => {
        const value = current.searchParams.get(key);
        if (value && /^[a-z_-]{1,30}$/i.test(value)) query.set(key, value);
      });
      data.path = current.pathname + (query.size ? '?' + query.toString() : '');
    }
    const body = JSON.stringify(data);
    if (navigator.sendBeacon) {
      navigator.sendBeacon('api/track.php', body);
    } else {
      fetch('api/track.php', { method: 'POST', body: body, keepalive: true });
    }
  }

  // ===== โหลดและวาดการ์ด =====
  async function loadListings() {
    if (!grid) return;
    try {
      const res = await fetch('api/list.php', { cache: 'no-store' });
      allListings = await res.json();
      renderGrid();
    } catch (err) {
      grid.replaceChildren(el('p', 'empty', 'โหลดประกาศไม่สำเร็จ กรุณารีเฟรชหน้า'));
    }
  }

  function renderGrid() {
    const items = allListings.filter((p) => !currentFilter || p.type === currentFilter);
    grid.replaceChildren();

    if (!items.length) {
      grid.append(el('p', 'empty', 'ยังไม่มีประกาศในหมวดนี้'));
      return;
    }
    items.forEach((p) => grid.append(buildCard(p)));
  }

  function buildCard(p) {
    const card = el('div', 'card' + (p.status === 'done' ? ' done' : ''));

    const thumb = el('div', 'thumb');
    if (p.images.length) {
      const img = el('img');
      img.src = p.images[0];
      img.alt = p.title;
      img.loading = 'lazy';
      const fallbackImage = () => img.replaceWith(el('div', 'noimg', 'ไม่มีรูปภาพ'));
      img.addEventListener('error', fallbackImage, { once: true });
      thumb.append(img);
      if (img.complete) {
        window.queueMicrotask(() => {
          if (img.naturalWidth === 0) fallbackImage();
        });
      }
    } else {
      thumb.append(el('div', 'noimg', 'ไม่มีรูปภาพ'));
    }
    thumb.append(el('span', 'badge ' + p.type, TYPE_LABEL[p.type] || ''));
    if (p.status === 'done') {
      thumb.append(el('div', 'ribbon', doneText(p)));
    }

    const info = el('div', 'info');
    info.append(
      el('h3', '', p.title),
      el('p', 'price', priceText(p)),
      el('p', 'loc', '📍 ' + (p.location || '-'))
    );

    card.append(thumb, info);
    card.addEventListener('click', () => openDetail(p));
    return card;
  }

  // ===== Modal รายละเอียด =====
  function openDetail(p) {
    current = p;
    imageIndex = 0;
    track({ event: 'property', property_id: p.id });
    drawGallery();
    drawDetail();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    current = null;
  }

  function drawGallery() {
    const p = current;
    const gallery = document.getElementById('gallery');
    const thumbs = document.getElementById('thumbs');
    gallery.replaceChildren();
    thumbs.replaceChildren();

    if (!p.images.length) {
      gallery.append(el('div', 'noimg', 'ไม่มีรูปภาพ'));
    } else {
      const img = el('img');
      img.src = p.images[imageIndex];
      img.alt = p.title;
      gallery.append(img);

      if (p.images.length > 1) {
        const prev = el('button', 'nav prev', '‹');
        const next = el('button', 'nav next', '›');
        prev.type = next.type = 'button';
        prev.addEventListener('click', () => moveImage(-1));
        next.addEventListener('click', () => moveImage(1));
        gallery.append(prev, next, el('span', 'counter', (imageIndex + 1) + ' / ' + p.images.length));
      }

      p.images.forEach((src, i) => {
        const t = el('img', i === imageIndex ? 'sel' : '');
        t.src = src;
        t.alt = '';
        t.addEventListener('click', () => { imageIndex = i; drawGallery(); });
        thumbs.append(t);
      });
    }

    if (p.status === 'done') {
      gallery.append(el('div', 'ribbon', doneText(p)));
    }
  }

  function moveImage(step) {
    const total = current.images.length;
    imageIndex = (imageIndex + step + total) % total;
    drawGallery();
  }

  function drawDetail() {
    const p = current;
    const box = document.getElementById('detail');
    box.replaceChildren();

    const tags = el('p');
    tags.append(el('span', 'tag-type', TYPE_LABEL[p.type] || ''));
    if (p.status === 'done') {
      tags.append(' ', el('span', 'status-tag', doneText(p)));
    }

    const contact = el('div', 'contact-card');
    contact.append(el('p', '', '👤 ' + (p.contactName || '-')));
    const tel = el('a', '', '📞 ' + (p.phone || '-'));
    tel.href = telHref(p.phone);
    contact.append(tel);

    box.append(
      tags,
      el('h2', '', p.title),
      el('p', 'price', priceText(p)),
      el('p', '', '📍 ' + (p.location || '-')),
      el('h4', '', 'รายละเอียด'),
      el('p', 'pre', p.desc || 'ไม่มีรายละเอียดเพิ่มเติม'),
      contact
    );
  }

  // ===== การทำงานของปุ่มและการปิด modal =====
  document.querySelectorAll('[data-filter]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      renderGrid();
    });
  });

  if (modal) {
    document.getElementById('modalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (!modal.classList.contains('open')) return;
      if (e.key === 'Escape') closeModal();
      if (e.key === 'ArrowLeft' && current.images.length > 1) moveImage(-1);
      if (e.key === 'ArrowRight' && current.images.length > 1) moveImage(1);
    });
  }

  // ===== เริ่มทำงาน =====
  track({ event: 'page' });
  if (grid) loadListings();
})();
