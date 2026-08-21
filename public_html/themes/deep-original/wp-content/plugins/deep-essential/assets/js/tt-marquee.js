(function () {
	function init(scope) {
		(scope || document).querySelectorAll('.tt-marquee').forEach(function (el) {
			if (el.__ttMarqueeRaf) {
				cancelAnimationFrame(el.__ttMarqueeRaf);
			}
			var speed = Number(el.dataset.ttSpeed || 40);
			var directionSetting = el.dataset.ttDirection || 'left';
			var vertical = directionSetting === 'top' || directionSetting === 'bottom';
			var direction = (directionSetting === 'right' || directionSetting === 'bottom') ? -1 : 1;
			if (el.dataset.ttReverseTablet === 'yes' && window.innerWidth <= 1024) {
				direction *= -1;
			}
			var pause = el.dataset.ttPause === 'yes';
			var seamless = el.dataset.ttSeamless !== 'no';
			var draggable = el.dataset.ttDraggable === 'yes';
			var track = el.querySelector('.tt-marquee__track');
			if (!track) { return; }

			track.querySelectorAll('.tt-marquee__group[data-tt-clone="yes"]').forEach(function (node) {
				node.remove();
			});
			if (seamless) {
				var firstGroup = track.querySelector('.tt-marquee__group');
				if (firstGroup) {
					var clone = firstGroup.cloneNode(true);
					clone.setAttribute('data-tt-clone', 'yes');
					clone.setAttribute('aria-hidden', 'true');
					track.appendChild(clone);
				}
			}

			var axisValue = 0;
			var paused = false;
			var dragging = false;
			var pointerStart = 0;
			var axisStart = 0;

			function step() {
				if (!paused && !dragging) {
					axisValue -= direction * Math.max(0.2, speed / 180);
					var totalLength = vertical ? track.scrollHeight : track.scrollWidth;
					var length = seamless ? totalLength / 2 : totalLength;
					if (direction > 0 && Math.abs(axisValue) >= length) {
						axisValue = 0;
					}
					if (direction < 0 && axisValue >= 0) {
						axisValue = -length;
					}
					track.style.transform = vertical
						? 'translate3d(0,' + axisValue + 'px,0)'
						: 'translate3d(' + axisValue + 'px,0,0)';
				}
				el.__ttMarqueeRaf = requestAnimationFrame(step);
			}

			if (pause) {
				el.addEventListener('mouseenter', function () { paused = true; });
				el.addEventListener('mouseleave', function () { paused = false; });
			}

			if (draggable) {
				el.style.cursor = 'grab';
				el.onpointerdown = function (event) {
					dragging = true;
					el.style.cursor = 'grabbing';
					pointerStart = vertical ? event.clientY : event.clientX;
					axisStart = axisValue;
				};
				window.addEventListener('pointermove', function (event) {
					if (!dragging) { return; }
					var current = vertical ? event.clientY : event.clientX;
					axisValue = axisStart + (current - pointerStart);
					track.style.transform = vertical
						? 'translate3d(0,' + axisValue + 'px,0)'
						: 'translate3d(' + axisValue + 'px,0,0)';
				});
				window.addEventListener('pointerup', function () {
					if (!dragging) { return; }
					dragging = false;
					el.style.cursor = 'grab';
				});
			}

			step();
		});
	}

	document.addEventListener('DOMContentLoaded', function () { init(document); });
	if (window.elementorFrontend) {
		window.addEventListener('elementor/frontend/init', function () {
			elementorFrontend.hooks.addAction('frontend/element_ready/deep-marquee.default', function ($scope) {
			init($scope[0]);
		});

		elementorFrontend.hooks.addAction('frontend/element_ready/treethemes-marquee.default', function ($scope) {
				init($scope[0]);
			});
		});
	}
})();
