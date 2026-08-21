/**
 * Demo 2 — Smokey WebGL fluid cursor.
 */
(function (window) {
	'use strict';

	function init() {
		var cfg = window.deepCursorSettings;
		if (!window.DeepCursorScope.shouldInit(cfg, 'demo2')) {
			return;
		}
		if (!window.SmokyFluid || typeof window.SmokyFluid.initFluid !== 'function') {
			return;
		}

		var scope = window.DeepCursorScope.create(cfg);
		if (!scope.ready) {
			return;
		}

		scope.mountFluidCanvas('deep-smokey-fluid-canvas');

		var parseSize =
			window.DeepCursorUtil && window.DeepCursorUtil.sizeFromConfig
				? window.DeepCursorUtil.sizeFromConfig.bind(window.DeepCursorUtil)
				: function (v, fb) {
						var n = Number(v);
						return Number.isFinite(n) ? n : fb;
					};
		var splatRadius = parseSize(cfg.fluidSplatRadius, 0.12);
		var sizeRatio = splatRadius / 0.28;
		var splatForce = Math.round(6000 * sizeRatio * sizeRatio);
		var trailDissipation = 1.2 + (1 - Math.min(sizeRatio, 1)) * 2.3;

		window.SmokyFluid.initFluid({
			id: 'deep-smokey-fluid-canvas',
			transparent: true,
			simResolution: 128,
			dyeResolution: 512,
			densityDissipation: trailDissipation,
			velocityDissipation: trailDissipation,
			pressureIteration: 12,
			curl: 12,
			splatRadius: splatRadius,
			splatForce: splatForce,
			shading: true,
			colorUpdateSpeed: 8,
		});

		var wrap = document.querySelector('.deep-cursor--demo2');
		if (wrap && !scope.isContainer) {
			scope.mountCursor(wrap, cfg);
		}
		if (wrap && scope.isContainer) {
			scope.bindPointer(function (pos, visible) {
				wrap.style.visibility = visible ? 'visible' : 'hidden';
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window);
