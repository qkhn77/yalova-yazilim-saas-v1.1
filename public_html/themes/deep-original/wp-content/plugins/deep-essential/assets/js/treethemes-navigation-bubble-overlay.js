/**
 * Treethemes Navigation — Bubble overlay.
 */
(function ($) {
	'use strict';

	function hasGsap() {
		return (
			typeof window.gsap !== 'undefined' &&
			typeof window.gsap.version === 'string'
		);
	}

	var THEME_VARS = [
		'--tt-nav6-overlay-nav-color',
		'--tt-nav6-overlay-content-color',
		'--tt-nav6-trigger-before',
		'--tt-nav6-trigger-after',
		'--tt-nav6-icon-color',
		'--tt-nav6-menu-color',
		'--tt-nav6-menu-hover-color',
		'--tt-nav6-menu-align',
		'--tt-nav6-submenu-duration',
		'--tt-nav6-submenu-item-gap',
		'--tt-nav6-submenu-link-padding',
		'--tt-nav6-close-icon-color',
		'--tt-nav6-chevron-color',
		'--tt-nav6-chevron-color-open',
		'--tt-nav6-chevron-size',
		'--tt-nav6-chevron-gap',
		'--tt-bubble-menu-bg',
		'--tt-bubble-tiles-opacity',
		'--tt-bubble-tiles-overlay-color',
		'--tt-bubble-tiles-rotate',
		'--tt-bubble-runner-1',
		'--tt-bubble-runner-2',
		'--tt-bubble-runner-3',
	];

	function isElementorEditMode() {
		if (document.body && document.body.classList.contains('elementor-editor-active')) {
			return true;
		}
		if (
			window.elementorFrontend &&
			typeof window.elementorFrontend.isEditMode === 'function'
		) {
			try {
				return !!window.elementorFrontend.isEditMode();
			} catch (e) {
				return false;
			}
		}
		return false;
	}

	function getWidgetId($root) {
		return (
			$root.closest('.elementor-element').attr('data-id') ||
			$root.attr('id') ||
			'tt-bubble'
		);
	}

	function debounce(fn, wait) {
		var timer;
		return function () {
			var ctx = this;
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function () {
				fn.apply(ctx, args);
			}, wait);
		};
	}

	function applyThemeVars($root, $portal) {
		if (!$root.length || !$portal.length) {
			return;
		}
		var computed = window.getComputedStyle($root.get(0));
		var theme = {};
		THEME_VARS.forEach(function (name) {
			var val = computed.getPropertyValue(name);
			if (val) {
				theme[name] = val.trim();
			}
		});
		$portal.css(theme);
	}

	function copyElementorWrapperClasses($root, $target) {
		var $widgetEl = $root.closest('.elementor-element');
		if (!$widgetEl.length) {
			return;
		}
		var classes = ($widgetEl.attr('class') || '')
			.replace(/\belementor-invisible\b/g, '')
			.replace(/\s+/g, ' ')
			.trim();
		if (classes) {
			$target.addClass(classes);
		}
	}

	function findPortaledBubble(widgetId) {
		return $('body')
			.children('.tt-bubble-portal-host[data-tt-bubble-widget="' + widgetId + '"]')
			.find('.tt-bubble-portal')
			.first();
	}

	function removeBodyPortal(widgetId) {
		$('body')
			.children('.tt-bubble-portal-host[data-tt-bubble-widget="' + widgetId + '"]')
			.remove();
	}

	/** Put portaled markup back in the widget so Elementor re-init can find it. */
	function restorePortalToRoot($root, widgetId) {
		var $host = $('body').children(
			'.tt-bubble-portal-host[data-tt-bubble-widget="' + widgetId + '"]'
		);
		if (!$host.length) {
			return;
		}
		var $portal = $host.find('.tt-bubble-portal').first();
		if ($portal.length) {
			$portal.removeData('ttBubblePortaled');
			$root.append($portal);
		}
		$host.remove();
	}

	function portalBubble($root) {
		var widgetId = getWidgetId($root);
		var $portal = $root.find('.tt-bubble-portal').first();

		if (!$portal.length) {
			$portal = findPortaledBubble(widgetId);
		}

		if (!$portal.length) {
			return { widgetId: widgetId, $portal: $() };
		}

		var slideActive = $root.hasClass('treethemes-menu-slide-active');
		$portal.toggleClass('treethemes-menu-slide-active', slideActive);
		$portal.find('.tt-bubble-menu-panel').toggleClass('treethemes-menu-slide-active', slideActive);

		$portal.attr('data-tt-bubble-widget', widgetId);

		if (isElementorEditMode()) {
			applyThemeVars($root, $portal);
			setupEditorThemeSync($root, $portal);
			primeTileMarquee($portal);
			return { widgetId: widgetId, $portal: $portal };
		}

		removeBodyPortal(widgetId);

		if (!$portal.data('ttBubblePortaled')) {
			var $host = $('<div class="tt-bubble-portal-host"></div>');
			$host.attr('data-tt-bubble-widget', widgetId);
			copyElementorWrapperClasses($root, $host);

			$portal.detach().appendTo($host);
			$host.appendTo(document.body);
			$portal.data('ttBubblePortaled', true);
			applyThemeVars($root, $portal);
			primeTileMarquee($portal);
		} else {
			applyThemeVars($root, $portal);
		}

		return { widgetId: widgetId, $portal: $portal };
	}

	function teardownEditorThemeSync($root) {
		var observer = $root.data('ttBubbleThemeObserver');
		if (observer) {
			observer.disconnect();
			$root.removeData('ttBubbleThemeObserver');
		}
	}

	function setupEditorThemeSync($root, $portal) {
		teardownEditorThemeSync($root);
		applyThemeVars($root, $portal);

		if (!window.MutationObserver) {
			return;
		}

		var sync = debounce(function () {
			var $livePortal = $root.find('.tt-bubble-portal').first();
			if ($livePortal.length) {
				applyThemeVars($root, $livePortal);
			}
		}, 80);

		var observer = new MutationObserver(sync);
		observer.observe($root[0], {
			attributes: true,
			attributeFilter: ['style', 'class'],
		});

		var $widgetEl = $root.closest('.elementor-element');
		if ($widgetEl.length) {
			observer.observe($widgetEl[0], {
				attributes: true,
				attributeFilter: ['style', 'class'],
			});
		}

		$root.data('ttBubbleThemeObserver', observer);
	}

	/** Offset marquee so rows are desynced and the loop does not jump on first paint. */
	function primeTileMarquee($portal) {
		$portal.find('.tt-bubble-tiles__track').each(function (index) {
			var track = this;
			var styles = window.getComputedStyle(track);
			var duration = parseFloat(styles.animationDuration);
			if (!duration || isNaN(duration)) {
				duration = 10 + index * 6;
			}
			var elapsed = (performance.now() / 1000 + index * (duration / 3)) % duration;
			track.style.animationDelay = '-' + elapsed + 's';
		});
	}

	function getLayers(widgetId, $root) {
		var $portal = findPortaledBubble(widgetId);
		if (!$portal.length && $root) {
			$portal = $root.find('.tt-bubble-portal').first();
		}
		return {
			$root: $root,
			$portal: $portal,
			$wrap: $portal.find('.tt-bubble-menu-wrap'),
			$path: $portal.find('.tt-bubble-overlay__path'),
			$nav: $portal.find('.cd-primary-nav').first(),
			$trigger: $root.find('.cd-nav-trigger'),
			$close: $portal.find('.tt-bubble-menu-close'),
		};
	}

	function setHeaderHidden($root, hidden) {
		if ($root.attr('data-nav6-hide-header') !== 'yes') {
			return;
		}
		var $header = $root
			.find('.cd-nav-trigger')
			.closest('header, .elementor-location-header, .elementor-sticky, .e-con.e-parent')
			.first();
		if ($header.length) {
			$header.toggleClass('treethemes-nav6-hideitall', hidden);
		}
	}

	function getTopLevelItems($nav) {
		var $wrapper = $nav.children('.menu_items_wrapper');
		if ($wrapper.length) {
			return $wrapper.children('li');
		}
		return $nav.children('li');
	}

	function ensureMenuWrapper($nav) {
		if ($nav.children('.menu_items_wrapper').length) {
			return;
		}
		var $top = $nav.children('li');
		if ($top.length) {
			$top.wrapAll('<div class="menu_items_wrapper"></div>');
		}
	}

	function getItemStagger($root) {
		var stagger = parseFloat($root.attr('data-nav6-stagger'));
		return isNaN(stagger) ? 0.05 : stagger;
	}

	function setPortalVisible($portal, visible) {
		$portal.toggleClass('is-portal-visible', visible).attr('aria-hidden', visible ? 'false' : 'true');
	}

	function setMenuOpen($root, $wrap, isOpen) {
		$root.toggleClass('is-open frame--menu-open', isOpen);
		$wrap.toggleClass('menu-wrap--open is-open', isOpen);
		if (!isElementorEditMode()) {
			$('body').toggleClass('tt-bubble-menu-open', isOpen);
		}
		setHeaderHidden($root, isOpen);
	}

	function setTriggerExpanded($trigger, isOpen) {
		if (!$trigger.length) {
			return;
		}
		$trigger.attr('aria-expanded', isOpen ? 'true' : 'false');
	}

	function prepareSubmenus($root, $nav) {
		ensureMenuWrapper($nav);

		$nav.find('ul.sub-menu, .treethemes-mega-menu-panel').each(function () {
			$(this).removeData('ttNav6MaxHeight');
		});

		if ($root.attr('data-nav6-hide-submenu-indicator') === 'yes') {
			$nav.find('.treethemes-submenu-indicator').addClass('tt-nav6-hidden-indicator');
		}

		if ($root.attr('data-nav6-submenu-indicator') === 'none') {
			$nav.find('.tt-nav6-chevron').remove();
			return;
		}

		var chevronHtml = $root.find('template.tt-nav6-chevron-template').html() || '';
		if (!chevronHtml) {
			return;
		}

		$nav.find('.menu-item-has-children > a, .treethemes-mega-menu > a').each(function () {
			var $link = $(this);
			if (!$link.children('.tt-nav6-chevron').length) {
				$link.append(chevronHtml);
			}
		});
	}

	function bindSubmenuAccordion($root, $nav) {
		var singleOpen = $root.attr('data-nav6-submenu-single') !== 'no';
		var selector =
			'.menu_items_wrapper > li.menu-item-has-children > a, .menu_items_wrapper > li.treethemes-mega-menu > a, > li.menu-item-has-children > a, > li.treethemes-mega-menu > a';

		$nav.off('click.ttBubbleSub', selector);
		$nav.on('click.ttBubbleSub', selector, function (e) {
			var $li = $(this).parent('li');
			var $sub = $li.children('ul.sub-menu').first();
			var $mega = $li.children('.treethemes-mega-menu-panel').first();
			var $panel = $sub.length ? $sub : $mega;

			if (!$panel.length) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();

			var maxH = $panel[0].scrollHeight;
			var isOpen = (parseFloat($panel.css('max-height')) || 0) > 1;

			if (singleOpen) {
				$nav.find('ul.sub-menu, .treethemes-mega-menu-panel').not($panel).css('max-height', 0);
				$nav.find('.tt-nav6-submenu-open').removeClass('tt-nav6-submenu-open');
			}

			if (isOpen) {
				$panel.css('max-height', 0);
				$li.removeClass('tt-nav6-submenu-open');
			} else {
				$panel.css('max-height', maxH + 'px');
				$li.addClass('tt-nav6-submenu-open');
			}
		});
	}

	/** Codrops Theodore open */
	function openMenu(ctx) {
		var $root = ctx.$root;
		var $wrap = ctx.$wrap;
		var $path = ctx.$path;
		var $items = getTopLevelItems(ctx.$nav);
		var duration = parseFloat($root.attr('data-bubble-duration')) || 0.8;
		var stagger = getItemStagger($root);

		setPortalVisible(ctx.$portal, true);
		setMenuOpen($root, $wrap, false);
		setTriggerExpanded(ctx.$trigger, false);
		$root.addClass('is-animating');

		if (!hasGsap() || !$path.length) {
			setMenuOpen($root, $wrap, true);
			setTriggerExpanded(ctx.$trigger, true);
			prepareSubmenus($root, ctx.$nav);
			$root.removeClass('is-animating');
			return;
		}

		gsap
			.timeline({
				onComplete: function () {
					$root.removeClass('is-animating');
				},
			})
			.set($path[0], { attr: { d: 'M 0 100 V 100 Q 50 100 100 100 V 100 z' } })
			.to($path[0], {
				duration: duration,
				ease: 'power4.in',
				attr: { d: 'M 0 100 V 50 Q 50 0 100 50 V 100 z' },
			}, 0)
			.to($path[0], {
				duration: 0.3,
				ease: 'power2',
				attr: { d: 'M 0 100 V 0 Q 50 0 100 0 V 100 z' },
				onComplete: function () {
					setMenuOpen($root, $wrap, true);
					setTriggerExpanded(ctx.$trigger, true);
					prepareSubmenus($root, ctx.$nav);
				},
			})
			.set($items.toArray(), { opacity: 0 })
			.set($path[0], { attr: { d: 'M 0 0 V 100 Q 50 100 100 100 V 0 z' } })
			.to($path[0], {
				duration: 0.3,
				ease: 'power2.in',
				attr: { d: 'M 0 0 V 50 Q 50 0 100 50 V 0 z' },
			})
			.to($path[0], {
				duration: duration,
				ease: 'power4',
				attr: { d: 'M 0 0 V 0 Q 50 0 100 0 V 0 z' },
			})
			.to(
				$items.toArray(),
				{
					duration: 1.1,
					ease: 'power4',
					startAt: { y: 150 },
					y: 0,
					opacity: 1,
					stagger: stagger,
				},
				'>-1.1'
			);
	}

	/** Codrops Theodore close */
	function closeMenu(ctx) {
		var $root = ctx.$root;
		var $wrap = ctx.$wrap;
		var $path = ctx.$path;
		var $items = getTopLevelItems(ctx.$nav);
		var duration = parseFloat($root.attr('data-bubble-duration')) || 0.8;
		var stagger = getItemStagger($root);

		$root.addClass('is-animating');

		if (!hasGsap() || !$path.length) {
			setMenuOpen($root, $wrap, false);
			setTriggerExpanded(ctx.$trigger, false);
			setPortalVisible(ctx.$portal, false);
			$root.removeClass('is-animating');
			return;
		}

		gsap
			.timeline({
				onComplete: function () {
					setMenuOpen($root, $wrap, false);
					setTriggerExpanded(ctx.$trigger, false);
					setPortalVisible(ctx.$portal, false);
					$root.removeClass('is-animating');
					gsap.set($path[0], {
						attr: { d: 'M 0 100 V 100 Q 50 100 100 100 V 100 z' },
					});
				},
			})
			.set($path[0], { attr: { d: 'M 0 0 V 0 Q 50 0 100 0 V 0 z' } })
			.to($path[0], {
				duration: duration,
				ease: 'power4.in',
				attr: { d: 'M 0 0 V 50 Q 50 100 100 50 V 0 z' },
			}, 0)
			.to($path[0], {
				duration: 0.3,
				ease: 'power2',
				attr: { d: 'M 0 0 V 100 Q 50 100 100 100 V 0 z' },
				onComplete: function () {
					setMenuOpen($root, $wrap, false);
				},
			})
			.set($path[0], { attr: { d: 'M 0 100 V 0 Q 50 0 100 0 V 100 z' } })
			.to($path[0], {
				duration: 0.3,
				ease: 'power2.in',
				attr: { d: 'M 0 100 V 50 Q 50 100 100 50 V 100 z' },
			})
			.to($path[0], {
				duration: duration,
				ease: 'power4',
				attr: { d: 'M 0 100 V 100 Q 50 100 100 100 V 100 z' },
			})
			.to(
				$items.toArray(),
				{
					duration: 0.8,
					ease: 'power2.in',
					y: 100,
					opacity: 0,
					stagger: -stagger,
				},
				0
			);
	}

	function destroyBubble($root) {
		var widgetId = getWidgetId($root);
		$(document).off('keydown.ttBubble' + widgetId);
		$root.find('.cd-nav-trigger').off('.ttBubble');
		teardownEditorThemeSync($root);

		var $portal = findPortaledBubble(widgetId);
		if (!$portal.length) {
			$portal = $root.find('.tt-bubble-portal').first();
		}
		$portal.off('.ttBubble').removeData('ttBubblePortaled');

		if (!isElementorEditMode()) {
			restorePortalToRoot($root, widgetId);
		}

		$root
			.removeData('ttBubbleInit')
			.removeClass('is-open is-animating frame--menu-open');
		$('body').removeClass('tt-bubble-menu-open');
	}

	function initBubble($root) {
		if ($root.data('ttBubbleInit')) {
			destroyBubble($root);
		}
		$root.data('ttBubbleInit', true);

		var portal = portalBubble($root);
		var ctx = getLayers(portal.widgetId, $root);

		if (!ctx.$portal.length || !ctx.$nav.length) {
			return;
		}

		ensureMenuWrapper(ctx.$nav);
		prepareSubmenus($root, ctx.$nav);
		bindSubmenuAccordion($root, ctx.$nav);

		ctx.$trigger.on('click.ttBubble', function (e) {
			e.preventDefault();
			if ($root.hasClass('is-animating') || $root.hasClass('is-open')) {
				return;
			}
			openMenu(ctx);
		});

		ctx.$close.on('click.ttBubble', function (e) {
			e.preventDefault();
			if ($root.hasClass('is-open') && !$root.hasClass('is-animating')) {
				closeMenu(ctx);
			}
		});

		$(document).on('keydown.ttBubble' + portal.widgetId, function (e) {
			if (e.key === 'Escape' && $root.hasClass('is-open') && !$root.hasClass('is-animating')) {
				closeMenu(ctx);
			}
		});
	}

	function boot(scope) {
		var $scope = scope instanceof $ ? scope : $(scope);
		$scope.find('.treethemes-nav-menu-bubble').each(function () {
			initBubble($(this));
		});
	}

	var elementorHooksRegistered = false;

	function registerElementorHooks() {
		if (elementorHooksRegistered) {
			return;
		}
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}
		elementorHooksRegistered = true;

		elementorFrontend.hooks.addAction(
			'frontend/element_ready/treethemes--navigation.default',
			function ($scope) {
				boot($scope);
			}
		);

		elementorFrontend.hooks.addAction(
			'frontend/element:before:destroy',
			function ($scope) {
				$scope.find('.treethemes-nav-menu-bubble').each(function () {
					destroyBubble($(this));
				});
			}
		);
	}

	$(function () {
		// Elementor calls element_ready per widget; avoid destroy/reinit race on first paint.
		if (!window.elementorFrontend) {
			boot(document);
		}
	});

	$(window).on('elementor/frontend/init', function () {
		registerElementorHooks();
		boot(document);
	});
})(jQuery);
