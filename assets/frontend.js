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
      var nav = tabs.querySelector('.jvb-tabs__nav');
      if (!nav) return;
      var btns = Array.from(nav.querySelectorAll('[role="tab"]'));
      var panels = Array.from(tabs.querySelectorAll('.jvb-tabs__panel[role="tabpanel"]'));
      function activate(index, moveFocus) {
        btns.forEach(function (btn, i) {
          var active = i === index;
          btn.classList.toggle('is-active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
          btn.setAttribute('tabindex', active ? '0' : '-1');
          if (active && moveFocus) btn.focus();
        });
        panels.forEach(function (panel, i) {
          panel.classList.toggle('is-active', i === index);
          panel.hidden = i !== index;
        });
      }
      btns.forEach(function (btn, i) {
        btn.addEventListener('click', function () { activate(i, false); });
        btn.addEventListener('keydown', function (event) {
          var next = null;
          if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (i + 1) % btns.length;
          if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (i - 1 + btns.length) % btns.length;
          if (event.key === 'Home') next = 0;
          if (event.key === 'End') next = btns.length - 1;
          if (next === null) return;
          event.preventDefault();
          activate(next, true);
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

  function initContentSliders() {
    document.querySelectorAll('[data-jvb-content-slider]').forEach(function (slider) {
      if (slider.__jvbContentSlider) return;
      slider.__jvbContentSlider = true;
      var slides = Array.from(slider.querySelectorAll('[data-jvb-content-slide]'));
      var dots = Array.from(slider.querySelectorAll('[data-jvb-content-dot]'));
      var previous = slider.querySelector('[data-jvb-content-prev]');
      var next = slider.querySelector('[data-jvb-content-next]');
      var progress = slider.querySelector('.jvb-content-slider__progress span');
      if (!slides.length) return;
      var activeIndex = 0;
      var timer = null;
      var autoplay = slider.getAttribute('data-jvb-autoplay') === '1';
      var loop = slider.getAttribute('data-jvb-loop') === '1';
      var delay = Math.max(1000, parseInt(slider.getAttribute('data-jvb-delay') || '4000', 10));
      var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      function normalize(index) {
        if (loop) return (index + slides.length) % slides.length;
        return Math.max(0, Math.min(slides.length - 1, index));
      }
      function render(index, moveFocus) {
        activeIndex = normalize(index);
        slides.forEach(function (slide, i) {
          var active = i === activeIndex;
          slide.classList.toggle('is-active', active);
          slide.setAttribute('aria-hidden', active ? 'false' : 'true');
          slide.toggleAttribute('inert', !active);
        });
        dots.forEach(function (dot, i) {
          var active = i === activeIndex;
          dot.classList.toggle('is-active', active);
          dot.setAttribute('aria-selected', active ? 'true' : 'false');
          dot.setAttribute('tabindex', active ? '0' : '-1');
          if (active && moveFocus) dot.focus();
        });
        if (previous) previous.disabled = !loop && activeIndex === 0;
        if (next) next.disabled = !loop && activeIndex === slides.length - 1;
        if (progress) progress.style.setProperty('--jvb-content-progress', ((activeIndex + 1) / slides.length * 100) + '%');
      }
      function stop() { if (timer) window.clearInterval(timer); timer = null; }
      function start() {
        stop();
        if (!autoplay || reducedMotion || slides.length < 2) return;
        timer = window.setInterval(function () { render(activeIndex + 1, false); }, delay);
      }
      function select(index, moveFocus) { render(index, moveFocus); start(); }
      if (previous) previous.addEventListener('click', function () { select(activeIndex - 1, false); });
      if (next) next.addEventListener('click', function () { select(activeIndex + 1, false); });
      dots.forEach(function (dot, index) {
        dot.addEventListener('click', function () { select(index, false); });
        dot.addEventListener('keydown', function (event) {
          var target = null;
          if (event.key === 'ArrowRight') target = normalize(index + 1);
          if (event.key === 'ArrowLeft') target = normalize(index - 1);
          if (event.key === 'Home') target = 0;
          if (event.key === 'End') target = slides.length - 1;
          if (target === null || target === index) return;
          event.preventDefault();
          select(target, true);
        });
      });
      slider.addEventListener('mouseenter', stop);
      slider.addEventListener('mouseleave', start);
      slider.addEventListener('focusin', stop);
      slider.addEventListener('focusout', function (event) { if (!slider.contains(event.relatedTarget)) start(); });
      slider.addEventListener('keydown', function (event) {
        if (event.target && event.target.hasAttribute('data-jvb-content-dot')) return;
        if (event.key === 'ArrowLeft') { event.preventDefault(); select(activeIndex - 1, false); }
        if (event.key === 'ArrowRight') { event.preventDefault(); select(activeIndex + 1, false); }
      });
      render(0, false);
      start();
    });
  }

  function initCardRails() {
    document.querySelectorAll('[data-jvb-card-rail]').forEach(function (rail) {
      if (rail.__jvbCardRail) return;
      rail.__jvbCardRail = true;
      var track = rail.querySelector('.jvb-card-rail__track');
      var initial = rail.querySelector('[data-jvb-rail-initial]');
      var centerOn = rail.getAttribute('data-jvb-center-on') || 'none';
      if (!track || !initial || centerOn === 'none') return;
      if (centerOn === 'mobile' && !window.matchMedia('(max-width: 767px)').matches) return;
      requestAnimationFrame(function () {
        track.scrollLeft = initial.offsetLeft - (track.clientWidth - initial.offsetWidth) / 2;
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
    initContentSliders();
    initCardRails();
    initCarousels();
  });

  // Expose for the builder frame (re-init after canvas refresh)
  window.JVBFrontend = { init: function () { initObservers(); initCountdowns(); initTabs(); initVideoFacades(); initLightbox(); initContentSliders(); initCardRails(); initCarousels(); } };
})();
