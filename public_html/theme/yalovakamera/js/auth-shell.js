(() => {
            const localVideoDevreDisi = ['127.0.0.1', 'localhost', '::1'].includes(window.location.hostname);

            const loadLazyVideos = () => {
                if (localVideoDevreDisi) {
                    return;
                }

                document.querySelectorAll('video[data-auth-lazy-video]').forEach((video) => {
                    if (!(video instanceof HTMLVideoElement) || video.dataset.authVideoLoaded === '1') {
                        return;
                    }

                    video.querySelectorAll('source[data-src]').forEach((source) => {
                        source.setAttribute('src', source.getAttribute('data-src') || '');
                        source.removeAttribute('data-src');
                    });

                    video.dataset.authVideoLoaded = '1';
                    video.load();
                    video.play().catch(() => {});
                });
            };

            const scheduleLazyVideos = () => {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(loadLazyVideos, { timeout: 1800 });

                    return;
                }

                window.setTimeout(loadLazyVideos, 900);
            };

            if (document.readyState === 'complete') {
                scheduleLazyVideos();
            } else {
                window.addEventListener('load', scheduleLazyVideos, { once: true });
            }

            document.querySelectorAll('[data-password-toggle]').forEach((button) => {
                const targetId = button.getAttribute('data-password-toggle');
                const input = targetId ? document.getElementById(targetId) : null;
                if (!input) return;

                button.addEventListener('click', () => {
                    const visible = input.type === 'text';
                    input.type = visible ? 'password' : 'text';
                    button.textContent = visible ? 'Göster' : 'Gizle';
                    button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                });
            });

            document.querySelectorAll('input[type="password"]').forEach((input) => {
                const warning = input.closest('.field')?.querySelector('.caps-warning');
                if (!warning) return;

                const update = (event) => {
                    if (typeof event.getModifierState === 'function') {
                        warning.classList.toggle('is-visible', event.getModifierState('CapsLock'));
                    }
                };

                input.addEventListener('keydown', update);
                input.addEventListener('keyup', update);
                input.addEventListener('blur', () => warning.classList.remove('is-visible'));
            });

            document.querySelectorAll('form[data-auth-form]').forEach((form) => {
                const rememberKey = form.getAttribute('data-remember-key');
                const rememberSource = rememberKey ? form.querySelector('[data-remember-source]') : null;
                if (rememberKey && rememberSource && !rememberSource.value) {
                    rememberSource.value = localStorage.getItem(rememberKey) || '';
                }

                form.addEventListener('submit', () => {
                    if (rememberKey && rememberSource) {
                        const value = (rememberSource.value || '').trim();
                        if (value) localStorage.setItem(rememberKey, value);
                    }

                    const submit = form.querySelector('[data-submit-loading]');
                    if (!submit) return;

                    submit.disabled = true;
                    submit.textContent = submit.getAttribute('data-submit-loading') || 'İşleniyor...';
                });
            });

            document.querySelectorAll('[data-clear-remember]').forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.getAttribute('data-clear-remember');
                    const input = key ? document.querySelector(`[data-remember-source][data-remember-key="${key}"]`) : null;
                    if (key) localStorage.removeItem(key);
                    if (input) input.value = '';
                    button.textContent = 'Temizlendi';
                    window.setTimeout(() => { button.textContent = 'Hatırlananı temizle'; }, 1400);
                });
            });

            document.querySelectorAll('[data-password-strength]').forEach((input) => {
                const wrap = input.closest('.field')?.querySelector('[data-strength-wrap]');
                const bar = wrap?.querySelector('[data-strength-bar]');
                const text = wrap?.querySelector('[data-strength-text]');
                if (!wrap || !bar || !text) return;

                const update = () => {
                    const value = input.value || '';
                    let score = 0;
                    if (value.length >= 8) score++;
                    if (value.length >= 12) score++;
                    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
                    if (/\d/.test(value)) score++;
                    if (/[^A-Za-z0-9]/.test(value)) score++;

                    const widths = ['0%', '22%', '42%', '62%', '82%', '100%'];
                    const colors = ['#e2e8f0', '#ef4444', '#f97316', '#eab308', '#22c55e', '#047857'];
                    const labels = ['Şifre gücü', 'Zayıf', 'Orta', 'İyi', 'Güçlü', 'Çok güçlü'];
                    bar.style.width = widths[score];
                    bar.style.background = colors[score];
                    text.textContent = labels[score];
                };

                input.addEventListener('input', update);
                update();
            });

            document.querySelectorAll('[data-copy-text]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const text = button.getAttribute('data-copy-text') || '';
                    if (!text) return;

                    try {
                        await navigator.clipboard.writeText(text);
                        button.textContent = 'Kopyalandı';
                    } catch (error) {
                        const range = document.createRange();
                        range.selectNodeContents(button.previousElementSibling || button);
                        const selection = window.getSelection();
                        selection.removeAllRanges();
                        selection.addRange(range);
                        button.textContent = 'Seçildi';
                    }

                    window.setTimeout(() => { button.textContent = 'Kopyala'; }, 1400);
                });
            });
        })();
