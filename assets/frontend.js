/* Jy Builder — frontend.js (v3.0)
 * Vanilla, no dependencies. Animations, counter, countdown, tabs, video facade, lightbox. */
(function () {
  'use strict';

  function onReady(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  /* ---- Entrance animations + counters (IntersectionObserver) ---- */
  function initObservers() {
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('[data-jvb-anim]').forEach(function (el) { el.classList.add('jvb-in'); });
      document.querySelectorAll('[data-jvb-count]').forEach(runCounter);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        var delay = parseInt(el.getAttribute('data-jvb-delay') || '0', 10);
        if (el.hasAttribute('data-jvb-anim')) {
          setTimeout(function () { el.classList.add('jvb-in'); }, delay);
        }
        if (el.hasAttribute('data-jvb-count')) runCounter(el);
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('[data-jvb-anim], [data-jvb-count]').forEach(function (el) { io.observe(el); });
  }

  function runCounter(el) {
    if (el.dataset.jvbDone) return;
    el.dataset.jvbDone = '1';
    var target = parseFloat(el.getAttribute('data-jvb-count')) || 0;
    var dur = parseInt(el.getAttribute('data-jvb-dur') || '1500', 10);
    var prefix = el.getAttribute('data-jvb-prefix') || '';
    var suffix = el.getAttribute('data-jvb-suffix') || '';
    var decimals = (String(target).split('.')[1] || '').length;
    var start = null;
    function fmt(n) {
      return prefix + n.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
    }
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(target * eased);
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = fmt(target);
    }
    requestAnimationFrame(step);
  }

  /* ---- Countdown ---- */
  function initCountdowns() {
    document.querySelectorAll('[data-jvb-target]').forEach(function (box) {
      var target = parseInt(box.getAttribute('data-jvb-target'), 10) * 1000;
      if (!target) return;
      function tick() {
        var diff = Math.max(0, target - Date.now());
        var d = Math.floor(diff / 86400000);
        var h = Math.floor(diff % 86400000 / 3600000);
        var m = Math.floor(diff % 3600000 / 60000);
        var s = Math.floor(diff % 60000 / 1000);
        var map = { d: d, h: h, m: m, s: s };
        box.querySelectorAll('[data-jvb-cd]').forEach(function (n) {
          var k = n.getAttribute('data-jvb-cd');
          n.textContent = String(map[k] || 0).padStart(2, '0');
        });
        if (diff > 0) setTimeout(tick, 1000);
      }
      tick();
    });
  }

  /* ---- Tabs ---- */
  function initTabs() {
    document.querySelectorAll('.jvb-tabs').forEach(function (tabs) {
      var btns = tabs.querySelectorAll('[role="tab"]');
      var panels = tabs.querySelectorAll('[role="tabpanel"]');
      btns.forEach(function (btn, i) {
        btn.addEventListener('click', function () {
          btns.forEach(function (b, j) {
            b.classList.toggle('is-active', i === j);
            b.setAttribute('aria-selected', i === j ? 'true' : 'false');
          });
          panels.forEach(function (p, j) {
            p.classList.toggle('is-active', i === j);
            p.hidden = i !== j;
          });
        });
      });
    });
  }

  /* ---- Video facade ---- */
  function initVideoFacades() {
    document.querySelectorAll('.jvb-video--facade').forEach(function (facade) {
      facade.addEventListener('click', function () {
        var embed = facade.getAttribute('data-embed');
        if (!embed) return;
        var iframe = document.createElement('iframe');
        iframe.src = embed;
        iframe.allow = 'autoplay; encrypted-media; fullscreen';
        iframe.allowFullscreen = true;
        facade.innerHTML = '';
        facade.appendChild(iframe);
        facade.classList.remove('jvb-video--facade');
      });
    });
  }

  /* ---- Gallery lightbox ---- */
  function initLightbox() {
    document.querySelectorAll('[data-jvb-lightbox]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var lb = document.createElement('div');
        lb.className = 'jvb-lightbox';
        lb.innerHTML = '<button class="jvb-lightbox__close" aria-label="Close">&times;</button><img src="' +
          link.getAttribute('href').replace(/"/g, '&quot;') + '" alt="">';
        document.body.appendChild(lb);
        function close() { lb.remove(); document.removeEventListener('keydown', onKey); }
        function onKey(ev) { if (ev.key === 'Escape') close(); }
        lb.addEventListener('click', close);
        document.addEventListener('keydown', onKey);
      });
    });
  }

  /* ---- Carousel (Swiper — shipped globally by CMS core) ---- */
  function initCarousels() {
    if (typeof Swiper === 'undefined') return;
    document.querySelectorAll('.jvb-carousel[data-jvb-carousel]').forEach(function (el) {
      if (el.__jvbSwiper) return;
      try {
        var cfg = JSON.parse(el.getAttribute('data-jvb-carousel') || '{}');
        el.__jvbSwiper = new Swiper(el, cfg);
      } catch (e) {}
    });
  }

  onReady(function () {
    initObservers();
    initCountdowns();
    initTabs();
    initVideoFacades();
    initLightbox();
    initCarousels();
  });

  // Expose for the builder frame (re-init after canvas refresh)
  window.JVBFrontend = { init: function () { initObservers(); initCountdowns(); initTabs(); initVideoFacades(); initLightbox(); initCarousels(); } };
})();
