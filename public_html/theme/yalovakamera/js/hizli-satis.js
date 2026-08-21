(() => {
            // quick-pos asset rev: supplier-barcode-provider-2026-08-10
            if (!document.getElementById('quick-pos-focus-fix-style')) {
                const style = document.createElement('style');
                style.id = 'quick-pos-focus-fix-style';
                style.textContent = `
                    .fi-main .quick-pos .quick-pos-field > input:focus,
                    .fi-main .quick-pos .quick-pos-search-input-wrap input:focus {
                        border-width: 2px !important;
                        border-left-width: 2px !important;
                        border-color: #155efd !important;
                        box-shadow: inset 0 0 0 1px #155efd !important;
                        outline: none !important;
                    }
                `;
                document.head.appendChild(style);
            }

            const iadeUrl = window.quickPosConfig?.iadeUrl ?? null;
            const kdvOranlari = Array.isArray(window.quickPosConfig?.kdvOranlari) && window.quickPosConfig.kdvOranlari.length > 0
                ? window.quickPosConfig.kdvOranlari
                : [{ oran: 0, etiket: '%0' }, { oran: 20, etiket: '%20' }];
            const birimSecenekleri = Array.isArray(window.quickPosConfig?.birimSecenekleri) && window.quickPosConfig.birimSecenekleri.length > 0
                ? window.quickPosConfig.birimSecenekleri
                : [{ kod: 'AD', etiket: 'AD' }];
            const kategoriSecenekleri = Array.isArray(window.quickPosConfig?.kategoriSecenekleri)
                ? window.quickPosConfig.kategoriSecenekleri
                : [];
            const aktifKategoriId = Number.parseInt(window.quickPosConfig?.aktifKategoriId || 0, 10) || 0;
            const yerelBarkodAdaylari = window.quickPosConfig?.barkodAdaylari && typeof window.quickPosConfig.barkodAdaylari === 'object'
                ? window.quickPosConfig.barkodAdaylari
                : {};
            const focusById = (id) => {
                const input = document.getElementById(id);
                if (!input) return;
                setTimeout(() => input.focus(), 50);
            };
            const livewireBileseni = () => {
                const barkod = document.getElementById('pos-barkod-input');
                const root = barkod ? barkod.closest('[wire\\:id]') : document.querySelector('[wire\\:id]');
                if (!root || !window.Livewire || typeof window.Livewire.find !== 'function') return null;
                const id = root.getAttribute('wire:id');
                return id ? window.Livewire.find(id) : null;
            };
            const call = (method) => {
                const cmp = livewireBileseni();
                if (cmp && typeof cmp.call === 'function') cmp.call(method);
            };
            const callWith = (method, ...args) => {
                const cmp = livewireBileseni();
                if (cmp && typeof cmp.call === 'function') return cmp.call(method, ...args);
                return null;
            };
            const cariAramaDegeriniIsle = (input) => {
                const value = (input.value || '').trim();
                if (value === '') {
                    callWith('hizliCariSec', null);
                    return;
                }

                const listId = input.getAttribute('list');
                const list = listId ? document.getElementById(listId) : null;
                const option = list
                    ? Array.from(list.options).find((item) => (item.value || '').trim() === value)
                    : null;
                const cariId = option?.dataset?.cariId ?? null;
                if (cariId) callWith('hizliCariSec', Number(cariId));
            };
            const aktifCanliInputMu = () => {
                const el = document.activeElement;
                if (!el) return false;
                const tag = (el.tagName || '').toLowerCase();
                return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
            };
            const preview = () => document.querySelector('[data-pos-image-preview]');
            const previewImg = () => document.querySelector('[data-pos-image-preview-img]');
            const previewZoomText = () => document.querySelector('[data-pos-image-preview-zoom]');
            let previewScale = 1;
            const previewScaleAta = (scale) => {
                previewScale = Math.min(4, Math.max(.5, scale));
                const img = previewImg();
                if (img) img.style.transform = `scale(${previewScale})`;
                const zoomText = previewZoomText();
                if (zoomText) zoomText.textContent = `${Math.round(previewScale * 100)}%`;
            };
            const previewAc = (src, title) => {
                const modal = preview();
                const img = previewImg();
                if (!modal || !img || !src) return;
                const titleEl = document.querySelector('[data-pos-image-preview-title]');
                img.src = src;
                img.alt = title || 'Ürün görseli';
                if (titleEl) titleEl.textContent = title || 'Ürün görseli';
                modal.hidden = false;
                previewScaleAta(1);
                document.body.style.overflow = 'hidden';
            };
            const previewKapat = () => {
                const modal = preview();
                const img = previewImg();
                if (!modal) return;
                modal.hidden = true;
                if (img) img.src = '';
                document.body.style.overflow = '';
                previewScaleAta(1);
            };
            const parasalCoz = (value) => {
                const raw = String(value || '').replace(/[^0-9,.-]/g, '');
                if (!raw) return 0;
                const normalized = raw.includes(',') && raw.includes('.')
                    ? raw.replaceAll('.', '').replace(',', '.')
                    : raw.replace(',', '.');

                return Math.max(0, Number.parseFloat(normalized) || 0);
            };
            const paraYaz = (value) => {
                const fixed = Math.max(0, value).toFixed(2);
                const [whole, cents] = fixed.split('.');

                return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${cents}`;
            };
            const paraBirimi = () => {
                const select = document.querySelector('select[wire\\:model\\.live="data.para_birimi"], select[wire\\:model="data.para_birimi"]');

                return select?.value || 'TRY';
            };
            const paraUstuGuncelle = (alinan) => {
                const total = parasalCoz(document.querySelector('[data-pos-grand-total]')?.dataset?.posGrandTotal || '0');
                const currency = paraBirimi();
                const paid = document.querySelector('[data-pos-cash-paid]');
                const change = document.querySelector('[data-pos-change-text]');

                if (paid) paid.innerHTML = `Alınan<br>${paraYaz(alinan)} ${currency}`;
                if (change) change.textContent = `Para Üstü ${paraYaz(Math.max(0, alinan - total))} ${currency}`;
            };
            const kupurEkle = (amount) => {
                const input = document.querySelector('[data-pos-cash-input]');
                if (!input) return;

                const next = parasalCoz(input.value) + amount;
                input.value = paraYaz(next);
                paraUstuGuncelle(next);
            };
            const htmlKacir = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            const sekmeUrunleri = (key) => {
                const script = document.querySelector(`[data-pos-tab-products="${CSS.escape(key)}"]`);
                if (!script) return null;

                try {
                    return JSON.parse(script.textContent || '[]');
                } catch {
                    return null;
                }
            };
            const tumOnizlemeUrunleri = () => {
                const byId = new Map();
                document.querySelectorAll('[data-pos-tab-products]').forEach((script) => {
                    try {
                        JSON.parse(script.textContent || '[]').forEach((urun) => {
                            const id = Number.parseInt(urun?.id, 10) || 0;
                            if (id > 0 && !byId.has(id)) byId.set(id, urun);
                        });
                    } catch {
                    }
                });

                return Array.from(byId.values());
            };
            const urunKartiHtml = (urun) => {
                const id = Number.parseInt(urun.id, 10) || 0;
                const ad = htmlKacir(urun.ad || '');
                const kod = htmlKacir(urun.kod || '');
                const barkod = htmlKacir(urun.barkod || '');
                const fiyat = htmlKacir(urun.fiyat_yazi || '');
                const stok = htmlKacir(urun.stok_yazi || '0');
                const gorsel = htmlKacir(urun.gorsel_url || '');
                const favori = urun.favori_mi ? ' is-active' : '';
                const media = gorsel
                    ? `<img src="${gorsel}" alt="${ad}" loading="lazy" decoding="async" fetchpriority="low">`
                    : `<span class="quick-pos-product-fallback">${ad}</span>`;

                return `
                    <button
                        type="button"
                        class="quick-pos-product"
                        data-pos-product-card
                        data-pos-client-card="1"
                        data-pos-product-id="${id}"
                        data-pos-product-name="${ad}"
                        data-pos-product-code="${kod}"
                        data-pos-product-barcode="${barkod}"
                        data-pos-product-price="${fiyat}"
                        data-pos-product-image="${gorsel}"
                    >
                        <span role="button" tabindex="0" class="quick-pos-favorite-button${favori}" data-pos-favorite-button data-pos-client-favorite="1" data-pos-product-id="${id}" title="Favori durumunu değiştir" aria-label="Favori durumunu değiştir">★</span>
                        <span class="quick-pos-product-media">${media}</span>
                        <span class="quick-pos-product-info">
                            <span class="quick-pos-product-name">${ad}</span>
                            <span class="quick-pos-product-meta">
                                <span>${fiyat}</span>
                                <span>${stok}</span>
                            </span>
                        </span>
                    </button>
                `;
            };
            let aramaOnizlemeLimit = 8;
            const aramaSonuclari = (query) => {
                const needle = String(query || '').trim().toLocaleLowerCase('tr-TR');
                if (needle.length < 2) return [];

                return tumOnizlemeUrunleri()
                    .filter((urun) => [
                        urun.ad,
                        urun.kod,
                        urun.barkod,
                    ].some((value) => String(value || '').toLocaleLowerCase('tr-TR').includes(needle)));
            };
            const aramaSonucHtml = (urun) => {
                const id = Number.parseInt(urun.id, 10) || 0;
                const ad = htmlKacir(urun.ad || '');
                const kod = htmlKacir(urun.kod || '-');
                const barkod = htmlKacir(urun.barkod || '-');
                const fiyat = htmlKacir(urun.fiyat_yazi || '');
                const stok = htmlKacir(urun.stok_yazi || '0');
                const gorsel = htmlKacir(urun.gorsel_url || '');
                const media = gorsel
                    ? `<img src="${gorsel}" alt="${ad}" loading="lazy" decoding="async" fetchpriority="low">`
                    : 'Görsel yok';

                return `
                    <button type="button" class="quick-pos-search-result" data-pos-client-search-result data-pos-product-id="${id}" data-pos-product-name="${ad}" data-pos-product-code="${kod}" data-pos-product-barcode="${barkod}" data-pos-product-price="${fiyat}" data-pos-product-image="${gorsel}">
                        <span class="quick-pos-search-media">${media}</span>
                        <span class="quick-pos-search-info">
                            <span class="quick-pos-search-name">${ad}</span>
                            <span class="quick-pos-search-meta">
                                <span>${kod}</span>
                                <span>${barkod}</span>
                            </span>
                        </span>
                        <span class="quick-pos-search-price">
                            ${fiyat}
                            <span class="quick-pos-search-stock">Stok: ${stok} AD</span>
                        </span>
                    </button>
                `;
            };
            const aramaPaneliniKapat = () => {
                document.querySelectorAll('[data-pos-client-search-panel]').forEach((panel) => panel.remove());
                document.querySelectorAll('[data-pos-search-panel]').forEach((panel) => panel.remove());
                document.querySelectorAll('.quick-pos-table-wrap').forEach((wrap) => {
                    wrap.style.display = '';
                });
            };
            const aramaPaneliniGoster = (query) => {
                const results = aramaSonuclari(query);
                const shown = results.slice(0, aramaOnizlemeLimit);
                const tableWrap = document.querySelector('.quick-pos-table-wrap');
                const anchor = tableWrap || document.querySelector('[data-pos-search-panel]');
                if (!anchor) return;

                tableWrap?.style.setProperty('display', 'none');
                document.querySelectorAll('[data-pos-search-panel]').forEach((panel) => {
                    panel.style.display = 'none';
                });

                let panel = document.querySelector('[data-pos-client-search-panel]');
                if (!panel) {
                    panel = document.createElement('div');
                    panel.className = 'quick-pos-panel';
                    panel.style.padding = '7px';
                    panel.dataset.posClientSearchPanel = '1';
                    anchor.parentNode.insertBefore(panel, anchor);
                }

                panel.innerHTML = `
                    <div class="quick-pos-search-toolbar">
                        <div class="quick-pos-search-count">${shown.length} / ${results.length} ön sonuç gösteriliyor</div>
                        <span class="quick-pos-search-sort">Aranıyor...</span>
                    </div>
                    <div class="quick-pos-search-results">
                        ${shown.length > 0 ? shown.map(aramaSonucHtml).join('') : '<div class="quick-pos-empty" style="grid-column: 1 / -1; min-height: 120px;">Arama başlatıldı, sonuçlar yükleniyor...</div>'}
                        <div class="quick-pos-search-footer">
                            ${results.length > shown.length ? '<button type="button" class="quick-pos-search-more" data-pos-search-more-client>Daha fazla göster</button>' : ''}
                            <button type="button" class="quick-pos-search-close" data-pos-search-clear style="${results.length > shown.length ? '' : 'grid-column: 1 / -1;'}">Aramayı Kapat</button>
                        </div>
                    </div>
                `;
            };
            const urunGridOnizlemeGoster = (key) => {
                const urunler = sekmeUrunleri(key);
                const grid = document.querySelector('.quick-pos-grid');
                if (!Array.isArray(urunler) || !grid) return;

                grid.innerHTML = urunler.length > 0
                    ? urunler.map(urunKartiHtml).join('')
                    : '<div class="quick-pos-empty" style="grid-column: 1 / -1;">Hızlı satışta gösterilecek ürün bulunamadı.</div>';
            };
            const kategoriSekmesiniAktiflestir = (tab) => {
                if (!tab) return;

                document.querySelectorAll('[data-pos-product-tab]').forEach((button) => {
                    button.classList.toggle('is-active', button === tab);
                });
                urunGridOnizlemeGoster(tab.dataset.posProductTab || '');
            };
            const odemeButonuAktiflestir = (tip) => {
                if (!tip || !['nakit', 'kart', 'havale', 'veresiye', 'taksitli'].includes(tip)) return;

                document.querySelectorAll('[data-pos-pay-button]').forEach((button) => {
                    const aktif = button.dataset.posPayButton === tip;
                    if (['nakit', 'kart', 'havale', 'veresiye', 'taksitli'].includes(button.dataset.posPayButton || '')) {
                        button.classList.toggle('is-active', aktif);
                        button.setAttribute('aria-pressed', aktif ? 'true' : 'false');
                    }
                });

                const select = document.getElementById('pos-odeme-tipi-input');
                if (select && select.value !== tip) select.value = tip;
            };
            const favoriButonuHizliDegistir = (button) => {
                if (!button) return;

                button.classList.toggle('is-active');
                button.classList.add('is-fast-feedback');
                setTimeout(() => button.classList.remove('is-fast-feedback'), 180);

                const productId = Number(button.dataset.posProductId || button.closest('[data-pos-product-card]')?.dataset?.posProductId || 0);
                if (productId > 0) {
                    callWith('hizliFavoriDegistir', productId, false);
                }
            };
            const sepetTablosuHazirla = () => {
                const wrap = document.querySelector('.quick-pos-table-wrap');
                if (!wrap) return null;

                let table = wrap.querySelector('.quick-pos-table');
                if (table) return table;

                const empty = wrap.querySelector('.quick-pos-empty');
                if (empty) empty.remove();

                table = document.createElement('table');
                table.className = 'quick-pos-table';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Ürün Tanımı</th>
                            <th>Miktar</th>
                            <th>Fiyat</th>
                            <th>Tutar</th>
                            <th>KDV</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                `;
                wrap.appendChild(table);

                return table;
            };
            const sepetBosaltOnizleme = (message = 'Sepet boş. Barkod okutun veya sağdaki ürünlerden seçim yapın.') => {
                const wrap = document.querySelector('.quick-pos-table-wrap');
                if (!wrap) return;

                wrap.querySelector('.quick-pos-table')?.remove();
                let empty = wrap.querySelector('.quick-pos-empty');
                if (!empty) {
                    empty = document.createElement('div');
                    empty.className = 'quick-pos-empty';
                    wrap.appendChild(empty);
                }
                empty.textContent = message;

                document.querySelectorAll('.quick-pos-summary-item strong, .quick-pos-total strong').forEach((item) => {
                    if (item.dataset.posGrandTotal) item.dataset.posGrandTotal = '0.00';
                    item.textContent = '0,00 TRY';
                });
            };
            const metniKacir = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            const paraBirimliYaz = (value, currency = 'TRY') => {
                const amount = Number.parseFloat(value || 0) || 0;

                return `${amount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency || 'TRY'}`;
            };
            const kdvSecenekHtml = () => {
                const seen = new Set();

                return kdvOranlari
                    .map((item) => ({
                        oran: Number.parseFloat(String(item?.oran ?? '0').replace(',', '.')) || 0,
                        etiket: String(item?.etiket ?? `%${item?.oran ?? 0}`),
                    }))
                    .filter((item) => {
                        const key = item.oran.toFixed(4);
                        if (seen.has(key)) return false;
                        seen.add(key);

                        return true;
                    })
                    .map((item) => `<option value="${metniKacir(item.oran)}"${item.oran === 20 ? ' selected' : ''}>${metniKacir(item.etiket)}</option>`)
                    .join('');
            };
            const birimSecenekHtml = () => {
                const seen = new Set();

                return birimSecenekleri
                    .map((item) => ({
                        kod: String(item?.kod || 'AD').trim().toLocaleUpperCase('tr-TR'),
                        etiket: String(item?.etiket || item?.kod || 'AD').trim(),
                    }))
                    .filter((item) => {
                        if (!item.kod || seen.has(item.kod)) return false;
                        seen.add(item.kod);

                        return true;
                    })
                    .map((item) => `<option value="${metniKacir(item.kod)}"${item.kod === 'AD' ? ' selected' : ''}>${metniKacir(item.etiket)}</option>`)
                    .join('');
            };
            const kategoriSecenekHtml = () => [
                '<option value="">Kategorisiz</option>',
                ...kategoriSecenekleri.map((item) => {
                    const id = Number.parseInt(item?.id, 10) || 0;
                    const ad = String(item?.ad || '').trim();
                    if (id < 1 || !ad) return '';

                    return `<option value="${id}"${id === aktifKategoriId ? ' selected' : ''}>${metniKacir(ad)}</option>`;
                }),
            ].join('');
            const kdvHesapla = (fiyat, oran, dahilMi) => {
                const tutar = Math.max(0, Number.parseFloat(fiyat || 0) || 0);
                const kdvOrani = Math.max(0, Number.parseFloat(oran || 0) || 0);
                const carpan = 1 + (kdvOrani / 100);
                const net = dahilMi && carpan > 0 ? tutar / carpan : tutar;
                const kdv = net * (kdvOrani / 100);
                const dahil = net + kdv;

                return { net, kdv, dahil };
            };
            const urunEkleKdvOzetiGuncelle = (form, modal) => {
                const summary = modal?.querySelector('[data-pos-client-product-tax-summary]');
                if (!summary || !form) return;

                const fiyat = Number.parseFloat(form.elements.satis_fiyati?.value || '0') || 0;
                const oran = Number.parseFloat(form.elements.kdv_orani?.value || '0') || 0;
                const dahilMi = Boolean(form.elements.kdv_dahil_mi?.checked);
                const currency = (document.querySelector('select[wire\\:model\\.live="data.para_birimi"]')?.value || 'TRY').trim() || 'TRY';
                const hesap = kdvHesapla(fiyat, oran, dahilMi);
                summary.innerHTML = `
                    <span>Net: <strong>${paraBirimliYaz(hesap.net, currency)}</strong></span>
                    <span>KDV: <strong>${paraBirimliYaz(hesap.kdv, currency)}</strong></span>
                    <span>Dahil: <strong>${paraBirimliYaz(hesap.dahil, currency)}</strong></span>
                `;
                summary.dataset.posTaxMode = dahilMi ? 'included' : 'excluded';
            };
            const hizliDuzenleModalKapat = () => {
                document.querySelector('[data-pos-client-edit-modal]')?.remove();
            };
            const hizliUrunEkleModalKapat = () => {
                document.querySelector('[data-pos-client-product-add-modal]')?.remove();
            };
            const yeniUrunOnizlemeSatiriEkle = (payload) => {
                const table = sepetTablosuHazirla();
                const tbody = table?.querySelector('tbody');
                if (!tbody) return;

                const currency = (document.querySelector('select[wire\\:model\\.live="data.para_birimi"]')?.value || 'TRY').trim() || 'TRY';
                const fiyat = Number.parseFloat(payload.satisFiyati || 0) || 0;
                const imageUrl = payload.gorselDataUrl || payload.gorselUrl;
                const image = imageUrl
                    ? `<span class="quick-pos-cart-thumb"><img src="${metniKacir(imageUrl)}" alt=""></span>`
                    : '<span class="quick-pos-cart-thumb">Yeni</span>';
                const row = document.createElement('tr');
                row.className = 'is-pending';
                row.innerHTML = `
                    <td data-label="Ürün">
                        <div class="quick-pos-cart-product">
                            ${image}
                            <span class="quick-pos-cart-info">
                                <strong>${metniKacir(payload.ad || 'Yeni ürün')}</strong>
                                <span style="display:block; font-size: 11px; color: #52687a;">Yeni / ${metniKacir(payload.barkod || '-')}</span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Miktar"><span class="quick-pos-number" style="display:inline-block;">1</span><span style="font-size: 11px;"> AD</span></td>
                    <td data-label="Fiyat"><span class="quick-pos-number" style="display:inline-block;">${paraBirimliYaz(fiyat, currency)}</span></td>
                    <td data-label="Tutar"><strong>${paraBirimliYaz(fiyat, currency)}</strong></td>
                    <td data-label="KDV"><span class="quick-pos-number" style="display:inline-block;">${metniKacir(payload.kdvOrani || 0)}</span></td>
                    <td data-label="İşlem">
                        <div class="quick-pos-row-actions">
                            <span class="quick-pos-row-action is-edit" style="display:inline-flex; align-items:center; justify-content:center;">✎</span>
                            <span class="quick-pos-row-action is-delete quick-pos-delete" style="display:inline-flex; align-items:center; justify-content:center;">⌫</span>
                        </div>
                    </td>
                `;
                tbody.prepend(row);
                sepetSatiriToplamlariniGuncelle();
            };
            const hizliUrunEkleModalAc = () => {
                hizliUrunEkleModalKapat();

                const modal = document.createElement('div');
                modal.className = 'quick-pos-modal';
                modal.dataset.posClientProductAddModal = '1';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-label', 'Hızlı ürün ekle');
                modal.innerHTML = `
                    <div class="quick-pos-modal-card">
                        <div class="quick-pos-modal-header">
                            <div>
                                <span>Hızlı Ürün Ekle</span>
                                <strong data-pos-client-product-title>Yeni stok kartı</strong>
                            </div>
                            <button type="button" class="quick-pos-modal-close" data-pos-client-product-close aria-label="Kapat">X</button>
                        </div>
                        <form class="quick-pos-modal-form" data-pos-client-product-form>
                            <label class="quick-pos-modal-field">
                                <span>Barkod</span>
                                <input type="text" name="barkod" autocomplete="off" placeholder="Barkod okutun" data-pos-client-product-barcode>
                            </label>
                            <div class="quick-pos-modal-lookup">
                                <button type="button" class="quick-pos-modal-button is-secondary" data-pos-client-product-lookup>İnternetten Ara</button>
                                <small data-pos-client-product-source></small>
                            </div>
                            <div class="quick-pos-modal-status" data-pos-client-product-status hidden></div>
                            <div class="quick-pos-modal-candidates" data-pos-client-product-candidates hidden></div>
                            <label class="quick-pos-modal-field is-wide">
                                <span>Ürün Adı</span>
                                <input type="text" name="ad" autocomplete="off">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Marka</span>
                                <input type="text" name="marka_uretici" autocomplete="off">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Kategori</span>
                                <select name="kategori_id">
                                    ${kategoriSecenekHtml()}
                                </select>
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Stok</span>
                                <input type="number" min="0" step="0.0001" name="stok_miktari" value="1">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Birim</span>
                                <select name="birim">
                                    ${birimSecenekHtml()}
                                </select>
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Alış Fiyatı</span>
                                <input type="number" min="0" step="0.01" name="alis_fiyati" value="0">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Satış Fiyatı</span>
                                <input type="number" min="0" step="0.01" name="satis_fiyati" value="0">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>KDV Oranı</span>
                                <select name="kdv_orani">
                                    ${kdvSecenekHtml()}
                                </select>
                            </label>
                            <label class="quick-pos-modal-toggle">
                                <input type="checkbox" name="kdv_dahil_mi">
                                <span>KDV dahil</span>
                            </label>
                            <div class="quick-pos-modal-tax-summary" data-pos-client-product-tax-summary></div>
                            <label class="quick-pos-modal-field">
                                <span>Görsel URL</span>
                                <input type="url" name="gorsel_url" autocomplete="off">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Görsel Dosyası</span>
                                <input type="file" name="gorsel_dosyasi" accept="image/*">
                            </label>
                            <div class="quick-pos-modal-product-preview" data-pos-client-product-preview hidden></div>
                            <div class="quick-pos-modal-actions">
                                <button type="button" class="quick-pos-modal-button is-secondary" data-pos-client-product-close>Vazgeç</button>
                                <button type="submit" class="quick-pos-modal-button is-primary">Kaydet ve Sepete Ekle</button>
                            </div>
                        </form>
                    </div>
                `;
                document.body.appendChild(modal);
                const form = modal.querySelector('[data-pos-client-product-form]');
                const barcodeInput = form?.elements?.barkod;
                let secilenGorselDataUrl = '';
                let secilenGorselKaynak = '';
                let gorselHazirlaniyor = false;
                barcodeInput?.focus();
                const statusYaz = (message, type = 'info') => {
                    const status = modal.querySelector('[data-pos-client-product-status]');
                    if (!status) return;
                    status.hidden = message === '';
                    status.textContent = message;
                    status.dataset.posStatusType = type;
                };

                const previewGuncelle = () => {
                    const preview = modal.querySelector('[data-pos-client-product-preview]');
                    const url = form?.elements?.gorsel_url?.value || '';
                    const previewUrl = secilenGorselDataUrl || url;
                    if (!preview) return;
                    if (!previewUrl) {
                        preview.hidden = true;
                        preview.innerHTML = '';
                        return;
                    }
                    preview.hidden = false;
                    const baslik = secilenGorselDataUrl
                        ? 'Kendi görseliniz kullanılacak'
                        : (secilenGorselKaynak === 'internet' ? 'İnternet görseli' : 'Görsel önizleme');
                    preview.innerHTML = `
                        <img src="${metniKacir(previewUrl)}" alt="">
                        <div class="quick-pos-modal-product-preview-info">
                            <strong>${metniKacir(baslik)}</strong>
                            <button type="button" class="quick-pos-modal-mini-button is-danger" data-pos-client-product-image-remove>Görseli Kaldır</button>
                        </div>
                    `;
                };
                const gorseliTemizle = () => {
                    secilenGorselDataUrl = '';
                    secilenGorselKaynak = '';
                    if (form?.elements?.gorsel_url) form.elements.gorsel_url.value = '';
                    if (form?.elements?.gorsel_dosyasi) form.elements.gorsel_dosyasi.value = '';
                    previewGuncelle();
                    statusYaz('Görsel kaldırıldı.', 'info');
                };

                const dosyayiDataUrlOku = (file) => new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.addEventListener('load', () => resolve(String(reader.result || '')));
                    reader.addEventListener('error', () => reject(new Error('Dosya okunamadı.')));
                    reader.readAsDataURL(file);
                });

                const gorseliKucult = (file) => new Promise((resolve, reject) => {
                    if (file.type === 'image/gif') {
                        dosyayiDataUrlOku(file).then(resolve).catch(reject);
                        return;
                    }

                    const objectUrl = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = () => {
                        URL.revokeObjectURL(objectUrl);
                        const maxEdge = 1200;
                        const ratio = Math.min(1, maxEdge / Math.max(img.width || maxEdge, img.height || maxEdge));
                        const width = Math.max(1, Math.round((img.width || maxEdge) * ratio));
                        const height = Math.max(1, Math.round((img.height || maxEdge) * ratio));
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const context = canvas.getContext('2d');
                        if (!context) {
                            reject(new Error('Görsel işlenemedi.'));
                            return;
                        }
                        context.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', .86));
                    };
                    img.onerror = () => {
                        URL.revokeObjectURL(objectUrl);
                        reject(new Error('Görsel okunamadı.'));
                    };
                    img.src = objectUrl;
                });

                const dosyaSeciminiIsle = async () => {
                    const file = form?.elements?.gorsel_dosyasi?.files?.[0] || null;
                    secilenGorselDataUrl = '';
                    if (!file) {
                        previewGuncelle();
                        return;
                    }
                    if (!file.type.startsWith('image/')) {
                        statusYaz('Sadece görsel dosyası seçebilirsiniz.', 'warning');
                        form.elements.gorsel_dosyasi.value = '';
                        previewGuncelle();
                        return;
                    }
                    if (file.size > 4 * 1024 * 1024) {
                        statusYaz('Görsel 4 MB üzerinde olmamalı.', 'warning');
                        form.elements.gorsel_dosyasi.value = '';
                        previewGuncelle();
                        return;
                    }
                    gorselHazirlaniyor = true;
                    statusYaz('Görsel hazırlanıyor...', 'info');

                    try {
                        secilenGorselDataUrl = await gorseliKucult(file);
                        secilenGorselKaynak = 'dosya';
                        previewGuncelle();
                        statusYaz('Kendi görseliniz hazırlandı ve internet görselinin yerine kullanılacak.', 'success');
                    } catch {
                        secilenGorselDataUrl = '';
                        secilenGorselKaynak = '';
                        statusYaz('Görsel dosyası okunamadı.', 'warning');
                        previewGuncelle();
                    } finally {
                        gorselHazirlaniyor = false;
                    }
                };

                const adayBilgisiniUygula = (candidate) => {
                    if (!candidate) return;
                    const name = (candidate.name || '').trim();
                    const brand = (candidate.brand || '').trim();
                    const image = (candidate.image || '').trim();
                    const sourceName = (candidate.source || 'Open Food Facts').trim();

                    if (name) {
                        form.elements.ad.value = name;
                        const title = modal.querySelector('[data-pos-client-product-title]');
                        if (title) title.textContent = name;
                    }
                    if (brand && form.elements.marka_uretici) {
                        form.elements.marka_uretici.value = brand;
                    }
                    if (image) {
                        form.elements.gorsel_url.value = image;
                        secilenGorselDataUrl = '';
                        secilenGorselKaynak = 'internet';
                        form.elements.gorsel_dosyasi.value = '';
                    }
                    previewGuncelle();
                    const source = modal.querySelector('[data-pos-client-product-source]');
                    if (source) source.textContent = sourceName;
                };

                const urunAdayiOlustur = (product, fallbackSource = 'Open Food Facts') => {
                    const brand = (product?.brands || '').trim();
                    let name = (product?.product_name_tr || product?.product_name || product?.generic_name || '').trim();
                    if (name && brand && !name.toLocaleLowerCase('tr-TR').includes(brand.toLocaleLowerCase('tr-TR'))) {
                        name = `${brand} ${name}`;
                    }

                    return {
                        name,
                        brand,
                        image: (product?.image_front_url || product?.image_url || '').trim(),
                        barcode: String(product?.code || product?._id || '').trim(),
                        source: fallbackSource,
                    };
                };

                const adaylariTekillestir = (items) => {
                    const seen = new Set();

                    return items
                        .filter((item) => item && (item.name || item.image))
                        .filter((item) => {
                            const key = `${item.barcode || ''}|${item.name || ''}|${item.image || ''}`.toLocaleLowerCase('tr-TR');
                            if (seen.has(key)) return false;
                            seen.add(key);

                            return true;
                        })
                        .slice(0, 8);
                };

                const barkodSaglayiciSonuclariniGetir = async (baseUrl, sourceName, barcode) => {
                    const fields = 'code,product_name,product_name_tr,generic_name,brands,image_front_url,image_url';
                    const productUrl = `${baseUrl}/api/v2/product/${encodeURIComponent(barcode)}.json?fields=${fields}`;
                    const searchUrl = `${baseUrl}/cgi/search.pl?search_terms=${encodeURIComponent(barcode)}&search_simple=1&action=process&json=1&page_size=8&fields=${fields}`;
                    const [productResult, searchResult] = await Promise.allSettled([
                        fetch(productUrl).then((response) => response.json()),
                        fetch(searchUrl).then((response) => response.json()),
                    ]);
                    const items = [];
                    const productJson = productResult.status === 'fulfilled' ? productResult.value : null;
                    if (productJson?.status === 1) items.push(urunAdayiOlustur(productJson.product || {}, sourceName));

                    const searchJson = searchResult.status === 'fulfilled' ? searchResult.value : null;
                    if (Array.isArray(searchJson?.products)) {
                        searchJson.products.forEach((product) => items.push(urunAdayiOlustur(product, sourceName)));
                    }

                    return items;
                };

                const barkodAramaDegeri = () => {
                    const raw = (form?.elements?.barkod?.value || '').trim();
                    const numeric = raw.replace(/\D/g, '');

                    return numeric.length >= 8 ? numeric : raw;
                };

                const adayListesiGoster = (candidates) => {
                    const wrap = modal.querySelector('[data-pos-client-product-candidates]');
                    if (!wrap) return;

                    if (!Array.isArray(candidates) || candidates.length < 2) {
                        wrap.hidden = true;
                        wrap.innerHTML = '';
                        return;
                    }

                    wrap.hidden = false;
                    wrap.innerHTML = `
                        <div class="quick-pos-modal-candidates-title">Birden fazla aday bulundu, ürün seçin</div>
                        ${candidates.map((candidate, index) => `
                            <button type="button" class="quick-pos-modal-candidate" data-pos-client-product-candidate="${index}">
                                <span class="quick-pos-modal-candidate-image">
                                    ${candidate.image ? `<img src="${metniKacir(candidate.image)}" alt="">` : 'Yok'}
                                </span>
                                <span class="quick-pos-modal-candidate-info">
                                    <strong>${metniKacir(candidate.name || 'Adsız ürün')}</strong>
                                    <small>${metniKacir(candidate.brand || candidate.barcode || candidate.source || '')}</small>
                                </span>
                            </button>
                        `).join('')}
                    `;
                    wrap.__quickPosCandidates = candidates;
                };

                const internettenAra = async () => {
                    const barcode = barkodAramaDegeri();
                    const source = modal.querySelector('[data-pos-client-product-source]');
                    const lookupButton = modal.querySelector('[data-pos-client-product-lookup]');
                    const candidateWrap = modal.querySelector('[data-pos-client-product-candidates]');
                    if (!barcode) {
                        statusYaz('Barkod okutun veya yazın.', 'warning');
                        barcodeInput?.focus();
                        return;
                    }
                    if (candidateWrap) {
                        candidateWrap.hidden = true;
                        candidateWrap.innerHTML = '';
                    }
                    if (form?.elements?.barkod && form.elements.barkod.value !== barcode) {
                        form.elements.barkod.value = barcode;
                    }
                    lookupButton?.setAttribute('disabled', 'disabled');
                    if (source) source.textContent = 'Aranıyor...';
                    statusYaz('Barkod bilgisi aranıyor...', 'info');

                    try {
                        const localCandidates = Array.isArray(yerelBarkodAdaylari[barcode])
                            ? adaylariTekillestir(yerelBarkodAdaylari[barcode])
                            : [];

                        if (localCandidates.length > 0) {
                            if (localCandidates.length > 1) {
                                adayListesiGoster(localCandidates);
                                adayBilgisiniUygula(localCandidates[0]);
                                statusYaz('Birden fazla ürün bulundu. Doğru ürünü listeden seçin.', 'info');
                                return;
                            }

                            adayBilgisiniUygula(localCandidates[0]);
                            statusYaz('Üretici kataloğundan bilgiler dolduruldu, fiyatı kontrol edip kaydedin.', 'success');
                            return;
                        }

                        const providerResults = await Promise.allSettled([
                            barkodSaglayiciSonuclariniGetir('https://world.openfoodfacts.org', 'Open Food Facts', barcode),
                            barkodSaglayiciSonuclariniGetir('https://world.openproductsfacts.org', 'Open Products Facts', barcode),
                        ]);
                        const items = providerResults.flatMap((result) => result.status === 'fulfilled' ? result.value : []);

                        const candidates = adaylariTekillestir(items);

                        if (candidates.length === 0) {
                            const backendResult = await callWith('hizliUrunInternetAdaylari', barcode);
                            const backendCandidates = adaylariTekillestir(Array.isArray(backendResult) ? backendResult : []);
                            if (backendCandidates.length === 1) {
                                adayBilgisiniUygula(backendCandidates[0]);
                                statusYaz('Tedarikçi kataloğundan bilgiler dolduruldu, fiyatı kontrol edip kaydedin.', 'success');
                                return;
                            }

                            if (backendCandidates.length > 1) {
                                adayListesiGoster(backendCandidates);
                                adayBilgisiniUygula(backendCandidates[0]);
                                statusYaz('Tedarikçi kataloglarında birden fazla ürün bulundu. Doğru ürünü listeden seçin.', 'info');
                                return;
                            }

                            if (source) source.textContent = 'Bulunamadı';
                            statusYaz('Ürün bilgisi bulunamadı. Manuel doldurabilirsiniz.', 'warning');
                            return;
                        }

                        if (candidates.length === 1) {
                            adayBilgisiniUygula(candidates[0]);
                            statusYaz('Bilgiler dolduruldu, fiyatı kontrol edip kaydedin.', 'success');
                            return;
                        }

                        adayListesiGoster(candidates);
                        adayBilgisiniUygula(candidates[0]);
                        statusYaz('Birden fazla ürün bulundu. Doğru ürünü listeden seçin.', 'info');
                    } catch {
                        if (source) source.textContent = 'Arama başarısız';
                        statusYaz('İnternet araması başarısız. Manuel kayıt yapabilirsiniz.', 'warning');
                    } finally {
                        lookupButton?.removeAttribute('disabled');
                    }
                };

                modal.addEventListener('click', (event) => {
                    if (event.target === modal || event.target.closest('[data-pos-client-product-close]')) {
                        event.preventDefault();
                        hizliUrunEkleModalKapat();
                    }
                    if (event.target.closest('[data-pos-client-product-lookup]')) {
                        event.preventDefault();
                        internettenAra();
                    }
                    if (event.target.closest('[data-pos-client-product-image-remove]')) {
                        event.preventDefault();
                        gorseliTemizle();
                    }
                    const candidateButton = event.target.closest('[data-pos-client-product-candidate]');
                    if (candidateButton) {
                        event.preventDefault();
                        const wrap = modal.querySelector('[data-pos-client-product-candidates]');
                        const candidates = Array.isArray(wrap?.__quickPosCandidates) ? wrap.__quickPosCandidates : [];
                        const candidate = candidates[Number.parseInt(candidateButton.dataset.posClientProductCandidate || '-1', 10)];
                        adayBilgisiniUygula(candidate);
                        wrap?.querySelectorAll('.quick-pos-modal-candidate').forEach((button) => button.classList.remove('is-selected'));
                        candidateButton.classList.add('is-selected');
                        statusYaz('Seçilen ürün forma aktarıldı.', 'success');
                    }
                });

                form?.elements?.gorsel_url?.addEventListener('input', previewGuncelle);
                form?.elements?.gorsel_dosyasi?.addEventListener('change', dosyaSeciminiIsle);
                form?.elements?.satis_fiyati?.addEventListener('input', () => urunEkleKdvOzetiGuncelle(form, modal));
                form?.elements?.kdv_orani?.addEventListener('change', () => urunEkleKdvOzetiGuncelle(form, modal));
                form?.elements?.kdv_dahil_mi?.addEventListener('change', () => urunEkleKdvOzetiGuncelle(form, modal));
                urunEkleKdvOzetiGuncelle(form, modal);
                barcodeInput?.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    internettenAra();
                });

                form?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (gorselHazirlaniyor) {
                        statusYaz('Görsel hazırlanıyor, bir saniye sonra tekrar deneyin.', 'info');
                        return;
                    }
                    const payload = {
                        barkod: (form.elements.barkod?.value || '').trim(),
                        ad: (form.elements.ad?.value || '').trim(),
                        markaUretici: (form.elements.marka_uretici?.value || '').trim(),
                        kategoriId: Number.parseInt(form.elements.kategori_id?.value || '0', 10) || null,
                        stokMiktari: Number.parseFloat(form.elements.stok_miktari?.value || '0') || 0,
                        birim: (form.elements.birim?.value || 'AD').trim().toLocaleUpperCase('tr-TR') || 'AD',
                        alisFiyati: Number.parseFloat(form.elements.alis_fiyati?.value || '0') || 0,
                        satisFiyati: Number.parseFloat(form.elements.satis_fiyati?.value || '0') || 0,
                        kdvOrani: Number.parseFloat(form.elements.kdv_orani?.value || '0') || 0,
                        kdvDahilMi: Boolean(form.elements.kdv_dahil_mi?.checked),
                        gorselUrl: (form.elements.gorsel_url?.value || '').trim(),
                        gorselDataUrl: secilenGorselDataUrl,
                    };
                    if (!payload.ad) {
                        statusYaz('Ürün adı zorunlu.', 'warning');
                        form.elements.ad?.focus();
                        return;
                    }
                    submitButton?.setAttribute('disabled', 'disabled');
                    if (submitButton) submitButton.textContent = 'Kaydediliyor...';
                    statusYaz('Stok kartı oluşturuluyor...', 'info');
                    const onizlemePayload = {
                        ...payload,
                        satisFiyati: payload.kdvDahilMi
                            ? kdvHesapla(payload.satisFiyati, payload.kdvOrani, true).net
                            : payload.satisFiyati,
                    };
                    yeniUrunOnizlemeSatiriEkle(onizlemePayload);
                    hizliUrunEkleModalKapat();
                    callWith('hizliUrunHizliKaydet', payload.barkod, payload.ad, payload.stokMiktari, payload.satisFiyati, payload.kdvOrani, payload.gorselUrl, payload.kdvDahilMi, payload.gorselDataUrl, payload.birim, payload.alisFiyati, payload.markaUretici, payload.kategoriId);
                });
            };
            const sepetSatiriToplamlariniGuncelle = () => {
                const currency = (document.querySelector('select[wire\\:model\\.live="data.para_birimi"]')?.value || 'TRY').trim() || 'TRY';
                let toplam = 0;
                let kdvToplami = 0;

                document.querySelectorAll('.quick-pos-table tbody tr').forEach((row) => {
                    const inputs = row.querySelectorAll('input.quick-pos-number');
                    const miktar = Number.parseFloat(inputs[0]?.value || row.querySelector('[data-label="Miktar"] .quick-pos-number')?.textContent?.replace(',', '.') || '0') || 0;
                    const fiyat = Number.parseFloat(inputs[1]?.value || row.querySelector('[data-label="Fiyat"] .quick-pos-number')?.textContent?.replace(/\./g, '').replace(',', '.') || '0') || 0;
                    const kdvOrani = Number.parseFloat(inputs[2]?.value || row.querySelector('[data-label="KDV"] .quick-pos-number')?.textContent?.replace(',', '.') || '0') || 0;
                    const tutar = Math.max(0, miktar * fiyat);
                    kdvToplami += Math.max(0, tutar * kdvOrani / 100);
                    toplam += tutar;
                    const tutarEl = row.querySelector('[data-label="Tutar"] strong');
                    if (tutarEl) tutarEl.textContent = paraBirimliYaz(tutar, currency);
                });

                document.querySelectorAll('.quick-pos-summary-item').forEach((item) => {
                    const label = item.querySelector('span')?.textContent || '';
                    const value = item.querySelector('strong');
                    if (!value) return;

                    if (label.includes('Ara') || label.includes('Genel')) {
                        if (label.includes('Genel')) value.dataset.posGrandTotal = toplam.toFixed(2);
                        value.textContent = paraBirimliYaz(toplam, currency);
                    }
                    if (label.includes('İskonto')) value.textContent = paraBirimliYaz(0, currency);
                    if (label.includes('KDV')) value.textContent = paraBirimliYaz(kdvToplami, currency);
                });
                document.querySelectorAll('.quick-pos-total strong').forEach((item) => {
                    item.textContent = paraBirimliYaz(toplam, currency);
                });
            };
            const sepetKalemIndeksleriniYenidenSirala = () => {
                document.querySelectorAll('.quick-pos-table tbody tr').forEach((row, index) => {
                    const edit = row.querySelector('[data-pos-cart-edit]');
                    if (edit) {
                        edit.dataset.posCartIndex = String(index);
                        edit.setAttribute('data-pos-cart-index', String(index));
                    }

                    const remove = row.querySelector('[data-pos-cart-delete]');
                    if (remove) {
                        remove.dataset.posCartIndex = String(index);
                        remove.setAttribute('data-pos-cart-index', String(index));
                    }
                });
            };
            const sepetKaleminiHizliSil = (button) => {
                const row = button?.closest('tr');
                const index = Number.parseInt(button?.dataset?.posCartIndex || '-1', 10);
                if (!row || index < 0) return;

                row.remove();
                sepetKalemIndeksleriniYenidenSirala();
                const tbody = document.querySelector('.quick-pos-table tbody');
                if (!tbody || tbody.querySelectorAll('tr').length === 0) {
                    sepetBosaltOnizleme();
                } else {
                    sepetSatiriToplamlariniGuncelle();
                }

                const request = callWith('hizliKalemSil', index);
                if (request && typeof request.catch === 'function') {
                    request.catch(() => {
                        window.location.reload();
                    });
                }
            };
            const hizliDuzenleSatiriGuncelle = (button, payload) => {
                const row = button?.closest('tr');
                if (!row) return;

                const name = row.querySelector('.quick-pos-cart-info strong');
                if (name) name.textContent = payload.ad;

                const inputs = row.querySelectorAll('input.quick-pos-number');
                if (inputs[1]) inputs[1].value = String(payload.sepetFiyati);
                if (inputs[2]) inputs[2].value = String(payload.kdvOrani);

                button.dataset.posStockName = payload.ad;
                button.dataset.posStockQuantity = String(payload.stokMiktari);
                button.dataset.posStockPrice = String(payload.satisFiyati);
                button.dataset.posStockDiscountPrice = String(payload.indirimliFiyat);
                button.dataset.posStockTax = String(payload.kdvOrani);
                sepetSatiriToplamlariniGuncelle();
            };
            const hizliDuzenleModalAc = (button) => {
                if (!button) return;
                hizliDuzenleModalKapat();

                const modal = document.createElement('div');
                modal.className = 'quick-pos-modal';
                modal.dataset.posClientEditModal = '1';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-label', 'Ürün hızlı düzenle');
                modal.innerHTML = `
                    <div class="quick-pos-modal-card">
                        <div class="quick-pos-modal-header">
                            <div>
                                <span>Hızlı Düzenle</span>
                                <strong>${metniKacir(button.dataset.posStockName || 'Ürün')}</strong>
                            </div>
                            <button type="button" class="quick-pos-modal-close" data-pos-client-edit-close aria-label="Kapat">X</button>
                        </div>
                        <form class="quick-pos-modal-form" data-pos-client-edit-form>
                            <label class="quick-pos-modal-field is-wide">
                                <span>Ürün İsmi</span>
                                <input type="text" name="ad" value="${metniKacir(button.dataset.posStockName || '')}" autocomplete="off">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Stok</span>
                                <input type="number" min="0" step="0.0001" name="stok_miktari" value="${metniKacir(button.dataset.posStockQuantity || '0')}">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>Satış Fiyatı</span>
                                <input type="number" min="0" step="0.01" name="satis_fiyati" value="${metniKacir(button.dataset.posStockPrice || '0')}">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>İndirimli Fiyat</span>
                                <input type="number" min="0" step="0.01" name="indirimli_fiyat" value="${metniKacir(button.dataset.posStockDiscountPrice || '0')}">
                            </label>
                            <label class="quick-pos-modal-field">
                                <span>KDV Oranı</span>
                                <input type="number" min="0" step="0.01" name="kdv_orani" value="${metniKacir(button.dataset.posStockTax || '0')}">
                            </label>
                            <div class="quick-pos-modal-actions">
                                <button type="button" class="quick-pos-modal-button is-secondary" data-pos-client-edit-close>Vazgeç</button>
                                <button type="submit" class="quick-pos-modal-button is-primary">Kaydet</button>
                            </div>
                        </form>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.querySelector('input[name="ad"]')?.focus();

                modal.addEventListener('click', (event) => {
                    if (event.target === modal || event.target.closest('[data-pos-client-edit-close]')) {
                        event.preventDefault();
                        hizliDuzenleModalKapat();
                    }
                });

                modal.querySelector('[data-pos-client-edit-form]')?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const form = event.currentTarget;
                    const ad = (form.elements.ad?.value || '').trim();
                    const stokMiktari = Number.parseFloat(form.elements.stok_miktari?.value || '0') || 0;
                    const satisFiyati = Number.parseFloat(form.elements.satis_fiyati?.value || '0') || 0;
                    const indirimliFiyat = Number.parseFloat(form.elements.indirimli_fiyat?.value || '0') || 0;
                    const kdvOrani = Number.parseFloat(form.elements.kdv_orani?.value || '0') || 0;
                    const stokId = Number.parseInt(button.dataset.posStockId || '0', 10) || 0;
                    if (!ad || stokId < 1) return;

                    const sepetFiyati = indirimliFiyat > 0 ? indirimliFiyat : satisFiyati;
                    hizliDuzenleSatiriGuncelle(button, { ad, stokMiktari, satisFiyati, indirimliFiyat, kdvOrani, sepetFiyati });
                    hizliDuzenleModalKapat();
                    callWith('hizliKalemHizliGuncelle', stokId, ad, stokMiktari, satisFiyati, indirimliFiyat, kdvOrani);
                });
            };
            const sepetOnizlemeSnapshot = () => {
                const rows = Array.from(document.querySelectorAll('.quick-pos-table tbody tr'));
                if (rows.length === 0) return null;

                const total = Number.parseFloat(document.querySelector('[data-pos-grand-total]')?.dataset.posGrandTotal || '0') || 0;
                const currency = (document.querySelector('select[wire\\:model\\.live="data.para_birimi"]')?.value || 'TRY').trim() || 'TRY';
                const kalemler = rows.map((row) => {
                    const name = row.querySelector('.quick-pos-cart-info strong')?.textContent?.trim() || '-';
                    const meta = row.querySelector('.quick-pos-cart-info span')?.textContent?.trim() || '- / -';
                    const [code = '-', barcode = '-'] = meta.split('/').map((item) => item.trim());
                    const inputs = row.querySelectorAll('input.quick-pos-number');
                    const image = row.querySelector('.quick-pos-cart-thumb img')?.getAttribute('src') || '';

                    return {
                        stok_adi: name,
                        stok_kod: code,
                        barkod: barcode,
                        gorsel_url: image,
                        miktar: Number.parseFloat(inputs[0]?.value || row.querySelector('[data-label="Miktar"] .quick-pos-number')?.textContent || '1') || 1,
                        birim: row.querySelector('[data-label="Miktar"] span:not(.quick-pos-number)')?.textContent?.trim() || 'AD',
                        birim_fiyat: Number.parseFloat(inputs[1]?.value || row.querySelector('[data-label="Fiyat"] .quick-pos-number')?.textContent?.replace(/\./g, '').replace(',', '.') || '0') || 0,
                        iskonto_tutari: 0,
                        kdv_orani: Number.parseFloat(inputs[2]?.value || '0') || 0,
                    };
                });

                return { para_birimi: currency, kalemler, toplam: total };
            };
            const bekleyenSepetOnizlemeOku = (heldItem) => {
                if (!heldItem) return null;
                if (heldItem.__quickPosHeldPreview) return heldItem.__quickPosHeldPreview;

                const script = heldItem.querySelector('[data-pos-held-preview]');
                if (!script) return null;

                try {
                    return JSON.parse(script.textContent || '{}');
                } catch {
                    return null;
                }
            };
            const sepetOnizlemeYukle = (snapshot) => {
                const kalemler = Array.isArray(snapshot?.kalemler) ? snapshot.kalemler : [];
                if (kalemler.length === 0) {
                    sepetBosaltOnizleme('Bekleyen sepet yükleniyor...');
                    return;
                }

                const table = sepetTablosuHazirla();
                const tbody = table?.querySelector('tbody');
                if (!tbody) return;

                const currency = snapshot?.para_birimi || 'TRY';
                let araToplam = 0;
                let iskontoToplami = 0;
                let kdvToplami = 0;
                let genelToplam = 0;
                tbody.innerHTML = kalemler.map((kalem) => {
                    const miktar = Number.parseFloat(kalem.miktar || 0) || 0;
                    const fiyat = Number.parseFloat(kalem.birim_fiyat || 0) || 0;
                    const iskonto = Number.parseFloat(kalem.iskonto_tutari || 0) || 0;
                    const kdvOrani = Number.parseFloat(kalem.kdv_orani || 0) || 0;
                    const tutar = Math.max(0, (miktar * fiyat) - iskonto);
                    araToplam += miktar * fiyat;
                    iskontoToplami += iskonto;
                    kdvToplami += tutar * (kdvOrani / 100);
                    genelToplam += tutar;
                    const image = kalem.gorsel_url
                        ? `<span class="quick-pos-cart-thumb"><img src="${metniKacir(kalem.gorsel_url)}" alt=""></span>`
                        : '<span class="quick-pos-cart-thumb">Görsel yok</span>';

                    return `
                        <tr class="is-pending">
                            <td data-label="Ürün">
                                <div class="quick-pos-cart-product">
                                    ${image}
                                    <span class="quick-pos-cart-info">
                                        <strong>${metniKacir(kalem.stok_adi || '-')}</strong>
                                        <span style="display:block; font-size: 11px; color: #52687a;">${metniKacir(kalem.stok_kod || '-')} / ${metniKacir(kalem.barkod || '-')}</span>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Miktar"><span class="quick-pos-number" style="display:inline-block;">${metniKacir(miktar.toLocaleString('tr-TR'))}</span><span style="font-size: 11px;"> ${metniKacir(kalem.birim || 'AD')}</span></td>
                            <td data-label="Fiyat"><span class="quick-pos-number" style="display:inline-block;">${paraBirimliYaz(fiyat, currency)}</span></td>
                            <td data-label="Tutar"><strong>${paraBirimliYaz(tutar, currency)}</strong></td>
                            <td data-label="KDV"><span class="quick-pos-number" style="display:inline-block;">${metniKacir(kdvOrani)}</span></td>
                            <td data-label="İşlem">
                                <div class="quick-pos-row-actions">
                                    <span class="quick-pos-row-action is-edit" style="display:inline-flex; align-items:center; justify-content:center;">✎</span>
                                    <span class="quick-pos-row-action is-delete quick-pos-delete" style="display:inline-flex; align-items:center; justify-content:center;">⌫</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                const toplam = Number.parseFloat(snapshot?.toplam || genelToplam || 0) || genelToplam;
                document.querySelectorAll('.quick-pos-summary-item').forEach((item) => {
                    const label = item.querySelector('span')?.textContent || '';
                    const value = item.querySelector('strong');
                    if (!value) return;

                    if (label.includes('Ara')) value.textContent = paraBirimliYaz(araToplam, currency);
                    if (label.includes('İskonto')) value.textContent = paraBirimliYaz(iskontoToplami, currency);
                    if (label.includes('KDV')) value.textContent = paraBirimliYaz(kdvToplami, currency);
                    if (label.includes('Genel')) {
                        value.dataset.posGrandTotal = toplam.toFixed(2);
                        value.textContent = paraBirimliYaz(toplam, currency);
                    }
                });
                document.querySelectorAll('.quick-pos-total strong').forEach((item) => {
                    item.textContent = paraBirimliYaz(toplam, currency);
                });
            };
            const bekleyenSepetKapsayici = () => {
                const actions = document.querySelector('.quick-pos-actions');
                if (!actions) return null;

                let held = actions.querySelector('.quick-pos-held-carts');
                if (held) return held;

                held = document.createElement('div');
                held.className = 'quick-pos-held-carts';
                held.setAttribute('aria-label', 'Bekleyen sepetler');
                held.innerHTML = '<span class="quick-pos-held-title"><span>Bekleyen</span><span>Sepet</span></span>';
                actions.appendChild(held);

                return held;
            };
            const bekleyenSepetEtiketi = () => {
                const now = new Date();
                const pad = (value) => String(value).padStart(2, '0');

                return `Sepet ${pad(now.getDate())}.${pad(now.getMonth() + 1)} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
            };
            const bekleyenSepetIndeksleriniKaydir = (delta) => {
                document.querySelectorAll('[data-pos-held-cart]').forEach((item) => {
                    const mevcut = Number.parseInt(item.dataset.posHeldIndex || '0', 10) || 0;
                    const yeni = Math.max(0, mevcut + delta);
                    item.dataset.posHeldIndex = String(yeni);

                    const load = item.querySelector('[data-pos-held-load]');
                    if (load) {
                        load.dataset.posHeldLoad = String(yeni);
                        load.setAttribute('data-pos-held-load', String(yeni));
                    }

                    const remove = item.querySelector('[data-pos-held-delete]');
                    if (remove) {
                        remove.dataset.posHeldDelete = String(yeni);
                        remove.setAttribute('data-pos-held-delete', String(yeni));
                    }
                });
            };
            const bekleyenSepetIndeksleriniYenidenSirala = () => {
                document.querySelectorAll('[data-pos-held-cart]').forEach((item, index) => {
                    item.dataset.posHeldIndex = String(index);

                    const load = item.querySelector('[data-pos-held-load]');
                    if (load) {
                        load.dataset.posHeldLoad = String(index);
                        load.setAttribute('data-pos-held-load', String(index));
                    }

                    const remove = item.querySelector('[data-pos-held-delete]');
                    if (remove) {
                        remove.dataset.posHeldDelete = String(index);
                        remove.setAttribute('data-pos-held-delete', String(index));
                    }
                });
            };
            const bekleyenSepetOnizlemeTamamla = (item) => {
                if (!item) return;

                item.classList.remove('is-pending');
                item.querySelectorAll('button').forEach((button) => {
                    button.disabled = false;
                });
            };
            const bekleyenSepetOnizlemeEkle = () => {
                const held = bekleyenSepetKapsayici();
                if (!held) return null;

                bekleyenSepetIndeksleriniKaydir(1);
                const item = document.createElement('span');
                item.className = 'quick-pos-held-cart';
                item.dataset.posHeldCart = '1';
                item.dataset.posHeldIndex = '0';
                item.dataset.posClientHeldCart = '1';
                item.innerHTML = `
                    <span class="quick-pos-held-name"></span>
                    <button type="button" class="quick-pos-held-action" data-pos-held-load="0">Yükle</button>
                    <button type="button" class="quick-pos-held-action is-danger" data-pos-held-delete="0">Sil</button>
                `;
                const name = item.querySelector('.quick-pos-held-name');
                if (name) name.textContent = bekleyenSepetEtiketi();
                held.insertBefore(item, held.children[1] || null);

                return item;
            };
            const bekleyenSepetSatiriniKaldir = (button) => {
                const item = button?.closest('[data-pos-held-cart], [data-pos-client-held-cart]');
                item?.remove();

                const held = document.querySelector('.quick-pos-held-carts');
                if (held && held.querySelectorAll('.quick-pos-held-cart, [data-pos-client-held-cart]').length === 0) {
                    held.remove();
                    return;
                }

                bekleyenSepetIndeksleriniYenidenSirala();
            };
            const iyimserSepetSatiriEkle = (card) => {
                if (!card || card.closest('[wire\\:loading], [aria-disabled="true"]')) return;

                const table = sepetTablosuHazirla();
                const tbody = table?.querySelector('tbody');
                if (!tbody) return;

                const id = card.dataset.posProductId || '';
                const name = card.dataset.posProductName || 'Ürün';
                const code = card.dataset.posProductCode || '-';
                const barcode = card.dataset.posProductBarcode || '-';
                const price = card.dataset.posProductPrice || '';
                const image = card.dataset.posProductImage || '';
                const mevcutSatir = id ? tbody.querySelector(`[data-pos-cart-product-id="${CSS.escape(id)}"], [data-pos-pending-product-id="${CSS.escape(id)}"]`) : null;
                if (mevcutSatir) {
                    const miktarInput = mevcutSatir.querySelector('[data-label="Miktar"] input.quick-pos-number');
                    const miktarText = mevcutSatir.querySelector('[data-label="Miktar"] .quick-pos-number');
                    const mevcutMiktar = Number.parseFloat((miktarInput?.value || miktarText?.textContent || '0').replace(',', '.')) || 0;
                    const yeniMiktar = mevcutMiktar + 1;
                    if (miktarInput) {
                        miktarInput.value = String(yeniMiktar);
                    } else if (miktarText) {
                        miktarText.textContent = yeniMiktar.toLocaleString('tr-TR');
                    }
                    const fiyat = parasalCoz(mevcutSatir.querySelector('[data-label="Fiyat"] .quick-pos-number')?.textContent || price);
                    const tutar = mevcutSatir.querySelector('[data-label="Tutar"] strong');
                    if (tutar) tutar.textContent = paraBirimliYaz(yeniMiktar * fiyat, paraBirimi());
                    sepetSatiriToplamlariniGuncelle();

                    return;
                }
                const row = document.createElement('tr');
                row.dataset.posPendingProductId = id;
                row.dataset.posCartProductId = id;

                const imageHtml = image
                    ? `<span class="quick-pos-cart-thumb"><img src="${image.replaceAll('"', '&quot;')}" alt=""></span>`
                    : '<span class="quick-pos-cart-thumb">Ekleniyor</span>';

                row.innerHTML = `
                    <td data-label="Ürün">
                        <div class="quick-pos-cart-product">
                            ${imageHtml}
                            <span class="quick-pos-cart-info">
                                <strong></strong>
                                <span style="display:block; font-size: 11px; color: #52687a;"></span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Miktar"><span class="quick-pos-number" style="display:inline-block;">1</span><span style="font-size: 11px;"> AD</span></td>
                    <td data-label="Fiyat"><span class="quick-pos-number" style="display:inline-block;">${price}</span></td>
                    <td data-label="Tutar"><strong>${price}</strong></td>
                    <td data-label="KDV"><span class="quick-pos-number" style="display:inline-block;">...</span></td>
                            <td data-label="İşlem">
                                <div class="quick-pos-row-actions">
                                    <span class="quick-pos-row-action is-edit" style="display:inline-flex; align-items:center; justify-content:center;">✎</span>
                                    <span class="quick-pos-row-action is-delete quick-pos-delete" style="display:inline-flex; align-items:center; justify-content:center;">⌫</span>
                                </div>
                            </td>
                `;
                row.querySelector('.quick-pos-cart-info strong').textContent = name;
                row.querySelector('.quick-pos-cart-info span').textContent = `${code} / ${barcode}`;
                tbody.appendChild(row);
                sepetSatiriToplamlariniGuncelle();
            };
            const topluUrunEklemeKuyrugu = [];
            let topluUrunEklemeZamanlayici = null;
            const topluUrunEklemeGonder = () => {
                if (topluUrunEklemeZamanlayici) {
                    clearTimeout(topluUrunEklemeZamanlayici);
                    topluUrunEklemeZamanlayici = null;
                }

                const ids = topluUrunEklemeKuyrugu.splice(0, topluUrunEklemeKuyrugu.length);
                if (ids.length === 0) return;

                callWith('hizliUrunKartlariniTopluEkle', ids);
            };
            const urunKartiniTopluEklemeKuyrugunaAl = (card) => {
                if (!card || card.closest('[wire\\:loading], [aria-disabled="true"]')) return;

                const productId = Number.parseInt(card.dataset.posProductId || '0', 10) || 0;
                if (productId < 1) return;

                card.classList.add('is-fast-feedback');
                setTimeout(() => card.classList.remove('is-fast-feedback'), 180);
                iyimserSepetSatiriEkle(card);
                topluUrunEklemeKuyrugu.push(productId);

                if (topluUrunEklemeZamanlayici) clearTimeout(topluUrunEklemeZamanlayici);
                topluUrunEklemeZamanlayici = setTimeout(topluUrunEklemeGonder, 35);
            };

            document.addEventListener('livewire:initialized', () => {
                focusById('pos-barkod-input');

                if (window.Livewire && typeof window.Livewire.on === 'function') {
                    window.Livewire.on('barkod-odakla', () => focusById('pos-barkod-input'));
                    window.Livewire.on('satis-fisi-ac', (payload = {}) => {
                        const url = payload?.url ?? null;
                        if (url) window.open(url, '_blank', 'noopener,noreferrer');
                    });
                }
            });

            document.addEventListener('click', (event) => {
                const favoriteButton = event.target.closest('[data-pos-favorite-button]');
                if (favoriteButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    favoriButonuHizliDegistir(favoriteButton);
                    return;
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                const favoriteButton = event.target.closest?.('[data-pos-favorite-button]');
                if (!favoriteButton || !['Enter', ' '].includes(event.key)) return;

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                favoriButonuHizliDegistir(favoriteButton);
            }, true);

            document.addEventListener('click', (event) => {
                const queuedProductCard = event.target.closest('[data-pos-product-card]');
                if (queuedProductCard && !event.target.closest('.quick-pos-favorite-button')) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    urunKartiniTopluEklemeKuyrugunaAl(queuedProductCard);
                    return;
                }

                const queuedClientSearchResult = event.target.closest('[data-pos-client-search-result]');
                if (queuedClientSearchResult) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    urunKartiniTopluEklemeKuyrugunaAl(queuedClientSearchResult);
                    return;
                }

                const productAdd = event.target.closest('[data-pos-product-add]');
                if (productAdd) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    hizliUrunEkleModalAc();
                    return;
                }

                const cartDelete = event.target.closest('[data-pos-cart-delete]');
                if (cartDelete) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    sepetKaleminiHizliSil(cartDelete);
                    return;
                }

                const cartEdit = event.target.closest('[data-pos-cart-edit]');
                if (cartEdit) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    hizliDuzenleModalAc(cartEdit);
                    return;
                }

                const holdCart = event.target.closest('[data-pos-hold-cart]');
                if (holdCart) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    const hasItems = !!document.querySelector('.quick-pos-table tbody tr');
                    const heldSnapshot = sepetOnizlemeSnapshot();
                    let pendingHeld = null;
                    if (hasItems) {
                        pendingHeld = bekleyenSepetOnizlemeEkle();
                        if (pendingHeld && heldSnapshot) pendingHeld.__quickPosHeldPreview = heldSnapshot;
                        sepetBosaltOnizleme();
                    }
                    const request = callWith('hizliSepetBeklet');
                    if (request && typeof request.then === 'function') {
                        request
                            .then(() => bekleyenSepetOnizlemeTamamla(pendingHeld))
                            .catch(() => bekleyenSepetOnizlemeTamamla(pendingHeld));
                    } else {
                        setTimeout(() => bekleyenSepetOnizlemeTamamla(pendingHeld), 250);
                    }
                    return;
                }

                const clearCart = event.target.closest('[data-pos-clear-cart]');
                if (clearCart) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    sepetBosaltOnizleme();
                    callWith('hizliSepetiTemizle');
                    return;
                }

                const heldLoad = event.target.closest('[data-pos-held-load]');
                if (heldLoad) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    const index = Number.parseInt(heldLoad.dataset.posHeldLoad || '0', 10) || 0;
                    const heldItem = heldLoad.closest('[data-pos-held-cart]');
                    const preview = bekleyenSepetOnizlemeOku(heldItem);
                    if (preview) {
                        sepetOnizlemeYukle(preview);
                    } else {
                        sepetBosaltOnizleme('Bekleyen sepet yükleniyor...');
                    }
                    callWith('bekleyenSepetiYukle', index, false);
                    return;
                }

                const heldDelete = event.target.closest('[data-pos-held-delete]');
                if (heldDelete) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    const index = Number.parseInt(heldDelete.dataset.posHeldDelete || '0', 10) || 0;
                    bekleyenSepetSatiriniKaldir(heldDelete);
                    callWith('hizliBekleyenSepetiSil', index);
                    return;
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                const barcodeInput = event.target.closest?.('[data-pos-quick-product-barcode]');
                if (!barcodeInput || event.key !== 'Enter') return;

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                const value = (barcodeInput.value || '').trim();
                if (value !== '') callWith('hizliUrunBarkoddanAra', value);
            }, true);

            document.addEventListener('click', (event) => {
                const feedbackTarget = event.target.closest('.quick-pos-button, .quick-pos-pay-button, .quick-pos-banknote-button, .quick-pos-held-action, .quick-pos-search-result, .quick-pos-search-more, .quick-pos-search-close, .quick-pos-row-action, .quick-pos-delete, .quick-pos-cash-box, .quick-pos-favorite-button');
                if (feedbackTarget) {
                    feedbackTarget.classList.add('is-fast-feedback');
                    setTimeout(() => feedbackTarget.classList.remove('is-fast-feedback'), 180);
                }

                if (event.target.closest('[data-pos-search-clear]')) {
                    event.preventDefault();
                    event.stopPropagation();
                    const input = document.getElementById('pos-hizli-ara-input');
                    if (input) input.value = '';
                    aramaOnizlemeLimit = 8;
                    aramaPaneliniKapat();
                    call('hizliUrunAramayiTemizle');
                    focusById('pos-hizli-ara-input');
                    return;
                }

                if (event.target.closest('[data-pos-search-more-client]')) {
                    event.preventDefault();
                    event.stopPropagation();
                    aramaOnizlemeLimit += 8;
                    aramaPaneliniGoster(document.getElementById('pos-hizli-ara-input')?.value || '');
                    call('hizliUrunAramaDahaFazla');
                    return;
                }

                if (event.target.closest('[data-pos-search-more]')) {
                    aramaOnizlemeLimit += 8;
                    aramaPaneliniGoster(document.getElementById('pos-hizli-ara-input')?.value || '');
                }

                const payButton = event.target.closest('[data-pos-pay-button]');
                if (payButton && payButton.dataset.posPayButton) {
                    odemeButonuAktiflestir(payButton.dataset.posPayButton);
                }

                const banknoteButton = event.target.closest('[data-pos-banknote]');
                if (banknoteButton) {
                    kupurEkle(Number.parseFloat(banknoteButton.dataset.posBanknote || '0') || 0);
                }

                const productTab = event.target.closest('[data-pos-product-tab]');
                if (productTab) {
                    kategoriSekmesiniAktiflestir(productTab);
                }

                const productCard = event.target.closest('[data-pos-product-card]');
                if (productCard && !event.target.closest('.quick-pos-favorite-button')) {
                    event.preventDefault();
                    event.stopPropagation();
                    urunKartiniTopluEklemeKuyrugunaAl(productCard);
                    return;
                }

                const clientSearchResult = event.target.closest('[data-pos-client-search-result]');
                if (clientSearchResult) {
                    event.preventDefault();
                    event.stopPropagation();
                    urunKartiniTopluEklemeKuyrugunaAl(clientSearchResult);
                    return;
                }

                const trigger = event.target.closest('[data-pos-image-preview-src]');
                if (trigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    previewAc(trigger.getAttribute('data-pos-image-preview-src'), trigger.getAttribute('data-pos-image-preview-title'));
                    return;
                }

                if (event.target.closest('[data-pos-image-preview-close]')) {
                    event.preventDefault();
                    previewKapat();
                    return;
                }

                if (event.target.closest('[data-pos-image-preview-zoom-in]')) {
                    event.preventDefault();
                    previewScaleAta(previewScale + .25);
                    return;
                }

                if (event.target.closest('[data-pos-image-preview-zoom-out]')) {
                    event.preventDefault();
                    previewScaleAta(previewScale - .25);
                    return;
                }

                if (event.target.closest('[data-pos-image-preview-reset]')) {
                    event.preventDefault();
                    previewScaleAta(1);
                    return;
                }

                if (event.target.matches('[data-pos-image-preview]')) {
                    previewKapat();
                }
            });

            document.addEventListener('input', (event) => {
                const productSearch = event.target.closest('[data-pos-product-search]');
                if (productSearch) {
                    const value = productSearch.value || '';
                    aramaOnizlemeLimit = 8;
                    if (value.trim().length >= 2) {
                        aramaPaneliniGoster(value);
                    } else {
                        aramaPaneliniKapat();
                    }
                    return;
                }

                const input = event.target.closest('[data-pos-cari-search]');
                if (!input) return;
                cariAramaDegeriniIsle(input);
            });

            document.addEventListener('change', (event) => {
                const input = event.target.closest('[data-pos-cari-search]');
                if (!input) return;
                cariAramaDegeriniIsle(input);
            });

            document.addEventListener('wheel', (event) => {
                if (preview()?.hidden) return;
                if (!event.target.closest('[data-pos-image-preview-stage]')) return;
                event.preventDefault();
                previewScaleAta(previewScale + (event.deltaY < 0 ? .15 : -.15));
            }, { passive: false });

            document.addEventListener('keydown', (event) => {
                if (!preview()?.hidden) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        previewKapat();
                        return;
                    }
                    if (event.key === '+' || event.key === '=') {
                        event.preventDefault();
                        previewScaleAta(previewScale + .25);
                        return;
                    }
                    if (event.key === '-') {
                        event.preventDefault();
                        previewScaleAta(previewScale - .25);
                        return;
                    }
                    if (event.key === '0') {
                        event.preventDefault();
                        previewScaleAta(1);
                        return;
                    }
                }
                if (event.key === 'F2') {
                    event.preventDefault();
                    focusById('pos-barkod-input');
                    return;
                }
                if (event.ctrlKey && (event.key === 'f' || event.key === 'F')) {
                    event.preventDefault();
                    focusById('pos-hizli-ara-input');
                    return;
                }
                if (event.altKey && event.key === '1') {
                    event.preventDefault();
                    callWith('odemeTipiSec', 'nakit');
                    return;
                }
                if (event.altKey && event.key === '2') {
                    event.preventDefault();
                    callWith('odemeTipiSec', 'kart');
                    return;
                }
                if (event.altKey && event.key === '3') {
                    event.preventDefault();
                    callWith('odemeTipiSec', 'havale');
                    return;
                }
                if (event.key === 'F8') {
                    event.preventDefault();
                    focusById('pos-odeme-tipi-input');
                    return;
                }
                if (event.key === 'F4') {
                    event.preventDefault();
                    callWith('hizliSepetBeklet');
                    return;
                }
                if (event.key === 'F6') {
                    event.preventDefault();
                    if (window.confirm('Mevcut sepeti iptal edip temizlemek istiyor musunuz?')) callWith('hizliSepetiTemizle');
                    return;
                }
                if (event.key === 'F9') {
                    event.preventDefault();
                    call('satisiTamamla');
                    return;
                }
                if (event.key === 'F7' && event.shiftKey) {
                    event.preventDefault();
                    call('seciliKalemMiktarAzalt');
                    return;
                }
                if (event.key === 'F7') {
                    event.preventDefault();
                    call('seciliKalemMiktarArttir');
                    return;
                }
                if (event.key === 'F10') {
                    event.preventDefault();
                    if (iadeUrl) window.location.href = iadeUrl;
                    return;
                }
                if (event.key === 'Delete' && !aktifCanliInputMu()) {
                    event.preventDefault();
                    call('seciliKalemSil');
                }
            });
        })();
