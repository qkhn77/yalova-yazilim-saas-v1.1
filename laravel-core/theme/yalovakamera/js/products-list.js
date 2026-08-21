document.addEventListener('DOMContentLoaded', function () {
    const grid = document.querySelector('.js-urun-grid');
    const viewSwitch = document.querySelector('.js-urun-view-switch');
    const filterForm = document.querySelector('.js-urun-filter-form');
    const content = document.querySelector('.urun-content');
    const sortSelect = document.querySelector('.js-urun-sort');
    const stokToggle = filterForm ? filterForm.querySelector('input[name="stokta_var"]') : null;

    const storageKey = 'yalova_urun_grid_cols';
    const applyCols = function (cols) {
        if (!grid || !viewSwitch) return;
        const safeCols = ['2', '3', '4'].includes(String(cols)) ? String(cols) : '3';
        grid.setAttribute('data-cols', safeCols);
        viewSwitch.querySelectorAll('[data-cols]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-cols') === safeCols);
        });
        localStorage.setItem(storageKey, safeCols);
    };

    if (viewSwitch) {
        const saved = localStorage.getItem(storageKey);
        if (saved) applyCols(saved);
        viewSwitch.querySelectorAll('[data-cols]').forEach((btn) => {
            btn.addEventListener('click', function () {
                applyCols(btn.getAttribute('data-cols'));
            });
        });
    }

    if (sortSelect && filterForm) {
        sortSelect.addEventListener('change', function () {
            filterForm.requestSubmit();
        });
    }

    if (stokToggle && filterForm) {
        stokToggle.addEventListener('change', function () {
            filterForm.requestSubmit();
        });
    }

    if (filterForm && content) {
        filterForm.addEventListener('submit', function () {
            content.classList.add('is-loading');
        });
    }
});
