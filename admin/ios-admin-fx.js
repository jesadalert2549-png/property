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
