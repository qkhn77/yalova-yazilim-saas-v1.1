(function () {
    'use strict';

    var home = document.querySelector('.deep-source-home, .deep-home');
    if (!home) return;

    var header = home.querySelector('[data-deep-header]');
    var toggle = home.querySelector('[data-deep-menu-toggle]');
    var menu = home.querySelector('[data-deep-menu]');

    function closeMenu() {
        if (!toggle || !menu) return;
        toggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
        document.body.classList.remove('deep-menu-open');
    }

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            menu.classList.toggle('is-open', !isOpen);
            document.body.classList.toggle('deep-menu-open', !isOpen);
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });
    }

    function updateHeader() {
        if (header) header.classList.toggle('is-scrolled', window.scrollY > 32);
    }

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    home.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    var revealItems = home.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries, instance) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                instance.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        revealItems.forEach(function (item) {
            var delay = item.getAttribute('data-reveal-delay');
            if (delay) item.style.setProperty('--deep-reveal-delay', delay + 'ms');
            observer.observe(item);
        });
    } else {
        revealItems.forEach(function (item) { item.classList.add('is-visible'); });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 860) closeMenu();
    });
})();
