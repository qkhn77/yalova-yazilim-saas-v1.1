/**
 * Treethemes Navigation — Overlay Style 6 (desktop + mobile, Deep header style 6).
 */
(function ($) {
	'use strict';

	function hasGsap() {
		return (
			typeof window.gsap !== 'undefined' &&
			typeof window.gsap.version === 'string'
		);
	}

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

	function getHeaderShell($trigger) {
		return $trigger.closest(
			'header, .elementor-location-header, .elementor-sticky, .e-con.e-parent'
		).first();
	}

	var NAV6_CSS_VARS = [
		'--tt-nav6-overlay-nav-color',
		'--tt-nav6-overlay-content-color',
		'--tt-nav6-trigger-before',
		'--tt-nav6-trigger-after',
		'--tt-nav6-icon-color',
		'--tt-nav6-menu-color',
		'--tt-nav6-menu-hover-color',
		'--tt-nav6-menu-item-gap',
		'--tt-nav6-menu-link-padding',
		'--tt-nav6-menu-align',
		'--tt-nav6-submenu-duration',
		'--tt-nav6-submenu-item-gap',
		'--tt-nav6-submenu-link-padding',
		'--tt-nav6-close-icon-color',
		'--tt-nav6-close-icon-color-hover',
		'--tt-nav6-close-top',
		'--tt-nav6-close-right',
		'--tt-nav6-close-size',
		'--tt-nav6-close-icon-size',
		'--tt-nav6-close-icon-weight',
		'--tt-nav6-close-bg',
		'--tt-nav6-close-bg-hover',
		'--tt-nav6-close-radius',
		'--tt-nav6-close-border-width',
		'--tt-nav6-close-border-style',
		'--tt-nav6-close-border-color',
		'--tt-nav6-close-border-color-hover',
		'--tt-nav6-chevron-color',
		'--tt-nav6-chevron-color-open',
		'--tt-nav6-chevron-size',
		'--tt-nav6-chevron-gap',
	];

	function applyNav6ThemeVars($root, $targets) {
		var computed = window.getComputedStyle($root.get(0));
		var theme = {};
		NAV6_CSS_VARS.forEach(function (name) {
			var val = computed.getPropertyValue(name);
			if (val) {
				theme[name] = val.trim();
			}
		});
		$targets.css(theme);
	}

	function ensureCloseButtonBg($close) {
		if ($close && $close.length && !$close.find('.tt-nav6-close-bg').length) {
			$close.prepend('<span class="tt-nav6-close-bg" aria-hidden="true"></span>');
		}
	}

	function portalCloseButton($root, widgetId) {
		var $close = $('body > .tt-nav6-menu-close[data-tt-nav6-widget="' + widgetId + '"]');
		if ($close.length) {
			ensureCloseButtonBg($close);
			return $close;
		}

		$close = $root.find('nav.nav-style-6 > .tt-nav6-menu-close').first();
		if (!$close.length) {
			$close = $('body > nav.nav-style-6[data-tt-nav6-widget="' + widgetId + '"] > .tt-nav6-menu-close');
		}

		if ($close.length && !$close.data('ttNav6Portaled')) {
			$close.appendTo(document.body).attr('data-tt-nav6-widget', widgetId);
			$close.data('ttNav6Portaled', true);
		}

		ensureCloseButtonBg($close);
		return $close;
	}

	function portalLayers($root) {
		var widgetId =
			$root.closest('.elementor-element').attr('data-id') ||
			$root.attr('id') ||
			'tt-nav6';

		var $layers = $root.find('.cd-overlay-nav, .cd-overlay-content, nav.nav-style-6');
		$layers.each(function () {
			var $el = $(this);
			if ($el.data('ttNav6Portaled')) {
				return;
			}
			$el.appendTo(document.body).attr('data-tt-nav6-widget', widgetId);
			$el.data('ttNav6Portaled', true);
		});

		var $close = portalCloseButton($root, widgetId);
		if ($close.length) {
			$layers = $layers.add($close);
		}

		if ($root.hasClass('tt-nav6-css-motion')) {
			$layers.addClass('tt-nav6-css-motion');
		}

		applyNav6ThemeVars($root, $layers);

		return widgetId;
	}

	function layerInit($trigger, $overlayNav, $overlayContent) {
		var $bg = $trigger.find('.cd-nav-bg');
		if (!$bg.length) {
			return;
		}

		var top = $bg.position().top + 30;
		var left = $bg.offset().left + 30;
		$overlayNav.add($overlayContent).css({ top: top, left: left });

		var diameter =
			Math.sqrt(Math.pow(window.innerHeight, 2) + Math.pow(window.innerWidth, 2)) * 2;
		var spanProps = {
			height: diameter,
			width: diameter,
			top: -(diameter / 2),
			left: -(diameter / 2),
			scale: 0,
			transformOrigin: '50% 50%',
		};

		if (hasGsap()) {
			gsap.set($overlayNav.find('span')[0], spanProps);
			gsap.set($overlayContent.find('span')[0], spanProps);
		} else {
			var cssSpanProps = {
				height: diameter + 'px',
				width: diameter + 'px',
				top: -(diameter / 2) + 'px',
				left: -(diameter / 2) + 'px',
				transform: 'scale(0)',
				transformOrigin: '50% 50%',
			};
			$overlayNav.find('span').css(cssSpanProps);
			$overlayContent.find('span').css(cssSpanProps);
		}
	}

	function lockBodyScroll(lock) {
		var $body = $('body');
		var $fixedChrome = $(
			'.elementor-location-header, header.elementor-sticky, .elementor-sticky--active'
		);

		if (lock) {
			var barWidth = window.innerWidth - document.documentElement.clientWidth;
			if (barWidth > 0) {
				$body.data('ttNav6ScrollPad', barWidth);
				$body.css('padding-right', barWidth + 'px');
				$fixedChrome.each(function () {
					var $el = $(this);
					if (!$el.data('ttNav6ScrollPad')) {
						$el.data('ttNav6ScrollPad', parseFloat($el.css('padding-right')) || 0);
					}
					$el.css(
						'padding-right',
						($el.data('ttNav6ScrollPad') + barWidth) + 'px'
					);
				});
			}
			return;
		}

		if ($body.data('ttNav6ScrollPad')) {
			$body.css('padding-right', '');
			$body.removeData('ttNav6ScrollPad');
		}
		$fixedChrome.each(function () {
			var $el = $(this);
			if ($el.data('ttNav6ScrollPad') !== undefined) {
				var base = $el.data('ttNav6ScrollPad');
				$el.css('padding-right', base ? base + 'px' : '');
				$el.removeData('ttNav6ScrollPad');
			}
		});
	}

	function setHeaderHidden($root, hidden) {
		if ($root.attr('data-nav6-hide-header') !== 'yes') {
			return;
		}

		var $trigger = $root.find('.cd-nav-trigger');
		var $header = getHeaderShell($trigger);
		if (!$header.length) {
			return;
		}

		$header.toggleClass('treethemes-nav6-hideitall', hidden);
	}

	function getTopLevelItems($navigation) {
		var $wrapped = $navigation.children('.menu_items_wrapper').children('li');
		if ($wrapped.length) {
			return $wrapped;
		}
		return $navigation.children('li');
	}

	var ENTRANCE_CLASSES =
		'tt-nav6-entrance-slide_top tt-nav6-entrance-fade_top tt-nav6-entrance-fade_bottom';

	var ENTRANCE_FROM = {
		slide_top: { opacity: 0, y: -36 },
		fade_top: { opacity: 0, y: -18 },
		fade_bottom: { opacity: 0, y: 40 },
	};

	function resetMenuItems($navigation) {
		var $items = getTopLevelItems($navigation);
		$navigation.removeClass('tt-nav6-items-animating ' + ENTRANCE_CLASSES);
		$items.each(function () {
			this.style.animationDelay = '';
			this.style.animation = '';
			this.style.opacity = '';
			this.style.transform = '';
		});
		if (hasGsap()) {
			gsap.killTweensOf($items.toArray());
			gsap.set($items.toArray(), { clearProps: 'opacity,transform' });
		}
	}

	function animateMenuItems($root, $navigation) {
		var entrance = $root.attr('data-nav6-entrance') || 'fade_bottom';
		if (entrance === 'none') {
			return;
		}

		var stagger = parseFloat($root.attr('data-nav6-stagger'));
		if (isNaN(stagger)) {
			stagger = 0.12;
		}

		var $items = getTopLevelItems($navigation);
		if (!$items.length) {
			return;
		}

		var from = ENTRANCE_FROM[entrance] || ENTRANCE_FROM.fade_bottom;

		$navigation
			.removeClass(ENTRANCE_CLASSES)
			.addClass('tt-nav6-items-animating tt-nav6-entrance-' + entrance);

		if (hasGsap()) {
			gsap.set($items.toArray(), from);
			gsap.to($items.toArray(), {
				opacity: 1,
				y: 0,
				duration: 0.6,
				ease: 'power3.out',
				stagger: stagger,
				delay: 0.15,
			});
			return;
		}

		var baseDelay = 0.15;
		$items.each(function (index) {
			this.style.animationDelay = baseDelay + index * stagger + 's';
		});
	}

	function animateCloseButtonIn($close) {
		if (!$close || !$close.length) {
			return;
		}

		var $bg = $close.find('.tt-nav6-close-bg');
		var $icon = $close.find('.tt-nav6-close-icon');
		if (!$bg.length) {
			return;
		}

		if (hasGsap()) {
			gsap.killTweensOf([$bg[0], $icon[0]].filter(Boolean));
			gsap.set($bg[0], { scale: 0, transformOrigin: '50% 50%' });
			if ($icon.length) {
				gsap.set($icon[0], { opacity: 0 });
			}
			gsap.to($bg[0], {
				scale: 1,
				duration: 0.3,
				ease: 'power3.inOut',
				delay: 0.4,
			});
			if ($icon.length) {
				gsap.to($icon[0], {
					opacity: 1,
					duration: 0.25,
					ease: 'power2.out',
					delay: 0.55,
				});
			}
			return;
		}

		$close.removeClass('tt-nav6-close-anim-in');
		void $close[0].offsetWidth;
		$close.addClass('tt-nav6-close-anim-in');
	}

	function resetCloseButtonAnim($close) {
		if (!$close || !$close.length) {
			return;
		}

		var $bg = $close.find('.tt-nav6-close-bg');
		var $icon = $close.find('.tt-nav6-close-icon');

		if (hasGsap()) {
			gsap.killTweensOf([$bg[0], $icon[0]].filter(Boolean));
			if ($bg.length) {
				gsap.set($bg[0], { scale: 0 });
			}
			if ($icon.length) {
				gsap.set($icon[0], { opacity: 0 });
			}
		}

		$close.removeClass('tt-nav6-close-anim-in');
	}

	function openMenu($root, $trigger, $overlayNav, $overlayContent, $navigation, $navPanel, $close) {
		$trigger
			.addClass('close-nav')
			.attr({
				'aria-expanded': 'true',
				'aria-label': 'Close menu',
			});
		lockBodyScroll(true);
		$('body').addClass('treethemes-nav-style6-panel-open');
		$root.addClass('treethemes-nav-style6-open');
		if ($navPanel && $navPanel.length) {
			$navPanel.addClass('tt-nav6-panel-open');
		}
		setHeaderHidden($root, true);
		syncOverlayThemeVars($root, $overlayNav, $overlayContent);
		if ($close && $close.length) {
			applyNav6ThemeVars($root, $close);
			animateCloseButtonIn($close);
		}

		$trigger.find('.cd-nav-bg, .cd-nav-bg-fake').addClass('active');

		var revealNav = function () {
			$navigation.addClass('fade-in');
			prepareSubmenus($root, $navigation);
			animateMenuItems($root, $navigation);
		};

		if (hasGsap()) {
			gsap.to($overlayNav.find('span')[0], {
				scale: 1,
				duration: 0.5,
				ease: 'power3.in',
				onComplete: revealNav,
			});
		} else {
			$overlayNav.find('span').css({ transform: 'scale(1)' });
			revealNav();
		}
	}

	function finishClosePanel($root, $trigger, $navPanel, $close) {
		resetCloseButtonAnim($close);
		$trigger
			.removeClass('close-nav')
			.attr({
				'aria-expanded': 'false',
				'aria-label': 'Menu',
			});
		if ($navPanel && $navPanel.length) {
			$navPanel.removeClass('tt-nav6-panel-open');
		}
		lockBodyScroll(false);
		$('body').removeClass('treethemes-nav-style6-panel-open');
		$root.removeClass('treethemes-nav-style6-open');
		setHeaderHidden($root, false);
	}

	function resetOverlaySpans($overlayNav, $overlayContent) {
		var $navSpan = $overlayNav.find('span');
		var $contentSpan = $overlayContent.find('span');

		if (hasGsap()) {
			gsap.killTweensOf([$navSpan[0], $contentSpan[0]].filter(Boolean));
			gsap.set($navSpan[0], { scale: 0 });
			gsap.set($contentSpan[0], { scale: 0, opacity: 1 });
			return;
		}

		$navSpan.css({ transform: 'scale(0)' });
		$contentSpan.css({ transform: 'scale(0)', opacity: 1 });
	}

	function syncOverlayThemeVars($root, $overlayNav, $overlayContent) {
		if (!$root || !$root.length) {
			return;
		}
		var $targets = $();
		if ($overlayNav && $overlayNav.length) {
			$targets = $targets.add($overlayNav);
		}
		if ($overlayContent && $overlayContent.length) {
			$targets = $targets.add($overlayContent);
		}
		if ($targets.length) {
			applyNav6ThemeVars($root, $targets);
		}
	}

	function closeMenu($root, $trigger, $overlayNav, $overlayContent, $navigation, $navPanel, $close) {
		resetMenuItems($navigation);
		collapseAllSubmenus($navigation);
		$navigation.removeClass('fade-in');
		$trigger.find('.cd-nav-bg, .cd-nav-bg-fake').removeClass('active');

		var $navSpan = $overlayNav.find('span');
		var $contentSpan = $overlayContent.find('span');

		// Block the ghost click that follows pointerup/touchend on the same spot.
		if ($root[0]) {
			$root[0].ttNav6SuppressToggleUntil = Date.now() + 450;
		}

		syncOverlayThemeVars($root, $overlayNav, $overlayContent);

		// Always reset open state immediately so close works even if overlay tween stalls.
		finishClosePanel($root, $trigger, $navPanel, $close);

		if (hasGsap() && $navSpan.length && $contentSpan.length) {
			gsap.killTweensOf([$navSpan[0], $contentSpan[0]]);
			// Content overlay (accent) covers the screen, then shrinks away on close.
			gsap.fromTo(
				$contentSpan[0],
				{ scale: 1, opacity: 1 },
				{ scale: 0, opacity: 1, duration: 0.45, ease: 'power3.inOut' }
			);
			gsap.to($navSpan[0], {
				scale: 0,
				duration: 0.45,
				ease: 'power3.inOut',
			});
			return;
		}

		if ($contentSpan.length) {
			$contentSpan.css({ transform: 'scale(1)', opacity: 1 });
			void $contentSpan[0].offsetWidth;
			$contentSpan.css({ transform: 'scale(0)' });
		}
		if ($navSpan.length) {
			$navSpan.css({ transform: 'scale(0)' });
		}
	}

	function ensureMenuWrapper($nav) {
		if (!$nav.children('.menu_items_wrapper').length) {
			var $topItems = $nav.children('li');
			if ($topItems.length) {
				$topItems.wrapAll('<div class="menu_items_wrapper"></div>');
			}
		}
	}

	function getAccordionPanel($li) {
		var $sub = $li.children('ul.sub-menu').first();
		if ($sub.length) {
			return $sub;
		}
		return $li.children('.treethemes-mega-menu-panel').first();
	}

	function measureAccordionPanel($panel) {
		if (!$panel.length) {
			return 0;
		}

		var cached = parseFloat($panel.data('ttNav6MaxHeight'));
		if (cached > 0) {
			return cached;
		}

		var h;
		if ($panel.is('ul')) {
			$panel.css({ maxHeight: 'none', height: 'auto', overflow: 'hidden', display: 'block' });
			h = $panel.outerHeight(true);
		} else {
			$panel.css({ maxHeight: 'none', height: 'auto', overflow: 'hidden', display: 'block' });
			h = $panel.outerHeight(true);
		}

		$panel.data('ttNav6MaxHeight', h);
		$panel.css({ maxHeight: 0, overflow: 'hidden' });
		return h;
	}

	function setChevronState($li, isOpen) {
		$li.toggleClass('tt-nav6-submenu-open', isOpen);
		$li.children('a').find('.tt-nav6-chevron').toggleClass('is-open', isOpen);
	}

	function collapseAccordionPanel($panel, $li) {
		if (!$panel.length) {
			return;
		}
		$panel.css('max-height', 0);
		if ($li) {
			setChevronState($li, false);
		}
	}

	function prepareSubmenus($root, $nav) {
		ensureMenuWrapper($nav);

		$nav.find('ul.sub-menu, .treethemes-mega-menu-panel').each(function () {
			var $panel = $(this);
			$panel.removeData('ttNav6MaxHeight');
			measureAccordionPanel($panel);
		});

		if ($root.attr('data-nav6-hide-submenu-indicator') === 'yes') {
			$nav.find('.treethemes-submenu-indicator').addClass('tt-nav6-hidden-indicator');
		}

		if ($root.attr('data-nav6-submenu-indicator') === 'none') {
			$nav.find('.tt-nav6-chevron').remove();
			return;
		}

		var $tpl = $root.find('template.tt-nav6-chevron-template');
		var chevronHtml = $tpl.length ? $tpl.html() : '';

		if (!chevronHtml) {
			return;
		}

		$nav.find('.menu-item-has-children > a, .treethemes-mega-menu > a').each(function () {
			var $link = $(this);
			if (!$link.find('.tt-nav6-chevron').length) {
				$link.append(chevronHtml);
			}
		});
	}

	function collapseAllSubmenus($nav) {
		$nav.find('ul.sub-menu, .treethemes-mega-menu-panel').each(function () {
			var $panel = $(this);
			var $li = $panel.parent('li');
			collapseAccordionPanel($panel, $li);
		});
	}

	function bindSubmenuAccordion($root, $nav) {
		if (!$nav.length) {
			return;
		}

		var singleOpen = $root.attr('data-nav6-submenu-single') !== 'no';

		$nav.off('click.ttNav6Sub', 'li').on('click.ttNav6Sub', 'li', function (e) {
			var $li = $(this);
			var $panel = getAccordionPanel($li);

			e.preventDefault();
			e.stopPropagation();

			if (!$panel.length) {
				if (isElementorEditMode()) {
					return;
				}
				var href = $li.children('a').first().attr('href');
				if (href && href !== '#') {
					window.location.href = href;
				}
				return;
			}

			$nav
				.find('ul.sub-menu, .treethemes-mega-menu-panel')
				.not($panel)
				.not($panel.parents('ul.sub-menu, .treethemes-mega-menu-panel'))
				.each(function () {
					collapseAccordionPanel($(this), $(this).parent('li'));
				});

			if (singleOpen) {
				$li.siblings('li').each(function () {
					var $sib = $(this);
					collapseAccordionPanel(getAccordionPanel($sib), $sib);
				});
			}

			var maxH = measureAccordionPanel($panel);
			var isOpen = (parseFloat($panel.css('max-height')) || 0) > 1;

			if (isOpen) {
				collapseAccordionPanel($panel, $li);
			} else {
				$panel.css('max-height', maxH + 'px');
				setChevronState($li, true);
			}
		});
	}

	function getLayersForRoot($root, widgetId) {
		var $overlayNav = $('body > .cd-overlay-nav[data-tt-nav6-widget="' + widgetId + '"]');
		var $overlayContent = $('body > .cd-overlay-content[data-tt-nav6-widget="' + widgetId + '"]');
		var $navPanel = $('body > nav.nav-style-6[data-tt-nav6-widget="' + widgetId + '"]');

		if (!$overlayNav.length) {
			$overlayNav = $root.find('.cd-overlay-nav');
			$overlayContent = $root.find('.cd-overlay-content');
			$navPanel = $root.find('nav.nav-style-6');
		}

		var $navigation = $navPanel.find('.cd-primary-nav').first();
		var $close = $('body > .tt-nav6-menu-close[data-tt-nav6-widget="' + widgetId + '"]');
		if (!$close.length) {
			$close = $navPanel.find('.tt-nav6-menu-close').first();
		}

		return {
			$overlayNav: $overlayNav,
			$overlayContent: $overlayContent,
			$navPanel: $navPanel,
			$navigation: $navigation,
			$close: $close,
		};
	}

	function initStyle6(scope) {
		var $scope = scope.jquery ? scope : $(scope);
		var $root = $scope.hasClass('treethemes-nav-menu-style-6')
			? $scope
			: $scope.find('.treethemes-nav-menu-style-6').not('[data-menu-presentation="bubble_overlay"]').first();

		if (!$root.length) {
			return;
		}
		if ($root.data('tt-nav6-init')) {
			return;
		}
		$root.data('tt-nav6-init', true);

		if (!hasGsap()) {
			$root.addClass('tt-nav6-css-motion');
		}

		var widgetId = portalLayers($root);
		var $trigger = $root.find('.cd-nav-trigger');
		var layers = getLayersForRoot($root, widgetId);
		var $overlayNav = layers.$overlayNav;
		var $overlayContent = layers.$overlayContent;
		var $navPanel = layers.$navPanel;
		var $navigation = layers.$navigation;
		var $close = layers.$close;

		function isToggleSuppressed() {
			return (
				!!$root[0].ttNav6SuppressToggleUntil &&
				Date.now() < $root[0].ttNav6SuppressToggleUntil
			);
		}

		function doClose() {
			if (!$root.hasClass('treethemes-nav-style6-open')) {
				return;
			}
			closeMenu($root, $trigger, $overlayNav, $overlayContent, $navigation, $navPanel, $close);
		}

		layerInit($trigger, $overlayNav, $overlayContent);
		prepareSubmenus($root, $navigation);
		bindSubmenuAccordion($root, $navigation);

		function handleTriggerOpen(e) {
			e.preventDefault();
			e.stopPropagation();
			if (isToggleSuppressed() || $root.hasClass('treethemes-nav-style6-open')) {
				return;
			}
			layerInit($trigger, $overlayNav, $overlayContent);
			openMenu($root, $trigger, $overlayNav, $overlayContent, $navigation, $navPanel, $close);
		}

		$trigger.on('click.ttNav6', handleTriggerOpen);
		$trigger.on('touchend.ttNav6', function (e) {
			if (e.cancelable) {
				e.preventDefault();
			}
			handleTriggerOpen(e);
		});

		$close.on('click.ttNav6', function (e) {
			e.preventDefault();
			e.stopPropagation();
			doClose();
		});

		$(document).on('keydown.ttNav6' + widgetId, function (e) {
			if (e.key === 'Escape') {
				doClose();
			}
		});

		var resizeTimer;
		$(window).on('resize.ttNav6-' + widgetId, function () {
			if ($root.hasClass('treethemes-nav-style6-open')) {
				doClose();
			}
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				layerInit($trigger, $overlayNav, $overlayContent);
			}, 120);
		});
	}

	function boot() {
		$('.elementor-widget-treethemes--navigation .treethemes-nav-menu-style-6')
			.not('[data-menu-presentation="bubble_overlay"]')
			.each(function () {
				initStyle6($(this));
			});
	}

	$(window).on('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}
		elementorFrontend.hooks.addAction(
			'frontend/element_ready/treethemes--navigation.default',
			function ($scope) {
				initStyle6($scope);
			}
		);
	});

	$(function () {
		boot();
	});
})(jQuery);
