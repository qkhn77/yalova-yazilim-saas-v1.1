document.addEventListener('DOMContentLoaded', function () {
        const config = window.YKHeaderUtilityConfig || {};
        const labels = Object.assign({
            cartAdded: 'Urun sepete eklendi.',
            goToCart: 'Sepete git',
            buyNow: 'Satin al',
            preferenceSaveFailed: 'Tercih kaydedilemedi.',
        }, config.labels || {});
        const csrfToken = String(config.csrfToken || '');
        const utilityBar = document.querySelector('.utility-bar');
        const cartMenu = document.querySelector('.utility-cart-menu');
        const loginMenu = document.querySelector('.utility-login-menu');
        let cartPanel = cartMenu ? cartMenu.querySelector('.utility-cart-menu-panel') : null;
        const cartToast = document.querySelector('[data-cart-toast]');
        const cartToastText = document.querySelector('[data-cart-toast-text]');
        let cartBadge = document.querySelector('.utility-cart-badge');

        let closeTimer = null;
        let loginCloseTimer = null;
        let toastTimer = null;

        document.addEventListener('click', (event) => {
            const stepButton = event.target.closest('[data-qty-step]');
            if (! stepButton) {
                return;
            }

            const control = stepButton.closest('.utility-cart-qty-control');
            const input = control ? control.querySelector('.utility-cart-qty-input') : null;
            if (! input) {
                return;
            }

            const step = Number(input.step || 1) || 1;
            const min = Number(input.min || 1) || 1;
            const current = Number(input.value || min) || min;
            const next = stepButton.dataset.qtyStep === 'up'
                ? current + step
                : Math.max(min, current - step);

            input.value = String(next);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        const syncCartPanelPosition = () => {
            if (! cartMenu || ! cartPanel || window.innerWidth <= 767) {
                if (cartMenu) {
                    cartMenu.style.setProperty('--cart-panel-offset-x', '0px');
                }
                return;
            }

            const previousDisplay = cartPanel.style.display;
            const previousVisibility = cartPanel.style.visibility;
            const previousOpacity = cartPanel.style.opacity;

            cartPanel.style.display = 'flex';
            cartPanel.style.visibility = 'hidden';
            cartPanel.style.opacity = '0';

            const rect = cartPanel.getBoundingClientRect();
            const viewportPadding = 12;
            let offset = 0;

            if (rect.left < viewportPadding) {
                offset = viewportPadding - rect.left;
            } else if (rect.right > (window.innerWidth - viewportPadding)) {
                offset = (window.innerWidth - viewportPadding) - rect.right;
            }

            cartMenu.style.setProperty('--cart-panel-offset-x', `${offset}px`);

            cartPanel.style.display = previousDisplay;
            cartPanel.style.visibility = previousVisibility;
            cartPanel.style.opacity = previousOpacity;
        };

        const openMenu = () => {
            if (cartMenu) {
                syncCartPanelPosition();
                cartMenu.classList.add('is-open');
            }
        };

        const closeMenu = () => {
            if (cartMenu) {
                cartMenu.classList.remove('is-open');
            }
        };

        const scheduleClose = (delay = 2800) => {
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(closeMenu, delay);
        };

        const showToast = (message) => {
            if (! cartToast || ! cartToastText) {
                return;
            }

            cartToastText.textContent = message;
            cartToast.classList.add('is-visible');
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(() => {
                cartToast.classList.remove('is-visible');
            }, 2400);
        };

        const updateCartBadge = (count) => {
            if (! cartMenu) {
                return;
            }

            let badge = cartBadge || cartMenu.querySelector('.utility-cart-badge');
            if (count > 0) {
                if (! badge) {
                    badge = document.createElement('span');
                    badge.className = 'utility-cart-badge';
                    const link = cartMenu.querySelector('.utility-link-with-badge');
                    if (link) {
                        link.appendChild(badge);
                    }
                }
                badge.textContent = String(count);
                cartBadge = badge;
            } else if (badge) {
                badge.remove();
                cartBadge = null;
            }
        };

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const renderMiniCart = (miniCart) => {
            if (! cartMenu || ! miniCart || Number(miniCart.count || 0) <= 0) {
                return;
            }

            if (! cartPanel) {
                cartPanel = document.createElement('div');
                cartPanel.className = 'utility-cart-menu-panel';
                cartMenu.appendChild(cartPanel);
            }

            const items = Array.isArray(miniCart.items) ? miniCart.items : [];
            const itemsHtml = items.map((item) => `
                <div class="utility-cart-item">
                    <a href="${escapeHtml(item.url)}" class="utility-cart-item-image-link" aria-label="${escapeHtml(item.name)}">
                        <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" class="utility-cart-item-image" loading="lazy" decoding="async">
                    </a>
                    <div>
                        <a href="${escapeHtml(item.url)}" class="utility-cart-item-name">${escapeHtml(item.name)}</a>
                        <div class="utility-cart-item-meta">
                            <span>${escapeHtml(item.quantity_label)}</span>
                            <strong>${escapeHtml(item.line_total)}</strong>
                        </div>
                        <div class="utility-cart-item-controls">
                            <form action="${escapeHtml(item.update_url)}" method="POST" class="utility-cart-qty-form">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="PATCH">
                                <span class="utility-cart-qty-control">
                                    <input type="number" name="miktar" value="${escapeHtml(item.quantity_value)}" min="1" step="1" class="utility-cart-qty-input" aria-label="Adet">
                                    <button type="button" class="utility-cart-qty-step utility-cart-qty-step-up" data-qty-step="up" aria-label="Adeti artır">+</button>
                                    <button type="button" class="utility-cart-qty-step utility-cart-qty-step-down" data-qty-step="down" aria-label="Adeti azalt">-</button>
                                </span>
                                <button type="submit" class="utility-cart-mini-btn">Güncelle</button>
                            </form>
                            <form action="${escapeHtml(item.remove_url)}" method="POST" class="utility-cart-remove-form">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="utility-cart-mini-btn utility-cart-mini-btn-danger">Kaldır</button>
                            </form>
                        </div>
                    </div>
                </div>
            `).join('');

            cartPanel.innerHTML = `
                <div class="utility-cart-summary" data-mini-cart-summary>
                    <div class="utility-cart-summary-title">Sepet Özeti</div>
                    <div class="utility-cart-items" data-mini-cart-items>${itemsHtml}</div>
                    <div class="utility-cart-more ${Number(miniCart.more_count || 0) > 0 ? '' : 'd-none'}" data-mini-cart-more>
                        ${Number(miniCart.more_count || 0) > 0 ? `+${Number(miniCart.more_count)} ürün daha` : ''}
                    </div>
                    <div class="utility-cart-total">
                        <span>Toplam</span>
                        <strong data-mini-cart-subtotal>${escapeHtml(miniCart.subtotal)}</strong>
                    </div>
                    <div class="utility-cart-actions">
                        <a href="${escapeHtml(miniCart.cart_url)}" class="utility-cart-action utility-cart-action-secondary">${escapeHtml(labels.goToCart)}</a>
                        <a href="${escapeHtml(miniCart.checkout_url)}" class="utility-cart-action utility-cart-action-primary">${escapeHtml(labels.buyNow)}</a>
                    </div>
                </div>
            `;

            syncCartPanelPosition();
        };

        const syncUtilityBarStickyState = () => {
            if (! utilityBar) {
                return;
            }

            utilityBar.classList.toggle('is-sticky', window.scrollY > 24);
        };

        syncUtilityBarStickyState();
        window.addEventListener('scroll', syncUtilityBarStickyState, { passive: true });
        window.addEventListener('resize', syncCartPanelPosition, { passive: true });

        if (cartMenu) {
            syncCartPanelPosition();

            if (cartMenu.classList.contains('is-open')) {
                scheduleClose(3200);
            }

            cartMenu.addEventListener('mouseenter', () => {
                window.clearTimeout(closeTimer);
                openMenu();
            });

            cartMenu.addEventListener('mouseleave', () => {
                scheduleClose(450);
            });

            document.addEventListener('click', (event) => {
                if (! cartMenu.contains(event.target)) {
                    closeMenu();
                }
            });
        }

        if (loginMenu) {
            const openLoginMenu = () => {
                window.clearTimeout(loginCloseTimer);
                loginMenu.classList.add('is-open');
            };

            const closeLoginMenu = () => {
                window.clearTimeout(loginCloseTimer);
                loginMenu.classList.remove('is-open');
            };

            const scheduleLoginClose = () => {
                window.clearTimeout(loginCloseTimer);
                loginCloseTimer = window.setTimeout(closeLoginMenu, 2000);
            };

            loginMenu.addEventListener('mouseenter', openLoginMenu);
            loginMenu.addEventListener('focusin', openLoginMenu);
            loginMenu.addEventListener('mouseleave', scheduleLoginClose);

            document.addEventListener('click', (event) => {
                if (! loginMenu.contains(event.target)) {
                    closeLoginMenu();
                }
            });
        }

        if (cartToast && cartToast.classList.contains('is-visible')) {
            showToast(cartToastText ? cartToastText.textContent : labels.cartAdded);
        }

        document.querySelectorAll('[data-cart-add-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const submitButton = form.querySelector('button[type="submit"]') || form.querySelector('button');
                const formData = new FormData(form);

                if (submitButton) {
                    submitButton.classList.add('is-loading');
                    submitButton.disabled = true;
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        throw new Error('Sepete ekleme başarısız');
                    }

                    const data = await response.json();
                    updateCartBadge(Number(data.cart_count || 0));
                    renderMiniCart(data.mini_cart);
                    openMenu();
                    scheduleClose(3200);
                    showToast(data.message || labels.cartAdded);
                } catch (error) {
                    form.submit();
                } finally {
                    if (submitButton) {
                        submitButton.classList.remove('is-loading');
                        submitButton.disabled = false;
                    }
                }
            });
        });
        const langSelect = document.getElementById('utilityLang');
        const currencySelect = document.getElementById('utilityCurrency');

        if (langSelect || currencySelect) {
            const tercihGonder = async (url, payload) => {
                const body = new URLSearchParams(payload);
                const yanit = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                });

                if (! yanit.ok) {
                    throw new Error(labels.preferenceSaveFailed);
                }
            };

            if (langSelect && config.localeUrl) {
                langSelect.addEventListener('change', function () {
                    tercihGonder(config.localeUrl, { locale: langSelect.value })
                        .then(() => window.location.reload())
                        .catch(() => window.location.reload());
                });
            }

            if (currencySelect && config.currencyUrl) {
                currencySelect.addEventListener('change', function () {
                    tercihGonder(config.currencyUrl, { currency: currencySelect.value })
                        .then(() => window.location.reload())
                        .catch(() => window.location.reload());
                });
            }
        }

        const initNewsletterModal = () => {
            const triggerInput = document.getElementById('newsletterTriggerEmail');
            const triggerButton = document.getElementById('newsletterTriggerButton');
            const modal = document.getElementById('newsletterModal');
            const modalClose = document.getElementById('newsletterModalClose');
            const modalEmail = document.getElementById('newsletterModalEmail');
            const modalBackdrop = modal ? modal.querySelector('.newsletter-modal__backdrop') : null;

            if (! triggerInput || ! triggerButton || ! modal || ! modalEmail) {
                return;
            }

            const openModal = () => {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            triggerButton.addEventListener('click', () => {
                if (typeof triggerInput.reportValidity === 'function' && ! triggerInput.reportValidity()) {
                    triggerInput.focus();
                    return;
                }

                if (typeof triggerInput.reportValidity !== 'function' && ! triggerInput.checkValidity()) {
                    triggerInput.focus();
                    return;
                }

                modalEmail.value = triggerInput.value.trim();
                openModal();
            });

            if (modalClose) {
                modalClose.addEventListener('click', closeModal);
            }

            if (modalBackdrop) {
                modalBackdrop.addEventListener('click', closeModal);
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            if (modal.dataset.openOnLoad === '1') {
                openModal();
            }
        };

        initNewsletterModal();
    });
