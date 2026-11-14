/* ==========================================================================
   iOS 26/27 - tactile button effects
   --------------------------------------------------------------------------
   Behaviour modelled on system iOS controls:
     1) The highlight starts where the finger lands, not at the centre.
     2) A soft specular ripple expands from that point and fades out.
     3) The control compresses slightly while held, then springs back.
     4) On desktop the light follows the pointer like tilted glass.
     5) A short haptic tick where the device supports it.
   No dependencies. Event delegation only, so markup stays untouched.
   NOTE: comments are intentionally ASCII - this file once shipped with
   corrupted Thai characters that broke the JavaScript parser.
   ========================================================================== */
(function () {
  'use strict';
  window.__iosFxBoot = (window.__iosFxBoot || 0) + 1;

  if (window.__iosFxReady) return;
  window.__iosFxReady = true;

  var SELECTOR = [
    '.scard',
    '.ceo-cat-card',
    '.ceo-cat-card a',
    '.dash-hero-actions a',
    '.dash-hero-actions .btn-hero',
    '.btn',
    '.btn-action',
    '.featured-manage-banner > a',
    '.admin-nav-item-link',
    '.admin-nav-dropdown-btn',
    '.tabs a',
    '.tabs button',
    '.cookie-consent button',
    '.pill'
  ].join(',');

  var mqReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  var reduceMotion = !!(mqReduce && mqReduce.matches);
  var hasHover = !!(window.matchMedia && window.matchMedia('(hover: hover)').matches);

  function scoped() {
    return document.body && document.body.classList.contains('admin-ios');
  }

  function haptic(ms) {
    if (reduceMotion) return;
    try {
      if (navigator.vibrate) navigator.vibrate(ms || 8);
    } catch (e) { /* ignore */ }
  }

  function ripple(el, clientX, clientY) {
    if (reduceMotion) return;

    var r = el.getBoundingClientRect();
    var x = clientX - r.left;
    var y = clientY - r.top;

    if (!clientX && !clientY) {
      x = r.width / 2;
      y = r.height / 2;
    }

    var dx = Math.max(x, r.width - x);
    var dy = Math.max(y, r.height - y);
    var radius = Math.sqrt(dx * dx + dy * dy);
    var size = radius * 2;

    var spot = document.createElement('span');
    spot.className = 'ios-ripple';
    spot.style.width = size + 'px';
    spot.style.height = size + 'px';
    spot.style.left = (x - radius) + 'px';
    spot.style.top = (y - radius) + 'px';

    if (getComputedStyle(el).position === 'static') {
      el.style.position = 'relative';
    }
    el.style.overflow = 'hidden';
    el.classList.add('ios-pressing');
    el.appendChild(spot);

    window.setTimeout(function () {
      el.classList.remove('ios-pressing');
      if (spot.parentNode) spot.parentNode.removeChild(spot);
    }, 620);
  }

  document.addEventListener('pointerdown', function (e) {
    if (!scoped()) return;
    var el = e.target.closest(SELECTOR);
    if (!el || el.disabled) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    ripple(el, e.clientX, e.clientY);
    haptic(8);
  }, { passive: true });

  document.addEventListener('keydown', function (e) {
    if (!scoped()) return;
    if (e.key !== 'Enter' && e.key !== ' ') return;
    if (e.repeat) return;
    var el = e.target.closest(SELECTOR);
    if (!el || el.disabled) return;
    ripple(el, 0, 0);
  });

  /* ---------------------------------------------------------------------
     Split the leading emoji out of a button label into its own element so
     CSS can centre it inside the glass circle.
     "[emoji] Label" -> <span class=ios-btn-icon>[emoji]</span><span>Label</span>
     Skipped when the label already starts with an element (e.g. an svg).
     --------------------------------------------------------------------- */
  var EMOJI_RE = /^([\p{Extended_Pictographic}\u{1F300}-\u{1FAFF}\u2600-\u27BF]+)\s*/u;

  function splitIcon(btn) {
    if (!btn || btn.dataset.iosIconSplit === '1') return;

    /* Layout A: bare text node -> "\u{1F4D3} CEO Notebook"
       Layout B: nested spans   -> <span><span>\u25A6</span><span>Label</span></span>
       The launchpad uses layout B, so handle both before giving up. */
    var first = btn.firstChild;

    if (first && first.nodeType === 1) {
      var wrapper = first.querySelector('span');
      var deepest = null;
      if (wrapper) {
        deepest = wrapper.querySelector('span');
      }
      if (deepest && deepest.textContent.trim()) {
        var glyph = deepest.textContent.trim();
        /* only treat it as an icon if it is a single pictographic char */
        if (glyph.length <= 3 && /^[\p{Extended_Pictographic}\u25A0-\u25FF\u{1F300}-\u{1FAFF}]/u.test(glyph)) {
          btn.dataset.iosIconSplit = '1';

          var iconB = document.createElement('span');
          iconB.className = 'ios-btn-icon';
          iconB.setAttribute('aria-hidden', 'true');
          iconB.textContent = glyph;

          /* hide the original icon span, keep the label text */
          deepest.style.display = 'none';

          var labelB = document.createElement('span');
          labelB.className = 'ios-btn-label';
          labelB.textContent = wrapper.textContent.replace(glyph, '').trim();

          wrapper.replaceWith(labelB);
          btn.insertBefore(iconB, labelB);
          return;
        }
      }
      return; // element first child but no recognisable glyph
    }

    if (!first || first.nodeType !== 3) return;

    var text = first.textContent;
    var m = text.match(EMOJI_RE);
    if (!m) return;

    btn.dataset.iosIconSplit = '1';

    var icon = document.createElement('span');
    icon.className = 'ios-btn-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = m[1];

    var label = document.createElement('span');
    label.className = 'ios-btn-label';
    label.textContent = text.slice(m[0].length);

    btn.replaceChild(label, first);
    btn.insertBefore(icon, label);
  }

  /* Table row actions have no icon in the markup. Give each semantic class a
     glyph so the four tiers read at a glance, matching the hero buttons. */
  var ACTION_ICONS = {
    'btn-green': '\u2713',          // check   -> approve
    'btn-gray': '\u2013',           // dash    -> neutral / reject
    'btn-accent': '\u21BB',         // arrow   -> relist / toggle
    'btn-red': '\u2715',            // cross   -> delete
    'btn-edit': '\u270E',           // pencil  -> edit
    'btn-feature': '\u2605',        // star    -> feature
    'btn-feature-remove': '\u2606', // hollow star -> unfeature
    'btn-order': '\u2195'           // up-down -> reorder
  };

  function addActionIcon(btn) {
    if (btn.dataset.iosActionIcon === '1') return;
    if (btn.classList.contains('btn-order')) return; // already icon-only

    /* Some labels already ship with a glyph (markup writes '\u2605 Feature' or
       a <strong> star). Do not add a second one on top of it. */
    var label = (btn.textContent || '').trim();
    if (label && /^[\u2600-\u27BF\u{1F300}-\u{1FAFF}]/u.test(label)) {
      btn.dataset.iosActionIcon = '1';
      return;
    }

    btn.dataset.iosActionIcon = '1';

    var glyph = null;
    Object.keys(ACTION_ICONS).some(function (k) {
      if (btn.classList.contains(k)) { glyph = ACTION_ICONS[k]; return true; }
      return false;
    });
    if (!glyph) return;

    var icon = document.createElement('span');
    icon.className = 'ios-btn-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = glyph;
    btn.insertBefore(icon, btn.firstChild);
  }

  /* The cookie banner is appended to <body> by site-controls.js, so a fixed
     position put it on top of the table. Move it inside the dashboard wrap
     once, at the end, so it scrolls with the page like a normal block. */
  function relocateBanner() {
    if (!document.body.classList.contains('admin-ios')) return;
    var banner = document.querySelector('.cookie-consent');
    var wrap = document.querySelector('.wrap');
    if (!banner || !wrap) return;
    if (wrap.contains(banner)) return;
    banner.classList.add('cookie-consent--inline');
    wrap.appendChild(banner);
  }

  function splitAll() {
    if (!document.body.classList.contains('admin-ios')) return;
    document
      .querySelectorAll('.dash-hero-actions a, .ceo-cat-card a')
      .forEach(splitIcon);
    document
      .querySelectorAll('.btn-action')
      .forEach(addActionIcon);
    relocateBanner();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', splitAll);
  } else {
    splitAll();
  }

  /* The script sits in <head> and runs before PHP has streamed the card
     markup, so a one-shot DOMContentLoaded pass can miss the launchpad.
     Watch for late nodes and process each one once (guarded by dataset). */
  if (window.MutationObserver) {
    var pending = false;
    var mo = new MutationObserver(function () {
      if (pending) return;
      pending = true;
      (window.requestAnimationFrame || window.setTimeout)(function () {
        pending = false;
        splitAll();
      }, 0);
    });
    window.addEventListener('DOMContentLoaded', function () {
      mo.observe(document.body, { childList: true, subtree: true });
      /* stop watching after the page settles to avoid needless work */
      window.setTimeout(function () { mo.disconnect(); }, 8000);
    });
  }
  window.addEventListener('load', splitAll);

  if (hasHover && !reduceMotion) {
    document.addEventListener('pointermove', function (e) {
      if (!scoped()) return;
      var el = e.target.closest(SELECTOR);
      if (!el) return;
      var r = el.getBoundingClientRect();
      if (!r.width || !r.height) return;
      var px = ((e.clientX - r.left) / r.width) * 100;
      var py = ((e.clientY - r.top) / r.height) * 100;
      el.style.setProperty('--fx-x', px.toFixed(1) + '%');
      el.style.setProperty('--fx-y', py.toFixed(1) + '%');
      el.classList.add('ios-hovering');
    }, { passive: true });

    document.addEventListener('pointerout', function (e) {
      if (!scoped()) return;
      var el = e.target.closest(SELECTOR);
      if (!el) return;
      if (el.contains(e.relatedTarget)) return;
      el.classList.remove('ios-hovering');
    }, { passive: true });
  }
})();
