@extends('front.layouts.app')

@section('title', __('front.cart.title'))
@section('canonical_url', route('cart.index'))
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
        $aktifParaBirimi = $fiyatServisi->aktifParaBirimi();
        $aktifFirmaId = (int) (session('aktif_firma_id') ?? 0);
        $gecmisSepetKalemleri = $sepet->kalemler->map(function ($kalem) use ($fiyatServisi) {
            $kalemParaBirimi = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));

            return [
                'slug' => (string) ($kalem->stokKarti?->slug ?? ''),
                'ad' => (string) ($kalem->urun_adi_snapshot ?? ''),
                'miktar' => (float) $kalem->miktar,
                'birim_fiyat' => (float) $kalem->birim_fiyat,
                'para_birimi' => $kalemParaBirimi,
                'birim_fiyat_try' => $fiyatServisi->cevir((float) $kalem->birim_fiyat, $kalemParaBirimi, 'TRY'),
            ];
        })->filter(fn ($kalem) => $kalem['slug'] !== '')->values();
    @endphp

    <style>
        .cart-page-shell {
            width: 100%;
            max-width: 1220px;
            margin: 0 auto;
            padding-left: 0;
            padding-right: 0;
        }

        .cart-table-card,
        .cart-summary-card,
        .cart-history-card {
            background: #ffffff;
            border: 1px solid rgba(15, 76, 129, 0.09);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(15, 76, 129, 0.08);
        }

        .cart-table-wrap {
            overflow-x: auto;
            padding: 6px 18px 12px;
        }

        .cart-table {
            margin-bottom: 0;
            min-width: 920px;
            vertical-align: middle;
        }

        .cart-table thead th {
            border-bottom: 1px solid rgba(15, 76, 129, 0.12);
            color: #20405f;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            padding: 18px 14px;
            white-space: nowrap;
        }

        .cart-table tbody td {
            border-bottom: 1px solid rgba(15, 76, 129, 0.08);
            padding: 18px 14px;
        }

        .cart-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .cart-image-button {
            width: 72px;
            height: 72px;
            padding: 0;
            border: 0;
            border-radius: 18px;
            overflow: hidden;
            background: #f4f7fb;
            box-shadow: 0 10px 24px rgba(15, 76, 129, 0.12);
            cursor: zoom-in;
        }

        .cart-image-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .cart-product-name {
            display: inline-block;
            font-weight: 700;
            color: #14324d;
            line-height: 1.4;
            min-width: 220px;
        }

        a.cart-product-name {
            text-decoration: none;
        }

        a.cart-product-name:hover {
            color: #d71920;
            text-decoration: underline;
        }

        .cart-qty-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cart-qty-input {
            max-width: 108px;
            min-width: 86px;
        }

        .cart-money {
            font-weight: 700;
            color: #183654;
            white-space: nowrap;
        }

        .cart-summary-card {
            margin-top: 28px;
            padding: 24px 26px;
        }

        .cart-summary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
            gap: 24px;
            align-items: start;
        }

        .cart-summary-values p {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 12px;
            color: #193956;
        }

        .cart-summary-values p strong {
            font-weight: 800;
        }

        .cart-summary-values p span {
            font-weight: 700;
            white-space: nowrap;
        }

        .cart-history-card {
            padding: 18px 20px;
        }

        .cart-image-lightbox {
            position: fixed;
            inset: 0;
            background: rgba(7, 19, 31, 0.78);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 1600;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
        }

        .cart-image-lightbox.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .cart-image-lightbox-dialog {
            position: relative;
            max-width: min(92vw, 820px);
            max-height: 88vh;
        }

        .cart-image-lightbox img {
            width: auto;
            max-width: 100%;
            max-height: 88vh;
            display: block;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
            background: #fff;
        }

        .cart-image-lightbox-close {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 42px;
            height: 42px;
            border: 0;
            border-radius: 999px;
            background: #fff;
            color: #14324d;
            font-size: 24px;
            line-height: 1;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
        }

        @media (max-width: 991.98px) {
            .cart-summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .cart-page-shell {
                max-width: none;
            }

            .cart-table-card,
            .cart-summary-card,
            .cart-history-card {
                border-radius: 18px;
            }

            .cart-table-wrap {
                padding: 0 0 10px;
            }

            .cart-summary-card {
                padding: 18px 16px;
            }

            .cart-history-card {
                padding: 16px 14px;
            }

            .cart-image-button {
                width: 64px;
                height: 64px;
                border-radius: 16px;
            }
        }
    </style>

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.cart.title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="cart-page-shell">
        @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($sepet->kalemler->isEmpty())
            <div class="alert alert-info">{{ __('front.cart.empty') }}</div>
            <a href="{{ route('products.index') }}" class="btn-default">{{ __('front.cart.back_to_products') }}</a>
        @else
            <div class="cart-table-card">
            <div class="cart-table-wrap">
                <table class="table cart-table">
                    <thead>
                    <tr>
                        <th>Görsel</th>
                        <th>{{ __('front.cart.product') }}</th>
                        <th>{{ __('front.cart.unit_price') }}</th>
                        <th>{{ __('front.cart.quantity') }}</th>
                        <th>Toplam Fiyat</th>
                        <th>İndirim Tutarı</th>
                        <th>KDV</th>
                        <th>{{ __('front.cart.line_total') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($sepet->kalemler as $kalem)
                        @php
                            $stokKarti = $kalem->stokKarti;
                            $gorselYolu = $stokKarti?->og_gorsel;
                            $gorselUrl = $stokKarti?->kapak_gorsel_url
                                ?: ($gorselYolu
                                    ? asset('uploads/' . ltrim(str_replace('\\', '/', $gorselYolu), '/'))
                                    : asset('theme/yalovakamera/images/yalova_kamera.png'));
                            $kalemParaBirimi = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));
                            $kalemKdvOrani = (float) ($kalem->kdv_orani ?? 0);
                            $adet = (float) $kalem->miktar;
                            $urunUrl = $stokKarti?->slug ? route('products.show', $stokKarti->slug) : null;
                            $listeBirimFiyat = round((float) ($stokKarti?->satis_fiyati ?: $kalem->birim_fiyat), 2);
                            $indirimsizToplam = round($listeBirimFiyat * $adet, 2);
                            $indirimTutari = round(max(0, $indirimsizToplam - (float) $kalem->satir_toplami), 2);
                            $araToplam = round((float) $kalem->satir_toplami, 2);
                            $kdvTutari = round($araToplam * ($kalemKdvOrani / 100), 2);
                            $satirToplami = round($araToplam + $kdvTutari, 2);
                            $sonBirimFiyat = $adet > 0 ? round($satirToplami / $adet, 2) : $satirToplami;
                        @endphp
                        <tr>
                            <td>
                                <button
                                    type="button"
                                    class="cart-image-button"
                                    data-cart-image-trigger
                                    data-cart-image-src="{{ $gorselUrl }}"
                                    data-cart-image-alt="{{ $kalem->urun_adi_snapshot }}"
                                    aria-label="Ürün görselini büyüt"
                                >
                                    <img src="{{ $gorselUrl }}" alt="{{ $kalem->urun_adi_snapshot }}" loading="lazy" decoding="async">
                                </button>
                            </td>
                            <td>
                                @if($urunUrl)
                                    <a href="{{ $urunUrl }}" class="cart-product-name">{{ $kalem->urun_adi_snapshot }}</a>
                                @else
                                    <div class="cart-product-name">{{ $kalem->urun_adi_snapshot }}</div>
                                @endif
                            </td>
                            <td class="cart-money">{{ $fiyatServisi->cevirVeFormatla($sonBirimFiyat, $kalemParaBirimi) }}</td>
                            <td>
                                <form action="{{ route('cart.update', $kalem->id) }}" method="POST" class="cart-qty-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="miktar" value="{{ $adet }}" min="1" step="1" class="form-control cart-qty-input">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('front.cart.update') }}</button>
                                </form>
                            </td>
                            <td class="cart-money">{{ $fiyatServisi->cevirVeFormatla($indirimsizToplam, $kalemParaBirimi) }}</td>
                            <td class="cart-money">-{{ $fiyatServisi->cevirVeFormatla($indirimTutari, $kalemParaBirimi) }}</td>
                            <td class="cart-money">{{ $fiyatServisi->cevirVeFormatla($kdvTutari, $kalemParaBirimi) }}</td>
                            <td class="cart-money">{{ $fiyatServisi->cevirVeFormatla($satirToplami, $kalemParaBirimi) }}</td>
                            <td>
                                <form action="{{ route('cart.remove', $kalem->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('front.cart.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            </div>

            <div class="cart-summary-card">
                <div class="cart-summary-grid">
                    <div>
                        <form action="{{ route('cart.coupon') }}" method="POST" class="row g-2 mb-3">
                            @csrf
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="kupon_kodu" placeholder="{{ __('front.cart.coupon_placeholder') }}" value="{{ $kuponKodu ?? '' }}">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-outline-primary w-100">{{ __('front.cart.apply') }}</button>
                            </div>
                        </form>
                    </div>
                    <div class="cart-summary-values">
                        <p><strong>{{ __('front.cart.sub_total') }}</strong><span>{{ $fiyatServisi->formatla((float) $toplamlar['ara_toplam'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span></p>
                        @if((float) ($toplamlar['indirim_toplami'] ?? 0) > 0)
                            <p><strong>{{ __('front.cart.discount') }}</strong><span>-{{ $fiyatServisi->formatla((float) $toplamlar['indirim_toplami'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span></p>
                        @endif
                        <p><strong>{{ __('front.cart.vat') }}</strong><span>{{ $fiyatServisi->formatla((float) $toplamlar['kdv_toplam'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span></p>
                        <p><strong>{{ __('front.cart.grand_total') }}</strong><span>{{ $fiyatServisi->formatla((float) $toplamlar['genel_toplam'], (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi)) }}</span></p>
                        @if(! empty($toplamlar['uygulanan_kampanya']))
                            <p class="text-success mb-3">
                                <strong>{{ __('front.cart.campaign_applied') }}</strong>
                                <span>{{ $toplamlar['uygulanan_kampanya']['ad'] }}</span>
                            </p>
                        @endif
                        @php
                            $toplamParaBirimi = (string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi);
                            $satirBazliToplamIndirim = $sepet->kalemler->sum(function ($kalem) use ($fiyatServisi, $toplamParaBirimi) {
                                $stokKarti = $kalem->stokKarti;
                                if (! $stokKarti) {
                                    return 0;
                                }

                                $paraBirimi = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));
                                $listeBirimFiyat = round((float) ($stokKarti->satis_fiyati ?: 0), 2);
                                $gercekBirimFiyat = round((float) $kalem->birim_fiyat, 2);

                                $indirim = max(0, ($listeBirimFiyat - $gercekBirimFiyat) * (float) $kalem->miktar);

                                return $fiyatServisi->cevir($indirim, $paraBirimi, $toplamParaBirimi);
                            });
                        @endphp
                        <p class="text-muted small mb-3">
                            <strong>Toplam İndirim Açıklaması</strong>
                            <span>{{ $fiyatServisi->formatla((float) $satirBazliToplamIndirim, $toplamParaBirimi) }}</span>
                        </p>
                        <a href="{{ route('checkout.index') }}" class="btn-default">{{ __('front.cart.checkout') }}</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="cart-history-card mt-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h5 class="mb-0">{{ __('front.cart.history_title') }}</h5>
                <small class="text-muted">{{ __('front.cart.history_desc') }}</small>
            </div>
            <div id="sepetGecmisiListe" class="small text-muted">{{ __('front.cart.history_loading') }}</div>
        </div>
        </div>
    </div>

    <div class="cart-image-lightbox" data-cart-image-lightbox>
        <div class="cart-image-lightbox-dialog">
            <button type="button" class="cart-image-lightbox-close" data-cart-image-close aria-label="Görseli kapat">×</button>
            <img src="" alt="" data-cart-image-preview>
        </div>
    </div>

    <script>
        (function () {
            const imageLightbox = document.querySelector('[data-cart-image-lightbox]');
            const imagePreview = imageLightbox ? imageLightbox.querySelector('[data-cart-image-preview]') : null;
            const imageClose = imageLightbox ? imageLightbox.querySelector('[data-cart-image-close]') : null;
            const sepetGecmisiListe = document.getElementById('sepetGecmisiListe');
            if (!sepetGecmisiListe) {
                if (imageLightbox && imagePreview && imageClose) {
                    document.querySelectorAll('[data-cart-image-trigger]').forEach((button) => {
                        button.addEventListener('click', function () {
                            imagePreview.src = this.getAttribute('data-cart-image-src') || '';
                            imagePreview.alt = this.getAttribute('data-cart-image-alt') || '';
                            imageLightbox.classList.add('is-open');
                            document.body.style.overflow = 'hidden';
                        });
                    });

                    const closeLightbox = () => {
                        imageLightbox.classList.remove('is-open');
                        document.body.style.overflow = '';
                    };

                    imageClose.addEventListener('click', closeLightbox);
                    imageLightbox.addEventListener('click', function (event) {
                        if (event.target === imageLightbox) {
                            closeLightbox();
                        }
                    });
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && imageLightbox.classList.contains('is-open')) {
                            closeLightbox();
                        }
                    });
                }
                return;
            }

            if (imageLightbox && imagePreview && imageClose) {
                document.querySelectorAll('[data-cart-image-trigger]').forEach((button) => {
                    button.addEventListener('click', function () {
                        imagePreview.src = this.getAttribute('data-cart-image-src') || '';
                        imagePreview.alt = this.getAttribute('data-cart-image-alt') || '';
                        imageLightbox.classList.add('is-open');
                        document.body.style.overflow = 'hidden';
                    });
                });

                const closeLightbox = () => {
                    imageLightbox.classList.remove('is-open');
                    document.body.style.overflow = '';
                };

                imageClose.addEventListener('click', closeLightbox);
                imageLightbox.addEventListener('click', function (event) {
                    if (event.target === imageLightbox) {
                        closeLightbox();
                    }
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && imageLightbox.classList.contains('is-open')) {
                        closeLightbox();
                    }
                });
            }

            const csrfToken = '{{ csrf_token() }}';
            const gecmisAnahtari = 'ecommerce:sepet-gecmisi:v1:firma:{{ $aktifFirmaId }}:kullanici:{{ (string) (auth()->id() ?? 'misafir') }}';
            const cartAddEndpointTemplate = '{{ route('cart.add', ['slug' => '__SLUG__']) }}';
            const maxKayit = 20;
            const mevcutKalemler = @json($gecmisSepetKalemleri, JSON_UNESCAPED_UNICODE);
            const aktifDil = @json(app()->getLocale());
            const dilHaritasi = { tr: 'tr-TR', en: 'en-US', de: 'de-DE' };
            const dilEtiketi = dilHaritasi[aktifDil] || 'tr-TR';
            const historyParaBirimi = @json((string) ($toplamlar['para_birimi'] ?? $aktifParaBirimi));
            const metinler = {
                historyEmpty: @json(__('front.cart.history_empty')),
                historyTotal: @json(__('front.cart.history_total')),
                historyMoreProducts: @json(__('front.cart.history_more_products')),
                historyLoad: @json(__('front.cart.history_load')),
                historyDelete: @json(__('front.cart.history_delete')),
                historyLoadingButton: @json(__('front.cart.history_loading_button')),
                historyLoadError: @json(__('front.cart.history_load_error')),
                addError: @json(__('front.cart.add_error')),
            };

            const güvenliJsonOku = (ham) => {
                try {
                    const ayrıştırılmış = JSON.parse(ham || '[]');
                    return Array.isArray(ayrıştırılmış) ? ayrıştırılmış : [];
                } catch (e) {
                    return [];
                }
            };

            const gecmisiOku = () => güvenliJsonOku(localStorage.getItem(gecmisAnahtari));

            const gecmisiYaz = (liste) => {
                localStorage.setItem(gecmisAnahtari, JSON.stringify(liste.slice(0, maxKayit)));
            };

            const para = (tutar) => {
                return new Intl.NumberFormat(dilEtiketi, {
                    style: 'currency',
                    currency: historyParaBirimi,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(Number(tutar || 0));
            };

            const toplamTutar = (kalemler) => {
                return (kalemler || []).reduce((toplam, kalem) => {
                    return toplam + (Number(kalem.miktar || 0) * Number(kalem.birim_fiyat_try || kalem.birim_fiyat || 0));
                }, 0);
            };

            const kayitOlustur = () => {
                const gecerliKalemler = (mevcutKalemler || [])
                    .filter((kalem) => typeof kalem.slug === 'string' && kalem.slug !== '' && Number(kalem.miktar || 0) > 0)
                    .map((kalem) => ({
                        slug: kalem.slug,
                        ad: kalem.ad || '',
                        miktar: Number(kalem.miktar || 0),
                        birim_fiyat: Number(kalem.birim_fiyat || 0),
                        birim_fiyat_try: Number(kalem.birim_fiyat_try || 0),
                    }));

                if (gecerliKalemler.length === 0) {
                    return;
                }

                const ozet = gecerliKalemler
                    .map((kalem) => `${kalem.slug}:${kalem.miktar}`)
                    .sort()
                    .join('|');

                const kayit = {
                    id: 'sg_' + Date.now() + '_' + Math.random().toString(16).slice(2, 8),
                    tarih: new Date().toISOString(),
                    ozet: ozet,
                    kalemler: gecerliKalemler,
                };

                const onceki = gecmisiOku();
                if (onceki[0] && onceki[0].ozet === kayit.ozet) {
                    return;
                }

                gecmisiYaz([kayit, ...onceki]);
            };

            const htmlEsc = (metin) => String(metin || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');

            const listeyiCiz = () => {
                const liste = gecmisiOku();
                if (liste.length === 0) {
                    sepetGecmisiListe.innerHTML = `<div class="text-muted">${htmlEsc(metinler.historyEmpty)}</div>`;
                    return;
                }

                const satirlar = liste.map((kayit, index) => {
                    const tarih = new Date(kayit.tarih || Date.now()).toLocaleString(dilEtiketi);
                    const kalemMetni = (kayit.kalemler || [])
                        .slice(0, 3)
                        .map((k) => `${htmlEsc(k.ad || k.slug)} x${Number(k.miktar || 0)}`)
                        .join(', ');
                    const kalan = Math.max(0, (kayit.kalemler || []).length - 3);
                    const ekMetin = kalan > 0
                        ? ` ${metinler.historyMoreProducts.replace(':count', String(kalan))}`
                        : '';

                    return `
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong>${htmlEsc(tarih)}</strong>
                                    <div class="text-muted">${kalemMetni}${ekMetin}</div>
                                    <div class="text-muted">${htmlEsc(metinler.historyTotal)}: ${htmlEsc(para(toplamTutar(kayit.kalemler || [])))}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-geri-yukle="${index}">${htmlEsc(metinler.historyLoad)}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-sil="${index}">${htmlEsc(metinler.historyDelete)}</button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                sepetGecmisiListe.innerHTML = satirlar;
            };

            const gecmistenSil = (index) => {
                const liste = gecmisiOku();
                if (!liste[index]) {
                    return;
                }
                liste.splice(index, 1);
                gecmisiYaz(liste);
                listeyiCiz();
            };

            const sepeteEkleIstek = async (slug, miktar) => {
                const url = cartAddEndpointTemplate.replace('__SLUG__', encodeURIComponent(slug));
                const body = new URLSearchParams();
                body.set('_token', csrfToken);
                body.set('miktar', String(miktar));

                const yanit = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                });

                if (!yanit.ok) {
                    throw new Error(metinler.addError);
                }
            };

            const gecmisiYukle = async (index) => {
                const liste = gecmisiOku();
                const kayit = liste[index];
                if (!kayit || !Array.isArray(kayit.kalemler) || kayit.kalemler.length === 0) {
                    return;
                }

                const buton = sepetGecmisiListe.querySelector(`[data-geri-yukle="${index}"]`);
                if (buton) {
                    buton.disabled = true;
                    buton.textContent = metinler.historyLoadingButton;
                }

                try {
                    for (const kalem of kayit.kalemler) {
                        const slug = String(kalem.slug || '');
                        const miktar = Math.max(1, Number(kalem.miktar || 1));
                        if (slug === '') {
                            continue;
                        }
                        await sepeteEkleIstek(slug, miktar);
                    }
                    window.location.href = '{{ route('cart.index') }}';
                } catch (e) {
                    alert(metinler.historyLoadError);
                    if (buton) {
                        buton.disabled = false;
                        buton.textContent = metinler.historyLoad;
                    }
                }
            };

            sepetGecmisiListe.addEventListener('click', function (event) {
                const hedef = event.target;
                if (!(hedef instanceof HTMLElement)) {
                    return;
                }

                const yukleIndex = hedef.getAttribute('data-geri-yukle');
                if (yukleIndex !== null) {
                    const index = Number(yukleIndex);
                    if (!Number.isNaN(index)) {
                        gecmisiYukle(index);
                    }
                    return;
                }

                const silIndex = hedef.getAttribute('data-sil');
                if (silIndex !== null) {
                    const index = Number(silIndex);
                    if (!Number.isNaN(index)) {
                        gecmistenSil(index);
                    }
                }
            });

            kayitOlustur();
            listeyiCiz();
        })();
    </script>
@endsection
