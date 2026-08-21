<x-filament-panels::page>
    @php
        $ozet = $this->sepetOzeti();
        $paraBirimi = (string) ($data['para_birimi'] ?? 'TRY');
        $odemeTipi = (string) ($data['odeme_tipi'] ?? 'nakit');
        $pesinatOdemeTipi = (string) ($data['pesinat_odeme_tipi'] ?? 'nakit');
        $vadeFarkiUygula = (bool) ($data['vade_farki_uygula'] ?? false);
        $vadeliFinansOzeti = in_array($odemeTipi, ['veresiye', 'taksitli'], true)
            ? $this->vadeliSatisFinansOzeti()
            : ['vade_farki_tutari' => 0, 'planlanacak_tutar' => 0];
        $para = fn ($tutar) => number_format((float) $tutar, 2, ',', '.').' '.$paraBirimi;
        $kategoriler = $this->hizliKategoriler();
        $sekmeKolonSayisi = 4;
        $sekmeKapasitesi = max(2, (int) $hizliKategoriSatirSayisi) * $sekmeKolonSayisi;
        $tumSekmeler = collect([
            ['tip' => 'genel', 'id' => null, 'ad' => 'Genel', 'wire' => 'kategoriSec', 'aktif' => $hizliKategoriId === null],
            ['tip' => 'favori', 'id' => null, 'ad' => 'Favori', 'wire' => 'favoriSekmesiniSec', 'aktif' => $hizliKategoriId === -1],
        ])->merge(collect($kategoriler)->map(fn ($kategori): array => [
            'tip' => 'kategori',
            'id' => (int) $kategori['id'],
            'ad' => (string) $kategori['ad'],
            'wire' => 'kategoriSec('.((int) $kategori['id']).')',
            'aktif' => $hizliKategoriId === (int) $kategori['id'],
        ]))->values();
        $gorunurSekmeSayisi = $tumSekmeler->count() > $sekmeKapasitesi ? max(1, $sekmeKapasitesi - 1) : $sekmeKapasitesi;
        $gorunurSekmeler = $tumSekmeler->take($gorunurSekmeSayisi);
        $gizliSekmeSayisi = max(0, $tumSekmeler->count() - $gorunurSekmeler->count());
        $aktifSekmeAnahtari = $hizliKategoriId === -1
            ? 'favori'
            : ($hizliKategoriId ? 'kategori:'.$hizliKategoriId : 'genel');
        $urunler = $this->hizliSatisUrunleri();
        $hizliVergiOranlari = $this->hizliVergiOraniSecenekleri();
        $sekmeUrunOnizlemeleri = [
            $aktifSekmeAnahtari => collect($urunler)
                ->map(fn (array $urun): array => [
                    'id' => (int) ($urun['id'] ?? 0),
                    'ad' => (string) ($urun['ad'] ?? ''),
                    'kod' => (string) ($urun['kod'] ?? ''),
                    'barkod' => (string) ($urun['barkod'] ?? ''),
                    'stok_yazi' => number_format((float) ($urun['stok'] ?? 0), 0, ',', '.'),
                    'fiyat_yazi' => $para($urun['fiyat'] ?? 0),
                    'gorsel_url' => (string) ($urun['gorsel_url'] ?? ''),
                    'favori_mi' => (bool) ($urun['favori_mi'] ?? false),
                ])
                ->values()
                ->all(),
        ];
        $cariSecenekleri = $this->hizliCariSecenekleri();
        $seciliCariId = (string) ($data['cari_id'] ?? '');
        $seciliCariAdi = $seciliCariId !== '' ? (string) ($cariSecenekleri[$seciliCariId] ?? '') : '';
        $urunAramasiAcik = count($barkodAdaylari) > 0 || count($hizliUrunAdaylari) > 0;
        $kasaSecenekleri = $odemeTipi === 'nakit' || (in_array($odemeTipi, ['veresiye', 'taksitli'], true) && $pesinatOdemeTipi === 'nakit')
            ? $this->hizliKasaSecenekleri()
            : [];
        $bankaSecenekleri = $odemeTipi === 'havale' || (in_array($odemeTipi, ['veresiye', 'taksitli'], true) && $pesinatOdemeTipi === 'havale')
            ? $this->hizliBankaSecenekleri()
            : [];
        $posSecenekleri = $odemeTipi === 'kart' || (in_array($odemeTipi, ['veresiye', 'taksitli'], true) && $pesinatOdemeTipi === 'kart')
            ? $this->hizliPosSecenekleri()
            : [];
        $fiyatDegistirmeYetkisiVar = $this->fiyatDegistirmeYetkisiVarMi();
        $paraUstuTutari = $this->paraUstuTutari();
        $seciliIndirimKalemi = $seciliKalemIndex !== null && isset($kalemler[$seciliKalemIndex]) ? $kalemler[$seciliKalemIndex] : null;
        try {
            $iadeUrl = \App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi::getUrl();
        } catch (\Throwable) {
            $iadeUrl = null;
        }
    @endphp

    @php
        $hizliSatisJsPath = public_path('theme/yalovakamera/js/hizli-satis.js');
    @endphp

    <div class="quick-pos cork-sales-operations {{ $urunAramasiAcik ? 'is-searching' : '' }}">
        <div class="quick-pos-shell">
            <section class="quick-pos-main" aria-label="Hızlı satış ana ekranı">
                <div class="quick-pos-topbar">
                    <div class="quick-pos-operator-compact">
                        <div class="quick-pos-operator-compact-row">
                            <label for="pos-satis-elemani-input">Satış Pers.</label>
                            <input id="pos-satis-elemani-input" type="text" value="{{ auth()->user()?->name ?? 'Kasa kullanıcısı' }}" readonly>
                        </div>
                        <div class="quick-pos-operator-compact-row">
                            <label for="pos-cari-search-input">Müşteri / Cari</label>
                            <input
                                id="pos-cari-search-input"
                                type="text"
                                list="pos-cari-secenekleri"
                                value="{{ $seciliCariAdi }}"
                                placeholder="Müşteri ara..."
                                autocomplete="off"
                                wire:focus="hizliCariSecenekleriniYukle"
                                data-pos-cari-search
                            >
                            <datalist id="pos-cari-secenekleri">
                                @foreach($cariSecenekleri as $id => $ad)
                                    <option value="{{ $ad }}" data-cari-id="{{ $id }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <div class="quick-pos-field">
                        <label for="pos-barkod-input">Barkod</label>
                        <input
                            id="pos-barkod-input"
                            type="text"
                            wire:model.live.debounce.500ms="data.barkod"
                            wire:keydown.enter.prevent="barkodEkle"
                            placeholder="Barkod okutun..."
                            autocomplete="off"
                            autofocus
                        />
                    </div>

                    <div class="quick-pos-field">
                        <label for="pos-hizli-ara-input">Ürün Ara</label>
                        <div class="quick-pos-search-input-wrap">
                            <input
                                id="pos-hizli-ara-input"
                                type="text"
                                wire:model.live.debounce.500ms="data.hizli_urun_ara"
                                wire:keydown.enter.prevent="hizliAramadanEkle"
                                placeholder="Kod / ad / barkod"
                                autocomplete="off"
                                data-pos-product-search
                            />
                            @if(filled($data['hizli_urun_ara'] ?? null))
                                <button
                                    type="button"
                                    class="quick-pos-search-clear"
                                    wire:click="hizliUrunAramayiTemizle"
                                    data-pos-search-clear
                                    aria-label="Aramayı temizle"
                                >
                                    X
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="quick-pos-total">
                        <span>KASA [1] TOPLAM</span>
                        <strong>{{ $para($ozet['genel_toplam'] ?? 0) }}</strong>
                    </div>
                </div>

                <div class="quick-pos-actions">
                    <button type="button" class="quick-pos-button" wire:click="hizliSepetBeklet" data-pos-hold-cart>Sepeti Beklet</button>
                    <button type="button" class="quick-pos-button" wire:click="hizliSepetiTemizle" data-pos-clear-cart>Sepeti Temizle</button>
                    @if($iadeUrl)
                        <a class="quick-pos-button" href="{{ $iadeUrl }}">Hızlı İade</a>
                    @endif
                    <button type="button" class="quick-pos-button is-add-product" wire:click="hizliUrunEkleAc" data-pos-product-add>Ürün Ekle</button>
                    @if(count($bekleyenSepetler) > 0)
                        <div class="quick-pos-held-carts" aria-label="Bekleyen sepetler">
                            <span class="quick-pos-held-title">
                                <span>Bekleyen</span>
                                <span>Sepet</span>
                            </span>
                            @foreach($bekleyenSepetler as $i => $sepet)
                                @php
                                    $bekleyenSepetOnizleme = [
                                        'para_birimi' => (string) (($sepet['data']['para_birimi'] ?? null) ?: $paraBirimi),
                                        'kalemler' => collect($sepet['kalemler'] ?? [])->map(fn (array $kalem): array => [
                                            'stok_adi' => (string) ($kalem['stok_adi'] ?? '-'),
                                            'stok_kod' => (string) ($kalem['stok_kod'] ?? '-'),
                                            'barkod' => (string) ($kalem['barkod'] ?? '-'),
                                            'gorsel_url' => (string) ($kalem['gorsel_url'] ?? ''),
                                            'miktar' => (float) ($kalem['miktar'] ?? 0),
                                            'birim' => (string) ($kalem['birim'] ?? 'AD'),
                                            'birim_fiyat' => (float) ($kalem['birim_fiyat'] ?? 0),
                                            'iskonto_tutari' => (float) ($kalem['iskonto_tutari'] ?? 0),
                                            'kdv_orani' => (float) ($kalem['kdv_orani'] ?? 0),
                                        ])->values()->all(),
                                    ];
                                @endphp
                                <span class="quick-pos-held-cart" wire:key="hizli-bekleyen-sepet-{{ $i }}" data-pos-held-cart data-pos-held-index="{{ $i }}">
                                    <span class="quick-pos-held-name">{{ $sepet['etiket'] ?? ('Sepet '.($i + 1)) }}</span>
                                    <button type="button" class="quick-pos-held-action" data-pos-held-load="{{ $i }}">Yükle</button>
                                    <button type="button" class="quick-pos-held-action is-danger" data-pos-held-delete="{{ $i }}">Sil</button>
                                    <script type="application/json" data-pos-held-preview>@json($bekleyenSepetOnizleme)</script>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($urunAramasiAcik)
                    <div class="quick-pos-panel" style="padding: 7px;" data-pos-search-panel>
                        @if($hizliUrunAdayToplam > 0)
                            <div class="quick-pos-search-toolbar">
                                <div class="quick-pos-search-count">
                                    {{ count($hizliUrunAdaylari) }} / {{ $hizliUrunAdayToplam }} sonuç gösteriliyor
                                </div>
                                <label class="quick-pos-search-sort">
                                    Sıralama
                                    <select class="quick-pos-mini-select" wire:model.live="hizliUrunAramaSiralamasi">
                                        <option value="ilgili">İlgili sonuçlar</option>
                                        <option value="ad">Ada göre A-Z</option>
                                        <option value="fiyat_artan">Fiyat artan</option>
                                        <option value="fiyat_azalan">Fiyat azalan</option>
                                        <option value="stok_fazla">Stok fazla</option>
                                    </select>
                                </label>
                            </div>
                        @endif
                        <div class="quick-pos-search-results" data-pos-search-results>
                            @foreach(array_merge($barkodAdaylari, $hizliUrunAdaylari) as $adayIndex => $aday)
                                <button type="button" class="quick-pos-search-result" wire:key="hizli-arama-aday-{{ (int) ($aday['id'] ?? 0) }}-{{ $adayIndex }}" wire:click="hizliAdaydanEkle({{ (int) ($aday['id'] ?? 0) }})">
                                    <span class="quick-pos-search-media">
                                        @if(filled($aday['gorsel_url'] ?? null))
                                            <img src="{{ $aday['gorsel_url'] }}" alt="{{ $aday['ad'] ?? 'Ürün görseli' }}" loading="lazy" decoding="async" fetchpriority="low">
                                        @else
                                            Görsel yok
                                        @endif
                                    </span>
                                    <span class="quick-pos-search-info">
                                        <span class="quick-pos-search-name">{{ $aday['ad'] ?? '-' }}</span>
                                        <span class="quick-pos-search-meta">
                                            <span>{{ $aday['kod'] ?? '-' }}</span>
                                            <span>{{ $aday['barkod'] ?? '-' }}</span>
                                        </span>
                                    </span>
                                    <span class="quick-pos-search-price">
                                        {{ $para($aday['fiyat'] ?? 0) }}
                                        <span class="quick-pos-search-stock">Stok: {{ number_format((float) ($aday['stok'] ?? 0), 0, ',', '.') }} {{ $aday['birim'] ?? 'AD' }}</span>
                                    </span>
                                </button>
                            @endforeach
                            @if(count($hizliUrunAdaylari) > 0)
                                <div class="quick-pos-search-footer">
                                    @if($hizliUrunAdayToplam > count($hizliUrunAdaylari))
                                        <button type="button" class="quick-pos-search-more" wire:click="hizliUrunAramaDahaFazla" data-pos-search-more>
                                            Daha fazla göster
                                        </button>
                                    @endif
                                    <button
                                        type="button"
                                        class="quick-pos-search-close"
                                        style="{{ $hizliUrunAdayToplam > count($hizliUrunAdaylari) ? '' : 'grid-column: 1 / -1;' }}"
                                        wire:click="hizliUrunAramayiTemizle"
                                        data-pos-search-clear
                                    >
                                        Aramayı Kapat
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @unless($urunAramasiAcik)
                    <div class="quick-pos-table-wrap">
                        @if(count($kalemler) === 0)
                            <div class="quick-pos-empty">Sepet boş. Barkod okutun veya sağdaki ürünlerden seçim yapın.</div>
                        @else
                            <table class="quick-pos-table">
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
                                <tbody>
                                    @foreach($kalemler as $index => $kalem)
                                        @php
                                            $miktar = (float) ($kalem['miktar'] ?? 0);
                                            $fiyat = (float) ($kalem['birim_fiyat'] ?? 0);
                                            $iskonto = (float) ($kalem['iskonto_tutari'] ?? 0);
                                            $satirTutar = max(0, ($miktar * $fiyat) - $iskonto);
                                        @endphp
                                        <tr wire:key="hizli-sepet-kalem-{{ (int) ($kalem['stok_id'] ?? 0) }}-{{ $index }}" wire:click="kalemSec({{ $index }})" class="{{ $seciliKalemIndex === $index ? 'is-selected' : '' }}" data-pos-cart-product-id="{{ (int) ($kalem['stok_id'] ?? 0) }}">
                                            <td data-label="Ürün">
                                                <div class="quick-pos-cart-product">
                                                    @if(filled($kalem['gorsel_url'] ?? null))
                                                        <button
                                                            type="button"
                                                            class="quick-pos-cart-thumb"
                                                            data-pos-image-preview-src="{{ $kalem['gorsel_url'] }}"
                                                            data-pos-image-preview-title="{{ $kalem['stok_adi'] ?? 'Ürün görseli' }}"
                                                            aria-label="{{ ($kalem['stok_adi'] ?? 'Ürün').' görselini büyüt' }}"
                                                        >
                                                            <img src="{{ $kalem['gorsel_url'] }}" alt="{{ $kalem['stok_adi'] ?? 'Ürün görseli' }}" loading="lazy" decoding="async" fetchpriority="low">
                                                        </button>
                                                    @else
                                                        <span class="quick-pos-cart-thumb">
                                                            Görsel yok
                                                        </span>
                                                    @endif
                                                    <span class="quick-pos-cart-info">
                                                        <strong>{{ $kalem['stok_adi'] ?? '-' }}</strong>
                                                        <span style="display:block; font-size: 11px; color: #52687a;">{{ $kalem['stok_kod'] ?? '-' }} / {{ $kalem['barkod'] ?? '-' }}</span>
                                                        @if(count(array_filter(array_map('trim', (array) ($kalem['seri_nolari'] ?? [])))) > 0)
                                                            <span style="display:block; font-size: 11px; color: #52687a;">Seri No Barkodu: {{ implode(', ', array_filter(array_map('trim', (array) ($kalem['seri_nolari'] ?? [])))) }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </td>
                                            <td data-label="Miktar">
                                                <input class="quick-pos-number" type="number" min="0.0001" step="0.0001" wire:click.stop wire:model.blur="kalemler.{{ $index }}.miktar" />
                                                <span style="font-size: 11px;">{{ $kalem['birim'] ?? 'AD' }}</span>
                                            </td>
                                            <td data-label="Fiyat">
                                                        <input class="quick-pos-number" type="number" min="0" step="0.01" wire:click.stop wire:model.blur="kalemler.{{ $index }}.birim_fiyat" @disabled(! $fiyatDegistirmeYetkisiVar) />
                                            </td>
                                            <td data-label="Tutar"><strong>{{ $para($satirTutar) }}</strong></td>
                                            <td data-label="KDV">
                                                <input class="quick-pos-number" type="number" min="0" step="0.01" wire:click.stop wire:model.blur="kalemler.{{ $index }}.kdv_orani" />
                                            </td>
                                            <td data-label="İşlem">
                                                <div class="quick-pos-row-actions">
                                                    <button
                                                        type="button"
                                                        class="quick-pos-row-action is-edit"
                                                        wire:click.stop="hizliKalemDuzenleAc({{ $index }})"
                                                        data-pos-cart-edit
                                                        data-pos-cart-index="{{ $index }}"
                                                        data-pos-stock-id="{{ (int) ($kalem['stok_id'] ?? 0) }}"
                                                        data-pos-stock-name="{{ $kalem['stok_adi'] ?? '' }}"
                                                        data-pos-stock-quantity="{{ (float) ($kalem['stok_miktari'] ?? 0) }}"
                                                        data-pos-stock-price="{{ (float) ($kalem['birim_fiyat'] ?? 0) }}"
                                                        data-pos-stock-discount-price="{{ (float) ($kalem['indirimli_fiyat'] ?? 0) }}"
                                                        data-pos-stock-tax="{{ (float) ($kalem['kdv_orani'] ?? 0) }}"
                                                        title="Düzenle"
                                                        aria-label="Ürünü düzenle"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-pencil-square" class="quick-pos-row-action-icon" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="quick-pos-row-action is-delete quick-pos-delete"
                                                        wire:click.stop="kalemSil({{ $index }})"
                                                        data-pos-cart-delete
                                                        data-pos-cart-index="{{ $index }}"
                                                        title="Sil"
                                                        aria-label="Sepetten sil"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-trash" class="quick-pos-row-action-icon" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endunless

                <div class="quick-pos-summary">
                    <div class="quick-pos-summary-item"><span>Ara Toplam</span><strong>{{ $para($ozet['ara_toplam'] ?? 0) }}</strong></div>
                    <div class="quick-pos-summary-item"><span>İskonto</span><strong>{{ $para($ozet['iskonto_toplami'] ?? 0) }}</strong></div>
                    <div class="quick-pos-summary-item"><span>KDV</span><strong>{{ $para($ozet['kdv_toplami'] ?? 0) }}</strong></div>
                    <div class="quick-pos-summary-item"><span>Genel Toplam</span><strong data-pos-grand-total="{{ number_format((float) ($ozet['genel_toplam'] ?? 0), 2, '.', '') }}">{{ $para($ozet['genel_toplam'] ?? 0) }}</strong></div>
                </div>

                <div class="quick-pos-bottom">
                    <button type="button" class="quick-pos-pay-button is-cash {{ $odemeTipi === 'nakit' ? 'is-active' : '' }}" wire:click="odemeTipiSec('nakit')" aria-pressed="{{ $odemeTipi === 'nakit' ? 'true' : 'false' }}" data-pos-pay-button="nakit"><span>Nakit Satış</span><small>Alt+1</small></button>
                    <button type="button" class="quick-pos-pay-button is-card {{ $odemeTipi === 'kart' ? 'is-active' : '' }}" wire:click="odemeTipiSec('kart')" aria-pressed="{{ $odemeTipi === 'kart' ? 'true' : 'false' }}" data-pos-pay-button="kart"><span>Kredi Kartı</span><small>Alt+2</small></button>
                    <button type="button" class="quick-pos-pay-button is-card {{ $odemeTipi === 'havale' ? 'is-active' : '' }}" wire:click="odemeTipiSec('havale')" aria-pressed="{{ $odemeTipi === 'havale' ? 'true' : 'false' }}" data-pos-pay-button="havale"><span>Havale/EFT</span><small>Alt+3</small></button>
                    <button type="button" class="quick-pos-pay-button is-term {{ $odemeTipi === 'veresiye' ? 'is-active' : '' }}" wire:click="odemeTipiSec('veresiye')" aria-pressed="{{ $odemeTipi === 'veresiye' ? 'true' : 'false' }}" data-pos-pay-button="veresiye"><span>Veresiye Satış</span><small>Cari hesaba</small></button>
                    <button type="button" class="quick-pos-pay-button is-term {{ $odemeTipi === 'taksitli' ? 'is-active' : '' }}" wire:click="odemeTipiSec('taksitli')" aria-pressed="{{ $odemeTipi === 'taksitli' ? 'true' : 'false' }}" data-pos-pay-button="taksitli"><span>Taksitli Satış</span><small>Planlı ödeme</small></button>
                    <button type="button" class="quick-pos-pay-button is-discount {{ $indirimGirisAcik ? 'is-active' : '' }}" wire:click="indirimGirisiniAc" aria-pressed="{{ $indirimGirisAcik ? 'true' : 'false' }}" data-pos-pay-button="indirim"><span>İndirim Ekle</span><small>Seçili satıra</small></button>
                    <button type="button" class="quick-pos-pay-button is-muted" wire:click="satisiTamamlaVeYazdir"><span>Kaydet + Yazdır</span><small>Fiş</small></button>
                </div>

                <div class="quick-pos-sale-controls">
                    <div class="quick-pos-sale-control">
                        <label for="pos-odeme-tipi-input">Ödeme</label>
                        <select id="pos-odeme-tipi-input" class="quick-pos-mini-select" wire:model.live="data.odeme_tipi">
                            <option value="nakit">Nakit</option>
                            <option value="kart">Kart</option>
                            <option value="havale">Havale/EFT</option>
                            <option value="veresiye">Veresiye Satış</option>
                            <option value="taksitli">Taksitli Satış</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>
                    <div class="quick-pos-sale-control">
                        <label>Para Birimi</label>
                        <select class="quick-pos-mini-select" wire:model.live="data.para_birimi">
                            <option value="TRY">TRY</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                    @if($odemeTipi === 'nakit')
                        <div class="quick-pos-sale-control">
                            <label>Kasa</label>
                            <select class="quick-pos-mini-select" wire:model.live="data.kasa_hesap_id">
                                <option value="">Kasa seçin</option>
                                @foreach($kasaSecenekleri as $id => $ad)
                                    <option value="{{ $id }}">{{ $ad }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($odemeTipi === 'kart')
                        <div class="quick-pos-sale-control">
                            <label>POS</label>
                            <select class="quick-pos-mini-select" wire:model.live="data.pos_hesap_id">
                                <option value="">POS seçin</option>
                                @foreach($posSecenekleri as $id => $ad)
                                    <option value="{{ $id }}">{{ $ad }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($odemeTipi === 'havale')
                        <div class="quick-pos-sale-control">
                            <label>Banka</label>
                            <select class="quick-pos-mini-select" wire:model.live="data.banka_hesap_id">
                                <option value="">Banka seçin</option>
                                @foreach($bankaSecenekleri as $id => $ad)
                                    <option value="{{ $id }}">{{ $ad }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="quick-pos-sale-control"></div>
                    @endif
                    @if(in_array($odemeTipi, ['veresiye', 'taksitli'], true))
                        <div class="quick-pos-term-panel {{ $odemeTipi === 'taksitli' ? 'is-installment' : '' }}">
                            <div class="quick-pos-term-field">
                                <label for="pos-pesinat-input">Peşinat</label>
                                <input
                                    id="pos-pesinat-input"
                                    class="quick-pos-money-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model.live.debounce.250ms="data.pesinat_tutari"
                                    placeholder="0,00"
                                >
                            </div>
                            <div class="quick-pos-term-field">
                                <label for="pos-pesinat-odeme-input">Peşinat Ödeme</label>
                                <select
                                    id="pos-pesinat-odeme-input"
                                    class="quick-pos-mini-select"
                                    wire:model.live="data.pesinat_odeme_tipi"
                                >
                                    <option value="nakit">Nakit</option>
                                    <option value="kart">Kart</option>
                                    <option value="havale">Havale/EFT</option>
                                </select>
                            </div>
                            @if($pesinatOdemeTipi === 'nakit')
                                <div class="quick-pos-term-field">
                                    <label for="pos-pesinat-kasa-input">Peşinat Kasa</label>
                                    <select id="pos-pesinat-kasa-input" class="quick-pos-mini-select" wire:model.live="data.kasa_hesap_id">
                                        <option value="">Kasa seçin</option>
                                        @foreach($kasaSecenekleri as $id => $ad)
                                            <option value="{{ $id }}">{{ $ad }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($pesinatOdemeTipi === 'kart')
                                <div class="quick-pos-term-field">
                                    <label for="pos-pesinat-pos-input">Peşinat POS</label>
                                    <select id="pos-pesinat-pos-input" class="quick-pos-mini-select" wire:model.live="data.pos_hesap_id">
                                        <option value="">POS seçin</option>
                                        @foreach($posSecenekleri as $id => $ad)
                                            <option value="{{ $id }}">{{ $ad }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($pesinatOdemeTipi === 'havale')
                                <div class="quick-pos-term-field">
                                    <label for="pos-pesinat-banka-input">Peşinat Banka</label>
                                    <select id="pos-pesinat-banka-input" class="quick-pos-mini-select" wire:model.live="data.banka_hesap_id">
                                        <option value="">Banka seçin</option>
                                        @foreach($bankaSecenekleri as $id => $ad)
                                            <option value="{{ $id }}">{{ $ad }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="quick-pos-term-field">
                                <label for="pos-vade-tarihi-input">{{ $odemeTipi === 'taksitli' ? 'İlk Vade' : 'Vade Tarihi' }}</label>
                                <input
                                    id="pos-vade-tarihi-input"
                                    class="quick-pos-mini-select"
                                    type="date"
                                    wire:model.live="data.vade_tarihi"
                                >
                            </div>
                            <label class="quick-pos-term-check" for="pos-faiz-uygula-input">
                                <input
                                    id="pos-faiz-uygula-input"
                                    type="checkbox"
                                    wire:model.live="data.vade_farki_uygula"
                                >
                                Vade farkı
                            </label>
                            @if($vadeFarkiUygula)
                                <div class="quick-pos-term-field">
                                    <label for="pos-vade-farki-tipi-input">Fark Tipi</label>
                                    <select
                                        id="pos-vade-farki-tipi-input"
                                        class="quick-pos-mini-select"
                                        wire:model.live="data.vade_farki_tipi"
                                    >
                                        <option value="tek_seferlik">Tek seferlik</option>
                                        <option value="aylik">Aylık</option>
                                        <option value="yillik">Yıllık</option>
                                    </select>
                                </div>
                                <div class="quick-pos-term-field">
                                    <label for="pos-vade-farki-orani-input">Vade Farkı %</label>
                                    <input
                                        id="pos-vade-farki-orani-input"
                                        class="quick-pos-mini-select"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        wire:model.live.debounce.250ms="data.vade_farki_orani"
                                        placeholder="0"
                                    >
                                </div>
                                <div class="quick-pos-term-summary">
                                    Vade Farkı: {{ $para($vadeliFinansOzeti['vade_farki_tutari']) }}<br>
                                    Kalan: {{ $para($vadeliFinansOzeti['planlanacak_tutar']) }}
                                </div>
                            @endif
                            @if($odemeTipi === 'taksitli')
                                <div class="quick-pos-term-field">
                                    <label for="pos-taksit-sayisi-input">Taksit</label>
                                    <input
                                        id="pos-taksit-sayisi-input"
                                        class="quick-pos-mini-select"
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model.live="data.taksit_sayisi"
                                    >
                                </div>
                                <div class="quick-pos-term-field">
                                    <label for="pos-taksit-araligi-input">Aralık Gün</label>
                                    <input
                                        id="pos-taksit-araligi-input"
                                        class="quick-pos-mini-select"
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model.live="data.taksit_araligi_gun"
                                    >
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @if($indirimGirisAcik)
                    <div class="quick-pos-discount-panel">
                        <div class="quick-pos-discount-title">
                            Seçili Satıra İndirim
                            <span>{{ $seciliIndirimKalemi['stok_adi'] ?? 'Satır seçilmedi' }}</span>
                        </div>
                        <div class="quick-pos-discount-field">
                            <label for="pos-indirim-tutari-input">Tutar</label>
                            <input
                                id="pos-indirim-tutari-input"
                                class="quick-pos-discount-input"
                                type="text"
                                wire:model.live="indirimTutari"
                                wire:keydown.enter.prevent="seciliKalemeIndirimUygula"
                                placeholder="0,00"
                            >
                        </div>
                        <button type="button" class="quick-pos-button" wire:click="seciliKalemeIndirimUygula">Uygula</button>
                        <button type="button" class="quick-pos-button" wire:click="indirimGirisiniKapat">Kapat</button>
                    </div>
                @endif
            </section>

            <aside class="quick-pos-side" aria-label="Hızlı ürün ve ödeme paneli">
                <div class="quick-pos-tabs">
                    @foreach($gorunurSekmeler as $sekme)
                        @php
                            $sekmeAnahtari = $sekme['tip'] === 'kategori' ? 'kategori:'.$sekme['id'] : $sekme['tip'];
                        @endphp
                        <button
                            type="button"
                            class="quick-pos-tab {{ $sekme['aktif'] ? 'is-active' : '' }}"
                            wire:key="hizli-sekme-{{ $sekmeAnahtari }}"
                            wire:click="{{ $sekme['wire'] }}"
                            data-pos-product-tab="{{ $sekmeAnahtari }}"
                        >
                            {{ $sekme['ad'] }}
                        </button>
                    @endforeach
                    @if($gizliSekmeSayisi > 0)
                        <button
                            type="button"
                            class="quick-pos-tab is-more"
                            wire:click="kategoriSekmeleriniGenislet"
                            data-pos-tabs-more
                        >
                            ... {{ $gizliSekmeSayisi }}
                        </button>
                    @endif
                </div>

                <div class="quick-pos-grid">
                    @forelse($urunler as $urun)
                        <button
                            type="button"
                            class="quick-pos-product"
                            wire:key="hizli-urun-{{ (int) $urun['id'] }}"
                            wire:click="hizliUrunKartindanEkle({{ (int) $urun['id'] }})"
                            data-pos-product-card
                            data-pos-product-id="{{ (int) $urun['id'] }}"
                            data-pos-product-name="{{ $urun['ad'] }}"
                            data-pos-product-code="{{ $urun['kod'] ?? '' }}"
                            data-pos-product-barcode="{{ $urun['barkod'] ?? '' }}"
                            data-pos-product-price="{{ $para($urun['fiyat'] ?? 0) }}"
                            data-pos-product-image="{{ $urun['gorsel_url'] ?? '' }}"
                        >
                            <span
                                role="button"
                                tabindex="0"
                                class="quick-pos-favorite-button {{ ($urun['favori_mi'] ?? false) ? 'is-active' : '' }}"
                                wire:click.stop="hizliFavoriDegistir({{ (int) $urun['id'] }})"
                                data-pos-favorite-button
                                data-pos-product-id="{{ (int) $urun['id'] }}"
                                title="{{ ($urun['favori_mi'] ?? false) ? 'Favoriden kaldır' : 'Favoriye ekle' }}"
                                aria-label="{{ ($urun['favori_mi'] ?? false) ? 'Favoriden kaldır' : 'Favoriye ekle' }}"
                            >★</span>
                            <span class="quick-pos-product-media">
                                @if(filled($urun['gorsel_url'] ?? null))
                                    <img src="{{ $urun['gorsel_url'] }}" alt="{{ $urun['ad'] }}" loading="lazy" decoding="async" fetchpriority="low">
                                @else
                                    <span class="quick-pos-product-fallback">{{ $urun['ad'] }}</span>
                                @endif
                            </span>
                            <span class="quick-pos-product-info">
                                <span class="quick-pos-product-name">{{ $urun['ad'] }}</span>
                                <span class="quick-pos-product-meta">
                                    <span>{{ $para($urun['fiyat'] ?? 0) }}</span>
                                    <span>{{ number_format((float) ($urun['stok'] ?? 0), 0, ',', '.') }}</span>
                                </span>
                            </span>
                        </button>
                    @empty
                        <div class="quick-pos-empty" style="grid-column: 1 / -1;">Hızlı satışta gösterilecek ürün bulunamadı.</div>
                    @endforelse
                </div>

                @foreach($sekmeUrunOnizlemeleri as $sekmeAnahtari => $sekmeUrunleri)
                    <script type="application/json" data-pos-tab-products="{{ $sekmeAnahtari }}">
                        @json($sekmeUrunleri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    </script>
                @endforeach

                <div class="quick-pos-payment-card">
                    <div class="quick-pos-payment-grid">
                        <div class="quick-pos-banknote-grid" aria-label="Hızlı nakit kupürleri">
                            @foreach([
                                5 => '5tl.png',
                                10 => '10tl.jpg',
                                20 => '20tl.png',
                                50 => '50tl.jpg',
                                100 => '100tl.jpg',
                                200 => '200tl.jpg',
                            ] as $kupur => $kupurGorseli)
                                <button type="button" class="quick-pos-banknote-button" wire:click="alinanParayaKupurEkle({{ $kupur }})" data-pos-banknote="{{ $kupur }}" aria-label="{{ $kupur }} TL ekle">
                                    <img src="{{ asset('images/pos-banknotes/' . $kupurGorseli) }}" alt="{{ $kupur }} TL" loading="lazy" decoding="async" fetchpriority="low">
                                </button>
                            @endforeach
                        </div>
                        <div>
                            <label style="display:block; font-size: 11px; font-weight: 900; margin-bottom: 4px;">Alınan Para</label>
                            <input class="quick-pos-money-input" type="text" wire:model.live.debounce.250ms="alinanPara" placeholder="0,00" data-pos-cash-input>
                        </div>
                        <div class="quick-pos-cash-box is-paid" data-pos-cash-paid>
                            Alınan<br>{{ filled($alinanPara) ? $alinanPara : '0,00' }} {{ $paraBirimi }}
                        </div>
                        <button type="button" class="quick-pos-cash-box is-change" style="grid-column: 1 / -1;" wire:click="satisiTamamla">
                            <span data-pos-change-text>Para Üstü {{ $para($paraUstuTutari) }}</span>
                            <span>Satışı Tamamla F9</span>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    @if($hizliUrunEklemeAcik)
        <div class="quick-pos-modal" role="dialog" aria-modal="true" aria-label="Hızlı ürün ekle">
            <div class="quick-pos-modal-card">
                <div class="quick-pos-modal-header">
                    <div>
                        <span>Hızlı Ürün Ekle</span>
                        <strong>{{ filled($hizliUrunEkleme['ad'] ?? null) ? $hizliUrunEkleme['ad'] : 'Yeni stok kartı' }}</strong>
                    </div>
                    <button type="button" class="quick-pos-modal-close" wire:click="hizliUrunEkleKapat" aria-label="Kapat">X</button>
                </div>
                <div class="quick-pos-modal-form">
                    <label class="quick-pos-modal-field">
                        <span>Barkod</span>
                        <input type="text" wire:model.blur="hizliUrunEkleme.barkod" autocomplete="off" placeholder="Barkod okutun" data-pos-quick-product-barcode>
                    </label>
                    <div class="quick-pos-modal-lookup">
                        <button type="button" class="quick-pos-modal-button is-secondary" wire:click="hizliUrunBarkoddanAra">İnternetten Ara</button>
                        <small>{{ $hizliUrunEkleme['kaynak'] ?? '' }}</small>
                    </div>
                    <label class="quick-pos-modal-field is-wide">
                        <span>Ürün Adı</span>
                        <input type="text" wire:model.blur="hizliUrunEkleme.ad" autocomplete="off">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Marka</span>
                        <input type="text" wire:model.blur="hizliUrunEkleme.marka_uretici" autocomplete="off">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Kategori</span>
                        <select wire:model.blur="hizliUrunEkleme.kategori_id">
                            <option value="">Kategorisiz</option>
                            @foreach($kategoriler as $kategoriSecenegi)
                                <option value="{{ (int) $kategoriSecenegi['id'] }}">{{ $kategoriSecenegi['ad'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Stok</span>
                        <input type="number" min="0" step="0.0001" wire:model.blur="hizliUrunEkleme.stok_miktari">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Birim</span>
                        <select wire:model.blur="hizliUrunEkleme.birim">
                            @foreach($this->hizliBirimSecenekleri() as $birimSecenegi)
                                <option value="{{ $birimSecenegi['kod'] }}">{{ $birimSecenegi['etiket'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Alış Fiyatı</span>
                        <input type="number" min="0" step="0.01" wire:model.blur="hizliUrunEkleme.alis_fiyati">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Satış Fiyatı</span>
                        <input type="number" min="0" step="0.01" wire:model.blur="hizliUrunEkleme.satis_fiyati">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>KDV Oranı</span>
                        <select wire:model.blur="hizliUrunEkleme.kdv_orani">
                            @foreach($hizliVergiOranlari as $vergiOrani)
                                <option value="{{ $vergiOrani['oran'] }}">{{ $vergiOrani['etiket'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="quick-pos-modal-toggle">
                        <input type="checkbox" wire:model.blur="hizliUrunEkleme.kdv_dahil_mi">
                        <span>KDV dahil</span>
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Görsel URL</span>
                        <input type="url" wire:model.blur="hizliUrunEkleme.gorsel_url" autocomplete="off">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Görsel Dosyası</span>
                        <input type="file" wire:model="hizliUrunGorselDosyasi" accept="image/*">
                    </label>
                    @if(filled($hizliUrunEkleme['gorsel_url'] ?? null))
                        <div class="quick-pos-modal-product-preview">
                            <img src="{{ $hizliUrunEkleme['gorsel_url'] }}" alt="{{ $hizliUrunEkleme['ad'] ?? 'Ürün görseli' }}" loading="lazy" decoding="async">
                            <div class="quick-pos-modal-product-preview-info">
                                <strong>Görsel önizleme</strong>
                                <button type="button" class="quick-pos-modal-mini-button is-danger" wire:click="hizliUrunGorseliniTemizle">Görseli Kaldır</button>
                            </div>
                        </div>
                    @endif
                    <div class="quick-pos-modal-actions">
                        <button type="button" class="quick-pos-modal-button is-secondary" wire:click="hizliUrunEkleKapat">Vazgeç</button>
                        <button type="button" class="quick-pos-modal-button is-primary" wire:click="hizliUrunEkleKaydet">Kaydet ve Sepete Ekle</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($hizliKalemDuzenlemeAcik)
        <div class="quick-pos-modal" role="dialog" aria-modal="true" aria-label="Ürün hızlı düzenle">
            <div class="quick-pos-modal-card">
                <div class="quick-pos-modal-header">
                    <div>
                        <span>Hızlı Düzenle</span>
                        <strong>{{ $hizliKalemDuzenleme['ad'] ?? 'Ürün' }}</strong>
                    </div>
                    <button type="button" class="quick-pos-modal-close" wire:click="hizliKalemDuzenleKapat" aria-label="Kapat">X</button>
                </div>
                <form wire:submit.prevent="hizliKalemDuzenleKaydet" class="quick-pos-modal-form">
                    <label class="quick-pos-modal-field is-wide">
                        <span>Ürün İsmi</span>
                        <input type="text" wire:model.blur="hizliKalemDuzenleme.ad" autocomplete="off">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Stok</span>
                        <input type="number" min="0" step="0.0001" wire:model.blur="hizliKalemDuzenleme.stok_miktari">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>Satış Fiyatı</span>
                        <input type="number" min="0" step="0.01" wire:model.blur="hizliKalemDuzenleme.satis_fiyati">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>İndirimli Fiyat</span>
                        <input type="number" min="0" step="0.01" wire:model.blur="hizliKalemDuzenleme.indirimli_fiyat">
                    </label>
                    <label class="quick-pos-modal-field">
                        <span>KDV Oranı</span>
                        <input type="number" min="0" step="0.01" wire:model.blur="hizliKalemDuzenleme.kdv_orani">
                    </label>
                    <div class="quick-pos-modal-actions">
                        <button type="button" class="quick-pos-modal-button is-secondary" wire:click="hizliKalemDuzenleKapat">Vazgeç</button>
                        <button type="submit" class="quick-pos-modal-button is-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="quick-pos-image-preview" data-pos-image-preview hidden wire:ignore>
        <div class="quick-pos-image-preview-bar">
            <div class="quick-pos-image-preview-title" data-pos-image-preview-title></div>
            <div class="quick-pos-image-preview-tools">
                <button type="button" data-pos-image-preview-zoom-out aria-label="Uzaklaştır">-</button>
                <span class="quick-pos-image-preview-zoom" data-pos-image-preview-zoom>100%</span>
                <button type="button" data-pos-image-preview-zoom-in aria-label="Yakınlaştır">+</button>
                <button type="button" data-pos-image-preview-reset aria-label="Sıfırla">1:1</button>
                <button type="button" data-pos-image-preview-close aria-label="Kapat">X</button>
            </div>
        </div>
        <div class="quick-pos-image-preview-stage" data-pos-image-preview-stage>
            <img src="" alt="" data-pos-image-preview-img>
        </div>
    </div>

        <script>
        window.quickPosConfig = {
            iadeUrl: @js($iadeUrl),
            kdvOranlari: @js($hizliVergiOranlari),
            birimSecenekleri: @js($this->hizliBirimSecenekleri()),
            kategoriSecenekleri: @js($kategoriler),
            aktifKategoriId: @js($hizliKategoriId && $hizliKategoriId > 0 ? $hizliKategoriId : null),
            barkodAdaylari: @js($this->hizliYerelBarkodAdaylari()),
        };
    </script>
    <script src="{{ asset('theme/yalovakamera/js/hizli-satis.js') }}?v={{ is_file($hizliSatisJsPath) ? filemtime($hizliSatisJsPath) : time() }}"></script>
</x-filament-panels::page>
