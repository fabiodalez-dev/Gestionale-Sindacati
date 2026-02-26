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
  var DURATION = 0.45;
  var STAGGER = 0.05;

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

    // Stat cards (dashboard KPI)
    var stats = document.querySelectorAll('.stat-card, .stats-card');
    if (stats.length) {
      gsap.set(stats, { opacity: 0, y: 12, scale: 0.97 });
      gsap.to(stats, {
        opacity: 1,
        y: 0,
        scale: 1,
        duration: 0.4,
        stagger: 0.07,
        ease: 'back.out(1.2)',
        clearProps: 'transform'
      });
    }

    // Page title
    var titles = document.querySelectorAll('.container-fluid > .h3, .container-fluid > h1');
    if (titles.length) {
      gsap.set(titles, { opacity: 0, x: -10 });
      gsap.to(titles, {
        opacity: 1,
        x: 0,
        duration: 0.35,
        ease: EASE,
        clearProps: 'transform'
      });
    }

    // Alerts
    var alerts = document.querySelectorAll('.alert');
    if (alerts.length) {
      gsap.set(alerts, { opacity: 0, x: -8 });
      gsap.to(alerts, {
        opacity: 1,
        x: 0,
        duration: 0.3,
        ease: EASE,
        clearProps: 'transform'
      });
    }
  }

  // ── Sidebar entrance animation ──
  function initSidebar() {
    var navItems = document.querySelectorAll('.sidebar .nav-item');
    if (navItems.length) {
      gsap.set(navItems, { opacity: 0, x: -8 });
      gsap.to(navItems, {
        opacity: 1,
        x: 0,
        duration: 0.25,
        stagger: 0.02,
        ease: EASE,
        clearProps: 'transform'
      });
    }
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

  // ── Modal animation ──
  function initModalAnimations() {
    if (typeof $ === 'undefined') return;
    $(document).on('show.bs.modal', function(e) {
      var modal = e.target.querySelector('.modal-content');
      if (modal) {
        gsap.set(modal, { opacity: 0, y: -16, scale: 0.97 });
        gsap.to(modal, {
          opacity: 1,
          y: 0,
          scale: 1,
          duration: 0.28,
          ease: 'back.out(1.4)'
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
