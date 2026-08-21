(function () {
  'use strict';

  var instances = [];
  var booted = false;
  var lastScroll = 0;

  function getAdminOffset() {
    var adminBar = document.getElementById('wpadminbar');
    return adminBar ? adminBar.offsetHeight : 0;
  }

  function getViewportDevice() {
    var width = window.innerWidth || document.documentElement.clientWidth || 0;
    if (width <= 767) return 'mobile';
    if (width <= 1024) return 'tablet';
    return 'desktop';
  }

  function isEditorMode() {
    try {
      if (document.body && (document.body.classList.contains('elementor-editor-active') || document.body.classList.contains('wp-admin'))) {
        return true;
      }
      if (window.elementorFrontend && typeof window.elementorFrontend.isEditMode === 'function' && window.elementorFrontend.isEditMode()) {
        return true;
      }
      if (window.location && /action=elementor/.test(window.location.search || '')) {
        return true;
      }
    } catch (e) {}
    return false;
  }

  function getScrollPos() {
    var nativeScroll = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    if (window.ScrollTrigger && typeof window.ScrollTrigger.scroll === 'function') {
      var stScroll = window.ScrollTrigger.scroll();
      if (typeof stScroll === 'number' && isFinite(stScroll)) {
        return Math.max(nativeScroll, stScroll);
      }
    }
    return nativeScroll;
  }

  function isDeviceEnabled(value) {
    if (value === undefined || value === null || value === '') return true;
    return value === 'yes';
  }

  function isAllowedForDevice(el) {
    var device = getViewportDevice();
    if (device === 'desktop') return isDeviceEnabled(el.dataset.bbStickyDesktop);
    if (device === 'tablet') return isDeviceEnabled(el.dataset.bbStickyTablet);
    return isDeviceEnabled(el.dataset.bbStickyMobile);
  }

  function addStateClasses(target, instance) {
    if (!target) return;
    target.classList.add('bb-is-sticky');
    if (instance.shadow) target.classList.add('bb-has-sticky-shadow');
    if (instance.bgEnabled) target.classList.add('bb-has-sticky-bg');
    if (instance.contentScheme && instance.contentScheme !== 'default') {
      target.classList.add('bb-sticky-scheme-' + instance.contentScheme);
    }
    target.classList.add('bb-shrink-mode-' + instance.shrinkMode);
  }

  function removeStateClasses(target) {
    if (!target) return;
    target.classList.remove('bb-is-sticky', 'bb-has-sticky-shadow', 'bb-has-sticky-bg', 'bb-is-shrunk', 'bb-sticky-scheme-light', 'bb-sticky-scheme-dark', 'bb-shrink-mode-compact', 'bb-shrink-mode-scale');
    target.style.removeProperty('--bb-sticky-current-scale');
    target.style.removeProperty('--bb-sticky-compact-ratio');
    target.style.removeProperty('--bb-sticky-bg');
  }

  function findCloneRoot(el) {
    if (!el) return el;
    return el.closest('.elementor-location-header') ||
      el.closest('[data-elementor-type="header"]') ||
      el.closest('.elementor[data-elementor-id]') ||
      el;
  }

  function findCloneTarget(instance) {
    if (!instance.cloneRoot) return null;
    if (instance.cloneRoot === instance.sourceRoot) return instance.cloneRoot;

    var dataId = instance.el.getAttribute('data-id');
    if (dataId) {
      var byDataId = instance.cloneRoot.querySelector('[data-id="' + dataId + '"]');
      if (byDataId) return byDataId;
    }
    return instance.cloneRoot;
  }




function captureLayoutVars(instance) {
    var target = instance.cloneTarget || instance.cloneRoot;
    if (!target) return;
    var computed = window.getComputedStyle(target);
    target.style.setProperty('--bb-pad-top', computed.paddingTop || '0px');
    target.style.setProperty('--bb-pad-bottom', computed.paddingBottom || '0px');
    var minHeight = parseFloat(computed.minHeight);
    target.style.setProperty('--bb-min-height', isNaN(minHeight) ? '0px' : (minHeight + 'px'));

    var inner = target.querySelector(':scope > .e-con-inner, :scope > .elementor-container');
    if (inner) {
      var innerComputed = window.getComputedStyle(inner);
      target.style.setProperty('--bb-inner-pad-top', innerComputed.paddingTop || '0px');
      target.style.setProperty('--bb-inner-pad-bottom', innerComputed.paddingBottom || '0px');
      var innerMinHeight = parseFloat(innerComputed.minHeight);
      target.style.setProperty('--bb-inner-min-height', isNaN(innerMinHeight) ? '0px' : (innerMinHeight + 'px'));
    }
  }


function forceStickyBackground(instance) {
  if (!instance || !instance.bgEnabled || !instance.bgColor || !instance.cloneRoot) return;

  var target = instance.cloneTarget || instance.cloneRoot;
  var roots = [instance.cloneRoot, target];

  if (target) {
    var inner = target.querySelector(':scope > .e-con-inner, :scope > .elementor-container');
    if (inner) roots.push(inner);
  }

  roots.forEach(function(el) {
    if (!el) return;
    el.style.setProperty('--bb-sticky-bg', instance.bgColor);
  });

  if (target) {
    target.classList.add('bb-has-sticky-bg');
  }
}

  function applyStickyVars(instance) {
    var target = instance.cloneTarget || instance.cloneRoot;
    if (!target) return;

    if (instance.bgEnabled && instance.bgColor) {
      target.style.setProperty('--bb-sticky-bg', instance.bgColor);
      if (instance.cloneRoot) {
        instance.cloneRoot.style.setProperty('--bb-sticky-bg', instance.bgColor);
      }
      var inner = target.querySelector(':scope > .e-con-inner, :scope > .elementor-container');
      if (inner) {
        inner.style.setProperty('--bb-sticky-bg', instance.bgColor);
      }
    }
  }

  function updateShrink(instance, currentScroll) {
    var target = instance.cloneTarget || instance.cloneRoot || instance.el;
    if (!target) return;

    if (!instance.shrink || !instance.isSticky) {
      if (instance.isShrunk) {
        target.classList.remove('bb-is-shrunk');
        instance.isShrunk = false;
      }
      return;
    }

    var shouldShrink = currentScroll >= (instance.triggerScroll + instance.shrinkAfter);
    if (shouldShrink && !instance.isShrunk) {
      target.classList.add('bb-is-shrunk');
      instance.isShrunk = true;
    } else if (!shouldShrink && instance.isShrunk) {
      target.classList.remove('bb-is-shrunk');
      instance.isShrunk = false;
    }
  }

  function ensureClone(instance) {
    if (instance.cloneRoot || !instance.sourceRoot) return;

    var cloneRoot = instance.sourceRoot.cloneNode(true);
    cloneRoot.classList.add('bb-sticky-shell');
    cloneRoot.setAttribute('aria-hidden', 'true');
    cloneRoot.style.setProperty('--bb-sticky-z-index', String(instance.zindex));
    cloneRoot.style.setProperty('--bb-sticky-scale', String(instance.shrinkScale));
    document.body.appendChild(cloneRoot);

    instance.cloneRoot = cloneRoot;
    instance.cloneTarget = findCloneTarget(instance);
    if (instance.cloneTarget) {
      instance.cloneTarget.classList.add('bb-sticky-clone-target');
    }

    captureLayoutVars(instance);
    applyStickyVars(instance);
    hideClone(instance, true);
  }

  function syncCloneMetrics(instance) {
    if (!instance || !instance.cloneRoot) return;
    instance.cloneRoot.style.top = getAdminOffset() + 'px';
    instance.cloneRoot.style.left = '0';
    instance.cloneRoot.style.right = '0';
    instance.cloneRoot.style.width = '100%';
    instance.cloneRoot.style.zIndex = String(instance.zindex);
  }

  function showClone(instance) {
    ensureClone(instance);
    syncCloneMetrics(instance);
    applyStickyVars(instance);
    addStateClasses(instance.cloneTarget || instance.cloneRoot, instance);
    forceStickyBackground(instance);
    instance.cloneRoot.classList.add('bb-clone-visible');
    instance.cloneRoot.classList.remove('bb-clone-hiding');
    instance.cloneRoot.style.pointerEvents = 'auto';

    window.requestAnimationFrame(function () {
      applyStickyVars(instance);
      forceStickyBackground(instance);
    });

    window.setTimeout(function () {
      applyStickyVars(instance);
      forceStickyBackground(instance);
    }, 60);
  }

  function hideClone(instance, immediate) {
    if (!instance || !instance.cloneRoot) return;
    if (immediate) {
      removeStateClasses(instance.cloneTarget || instance.cloneRoot);
      instance.cloneRoot.classList.remove('bb-clone-visible', 'bb-clone-hiding');
    } else {
      instance.cloneRoot.classList.remove('bb-clone-visible');
      instance.cloneRoot.classList.add('bb-clone-hiding');
      window.setTimeout(function () {
        if (instance && instance.cloneRoot && !instance.isSticky) {
          removeStateClasses(instance.cloneTarget || instance.cloneRoot);
          instance.cloneRoot.classList.remove('bb-clone-hiding');
        }
      }, 360);
    }
    instance.cloneRoot.style.pointerEvents = 'none';
  }

  function computeTriggerScroll(instance) {
    if (typeof instance.originalScrollTop !== 'number') {
      instance.originalScrollTop = instance.el.getBoundingClientRect().top + getScrollPos();
    }
    return instance.originalScrollTop + instance.offset;
  }

  function isThemeHeaderSticky(el) {
    return !!el.closest('.elementor-location-header, [data-elementor-type="header"]');
  }

  function cleanupOrphanStickyShells() {
    var shells = document.querySelectorAll('.bb-sticky-shell');
    for (var i = 0; i < shells.length; i++) {
      var shell = shells[i];
      var owned = false;
      for (var j = 0; j < instances.length; j++) {
        if (instances[j].cloneRoot === shell) {
          owned = true;
          break;
        }
      }
      if (!owned) shell.remove();
    }
  }

  function shouldInitSticky(el) {
    if (!el || el.closest('.bb-sticky-shell')) return false;
    if (isEditorMode()) return true;
    if (instances.length >= 1) return false;

    var themeStickies = document.querySelectorAll(
      '.elementor-location-header [data-bb-sticky="yes"], [data-elementor-type="header"] [data-bb-sticky="yes"]'
    );
    if (themeStickies.length && !isThemeHeaderSticky(el)) return false;

    return true;
  }

  function createInstance(el) {
    if (!el || el.dataset.bbStickyInit === 'yes') return null;
    if (!shouldInitSticky(el)) return null;
    el.dataset.bbStickyInit = 'yes';

    var shrinkScalePercent = Math.max(10, Math.min(100, parseInt(el.dataset.bbStickyShrinkScale || '95', 10) || 95));
    var instance = {
      el: el,
      sourceRoot: findCloneRoot(el),
      cloneRoot: null,
      cloneTarget: null,
      offset: parseInt(el.dataset.bbStickyOffset || '0', 10) || 0,
      zindex: parseInt(el.dataset.bbStickyZindex || '999', 10) || 999,
      scrollBehavior: el.dataset.bbStickyScrollBehavior || 'default',
      bgEnabled: el.dataset.bbStickyBg === 'yes',
      bgColor: el.dataset.bbStickyBgColor || '',
      contentScheme: 'default',
      shrink: false,
      shrinkMode: 'compact',
      shrinkAfter: 120,
      shrinkScale: shrinkScalePercent / 100,
      compactRatio: Math.max(0.72, Math.min(0.98, (shrinkScalePercent / 100))),
      shadow: el.dataset.bbStickyShadow === 'yes',
      isSticky: false,
      isShrunk: false,
      originalScrollTop: null,
      triggerScroll: 0
    };

    ensureClone(instance);
    instance.triggerScroll = computeTriggerScroll(instance);
    instances.push(instance);
    return instance;
  }

  function shouldStick(instance, currentScroll) {
    var pastOffset = currentScroll >= instance.triggerScroll;
    if (!pastOffset) return false;
    if (instance.scrollBehavior === 'down_show_up_hide') {
      return currentScroll > lastScroll;
    }
    return true;
  }

  function updateInstance(instance) {
    if (!instance || !instance.el || !document.body.contains(instance.el)) return;
    syncCloneMetrics(instance);

    if (!isAllowedForDevice(instance.el)) {
      instance.isSticky = false;
      instance.isShrunk = false;
      hideClone(instance, true);
      return;
    }

    instance.triggerScroll = computeTriggerScroll(instance);
    var currentScroll = getScrollPos();
    var nextSticky = shouldStick(instance, currentScroll);

    if (nextSticky) {
      if (!instance.isSticky) {
        instance.isSticky = true;
        showClone(instance);
      }
    } else if (instance.isSticky) {
      instance.isSticky = false;
      instance.isShrunk = false;
      hideClone(instance, false);
    }

    updateShrink(instance, currentScroll);
  }

  function updateAll() {
    var currentScroll = getScrollPos();
    for (var i = 0; i < instances.length; i++) {
      updateInstance(instances[i]);
    }
    lastScroll = currentScroll;
  }

  function resetPositions() {
    for (var i = 0; i < instances.length; i++) {
      instances[i].originalScrollTop = null;
      captureLayoutVars(instances[i]);
      applyStickyVars(instances[i]);
      forceStickyBackground(instances[i]);
    }
  }

  function init(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var headers = Array.prototype.slice.call(root.querySelectorAll('[data-bb-sticky="yes"]'));
    headers.sort(function (a, b) {
      var aTheme = isThemeHeaderSticky(a) ? 0 : 1;
      var bTheme = isThemeHeaderSticky(b) ? 0 : 1;
      return aTheme - bTheme;
    });
    for (var i = 0; i < headers.length; i++) {
      createInstance(headers[i]);
    }
    cleanupOrphanStickyShells();
    updateAll();
  }

  function bind() {
    if (booted) return;
    booted = true;

    var ticking = false;
    function requestTick() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        updateAll();
      });
    }

    window.addEventListener('scroll', requestTick, { passive: true });
    window.addEventListener('resize', function () { resetPositions(); requestTick(); });
    window.addEventListener('orientationchange', function () { resetPositions(); requestTick(); });
    window.addEventListener('load', function () { resetPositions(); requestTick(); });

    if (window.ScrollTrigger && typeof window.ScrollTrigger.addEventListener === 'function') {
      window.ScrollTrigger.addEventListener('refresh', function () { resetPositions(); requestTick(); });
      window.ScrollTrigger.addEventListener('update', requestTick);
    }

    setTimeout(function () { resetPositions(); requestTick(); }, 150);
    setTimeout(function () { resetPositions(); requestTick(); }, 800);
  }

  function boot(scope) {
    if (isEditorMode()) return;
    bind();
    init(scope);
  }

  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.jQuery(window).on('elementor/frontend/init', function () {
      boot(document);
      window.elementorFrontend.hooks.addAction('frontend/element_ready/container', function ($scope) {
        boot($scope[0]);
      });
      window.elementorFrontend.hooks.addAction('frontend/element_ready/section', function ($scope) {
        boot($scope[0]);
      });
    });
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      boot(document);
    });
  }
})();
