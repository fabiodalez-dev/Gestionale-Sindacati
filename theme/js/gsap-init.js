/**
 * ADL CRM — Global GSAP Animations
 * Elegant, performant micro-interactions and page transitions.
 * Requires: gsap.min.js + ScrollTrigger.min.js loaded before this file.
 */
(function() {
  'use strict';

  // Prevent double initialization
  if (window._gsapInitDone) return;
  window._gsapInitDone = true;

  if (typeof gsap === 'undefined') return;

  // Register ScrollTrigger if available
  if (typeof ScrollTrigger !== 'undefined') {
    gsap.registerPlugin(ScrollTrigger);
  }

  // ── Settings ──
  var EASE = 'power2.out';
  var DURATION = 0.3;
  var STAGGER = 0.03;

  // ── Page Load: Staggered reveal ──
  function initPageReveal() {
    // Cards - use gsap.from (not fromTo) to avoid visible flash
    var cards = document.querySelectorAll('#content .card');
    if (cards.length) {
      gsap.set(cards, { opacity: 0, y: 16 });
      gsap.to(cards, {
        opacity: 1,
        y: 0,
        duration: DURATION,
        stagger: STAGGER,
        ease: EASE,
        clearProps: 'transform'
      });
    }

    // Stat cards (dashboard KPI) — subtle fade only
    var stats = document.querySelectorAll('.stat-card, .stats-card');
    if (stats.length) {
      gsap.set(stats, { opacity: 0 });
      gsap.to(stats, {
        opacity: 1,
        duration: DURATION,
        stagger: STAGGER,
        ease: EASE
      });
    }
  }

  // ── Sidebar: nessuna animazione (navigazione istantanea) ──
  function initSidebar() {
    // La sidebar non ha animazioni: la navigazione deve essere immediata e professionale.
  }

  // ── ScrollTrigger: Reveal on scroll ──
  function initScrollReveal() {
    if (typeof ScrollTrigger === 'undefined') return;

    var scrollItems = document.querySelectorAll('[data-gsap]');
    scrollItems.forEach(function(el) {
      var anim = el.getAttribute('data-gsap');
      var props = { opacity: 0 };

      if (anim === 'fade-up') props.y = 20;
      else if (anim === 'fade-left') props.x = -20;
      else if (anim === 'fade-right') props.x = 20;
      else if (anim === 'scale') { props.scale = 0.95; }

      gsap.from(el, Object.assign({}, props, {
        duration: DURATION,
        ease: EASE,
        scrollTrigger: {
          trigger: el,
          start: 'top 90%',
          once: true
        },
        clearProps: 'transform'
      }));
    });
  }

  // ── Modal animation — subtle fade ──
  function initModalAnimations() {
    if (typeof $ === 'undefined') return;
    $(document).on('show.bs.modal', function(e) {
      var modal = e.target.querySelector('.modal-content');
      if (modal) {
        gsap.set(modal, { opacity: 0, y: -8 });
        gsap.to(modal, {
          opacity: 1,
          y: 0,
          duration: 0.2,
          ease: EASE
        });
      }
    });
  }

  // ── Counter animation for KPI numbers ──
  function initCounterAnimations() {
    var counters = document.querySelectorAll('[data-counter]');
    counters.forEach(function(el) {
      var target = parseFloat(el.getAttribute('data-counter'));
      var suffix = el.getAttribute('data-suffix') || '';
      var isPercent = suffix === '%';
      var obj = { val: 0 };

      gsap.to(obj, {
        val: target,
        duration: 1,
        delay: 0.3,
        ease: 'power2.out',
        onUpdate: function() {
          el.textContent = (isPercent ? obj.val.toFixed(1) : Math.round(obj.val)) + suffix;
        }
      });
    });
  }

  // ── Initialize everything ──
  function init() {
    initSidebar();
    initPageReveal();
    initScrollReveal();
    initCounterAnimations();
    initModalAnimations();
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
