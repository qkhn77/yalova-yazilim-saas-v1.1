/**
 * Deep smooth scroll — easeInOutQuart @ 1500ms, same-page + cross-page hash targets.
 */
(function () {
  "use strict";

  const STORAGE_KEY = "deep_pending_scroll";
  const DEFAULT_DURATION = 1500;
  const DEFAULT_OFFSET = 80;
  const SPY_ROOT = "[data-deep-scroll-spy='yes'], [data-treethemes-scroll-spy='yes']";
  const SMOOTH_SCROLL_ROOT =
    "[data-deep-smooth-scroll='yes'], [data-treethemes-smooth-scroll='yes']";
  const ACTIVE_CLASS = "deep-nav-section-active";
  const SCROLL_SPY_TOLERANCE = 16;

  const NAV_LINK_SELECTORS = [
    ".deep-nav-menu-container a[href*='#']",
    ".wcf-nav-menu-container a[href*='#']",
    ".wcf--onepage-nav a[href*='#']",
    ".bbe-offcanvas a[href*='#']",
    "a.deep-nav-item[href*='#']",
    "a.wcf-nav-item[href*='#']",
  ].join(", ");

  const DEMO_PREVIEW_HOSTS = [
    "preview.treethemes.com",
    "treethemes.com",
    "member.treethemes.com",
  ];

  /** UI that must keep native hash/tab behaviour (WooCommerce, Elementor tabs, etc.). */
  const NATIVE_TAB_ROOT_SELECTORS = [
    ".woocommerce-tabs",
    ".woocommerce-MyAccount-navigation",
    ".wc-tabs",
    "ul.tabs",
    ".elementor-tab-title",
    ".elementor-tabs-wrapper",
    ".e-n-tabs",
    ".eae-tabs",
    "[role='tablist']",
    ".treethemes-testimonials",
  ].join(", ");

  function isNativeTabAnchor(anchor) {
    if (!anchor) return false;
    return !!anchor.closest(NATIVE_TAB_ROOT_SELECTORS);
  }

  function isNativeTabHash(hash) {
    if (!hash || hash === "#") return false;
    const id = hash.charAt(0) === "#" ? hash.slice(1) : hash;
    if (!id.startsWith("tab-")) return false;
    return !!document.querySelector(NATIVE_TAB_ROOT_SELECTORS);
  }

  /** Only hijack hashes used for one-page nav / scroll spy — not product tabs, etc. */
  function shouldHandleSmoothScroll(anchor, parsed, inNav) {
    if (isNativeTabAnchor(anchor)) return false;
    if (inNav) return true;
    if (anchor.closest(`${SMOOTH_SCROLL_ROOT}, .wcf--onepage-nav, .bbe-offcanvas`)) {
      return true;
    }
    if (findSectionByHash(parsed.hash)) return true;
    return false;
  }

  /** @type {{ hash: string, target: Element, links: HTMLElement[] }[]} */
  let spySections = [];
  let spyPinnedHash = null;
  let forcedActiveHash = null;
  let isSmoothScrolling = false;
  let smoothScrollFrame = null;
  let spyObserver = null;
  const spyVisibility = new Map();
  let crossPageScrollHandled = false;

  function isElementorEditor() {
    if (document.body && document.body.classList.contains("elementor-editor-active")) {
      return true;
    }
    if (typeof elementorFrontend !== "undefined" && typeof elementorFrontend.isEditMode === "function") {
      try {
        return !!elementorFrontend.isEditMode();
      } catch {
        return false;
      }
    }
    return false;
  }

  function normalizeHash(hash) {
    if (!hash) return "";
    const value = hash.charAt(0) === "#" ? hash : `#${hash}`;
    return value.toLowerCase();
  }

  function findSectionByHash(hash) {
    const id = normalizeHash(hash);
    return spySections.find((section) => normalizeHash(section.hash) === id) || null;
  }

  function highlightForClick(hash) {
    if (!hash) return;
    spyPinnedHash = hash;
    forcedActiveHash = hash;
    setActiveForHash(hash);
    requestAnimationFrame(() => setActiveForHash(hash));
  }

  function releaseForcedActiveIfLanded() {
    if (!forcedActiveHash) return false;

    const section = findSectionByHash(forcedActiveHash);
    if (!section) {
      forcedActiveHash = null;
      return false;
    }

    const position = window.pageYOffset + getScrollOffset();
    const top = getTargetDocumentTop(section.target) - SCROLL_SPY_TOLERANCE;

    if (position >= top) {
      forcedActiveHash = null;
      return false;
    }

    setActiveForHash(forcedActiveHash);
    return true;
  }

  function isSpyLocked() {
    return isSmoothScrolling;
  }

  function cancelSmoothScroll() {
    if (!isSmoothScrolling) return;
    isSmoothScrolling = false;
    spyPinnedHash = null;
    forcedActiveHash = null;
    if (smoothScrollFrame) {
      cancelAnimationFrame(smoothScrollFrame);
      smoothScrollFrame = null;
    }
    updateScrollSpy();
  }

  function easeInOutQuart(t) {
    return t < 0.5 ? 8 * t * t * t * t : 1 - Math.pow(-2 * t + 2, 4) / 2;
  }

  function normalizePath(pathname) {
    const path = pathname || "/";
    const trimmed = path.replace(/\/+$/, "");
    return trimmed === "" ? "/" : trimmed;
  }

  function isDemoPreviewHost(hostname) {
    if (!hostname) return false;
    const host = hostname.toLowerCase();
    return DEMO_PREVIEW_HOSTS.some(
      (demo) => host === demo || host.endsWith("." + demo)
    );
  }

  /**
   * Imported demos ship menu links like preview.treethemes.com/.../onepage2/#section.
   * Rewrite to #section so smooth scroll + scroll spy work on the customer's domain.
   */
  function rewriteDemoPreviewNavAnchors() {
    document.querySelectorAll(NAV_LINK_SELECTORS).forEach((link) => {
      const href = (link.getAttribute("href") || "").trim();
      if (!href || !href.includes("#")) return;

      let url;
      try {
        url = new URL(href, window.location.href);
      } catch {
        return;
      }

      if (!isDemoPreviewHost(url.hostname) || !url.hash || url.hash === "#") return;
      if (!resolveTarget(url.hash)) return;

      if (link.getAttribute("href") !== url.hash) {
        link.setAttribute("href", url.hash);
      }
    });
  }

  function throttle(fn, wait) {
    let last = 0;
    let timer;
    return function throttled(...args) {
      const now = Date.now();
      const remaining = wait - (now - last);
      if (remaining <= 0) {
        last = now;
        fn.apply(this, args);
      } else {
        clearTimeout(timer);
        timer = setTimeout(() => {
          last = Date.now();
          fn.apply(this, args);
        }, remaining);
      }
    };
  }

  function getScrollOffset() {
    const body = document.body;
    if (!body) return DEFAULT_OFFSET;
    const custom = parseInt(body.getAttribute("data-deep-scroll-offset") || "", 10);
    return Number.isFinite(custom) ? custom : DEFAULT_OFFSET;
  }

  function getDuration() {
    const body = document.body;
    if (!body) return DEFAULT_DURATION;
    const custom = parseInt(body.getAttribute("data-deep-scroll-duration") || "", 10);
    return Number.isFinite(custom) && custom > 0 ? custom : DEFAULT_DURATION;
  }

  function getScrollSmoother() {
    if (window.wcf_smoother) return window.wcf_smoother;
    if (typeof ScrollSmoother !== "undefined" && typeof ScrollSmoother.get === "function") {
      try {
        return ScrollSmoother.get() || null;
      } catch {
        return null;
      }
    }
    return null;
  }

  function getScrollPosition() {
    const smoother = getScrollSmoother();
    if (smoother && typeof smoother.scrollTop === "function") {
      return smoother.scrollTop();
    }
    return window.pageYOffset;
  }

  function setScrollPosition(y, smooth) {
    const smoother = getScrollSmoother();
    if (smoother && typeof smoother.scrollTop === "function") {
      smoother.scrollTop(y, !!smooth);
      return;
    }
    window.scrollTo(0, y);
  }

  function scrollToTopImmediate() {
    if ("scrollRestoration" in history) {
      history.scrollRestoration = "manual";
    }
    setScrollPosition(0, false);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
  }

  function getOnepageHomeUrl() {
    const body = document.body;
    if (!body) return null;

    const custom = (body.getAttribute("data-deep-onepage-home") || "").trim();
    if (!custom) return null;

    try {
      return new URL(custom, window.location.href).href;
    } catch {
      return null;
    }
  }

  function navigateToOnepageSection(hash) {
    const homeUrl = getOnepageHomeUrl();
    if (!homeUrl || !hash || hash === "#") return false;

    let destUrl;
    try {
      destUrl = new URL(hash, homeUrl);
    } catch {
      return false;
    }

    if (
      destUrl.origin === window.location.origin &&
      normalizePath(destUrl.pathname) === normalizePath(window.location.pathname)
    ) {
      return false;
    }

    storePendingScroll(destUrl);
    closeMobileMenus();
    window.location.assign(destUrl.origin + destUrl.pathname + (destUrl.search || ""));
    return true;
  }

  function parseAnchorLink(anchor) {
    if (!anchor || !anchor.getAttribute) return null;
    const href = (anchor.getAttribute("href") || "").trim();
    if (!href || href === "#" || href.startsWith("javascript:")) return null;
    if (!href.includes("#")) return null;

    try {
      const url = new URL(href, window.location.href);
      const hash = url.hash;
      if (!hash || hash === "#") return null;

      let samePage =
        url.origin === window.location.origin &&
        normalizePath(url.pathname) === normalizePath(window.location.pathname);

      if (
        !samePage &&
        url.hash &&
        url.hash !== "#" &&
        isDemoPreviewHost(url.hostname) &&
        resolveTarget(url.hash)
      ) {
        samePage = true;
      }

      return { url, hash, samePage, href };
    } catch {
      if (href.startsWith("#")) {
        return {
          url: new URL(window.location.href),
          hash: href,
          samePage: true,
          href,
        };
      }
      return null;
    }
  }

  function resolveTarget(hash) {
    if (!hash || hash === "#") return null;
    const id = hash.charAt(0) === "#" ? hash.slice(1) : hash;
    if (!id) return null;
    return document.getElementById(id) || document.querySelector("#" + CSS.escape(id));
  }

  function getTargetDocumentTop(target) {
    return target.getBoundingClientRect().top + window.pageYOffset;
  }

  function getTargetScrollY(target) {
    const offset = getScrollOffset();
    const smoother = getScrollSmoother();
    if (smoother && typeof smoother.offset === "function") {
      return smoother.offset(target, `top ${offset}px`);
    }
    return Math.max(0, getTargetDocumentTop(target) - offset);
  }

  function finishSmoothScroll(landedHash, onComplete) {
    if (landedHash) {
      forcedActiveHash = landedHash;
      setActiveForHash(landedHash);
      requestAnimationFrame(() => {
        setActiveForHash(landedHash);
        if (!releaseForcedActiveIfLanded()) {
          updateScrollSpy();
        }
      });
    } else {
      updateScrollSpy();
    }

    if (typeof onComplete === "function") {
      onComplete();
    }
  }

  function scrollToElement(target, duration, onComplete) {
    if (!target) return false;

    const startY = getScrollPosition();
    const targetY = getTargetScrollY(target);
    const distance = targetY - startY;
    const dur = duration || getDuration();
    const startTime = performance.now();
    isSmoothScrolling = true;

    function step(now) {
      if (!isSmoothScrolling) return;

      const elapsed = now - startTime;
      const progress = Math.min(elapsed / dur, 1);
      const eased = easeInOutQuart(progress);
      setScrollPosition(startY + distance * eased, false);

      if (spyPinnedHash) {
        setActiveForHash(spyPinnedHash);
      }

      if (progress < 1) {
        smoothScrollFrame = requestAnimationFrame(step);
        return;
      }

      setScrollPosition(targetY, false);

      const landedHash = spyPinnedHash;
      isSmoothScrolling = false;
      smoothScrollFrame = null;
      spyPinnedHash = null;
      finishSmoothScroll(landedHash, onComplete);
    }

    smoothScrollFrame = requestAnimationFrame(step);
    return true;
  }

  function scrollToHash(hash, duration, onComplete) {
    const target = resolveTarget(hash);
    if (!target) return false;
    if (hash) {
      highlightForClick(hash);
    }
    return scrollToElement(target, duration, onComplete);
  }

  function peekPendingCrossPageHash() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      const pending = JSON.parse(raw);
      if (
        pending &&
        pending.hash &&
        normalizePath(window.location.pathname) === pending.path
      ) {
        return pending.hash;
      }
    } catch {
      /* ignore */
    }

    return null;
  }

  function takePendingCrossPageHash() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      sessionStorage.removeItem(STORAGE_KEY);
      const pending = JSON.parse(raw);
      if (
        pending &&
        pending.hash &&
        normalizePath(window.location.pathname) === pending.path
      ) {
        return pending.hash;
      }
    } catch {
      /* ignore */
    }

    return null;
  }

  function shouldSmoothScrollHash(hash) {
    if (!hash || hash === "#" || isNativeTabHash(hash)) return false;

    const target = resolveTarget(hash);
    if (!target) return false;

    if (document.body && document.body.classList.contains("deep-smooth-scroll-active")) {
      return true;
    }

    if (findSectionByHash(hash)) return true;

    return false;
  }

  function primeCrossPageScroll() {
    if (!peekPendingCrossPageHash() && !window.location.hash) return;
    scrollToTopImmediate();
  }

  function applyHashToUrl(hash) {
    if (!hash || hash === "#" || !history.replaceState) return;

    history.replaceState(
      null,
      "",
      window.location.pathname + (window.location.search || "") + hash
    );
  }

  function runPendingScrollToHash(hash, options) {
    const opts = options || {};
    const deferred = !!opts.deferred;
    const target = resolveTarget(hash);
    if (!target) return false;

    if (deferred) {
      scrollToTopImmediate();
    }

    highlightForClick(hash);

    return scrollToHash(hash, opts.duration, () => {
      if (deferred) {
        applyHashToUrl(hash);
      }
    });
  }

  function schedulePendingScrollToHash(hash, options) {
    const opts = options || {};
    const delays = opts.deferred ? [100, 350, 700] : [0, 120, 450];
    let completed = false;

    const tryScroll = () => {
      if (completed || crossPageScrollHandled) return true;
      if (!resolveTarget(hash)) return false;

      completed = true;
      crossPageScrollHandled = true;
      runPendingScrollToHash(hash, opts);
      return true;
    };

    delays.forEach((delay) => {
      window.setTimeout(() => {
        tryScroll();
      }, delay);
    });
  }

  function closeMobileMenus() {
    document.querySelectorAll(".deep__nav-menu.deep-nav-is-toggled, .deep__nav-menu.wcf-nav-is-toggled").forEach((menu) => {
      menu.classList.remove("deep-nav-is-toggled", "wcf-nav-is-toggled");
      menu.querySelectorAll(".is-accordion-open").forEach((item) => {
        item.classList.remove("is-accordion-open");
      });
      menu.querySelectorAll(".deep-menu-hamburger.deep-menu-theme-trigger.opened").forEach((btn) => {
        btn.classList.remove("opened");
        btn.setAttribute("aria-expanded", "false");
      });
    });
    document.querySelectorAll(".wcf__nav-menu.wcf-nav-is-toggled").forEach((menu) => {
      menu.classList.remove("wcf-nav-is-toggled");
    });
    document.body.style.overflow = "";

    document.querySelectorAll(".deep-menu-close, .wcf-menu-close").forEach((btn) => {
      btn.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    });
  }

  /** Let deep-navigation toggle submenus instead of closing the drawer (one-page parent links). */
  function isMobileNavExpandableParent(anchor) {
    const menu = anchor.closest(".deep__nav-menu.deep-nav-is-toggled, .wcf__nav-menu.wcf-nav-is-toggled");
    if (!menu || !menu.classList.contains("mobile-menu-active")) return false;

    const container = anchor.closest(".deep-nav-menu-container, .wcf-nav-menu-container");
    if (!container) return false;

    if (anchor.closest(".sub-menu, .deep-mega-menu-panel")) return false;

    const parentLi = anchor.closest("li.menu-item, li.deep-mega-menu");
    if (!parentLi || !container.contains(parentLi)) return false;

    const directLink = parentLi.querySelector(":scope > a");
    if (!directLink || (anchor !== directLink && !directLink.contains(anchor))) return false;

    return (
      parentLi.classList.contains("menu-item-has-children") ||
      parentLi.classList.contains("deep-mega-menu") ||
      !!parentLi.querySelector(":scope > .sub-menu") ||
      !!parentLi.querySelector(":scope > .deep-mega-menu-panel")
    );
  }

  function storePendingScroll(url) {
    try {
      sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          path: normalizePath(url.pathname),
          hash: url.hash,
        })
      );
    } catch {
      /* ignore */
    }
  }

  function collectSpySections() {
    const byHash = new Map();

    document.querySelectorAll(SPY_ROOT).forEach((root) => {
      root.querySelectorAll("a[href*='#']").forEach((link) => {
        const parsed = parseAnchorLink(link);
        if (!parsed || !parsed.samePage) return;

        const target = resolveTarget(parsed.hash);
        const li = link.closest(".menu-item");
        if (!target || !li) return;

        const hashKey = normalizeHash(parsed.hash);
        if (!byHash.has(hashKey)) {
          byHash.set(hashKey, {
            hash: parsed.hash,
            target,
            links: [],
          });
        }

        const section = byHash.get(hashKey);
        if (!section.links.includes(li)) {
          section.links.push(li);
        }
      });
    });

    spySections = Array.from(byHash.values()).sort(
      (a, b) => getTargetDocumentTop(a.target) - getTargetDocumentTop(b.target)
    );
  }

  function clearSpyActive() {
    document.querySelectorAll(`.menu-item.${ACTIVE_CLASS}`).forEach((li) => {
      li.classList.remove(ACTIVE_CLASS);
    });
  }

  function setActiveForHash(hash) {
    const section = findSectionByHash(hash);
    if (!section) return;
    clearSpyActive();
    section.links.forEach((li) => li.classList.add(ACTIVE_CLASS));
  }

  function applySpySection(section) {
    clearSpyActive();
    if (section) {
      section.links.forEach((li) => li.classList.add(ACTIVE_CLASS));
    }
  }

  function findActiveSectionFromScroll() {
    if (!spySections.length) return null;

    const offset = getScrollOffset();
    const position = window.pageYOffset + offset;
    const tolerance = SCROLL_SPY_TOLERANCE;
    const firstTop = getTargetDocumentTop(spySections[0].target);

    if (position < firstTop - tolerance) {
      return null;
    }

    let active = null;
    spySections.forEach((section) => {
      const top = getTargetDocumentTop(section.target);
      if (position >= top - tolerance) {
        active = section;
      }
    });

    return active;
  }

  function findActiveSectionFromObserver() {
    let best = null;
    let bestRatio = 0;

    spyVisibility.forEach((ratio, section) => {
      if (ratio > bestRatio) {
        bestRatio = ratio;
        best = section;
      }
    });

    return bestRatio >= 0.1 ? best : null;
  }

  function updateScrollSpy() {
    if (!spySections.length) return;

    if (isSpyLocked()) {
      if (spyPinnedHash) {
        setActiveForHash(spyPinnedHash);
      }
      return;
    }

    if (releaseForcedActiveIfLanded()) {
      return;
    }

    const scrollSection = findActiveSectionFromScroll();
    const observerSection = findActiveSectionFromObserver();
    const current = scrollSection || observerSection;

    applySpySection(current);
  }

  function refreshScrollSpy() {
    rewriteDemoPreviewNavAnchors();
    collectSpySections();
    initSpyObserver();
    updateScrollSpy();
  }

  function destroySpyObserver() {
    if (spyObserver) {
      spyObserver.disconnect();
      spyObserver = null;
    }
    spyVisibility.clear();
  }

  function initSpyObserver() {
    destroySpyObserver();
    if (!spySections.length) return;

    const offset = getScrollOffset();

    spyObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((observed) => {
          const match = spySections.find((section) => section.target === observed.target);
          if (match) {
            spyVisibility.set(match, observed.isIntersecting ? observed.intersectionRatio : 0);
          }
        });
        updateScrollSpy();
      },
      {
        root: null,
        rootMargin: `-${offset}px 0px -65% 0px`,
        threshold: [0, 0.05, 0.1, 0.2, 0.35, 0.5, 0.75, 1],
      }
    );

    spySections.forEach((section) => {
      spyVisibility.set(section, 0);
      spyObserver.observe(section.target);
    });
  }

  let scrollSpyListenersBound = false;

  function bindScrollSpyListeners() {
    if (scrollSpyListenersBound) return;
    scrollSpyListenersBound = true;

    const onScroll = throttle(updateScrollSpy, 16);
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("wheel", cancelSmoothScroll, { passive: true });
    window.addEventListener("touchstart", cancelSmoothScroll, { passive: true });
    window.addEventListener(
      "resize",
      throttle(refreshScrollSpy, 200),
      { passive: true }
    );
  }

  function initScrollSpy() {
    if (!document.querySelector(SPY_ROOT)) return;

    collectSpySections();
    if (!spySections.length) return;

    bindScrollSpyListeners();
    initSpyObserver();

    if (window.location.hash) {
      setActiveForHash(window.location.hash);
    } else {
      updateScrollSpy();
    }
  }

  function consumePendingScroll() {
    if (isElementorEditor() || crossPageScrollHandled) return;

    const pendingHash = takePendingCrossPageHash();
    if (!pendingHash) return;

    if (isNativeTabHash(pendingHash)) return;

    if (!shouldSmoothScrollHash(pendingHash)) {
      const target = resolveTarget(pendingHash);
      if (target) {
        target.scrollIntoView();
      }
      applyHashToUrl(pendingHash);
      crossPageScrollHandled = true;
      return;
    }

    scrollToTopImmediate();
    schedulePendingScrollToHash(pendingHash, { deferred: true });
  }

  function handleAnchorClick(event) {
    const anchor = event.target && event.target.closest ? event.target.closest("a") : null;
    if (!anchor) return;

    const inNav =
      anchor.matches(NAV_LINK_SELECTORS) ||
      !!anchor.closest(
        ".deep-nav-menu-container, .treethemes-nav-menu-container, .wcf-nav-menu-container, .wcf--onepage-nav, .bbe-offcanvas, nav.nav-style-6, .tt-bubble-portal, .cd-primary-nav, .treethemes-nav-menu-nav"
      );

    if (!isElementorEditor() && inNav && isMobileNavExpandableParent(anchor)) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    const parsed = parseAnchorLink(anchor);

    if (isElementorEditor()) {
      if (inNav && !anchor.classList.contains("cd-nav-trigger")) {
        event.preventDefault();
        event.stopPropagation();
      }
      return;
    }

    if (!parsed) return;

    if (isNativeTabAnchor(anchor)) return;

    if (!shouldHandleSmoothScroll(anchor, parsed, inNav)) return;

    if (!parsed.samePage) {
      if (!inNav && !anchor.closest(SMOOTH_SCROLL_ROOT)) return;

      event.preventDefault();
      storePendingScroll(parsed.url);
      closeMobileMenus();

      const dest =
        parsed.url.origin +
        parsed.url.pathname +
        (parsed.url.search || "");

      window.location.assign(dest);
      return;
    }

    const menuIsOpen = !!anchor.closest(
      ".deep__nav-menu.deep-nav-is-toggled, .wcf__nav-menu.wcf-nav-is-toggled"
    );

    const target = resolveTarget(parsed.hash);
    if (!target) {
      if (inNav && parsed.hash && navigateToOnepageSection(parsed.hash)) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      if (menuIsOpen && !isMobileNavExpandableParent(anchor)) closeMobileMenus();
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (menuIsOpen) closeMobileMenus();

    if (history.pushState) {
      history.pushState(null, "", parsed.hash);
    } else {
      window.location.hash = parsed.hash;
    }

    highlightForClick(parsed.hash);
    scrollToElement(target);
  }

  function init() {
    primeCrossPageScroll();
    rewriteDemoPreviewNavAnchors();
    document.addEventListener("click", handleAnchorClick, true);
    window.addEventListener("hashchange", () => {
      const hash = window.location.hash;
      if (!hash || hash === "#") {
        spyPinnedHash = null;
        updateScrollSpy();
        return;
      }

      if (isNativeTabHash(hash)) return;

      const section = findSectionByHash(hash);
      if (!section) return;

      highlightForClick(hash);
      scrollToHash(hash);
    });

    const bootSpy = () => {
      initScrollSpy();
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", bootSpy);
    } else {
      bootSpy();
    }

    window.addEventListener("load", () => {
      refreshScrollSpy();
      consumePendingScroll();
      if (!window.location.hash) {
        updateScrollSpy();
      }
    });
  }

  function hookElementorNavRefresh() {
    const hook = () => {
      if (!window.elementorFrontend?.hooks) return;

      const refresh = () => {
        if (document.querySelector(SPY_ROOT)) {
          refreshScrollSpy();
        }
      };

      elementorFrontend.hooks.addAction("frontend/element_ready/treethemes--navigation.default", refresh);
      elementorFrontend.hooks.addAction("frontend/element_ready/deep--navigation.default", refresh);
      elementorFrontend.hooks.addAction("frontend/element_ready/wcf--nav-menu.default", refresh);
    };

    if (window.elementorFrontend?.hooks) {
      hook();
    } else {
      window.addEventListener("elementor/frontend/init", hook);
    }
  }

  window.DeepSmoothScroll = {
    easeInOutQuart,
    scrollToHash,
    scrollToElement,
    setActiveForHash,
    updateScrollSpy,
    refreshScrollSpy,
    DEFAULT_DURATION,
  };

  init();
  hookElementorNavRefresh();
})();
