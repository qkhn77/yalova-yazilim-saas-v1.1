(function() {
  var targetObservers = new WeakMap();
  var elementorHooksRegistered = false;

  function parseThreshold(raw) {
    if (!raw) return 0.1;
    var match = String(raw).match(/(\d{1,3})\s*%/);
    if (!match) return 0.1;
    var pct = parseInt(match[1], 10);
    if (Number.isNaN(pct)) return 0.1;
    pct = Math.min(100, Math.max(1, pct));
    return 1 - pct / 100;
  }

  function isAnimatedHeadingScope(scope) {
    return !!(scope && scope.querySelector('.animated--heading'));
  }

  function resolveTargets(scope, item) {
    if (!scope || !item) return [];
    if (item.selector === 'widget' || item.elementType === 'widget') {
      var primaryTarget =
        scope.querySelector('.elementor-heading-title') ||
        scope.querySelector('.animated--heading') ||
        scope.querySelector('.wcf--title') ||
        scope.querySelector('.wcf--text');

      return primaryTarget ? [primaryTarget] : [scope];
    }

    try {
      return Array.prototype.slice.call(scope.querySelectorAll(item.selector));
    } catch (e) {
      return [];
    }
  }

  function cleanupTarget(target) {
    if (!target) return;
    var observer = targetObservers.get(target);
    if (observer) {
      observer.disconnect();
      targetObservers.delete(target);
    }

    target.classList.remove('deep-notion-target', 'deep-notion-loop', 'deep-notion-active');
    Array.prototype.slice.call(target.classList).forEach(function(className) {
      if (className.indexOf('deep-notion-style-') === 0) {
        target.classList.remove(className);
      }
    });

    delete target.dataset.deepNotionApplied;
    target.style.removeProperty('--deep-notion-color');
    target.style.removeProperty('--deep-notion-stroke-width');
    target.style.removeProperty('--deep-notion-duration');
    target.style.removeProperty('--deep-notion-circle-length');
    target.style.removeProperty('--deep-notion-circle-rotation');

    var ring = target.querySelector('.deep-notion-circle-ring');
    if (ring) ring.remove();
  }

  function applyEffect(target, item) {
    if (!target) return;
    cleanupTarget(target);
    target.dataset.deepNotionApplied = 'yes';
    target.classList.add('deep-notion-target');
    var style = item.style || 'underline';
    if (style === 'strike') {
      style = 'mark-decoration';
    }
    target.classList.add('deep-notion-style-' + style);
    if (item.infinityLoop) target.classList.add('deep-notion-loop');
    target.style.setProperty('--deep-notion-color', item.color || '#ff4fd8');
    target.style.setProperty('--deep-notion-stroke-width', (item.strokeWidth || 3) + 'px');
    target.style.setProperty('--deep-notion-duration', (item.animationDuration || 800) + 'ms');

    if ((item.style || 'underline') === 'circle') {
      var circleDuration = item.circleDuration || item.animationDuration || 900;
      target.style.setProperty('--deep-notion-duration', circleDuration + 'ms');
      target.style.setProperty(
        '--deep-notion-circle-rotation',
        (item.circleRotation != null ? item.circleRotation : -9) + 'deg'
      );
      ensureCircleRing(target, item);
    }
  }

  function ensureCircleRing(target, item) {
    if (!target || target.querySelector('.deep-notion-circle-ring')) return;

    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    var ellipse = document.createElementNS(ns, 'ellipse');

    svg.setAttribute('class', 'deep-notion-circle-ring');
    svg.setAttribute('viewBox', '0 0 100 100');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('aria-hidden', 'true');

    ellipse.setAttribute('class', 'deep-notion-circle-path');
    ellipse.setAttribute('cx', '50');
    ellipse.setAttribute('cy', '50');
    ellipse.setAttribute('rx', String(item && item.circleRx ? item.circleRx : 48));
    ellipse.setAttribute('ry', String(item && item.circleRy ? item.circleRy : 33));

    svg.appendChild(ellipse);
    target.appendChild(svg);

    var length = ellipse.getTotalLength();
    var duration = item && item.circleDuration ? parseInt(item.circleDuration, 10) : (item && item.animationDuration ? parseInt(item.animationDuration, 10) : 900);
    if (Number.isNaN(duration) || duration < 100) duration = 800;

    target.style.setProperty('--deep-notion-circle-length', String(length));
    target.style.setProperty('--deep-notion-duration', duration + 'ms');
  }

  function isInViewport(target) {
    var rect = target.getBoundingClientRect();
    return rect.top < window.innerHeight && rect.bottom > 0 && rect.width > 0 && rect.height > 0;
  }

  function activateTarget(target) {
    target.classList.remove('deep-notion-active');
    void target.offsetWidth;
    target.classList.add('deep-notion-active');
  }

  function observeAndActivate(target, item) {
    if (!('IntersectionObserver' in window)) {
      activateTarget(target);
      return;
    }

    var threshold = parseThreshold(item.waypointOffset);
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        activateTarget(target);
        if (!item.infinityLoop) {
          observer.unobserve(target);
        }
      });
    }, { threshold: Math.max(0, Math.min(1, threshold)) });

    targetObservers.set(target, observer);
    observer.observe(target);

    if (isInViewport(target)) {
      activateTarget(target);
    }
  }

  function initScope(scope) {
    if (!scope) return;

    var hasNotion = scope.dataset.deepNotion === 'yes' || scope.dataset.deepEssentialNotion === 'yes';
    if (!hasNotion) return;

    var raw = scope.getAttribute('data-deep-essential-notion-items') || scope.getAttribute('data-deep-notion-items');
    if (!raw) return;

    var items = [];
    try {
      items = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!Array.isArray(items) || !items.length) return;

    items.forEach(function(item) {
      resolveTargets(scope, item).forEach(function(target) {
        applyEffect(target, item);
        observeAndActivate(target, item);
      });
    });
  }

  function runScopeInit(scope) {
    if (!scope) return;
    initScope(scope);

    if (!isAnimatedHeadingScope(scope)) return;

    // GSAP SplitText on Animated Heading runs after element_ready; re-apply once it has finished.
    setTimeout(function() {
      initScope(scope);
    }, 250);
    setTimeout(function() {
      initScope(scope);
    }, 700);
  }

  function boot() {
    document.querySelectorAll('[data-deep-notion="yes"], [data-deep-essential-notion="yes"]').forEach(runScopeInit);
  }

  function registerElementorHooks() {
    if (elementorHooksRegistered) return;
    if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
      return;
    }

    elementorHooksRegistered = true;
    var hooks = window.elementorFrontend.hooks;
    var widgets = [
      'heading',
      'wcf--text',
      'wcf--title',
      'wcf--animated-heading'
    ];

    widgets.forEach(function(widget) {
      hooks.addAction('frontend/element_ready/' + widget + '.default', function($scope) {
        if (!$scope || !$scope[0]) return;
        runScopeInit($scope[0]);
      });
    });
  }

  function setup() {
    registerElementorHooks();
    boot();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup);
  } else {
    setup();
  }

  document.addEventListener('elementor/frontend/init', setup);
})();
