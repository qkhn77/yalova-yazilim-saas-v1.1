/**
 * Shared scope + hover helpers for Deep custom cursors.
 */
(function (window) {
	'use strict';

	function lerp(a, b, n) {
		return (1 - n) * a + n * b;
	}

	function getMousePos(e) {
		return { x: e.clientX, y: e.clientY };
	}

	var portalTopGuardBound = false;

	function bindPortalTopGuard() {
		if (portalTopGuardBound) {
			return;
		}
		portalTopGuardBound = true;

		var tick = function () {
			window.DeepCursorScope.ensureCursorLayerOnTop();
		};

		window.addEventListener('scroll', tick, { passive: true });
		window.addEventListener('resize', tick, { passive: true });
	}

	window.DeepCursorScope = {
		lerp: lerp,
		getMousePos: getMousePos,

		usesBlendMode: function (cfg) {
			cfg = cfg || {};
			var blend = cfg.blendMode || '';
			return blend !== '' && blend !== 'normal';
		},

		/**
		 * Mount point outside ScrollSmoother / transformed wrappers (#smooth-content).
		 */
		getMountRoot: function () {
			return document.documentElement;
		},

		/**
		 * Keep cursor layers last on <html> so sticky headers cannot paint above them.
		 */
		ensureCursorLayerOnTop: function () {
			var mountRoot = this.getMountRoot();
			var portal = document.getElementById('deep-cursor-root');

			if (portal && portal.parentNode === mountRoot && mountRoot.lastElementChild !== portal) {
				mountRoot.appendChild(portal);
			}

			var layers = mountRoot.querySelectorAll(':scope > .deep-cursor, :scope > .deep-cursor-blend-layer');
			for (var i = 0; i < layers.length; i++) {
				if (mountRoot.lastElementChild !== layers[i]) {
					mountRoot.appendChild(layers[i]);
				}
			}
		},

		mountToLayer: function (el) {
			if (!el) {
				return;
			}

			var mountRoot = this.getMountRoot();
			if (el.parentNode !== mountRoot) {
				mountRoot.appendChild(el);
			}

			this.ensureCursorLayerOnTop();
		},

		/** @deprecated Use ensureCursorLayerOnTop */
		ensurePortalOnTop: function () {
			this.ensureCursorLayerOnTop();
		},

		/**
		 * @param {object} cfg deepCursorSettings
		 * @returns {object}
		 */
		create: function (cfg) {
			cfg = cfg || {};
			var isContainer = cfg.scope === 'container';
			var selector = cfg.containerSelector || '.deep-cursor-area';
			if (selector.charAt(0) !== '.' && selector.charAt(0) !== '#') {
				selector = '.' + selector;
			}

			var containers = isContainer
				? Array.prototype.slice.call(document.querySelectorAll(selector))
				: [];

			if (isContainer && !containers.length) {
				return { ready: false, isContainer: true, containers: [] };
			}

			return {
				ready: true,
				isContainer: isContainer,
				containers: containers,
				selector: selector,

				isInside: function (x, y) {
					if (!isContainer) {
						return true;
					}
					for (var i = 0; i < containers.length; i++) {
						var rect = containers[i].getBoundingClientRect();
						if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
							return true;
						}
					}
					return false;
				},

				/**
				 * Bind mousemove; callback receives {x,y} and visible flag.
				 */
				bindPointer: function (callback) {
					var self = this;
					var onMove = function (e) {
						var pos = getMousePos(e);
						var visible = self.isInside(pos.x, pos.y);
						callback(pos, visible, e);
					};
					document.addEventListener('mousemove', onMove);
					return function () {
						document.removeEventListener('mousemove', onMove);
					};
				},

				/**
				 * Bind hover on interactive elements within scope.
				 */
				bindHovers: function (onEnter, onLeave) {
					var hoverSelector =
						cfg.hoverSelector ||
						'a[href], button:not(:disabled), input[type="submit"], input[type="button"], .elementor-button, [data-deep-cursor-hover]';

					var roots = isContainer ? containers : [document];
					roots.forEach(function (root) {
						var items = root.querySelectorAll(hoverSelector);
						Array.prototype.forEach.call(items, function (el) {
							el.addEventListener('mouseenter', function (e) {
								onEnter(e);
							});
							el.addEventListener('mouseleave', function (e) {
								onLeave(e);
							});
						});
					});
				},

				/**
				 * Fullscreen portal on body — avoids overflow:hidden on theme wrappers clipping the cursor.
				 */
				getPortal: function () {
					var mountRoot = window.DeepCursorScope.getMountRoot();
					var portal = document.getElementById('deep-cursor-root');

					if (!portal) {
						portal = document.createElement('div');
						portal.id = 'deep-cursor-root';
						portal.className = 'deep-cursor-root';
						portal.setAttribute('aria-hidden', 'true');
						mountRoot.appendChild(portal);
						bindPortalTopGuard();
					} else if (portal.parentNode !== mountRoot) {
						mountRoot.appendChild(portal);
					}

					window.DeepCursorScope.ensureCursorLayerOnTop();
					return portal;
				},

				mountToPortal: function (el) {
					if (!el) {
						return;
					}
					var portal = this.getPortal();
					if (el.parentNode !== portal) {
						portal.appendChild(el);
					}
					window.DeepCursorScope.ensureCursorLayerOnTop();
				},

				/**
				 * Mount cursor for correct compositing: blend modes paint on body,
				 * otherwise use the top-layer portal (above sticky headers).
				 */
				mountCursor: function (el, cfg) {
					if (!el) {
						return;
					}

					if (window.DeepCursorScope.usesBlendMode(cfg)) {
						window.DeepCursorScope.mountToLayer(el);
					} else {
						this.mountToPortal(el);
					}
				},

				/**
				 * Move SVG cursor (blend layer if present) outside smooth-scroll wrappers.
				 */
				mountSvgCursor: function (svgEl) {
					if (!svgEl) {
						return;
					}
					var node = svgEl.closest('.deep-cursor-blend-layer') || svgEl;
					window.DeepCursorScope.mountToLayer(node);
					bindPortalTopGuard();
				},

				/** @deprecated Use mountCursor */
				mountToBody: function (el, cfg) {
					this.mountCursor(el, cfg);
				},

				mountFluidCanvas: function (canvasId) {
					if (!isContainer || !containers.length) {
						return;
					}
					var canvas = document.getElementById(canvasId);
					var wrap = document.querySelector('.deep-cursor--demo2');
					if (!canvas || !wrap) {
						return;
					}
					var target = containers[0];
					if (window.getComputedStyle(target).position === 'static') {
						target.style.position = 'relative';
					}
					target.appendChild(wrap);
					wrap.style.position = 'absolute';
					wrap.style.inset = '0';
					wrap.style.width = '100%';
					wrap.style.height = '100%';
				},
			};
		},

		/**
		 * Run demo init only when settings match.
		 */
		shouldInit: function (cfg, style) {
			if (!cfg || cfg.style !== style) {
				return false;
			}
			if (cfg.hideOnTouch && window.matchMedia('(pointer: coarse)').matches) {
				return false;
			}
			return true;
		},
	};
})(window);
