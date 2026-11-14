/**
 * PropertyGO AI Deep Search — front-end controller
 *
 * ทำงานแบบ progressive enhancement:
 *  - ถ้า JS ปิดอยู่ ฟอร์มยังส่ง GET ไป ai-search.php ได้ตามปกติ
 *  - ถ้ามี JS จะเรียก api/ai-search.php แล้ววาดผลลัพธ์โดยไม่ reload
 */
(() => {
  'use strict';

  const cfg = window.AI_SEARCH_CONFIG || {};
  const labels = cfg.labels || {};

  const form = document.getElementById('aiSearchForm');
  const input = document.getElementById('aiSearchInput');
  const submitBtn = document.getElementById('aiSearchSubmit');
  const results = document.getElementById('aiResults');
  const resultsInner = document.getElementById('aiResultsInner');

  if (!form || !input || !resultsInner) return;

  const ICONS = {
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    help: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.2 9a2.9 2.9 0 1 1 4 2.7c-.8.35-1.2.9-1.2 1.8"/><path d="M12 17h.01"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>',
    bed: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6h18v6"/><path d="M3 12V7h9v5"/><path d="M3 18v2M21 18v2"/></svg>',
    bath: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3Z"/><path d="M6 12V6a2 2 0 0 1 3.4-1.4"/><path d="M6 19v2M18 19v2"/></svg>',
    car: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17h14"/><path d="M6.5 17 5 12.5 7 7h10l2 5.5L17.5 17"/><circle cx="7.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/></svg>',
    area: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 4v5H4M15 20v-5h5"/></svg>',
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>',
    chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[ch]));

  const formatNumber = (value) => Number(value || 0).toLocaleString('th-TH');

  let isLoading = false;
  let currentMore = [];

  /* ── rendering ────────────────────────────────────────────────── */

  function renderLoading() {
    results.setAttribute('aria-busy', 'true');
    resultsInner.innerHTML = `
      <div class="ai-summary">
        <div class="ai-summary-head">
          <span class="ai-summary-title">${escapeHtml(labels.searching || 'Searching...')}</span>
        </div>
      </div>
      <div class="ai-loading">
        ${[0, 1, 2].map(() => `
          <div class="ai-skeleton">
            <div class="ai-skeleton-media"></div>
            <div class="ai-skeleton-body">
              <div class="ai-skeleton-line w-40"></div>
              <div class="ai-skeleton-line w-85"></div>
              <div class="ai-skeleton-line w-60"></div>
            </div>
          </div>`).join('')}
      </div>`;
  }

  function renderSummary(data) {
    const chips = (data.labels || []).map((label) => `<span class="ai-criteria-chip">${escapeHtml(label)}</span>`).join('');
    return `
      <div class="ai-summary">
        <div class="ai-summary-head">
          <span class="ai-summary-title">
            ${escapeHtml((labels.results || 'Found %d listings').replace('%d', String(data.total ?? 0)))}
            <span class="ai-summary-count">${escapeHtml(String(data.total ?? 0))}</span>
          </span>
          <span class="ai-summary-sub">${escapeHtml(data.query || '')}</span>
          ${chips ? `<div class="ai-criteria">${chips}</div>` : ''}
        </div>
      </div>`;
  }

  function renderRelaxNote(data) {
    if (!data.relaxed || !data.relax_note) return '';
    return `
      <div class="ai-relax-note">
        ${ICONS.info}
        <span>${escapeHtml(data.relax_note)}</span>
      </div>`;
  }

  function specItem(icon, text) {
    if (!text) return '';
    return `<span class="ai-spec">${icon}${escapeHtml(text)}</span>`;
  }

  function renderExplainGroup(kind, items, title, icon) {
    if (!items || !items.length) return '';
    const list = items.map((text) => `<li>${icon}<span>${escapeHtml(text)}</span></li>`).join('');
    return `
      <div class="ai-explain-group is-${kind}">
        <span class="ai-explain-label is-${kind === 'ok' ? 'ok' : kind === 'warn' ? 'warn' : 'unknown'}">${escapeHtml(title)}</span>
        <ul class="ai-explain-list">${list}</ul>
      </div>`;
  }

  function renderCard(item) {
    const hasCover = Boolean(item.cover);

    // Load the first three covers eagerly: lazy images below the fold can stay
    // undecoded on mobile and would leave a tall blank tile.
    const eager = item.rank <= 3;
    const mediaBlock = hasCover
      ? `<div class="ai-card-media">
           <img src="${escapeHtml(item.cover)}" alt="${escapeHtml(item.title)}" loading="${eager ? 'eager' : 'lazy'}" decoding="async"
                onerror="this.parentElement.classList.add('is-empty');this.closest('.ai-card')?.classList.add('no-cover');this.remove();">
           <span class="ai-rank">#${escapeHtml(String(item.rank))}</span>
           <span class="ai-score">${escapeHtml(String(item.score))}<small>/100</small></span>
         </div>`
      : `<div class="ai-card-media is-empty">${ICONS.home}
           <span class="ai-rank">#${escapeHtml(String(item.rank))}</span>
           <span class="ai-score">${escapeHtml(String(item.score))}<small>/100</small></span>
         </div>`;

    const specs = [
      item.bedrooms !== null ? specItem(ICONS.bed, `${item.bedrooms} ห้องนอน`) : '',
      item.bathrooms !== null ? specItem(ICONS.bath, `${item.bathrooms} ห้องน้ำ`) : '',
      item.parking !== null ? specItem(ICONS.car, `จอด ${item.parking}`) : '',
      item.area_sqm !== null ? specItem(ICONS.area, `${item.area_sqm} ตร.ม.`) : '',
    ].join('');

    const matched = (item.matched || []).map((m) => m.text);
    const mismatched = (item.mismatched || []).map((m) => m.text);
    const unknown = item.unknown || [];

    const explain = (matched.length || mismatched.length || unknown.length) ? `
      <div class="ai-explain">
        ${renderExplainGroup('ok', matched, labels.matched || 'Matches', ICONS.check)}
        ${renderExplainGroup('warn', mismatched, labels.mismatch || 'Not matched', ICONS.x)}
        ${renderExplainGroup('unknown', unknown, labels.unknown || 'Not available', ICONS.help)}
      </div>` : '';

    return `
      <article class="ai-card${hasCover ? '' : ' no-cover'}">
        ${mediaBlock}
        <div class="ai-card-body">
          <div class="ai-card-topline">
            <span class="ai-tag ${item.type === 'rent' ? 'is-rent' : 'is-sale'}">${escapeHtml(item.type_label)}</span>
            <span class="ai-tag">${escapeHtml(item.category_label)}</span>
          </div>
          <a class="ai-card-title" href="${escapeHtml(item.url)}">${escapeHtml(item.title)}</a>
          ${item.location ? `<span class="ai-card-loc">${ICONS.pin}${escapeHtml(item.location)}</span>` : ''}
          <div class="ai-price-row">
            <span class="ai-price">฿${formatNumber(item.price)}</span>
            ${item.original_price && item.original_price > item.price
        ? `<span class="ai-price-orig">฿${formatNumber(item.original_price)}</span>` : ''}
          </div>
          ${specs ? `<div class="ai-specs">${specs}</div>` : ''}
          ${explain}
          <div class="ai-card-actions">
            <a class="ai-view-btn" href="${escapeHtml(item.url)}">ดูประกาศนี้</a>
          </div>
        </div>
      </article>`;
  }

  function renderResults(data) {
    const items = data.items || [];
    if (!items.length) {
      resultsInner.innerHTML = `
        ${renderSummary(data)}
        <div class="ai-message">
          ${ICONS.help}
          <strong>${escapeHtml(labels.noResult || 'No listings found')}</strong>
          <p>${escapeHtml(data.relax_note || data.message || '')}</p>
        </div>`;
      return;
    }

    currentMore = data.more || [];
    const moreBtn = currentMore.length
      ? `<div class="ai-more-wrap">
           <button type="button" class="ai-more-btn" id="aiMoreBtn">
             ${escapeHtml((labels.more || 'Show more results'))} (${currentMore.length}) ${ICONS.chevron}
           </button>
         </div>`
      : '';

    resultsInner.innerHTML = `
      ${renderSummary(data)}
      ${renderRelaxNote(data)}
      <div class="ai-list" id="aiList">${items.map(renderCard).join('')}</div>
      ${moreBtn}`;

    // Lazy images that fail to decode never fire `error` reliably on every engine,
    // so attach the listener immediately and also sweep on load/decode settle.
    repairBrokenCovers(resultsInner);
    setTimeout(() => repairBrokenCovers(resultsInner), 1200);
  }

  function repairBrokenCovers(root = document) {
    root.querySelectorAll('.ai-card-media img').forEach((img) => {
      if (img.dataset.repairWired === '1') return;
      img.dataset.repairWired = '1';

      const markBroken = () => {
        const media = img.closest('.ai-card-media');
        if (!media || media.classList.contains('is-empty')) return;
        img.remove();
        media.classList.add('is-empty');
        media.insertAdjacentHTML('afterbegin', ICONS.home);
        media.closest('.ai-card')?.classList.add('no-cover');
      };

      // Already-failed images (e.g. DecodeError) may never emit another event.
      if (img.complete && img.naturalWidth === 0) {
        markBroken();
        return;
      }

      img.addEventListener('error', markBroken, { once: true });
      img.addEventListener('load', () => {
        if (img.naturalWidth === 0) markBroken();
      }, { once: true });

      // Some images stall mid-decode and never fire load/error. Give them a
      // bounded window, then collapse instead of leaving a tall empty tile.
      setTimeout(() => {
        if (img.isConnected && (!img.complete || img.naturalWidth === 0)) {
          markBroken();
        }
      }, 2500);
    });
  }

  function renderError(message) {
    resultsInner.innerHTML = `
      <div class="ai-message is-error">
        ${ICONS.info}
        <strong>${escapeHtml(labels.error || 'Something went wrong')}</strong>
        <p>${escapeHtml(message || '')}</p>
      </div>`;
  }

  /* ── network ──────────────────────────────────────────────────── */

  async function search(query) {
    if (isLoading || !query.trim()) return;
    isLoading = true;
    submitBtn?.classList.add('is-loading');
    submitBtn?.setAttribute('aria-disabled', 'true');
    renderLoading();

    const url = new URL(cfg.endpoint || 'api/ai-search.php', window.location.href);
    url.searchParams.set('q', query);
    url.searchParams.set('limit', '5');

    // ส่งตัวกรองที่มาจาก Quick AI Search บนหน้าแรก
    const quick = cfg.quickFilters || {};
    (quick.amenities || []).forEach((am) => url.searchParams.append('am[]', am));
    if (quick.category) url.searchParams.set('category', quick.category);
    if (quick.type) url.searchParams.set('type', quick.type);

    try {
      const res = await fetch(url.toString(), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      const data = await res.json();
      if (!res.ok || data.ok === false) {
        throw new Error(data.error || 'Request failed');
      }
      renderResults(data);

      // อัปเดต URL ให้แชร์ผลการค้นหาได้
      const pageUrl = new URL(window.location.href);
      pageUrl.searchParams.set('q', query);
      window.history.replaceState({}, '', pageUrl.toString());
    } catch (err) {
      renderError(err && err.message ? err.message : '');
    } finally {
      isLoading = false;
      submitBtn?.classList.remove('is-loading');
      submitBtn?.removeAttribute('aria-disabled');
      results.setAttribute('aria-busy', 'false');
    }
  }

  /* ── events ───────────────────────────────────────────────────── */



  form.addEventListener('submit', (e) => {
    e.preventDefault();
    search(input.value);
  });

  document.addEventListener('click', (e) => {
    const chip = e.target.closest('.ai-example-chip');
    if (chip) {
      input.value = chip.dataset.query || chip.textContent.trim();
      search(input.value);
      return;
    }

    const moreBtn = e.target.closest('#aiMoreBtn');
    if (moreBtn && currentMore.length) {
      const list = document.getElementById('aiList');
      if (list) {
        const offset = list.querySelectorAll('.ai-card').length;
        currentMore.forEach((item, i) => {
          const html = renderCard({ ...item, rank: offset + i + 1 });
          list.insertAdjacentHTML('beforeend', html);
        });
      }
      repairBrokenCovers(resultsInner);

      const remaining = currentMore.length;
      currentMore = [];
      moreBtn.remove();
      if (remaining === 0) {
        resultsInner.querySelector('.ai-more-wrap')?.remove();
      }
      return;
    }

    if (moreBtn) {
      moreBtn.remove();
    }
  });

  // ค้นหาอัตโนมัติเมื่อเปิดหน้ามาพร้อม ?q=
  if (cfg.initialQuery) {
    search(cfg.initialQuery);
  }
})();
