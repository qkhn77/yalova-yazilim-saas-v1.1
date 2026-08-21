!function(e){"use strict";var t={init:function(n){t.config={responsive_menu_width:1199,header_menu:e(".lawyer-header__inner"),header:e(".default-blog-header")},e.extend(t.config,n),t.setup()},setup:function(){t.offcanvas(),t.woo()},offcanvas:function(){let e=document.querySelector(".offcanvas__area"),t=document.querySelector(".info-default-offcanvas"),n=document.querySelector(".offcanvas__close");t&&t.addEventListener("click",()=>{e.classList.add("show")}),n&&n.addEventListener("click",()=>{e.classList.remove("show")}),document.addEventListener("click",n=>{if(e){let a=e.contains(n.target)||t.contains(n.target);a||e.classList.remove("show")}});let a=document.querySelectorAll(".deep-mb-menu-items li > a");a.forEach(e=>{let t=e.nextElementSibling;t&&"UL"===t.tagName&&t.classList.contains("dp-menu")&&(e.querySelector(".nav-direction-icon").setAttribute("data-icon","+"),e.querySelector(".nav-direction-icon").addEventListener("click",function(e){let t=this.parentElement.nextElementSibling;if(t&&"UL"===t.tagName&&t.classList.contains("dp-menu")){e.preventDefault();let n="true"===t.getAttribute("aria-expanded");t.setAttribute("aria-expanded",!n),t.style.display=n?"none":"block",n?this.setAttribute("data-icon","+"):this.setAttribute("data-icon","-")}}))}),document.querySelectorAll(".hello-animation-mb-menu-items a").forEach(e=>{e.addEventListener("keydown",function(e){if("ArrowDown"===e.key){e.preventDefault();let t=e.target.parentElement.nextElementSibling;t&&t.querySelector("a").focus()}if("ArrowUp"===e.key){e.preventDefault();let n=e.target.parentElement.previousElementSibling;n&&n.querySelector("a").focus()}})})},woo:function(){let t=0,n=null;e(document).on("click",".quantity .plus",function(){n=e(this).parents(".quantity").find("input"),t=parseInt(n.val()),n.val(++t),jQuery("[name='update_cart']").prop("disabled",!1),deep_obj.cart_update_qty_change&&jQuery("[name='update_cart']").trigger("click")}),e(document).on("click",".quantity .minus",function(){n=e(this).parents(".quantity").find("input"),t=parseInt(n.val()),0==(t=--t)&&(t=1),n.val(t),jQuery("[name='update_cart']").prop("disabled",!1),deep_obj.cart_update_qty_change&&jQuery("[name='update_cart']").trigger("click")})}};e(document).ready(t.init)}(jQuery);

(function () {
  // Desktop only
  if (window.matchMedia("(max-width: 1024px)").matches) return;

  function initRotateBadges() {
    // Make sure GSAP + ScrollTrigger exist
    if (!window.gsap || !window.ScrollTrigger) return;

    // IMPORTANT: register plugin (required in many setups)
    try { gsap.registerPlugin(ScrollTrigger); } catch (e) {}

    // Helper: only create if element exists
    function makeRotation(selector) {
      const el = document.querySelector(selector);
      if (!el) return;

      gsap.timeline({
        scrollTrigger: {
          trigger: el,
          start: "bottom bottom",
          end: "top top",
          scrub: 2,
          // pin: true, // <-- pin belongs here, not in the .to() (more reliable)
          // markers: true, // uncomment to debug
        }
      }).to(el, {
        rotation: 360,
        ease: "none"
      });
    }

    makeRotation(".rotate-on-scroll");
    makeRotation(".rotate-expertise");
    makeRotation(".rotate-about");
  }

  // Run after page fully loads (safer with Elementor)
  window.addEventListener("load", initRotateBadges);

  // Also re-run when Elementor renders content (editor + some dynamic loads)
  document.addEventListener("elementor/frontend/init", function () {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction("frontend/element_ready/global", initRotateBadges);
    }
  });
})();


(function () {
  // Desktop only
  if (window.matchMedia("(max-width: 1024px)").matches) return;

  function initMoveOnScroll() {
    // Make sure GSAP + ScrollTrigger exist
    if (!window.gsap || !window.ScrollTrigger) return;

    // Register plugin
    try { gsap.registerPlugin(ScrollTrigger); } catch (e) {}

    function makeMove(selector, xPercentValue) {
      const els = gsap.utils.toArray(selector);
      if (!els.length) return;

      els.forEach((el) => {
        // Prevent duplicates when Elementor re-inits, but only for this feature's triggers.
        const triggerId = "bb-move-on-scroll-" + selector.replace(/[^a-z0-9_-]/gi, "") + "-" + (el.dataset.id || el.id || Math.random().toString(36).slice(2));
        const existing = ScrollTrigger.getById(triggerId);
        if (existing) existing.kill();
        gsap.killTweensOf(el);

        gsap.to(el, {
          xPercent: xPercentValue,
          ease: "none",
          scrollTrigger: {
            id: triggerId,
            trigger: el,
            start: "top center",
            end: "bottom top",
            scrub: true,
            // markers: true,
          }
        });
      });
    }

    makeMove(".move-x-left", -20);
    makeMove(".move-x-right", 20);
  }

  // Run after page fully loads (safer with Elementor)
  window.addEventListener("load", initMoveOnScroll);

  // Re-run when Elementor renders content (editor + dynamic loads)
  document.addEventListener("elementor/frontend/init", function () {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction("frontend/element_ready/global", initMoveOnScroll);
    }
  });
})();



/* Fix for sticky sections + brand slider */
(function () {
  "use strict";

  const SLIDER_SEL = ".wcf--brand-slider-wrapper .swiper";
  const STICKY_CONTEXT_SEL = ".pin-spacer, .aae-pro-sticky-active, .aae-is-translating";

  // How often we check if it is stalled
  const WATCH_INTERVAL = 300; // ms

  // How long it must be not moving to be considered stalled
  const STALL_TIME = 400; // ms

  // Minimal translate delta to consider "moving"
  const EPS = 0.5;

  const DEBUG = false;
  const log = (...a) => DEBUG && console.log("[brand-slider-watchdog]", ...a);

  function qsa(sel, root = document) {
    return Array.from(root.querySelectorAll(sel));
  }

  function isSticky(el) {
    return !!el.closest(STICKY_CONTEXT_SEL);
  }

  function getStickyBrandSwipers() {
    return qsa(SLIDER_SEL)
      .filter(el => isSticky(el))
      .map(el => el.swiper)
      .filter(sw => sw && !sw.destroyed);
  }

  function inViewport(el) {
    if (!el) return false;
    const r = el.getBoundingClientRect();
    // consider visible if it intersects viewport
    return r.bottom > 0 && r.top < (window.innerHeight || document.documentElement.clientHeight);
  }

  function softUpdate(sw) {
    try {
      sw.update();
      sw.updateSize();
    } catch (e) {}
  }

  function hardRevive(sw) {
    try {
      // light updates first
      sw.update();
      sw.updateSize();
      sw.updateSlides();

      // only restart if autoplay exists
      if (sw.autoplay) {
        sw.autoplay.stop();
        sw.autoplay.start();
      }

      // re-apply current translate (no GPU nudge)
      if (typeof sw.getTranslate === "function" && typeof sw.setTranslate === "function") {
        sw.setTranslate(sw.getTranslate());
      }

      log("hardRevive", sw);
    } catch (e) {
      log("hardRevive error", e);
    }
  }

  // ---- Watchdog state per swiper ----
  const state = new WeakMap();

  function tickWatchdog() {
    const list = getStickyBrandSwipers();

    list.forEach(sw => {
      // only care when visible; avoids work + avoids interfering with offscreen things
      if (!inViewport(sw.el)) return;

      const now = performance.now();
      const t = (typeof sw.getTranslate === "function") ? sw.getTranslate() : null;

      let s = state.get(sw);
      if (!s) {
        s = { lastT: t, lastMoveAt: now, lastCheckAt: now };
        state.set(sw, s);
        return;
      }

      // if translate changes, it is moving
      if (t !== null && s.lastT !== null && Math.abs(t - s.lastT) > EPS) {
        s.lastT = t;
        s.lastMoveAt = now;
        s.lastCheckAt = now;
        return;
      }

      // no movement detected
      s.lastCheckAt = now;

      const running = !!sw.autoplay?.running;

      // If autoplay says it is running BUT we haven't moved for a while -> revive
      if (running && (now - s.lastMoveAt) > STALL_TIME) {
        hardRevive(sw);
        // reset timers after revive
        s.lastMoveAt = now;
        s.lastT = (typeof sw.getTranslate === "function") ? sw.getTranslate() : s.lastT;
      }
    });
  }

  // ---- Trigger revives on “interaction events” (but NOT on scroll) ----
  let reviveTimer = 0;
  function scheduleRevive(delay = 120) {
    clearTimeout(reviveTimer);
    reviveTimer = setTimeout(() => {
      getStickyBrandSwipers().forEach(sw => {
        // Only revive if it claims autoplay exists but not running (or if it’s disabled)
        if (sw.autoplay && !sw.autoplay.running) hardRevive(sw);
        else softUpdate(sw);
      });
    }, delay);
  }

  function bindTriggers() {
    // clicks (one-page nav etc.)
    document.addEventListener("click", () => scheduleRevive(150), true);
    window.addEventListener("hashchange", () => scheduleRevive(150));

    // returning to tab/window
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) scheduleRevive(0);
    });
    window.addEventListener("focus", () => scheduleRevive(0));

    // Scroll: DO NOT revive continuously.
    // Only do a *soft* update after scrolling stops.
    let scrollEndTimer = 0;
    window.addEventListener("scroll", () => {
      clearTimeout(scrollEndTimer);
      scrollEndTimer = setTimeout(() => {
        getStickyBrandSwipers().forEach(softUpdate);
      }, 180);
    }, { passive: true });
  }

  let watchdogId = null;

  function boot() {
    bindTriggers();

    // initial soft update
    getStickyBrandSwipers().forEach(softUpdate);

    // start watchdog
    if (!watchdogId) {
      watchdogId = setInterval(tickWatchdog, WATCH_INTERVAL);
    }

    // a couple of late passes (Elementor/Swiper init timing)
    scheduleRevive(300);
    scheduleRevive(1200);
  }

  document.addEventListener("DOMContentLoaded", boot);
  window.addEventListener("load", boot);
})();


/* Fix mobile menu on onepage and remove cursor */

(function () {
  "use strict";

  // Only run on touch/mobile-ish devices
  const isTouch =
    "ontouchstart" in window ||
    (navigator.maxTouchPoints || 0) > 0 ||
    window.matchMedia("(hover: none), (pointer: coarse)").matches;

  const MOBILE_MQ = window.matchMedia("(max-width: 1024px)");

  const SELECTORS = {
    menuRoot: ".wcf__nav-menu, .deep__nav-menu",
    burger: ".wcf-menu-hamburger, .deep-menu-hamburger, button[aria-label='hamburger-icon']",
    overlay: ".mobile-sub-back",
    menuLinks:
      ".deep-nav-menu-container a.deep-nav-item, .deep-nav-menu-container a[href], .wcf-nav-menu-container a.wcf-nav-item, .wcf-nav-menu-container a[href]"
  };

  const OPEN_CLASSES = [
    "mobile-menu-active",
    "wcf-nav-is-toggled",
    "deep-nav-is-toggled",
    "is-open",
    "open"
  ];

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function getBurger() {
    return $(SELECTORS.burger);
  }

  function getAllMenuRoots() {
    return $$(".deep__nav-menu, .wcf__nav-menu");
  }

  function findMenuForStrayNode(node) {
    const widget = node.closest(".elementor-element");
    if (widget) {
      const menu = widget.querySelector(".deep__nav-menu, .wcf__nav-menu");
      if (menu) return menu;
    }
    const menus = getAllMenuRoots();
    return menus[0] || null;
  }

  /** Keep hamburger → panel → overlay so the backdrop cannot sit above the drawer. */
  function normalizeMenuDomOrder(menu) {
    if (!menu) return;

    const hamburger = menu.querySelector(".deep-menu-hamburger, .wcf-menu-hamburger");
    const container = menu.querySelector(".deep-nav-menu-container, .wcf-nav-menu-container");
    const overlay = menu.querySelector(".deep-menu-overlay, .wcf-menu-overlay");

    if (container && overlay) {
      menu.insertBefore(container, overlay);
    }
    if (hamburger && container) {
      menu.insertBefore(hamburger, container);
    }
  }

  function repairDeepNavDom() {
    document.body.classList.remove("wcf-mobile-nav-open");

    $$(".deep-menu-overlay, .wcf-menu-overlay").forEach((el) => {
      el.style.removeProperty("pointer-events");
      el.style.removeProperty("visibility");
      el.style.removeProperty("opacity");
      el.style.removeProperty("display");
    });

    $$(
      "body > .deep-menu-overlay, body > .wcf-menu-overlay, body > .deep-nav-menu-container, body > .wcf-nav-menu-container"
    ).forEach((el) => {
      const menu = findMenuForStrayNode(el);
      if (menu) menu.appendChild(el);
    });

    getAllMenuRoots().forEach(normalizeMenuDomOrder);
  }

  function getMenuRoot() {
    const burger = getBurger();
    if (burger) {
      return burger.closest(".deep__nav-menu") || burger.closest(".wcf__nav-menu");
    }
    return $(".deep__nav-menu") || $(".wcf__nav-menu");
  }

  function isAnyNavDrawerOpen() {
    return !!document.querySelector(
      ".deep__nav-menu.deep-nav-is-toggled, .wcf__nav-menu.wcf-nav-is-toggled"
    );
  }

  function isMenuOpen(root) {
    if (!root) return false;
    return OPEN_CLASSES.some((c) => root.classList.contains(c)) || OPEN_CLASSES.some((c) => document.body.classList.contains(c));
  }

  function unlockScrollHard() {
    // Remove common scroll locks
    document.documentElement.style.overflow = "";
    document.documentElement.style.position = "";
    document.documentElement.style.height = "";
    document.documentElement.style.top = "";
    document.documentElement.style.width = "";

    document.body.style.overflow = "";
    document.body.style.position = "";
    document.body.style.height = "";
    document.body.style.top = "";
    document.body.style.width = "";
    document.body.style.touchAction = "";

    // Ensure overlay/backdrop isn't blocking taps
    const overlay = $(SELECTORS.overlay);
    if (overlay) overlay.style.display = "none";
  }

  function syncScrollLock() {
    if (isAnyNavDrawerOpen()) return;

    const root = getMenuRoot();
    if (!isMenuOpen(root)) {
      const bodyOverflow = getComputedStyle(document.body).overflow;
      const htmlOverflow = getComputedStyle(document.documentElement).overflow;
      if (bodyOverflow === "hidden" || htmlOverflow === "hidden") {
        unlockScrollHard();
      }
    }
  }
  
    function menuLooksVisible() {
	  const panel =
	    document.querySelector(".deep-nav-menu-container") ||
	    document.querySelector(".wcf-nav-menu-container") ||
	    document.querySelector(".wcf__nav-menu-container") ||
	    document.querySelector(".wcf-nav-menu__container") ||
	    document.querySelector(".wcf__nav-menu");
	
	  if (!panel) return false;
	
	  const rect = panel.getBoundingClientRect();
	  const style = window.getComputedStyle(panel);
	
	  const vw = window.innerWidth || document.documentElement.clientWidth;
	  const vh = window.innerHeight || document.documentElement.clientHeight;
	
	  // fully off-canvas / outside viewport
	  const offscreen =
	    rect.right <= 1 ||            // hidden to the left (your case: right == 0)
	    rect.left >= vw - 1 ||        // hidden to the right
	    rect.bottom <= 1 ||           // hidden above
	    rect.top >= vh - 1;           // hidden below
	
	  const notRenderable =
	    style.display === "none" ||
	    style.visibility === "hidden" ||
	    style.opacity === "0" ||
	    style.pointerEvents === "none";
	
	  return !(offscreen || notRenderable);
	}


  // 🔥 Stronger scroll-lock sync:
  // If scroll is locked BUT menu isn't visible, unlock no matter what classes say.
  function syncScrollLockHard() {
    const bodyOverflow = getComputedStyle(document.body).overflow;
    const htmlOverflow = getComputedStyle(document.documentElement).overflow;

    const locked = bodyOverflow === "hidden" || htmlOverflow === "hidden";

    if (!locked) return;

    // If menu isn't actually visible, this is a bug -> unlock
    if (!menuLooksVisible()) {
      const root = getMenuRoot();

      // Remove common "open" classes aggressively
      if (root) root.classList.remove(...OPEN_CLASSES);
      document.body.classList.remove(...OPEN_CLASSES);

      unlockScrollHard();
    }
  }


  function clickBurger() {
    const burger = getBurger();
    if (burger) burger.click();
  }

  // ----- anchor detection -----
  function isSamePageHashLink(a) {
    if (!a || !a.getAttribute) return false;
    const href = a.getAttribute("href") || "";
    if (!href.includes("#")) return false;

    if (href.startsWith("#")) return true;

    try {
      const url = new URL(href, window.location.href);
      return (
        url.origin === window.location.origin &&
        url.pathname.replace(/\/+$/, "") === window.location.pathname.replace(/\/+$/, "") &&
        !!url.hash
      );
    } catch {
      return false;
    }
  }

  function getHash(a) {
    const href = a.getAttribute("href") || "";
    if (href.startsWith("#")) return href;
    try {
      const url = new URL(href, window.location.href);
      return url.hash || "";
    } catch {
      return "";
    }
  }

  // ----- smooth scroll (easeInOutQuart @ 1500ms via deep-smooth-scroll.js) -----
  function nativeSmoothScrollToHash(hash) {
    if (!hash || hash === "#") return;
    if (window.DeepSmoothScroll && typeof window.DeepSmoothScroll.scrollToHash === "function") {
      window.DeepSmoothScroll.scrollToHash(hash);
      return;
    }

    const id = hash.slice(1);
    const target = document.getElementById(id) || document.querySelector(hash);
    if (!target) return;

    const startY = window.pageYOffset;
    const targetY = target.getBoundingClientRect().top + startY;
    const distance = targetY - startY;
    const duration = 1500;
    const startTime = performance.now();

    function easeInOutQuart(t) {
      return t < 0.5 ? 8 * t * t * t * t : 1 - Math.pow(-2 * t + 2, 4) / 2;
    }

    function step(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = easeInOutQuart(progress);
      window.scrollTo(0, startY + distance * eased);
      if (progress < 1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
  }

  // ----- close menu safely -----
  function closeMenuAndUnlock() {
    const root = getMenuRoot();
    if (!root) {
      unlockScrollHard();
      return;
    }

    if (isMenuOpen(root)) {
      clickBurger(); // close via theme
    }

    // Always ensure scroll is not stuck
    setTimeout(unlockScrollHard, 60);
    setTimeout(syncScrollLock, 120);
    setTimeout(syncScrollLock, 500);
  }

  // ----- burger reliability -----
  function installBurgerFailsafe() {
    const burger = getBurger();
    if (!burger) return;

    // Make burger easier to tap (z-index + touch-action)
    const style = document.createElement("style");
    style.textContent = `
      .wcf-menu-hamburger, .deep-menu-hamburger, button[aria-label='hamburger-icon']{
        position: relative;
        z-index: 999999;
        pointer-events: auto;
        touch-action: manipulation;
      }
    `;
    document.head.appendChild(style);

    // After any burger click, resync scroll lock
    burger.addEventListener(
      "click",
      function () {
        setTimeout(syncScrollLock, 800);
      },
      true
    );

    window.addEventListener("resize", syncScrollLock, { passive: true });
    window.addEventListener("orientationchange", syncScrollLock, { passive: true });
  }

  function isMobileExpandableParentLink(a) {
    const menu = a.closest(".deep__nav-menu.deep-nav-is-toggled, .wcf__nav-menu.wcf-nav-is-toggled");
    if (!menu || !menu.classList.contains("mobile-menu-active")) return false;
    if (a.closest(".sub-menu, .deep-mega-menu-panel")) return false;

    const parentLi = a.closest("li.menu-item, li.deep-mega-menu");
    if (!parentLi) return false;

    const directLink = parentLi.querySelector(":scope > a");
    if (!directLink || (a !== directLink && !directLink.contains(a))) return false;

    return (
      parentLi.classList.contains("menu-item-has-children") ||
      parentLi.classList.contains("deep-mega-menu") ||
      !!parentLi.querySelector(":scope > .sub-menu") ||
      !!parentLi.querySelector(":scope > .deep-mega-menu-panel")
    );
  }

  // ----- close on anchor click (mobile menu) -----
  function installCloseOnAnchorClick() {
    document.addEventListener(
      "click",
      function (e) {
        if (!MOBILE_MQ.matches) return;

        const a = e.target && e.target.closest ? e.target.closest(SELECTORS.menuLinks) : null;
        if (!a) return;

        if (isMobileExpandableParentLink(a)) return;

        if (!isSamePageHashLink(a)) return;

        const root = getMenuRoot();
        if (!isMenuOpen(root)) return;

        if (window.DeepSmoothScroll) return;

        e.preventDefault();
        const hash = getHash(a);

        // close menu first
        closeMenuAndUnlock();

        // then smooth scroll (small delay so menu can close)
        setTimeout(() => nativeSmoothScrollToHash(hash), 180);
      },
      true
    );
  }

  // ----- disable cursor on touch (SAFE way) -----
  function disableCursorOnTouch() {
    if (!isTouch) return;

    // Hide cursor elements so they never block touches
    const style = document.createElement("style");
    style.textContent = `
      @media (hover: none), (pointer: coarse) {
        .wcf-cursor,
        .wcf-cursor-wrapper,
        .wcf-cursor-dot,
        .wcf-cursor-outline,
        .cursor-hover-effects {
          display: none !important;
          pointer-events: none !important;
          visibility: hidden !important;
        }
      }
    `;
    document.head.appendChild(style);

    // Remove any cursor nodes already injected
    const remove = () => {
      $$(".wcf-cursor, .wcf-cursor-wrapper, .wcf-cursor-dot, .wcf-cursor-outline, .cursor-hover-effects").forEach((el) => el.remove());
    };
    remove();
    window.addEventListener("load", remove);
  }

  function init() {
    disableCursorOnTouch();
    installBurgerFailsafe();
    installCloseOnAnchorClick();

    repairDeepNavDom();

    setTimeout(syncScrollLock, 0);

    window.addEventListener("elementor/frontend/init", () => {
      setTimeout(repairDeepNavDom, 0);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();

