<x-filament-panels::page>
    <div class="muhasebe-cork-screen cork-sales-operations space-y-6">
        <div class="cork-sales-form max-w-none">
            {{ $this->form }}
        </div>

        <x-filament::section heading="Sepet kalemleri">
            <div x-data="{ previewUrl: null }" class="space-y-3">
            <div class="mb-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-100">
                Satis modu:
                @if(filled($data['cari_id'] ?? null))
                    <strong>Cari secili satis</strong>
                @else
                    <strong>Hizli satis (cari secmeden)</strong>
                @endif
            </div>
            @if(count($barkodAdaylari) > 0)
                <div class="rounded-lg border border-primary-200 bg-white p-2 dark:border-primary-700 dark:bg-gray-900/40">
                    <div class="mb-2 text-xs font-semibold text-primary-700 dark:text-primary-300">Barkod adaylari</div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($barkodAdaylari as $aday)
                            <button type="button" wire:click="barkodAdaydanEkle({{ (int) ($aday['id'] ?? 0) }})" class="flex items-center gap-2 rounded-md border border-gray-200 p-2 text-left hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50">
                                @if(filled($aday['gorsel_url'] ?? null))
                                    <img src="{{ $aday['gorsel_url'] }}" alt="Urun" class="h-10 w-10 rounded object-cover">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 text-[10px] text-gray-500 dark:bg-gray-800">YOK</div>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-semibold">{{ $aday['ad'] ?? '-' }}</div>
                                    <div class="truncate text-[11px] text-gray-600 dark:text-gray-300">{{ $aday['kod'] ?? '-' }}</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(count($hizliUrunAdaylari) > 0)
                <div class="rounded-lg border border-info-200 bg-white p-2 dark:border-info-700 dark:bg-gray-900/40">
                    <div class="mb-2 text-xs font-semibold text-info-700 dark:text-info-300">Hizli urun ara adaylari</div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($hizliUrunAdaylari as $aday)
                            <button type="button" wire:click="hizliAdaydanEkle({{ (int) ($aday['id'] ?? 0) }})" class="flex items-center gap-2 rounded-md border border-gray-200 p-2 text-left hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50">
                                @if(filled($aday['gorsel_url'] ?? null))
                                    <img src="{{ $aday['gorsel_url'] }}" alt="Urun" class="h-10 w-10 rounded object-cover">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 text-[10px] text-gray-500 dark:bg-gray-800">YOK</div>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-semibold">{{ $aday['ad'] ?? '-' }}</div>
                                    <div class="truncate text-[11px] text-gray-600 dark:text-gray-300">{{ $aday['kod'] ?? '-' }}</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                Kisayollar: <strong>F2</strong> barkod odagi, <strong>Ctrl+F</strong> hizli ara, <strong>F7</strong> satir +1,
                <strong>Shift+F7</strong> satir -1, <strong>Del</strong> satir sil, <strong>F8</strong> odeme tipi,
                <strong>F9</strong> satisi tamamla, <strong>F4</strong> sepet beklet, <strong>F6</strong> sepet iptal onayi,
                <strong>F10</strong> hizli iade, <strong>Esc</strong> sepet temizle, <strong>Alt+1/2/3</strong> odeme sec.
            </div>
            @if($seciliKalemIndex !== null && isset($kalemler[$seciliKalemIndex]))
                <div class="mb-3 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs text-primary-900 dark:border-primary-700 dark:bg-primary-900/20 dark:text-primary-100">
                    Secili satir: <strong>{{ $kalemler[$seciliKalemIndex]['stok_adi'] ?? '-' }}</strong>
                    | Miktar: <strong>{{ number_format((float) ($kalemler[$seciliKalemIndex]['miktar'] ?? 0), 2, ',', '.') }}</strong>
                    | Etiket Adedi: <strong>{{ (int) ($etiketYazdirmaAdedi ?? 1) }}</strong>
                    <x-filament::button size="xs" color="primary" class="ml-2" wire:click="etiketYazdirmaAdediDegistir(5)">+5</x-filament::button>
                    <x-filament::button size="xs" color="primary" class="ml-1" wire:click="etiketYazdirmaAdediDegistir(10)">+10</x-filament::button>
                    <x-filament::button size="xs" color="gray" class="ml-1" wire:click="$set('etiketYazdirmaAdedi', 1)">Sifirla</x-filament::button>
                </div>
            @endif
            <div class="mb-4 flex flex-wrap gap-2">
                <x-filament::button id="pos-btn-row-plus" tabindex="21" size="sm" color="gray" icon="heroicon-o-plus" wire:click="seciliKalemMiktarArttir">Secili Satir +1</x-filament::button>
                <x-filament::button id="pos-btn-row-minus" tabindex="22" size="sm" color="gray" icon="heroicon-o-minus" wire:click="seciliKalemMiktarAzalt">Secili Satir -1</x-filament::button>
                <x-filament::button id="pos-btn-row-delete" tabindex="23" size="sm" color="danger" icon="heroicon-o-trash" wire:click="seciliKalemSil">Secili Satiri Sil</x-filament::button>
                @if(filled($this->seciliEtiketYazdirUrl()))
                    <x-filament::button
                        id="pos-btn-row-label-print"
                        tabindex="23"
                        size="sm"
                        color="primary"
                        icon="heroicon-o-tag"
                        tag="a"
                        target="_blank"
                        href="{{ $this->seciliEtiketYazdirUrl() }}"
                    >
                        Secili Urun Etiketi
                    </x-filament::button>
                @endif
                <x-filament::button id="pos-btn-hold" tabindex="24" size="sm" color="info" icon="heroicon-o-pause-circle" wire:click="sepetBeklet">Sepeti Beklet (F4)</x-filament::button>
                <x-filament::button id="pos-btn-clear" tabindex="25" size="sm" color="warning" icon="heroicon-o-x-mark" wire:click="sepetiTemizle">Sepeti Temizle</x-filament::button>
                <x-filament::button id="pos-btn-complete-print" tabindex="25" size="sm" color="info" icon="heroicon-o-printer" wire:click="satisiTamamlaVeYazdir">Kaydet + Yazdir</x-filament::button>
                <x-filament::button id="pos-btn-cash" tabindex="26" size="sm" color="success" icon="heroicon-o-banknotes" wire:click="odemeTipiSec('nakit')">Nakit (Alt+1)</x-filament::button>
                <x-filament::button id="pos-btn-card" tabindex="27" size="sm" color="success" icon="heroicon-o-credit-card" wire:click="odemeTipiSec('kart')">Kart (Alt+2)</x-filament::button>
                <x-filament::button id="pos-btn-wire" tabindex="28" size="sm" color="success" icon="heroicon-o-building-library" wire:click="odemeTipiSec('havale')">Havale (Alt+3)</x-filament::button>
                <x-filament::button id="pos-btn-quick-refund" tabindex="29" size="sm" color="info" icon="heroicon-o-arrow-uturn-left" tag="a" href="{{ \App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi::getUrl() }}">Hizli Iade (F10)</x-filament::button>
            </div>

            @if(count($bekleyenSepetler) > 0)
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900/40 dark:bg-blue-900/20">
                    <div class="mb-2 text-xs font-semibold text-blue-900 dark:text-blue-200">Bekleyen Sepetler</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($bekleyenSepetler as $i => $sepet)
                            <div class="inline-flex items-center gap-2 rounded-md border border-blue-300 bg-white px-2 py-1 text-xs dark:border-blue-700 dark:bg-blue-950/40">
                                <span>{{ $sepet['etiket'] ?? ('Sepet '.($i + 1)) }}</span>
                                <x-filament::button size="xs" color="info" wire:click="bekleyenSepetiYukle({{ $i }})">Yukle</x-filament::button>
                                <x-filament::button size="xs" color="danger" wire:click="bekleyenSepetiSil({{ $i }})">Sil</x-filament::button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(count($kalemler) === 0)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Sepet bos. Barkod alanindan urun ekleyerek baslayabilirsiniz.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">Gorsel</th>
                                <th class="px-2 py-2">Urun</th>
                                <th class="px-2 py-2">Barkod</th>
                                <th class="px-2 py-2">Miktar</th>
                                <th class="px-2 py-2">Birim Fiyat</th>
                                <th class="px-2 py-2">Iskonto</th>
                                <th class="px-2 py-2">KDV %</th>
                                <th class="px-2 py-2 text-right">Islem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kalemler as $index => $kalem)
                                <tr
                                    wire:click="kalemSec({{ $index }})"
                                    class="cursor-pointer border-t border-gray-200 dark:border-gray-700 {{ $seciliKalemIndex === $index ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}"
                                >
                                    <td class="px-2 py-2">
                                        @if(filled($kalem['gorsel_url'] ?? null))
                                            <img
                                                src="{{ $kalem['gorsel_url'] }}"
                                                alt="{{ $kalem['stok_adi'] ?? 'Urun' }}"
                                                class="h-12 w-12 rounded object-cover"
                                                x-on:click.stop="previewUrl = @js($kalem['gorsel_url'])"
                                            />
                                        @else
                                            <div class="flex h-12 w-12 items-center justify-center rounded bg-gray-100 text-[10px] text-gray-500 dark:bg-gray-800">YOK</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $kalem['stok_adi'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Kod: {{ $kalem['stok_kod'] ?? '-' }} | Birim: {{ $kalem['birim'] ?? 'AD' }}</div>
                                        @if(filled($kalem['stok_partisi_no'] ?? null))
                                            <div class="text-xs font-medium text-info-600 dark:text-info-400">Stok parçası: {{ $kalem['stok_partisi_no'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">{{ $kalem['barkod'] ?? '-' }}</td>
                                    <td class="px-2 py-2">
                                        <input
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            tabindex="{{ 100 + ($index * 10) + 1 }}"
                                            wire:click.stop
                                            wire:model.blur="kalemler.{{ $index }}.miktar"
                                            class="fi-input block w-28 rounded-lg border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            tabindex="{{ 100 + ($index * 10) + 2 }}"
                                            wire:click.stop
                                            wire:model.blur="kalemler.{{ $index }}.birim_fiyat"
                                            @disabled(! $this->fiyatDegistirmeYetkisiVarMi())
                                            class="fi-input block w-28 rounded-lg border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            tabindex="{{ 100 + ($index * 10) + 3 }}"
                                            wire:click.stop
                                            wire:model.blur="kalemler.{{ $index }}.iskonto_tutari"
                                            @disabled(! $this->iskontoUygulamaYetkisiVarMi())
                                            class="fi-input block w-24 rounded-lg border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            tabindex="{{ 100 + ($index * 10) + 4 }}"
                                            wire:click.stop
                                            wire:model.blur="kalemler.{{ $index }}.kdv_orani"
                                            class="fi-input block w-20 rounded-lg border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <x-filament::button
                                            color="danger"
                                            size="xs"
                                            icon="heroicon-o-trash"
                                            tabindex="{{ 100 + ($index * 10) + 5 }}"
                                            wire:click.stop
                                            wire:click="kalemSil({{ $index }})"
                                        >
                                            Sil
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @php($ozet = $this->sepetOzeti())
                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    <div class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Ara Toplam: <strong>{{ number_format((float) ($ozet['ara_toplam'] ?? 0), 2, ',', '.') }}</strong></div>
                    <div class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Iskonto: <strong>{{ number_format((float) ($ozet['iskonto_toplami'] ?? 0), 2, ',', '.') }}</strong></div>
                    <div class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">KDV: <strong>{{ number_format((float) ($ozet['kdv_toplami'] ?? 0), 2, ',', '.') }}</strong></div>
                    <div class="rounded-lg bg-emerald-100 px-3 py-2 dark:bg-emerald-900/30">Genel Toplam: <strong>{{ number_format((float) ($ozet['genel_toplam'] ?? 0), 2, ',', '.') }}</strong></div>
                </div>
                @if(! $this->fiyatDegistirmeYetkisiVarMi() || ! $this->iskontoUygulamaYetkisiVarMi())
                    <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                        Fiyat ve/veya iskonto alanlari yetkinize gore kilitlidir.
                    </div>
                @endif
            @endif

            <div
                x-show="previewUrl"
                x-on:click.self="previewUrl = null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                style="display: none;"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <button type="button" class="absolute -right-2 -top-2 rounded-full bg-white px-2 py-1 text-xs text-black" x-on:click="previewUrl = null">Kapat</button>
                    <img :src="previewUrl" alt="On izleme" class="max-h-[88vh] max-w-[88vw] rounded bg-white object-contain p-2" />
                </div>
            </div>
            </div>
        </x-filament::section>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 p-2 shadow-lg backdrop-blur md:hidden dark:border-gray-700 dark:bg-gray-950/95">
        <div class="grid grid-cols-4 gap-2">
            <x-filament::button size="xs" color="gray" wire:click="seciliKalemMiktarArttir">+1</x-filament::button>
            <x-filament::button size="xs" color="info" wire:click="sepetBeklet">Beklet</x-filament::button>
            <x-filament::button size="xs" color="warning" wire:click="satisiTamamlaVeYazdir">Yazdir</x-filament::button>
            <x-filament::button size="xs" color="success" wire:click="satisiTamamla">Tamamla</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

<script>
    (() => {
        const iadeUrl = @js(\App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi::getUrl());
        const focusBarkod = () => {
            const input = document.getElementById('pos-barkod-input');
            if (!input) return;
            setTimeout(() => input.focus(), 50);
        };
        const focusHizliAra = () => {
            const input = document.getElementById('pos-hizli-ara-input');
            if (!input) return;
            setTimeout(() => input.focus(), 50);
        };
        const focusOdemeTipi = () => {
            const input = document.getElementById('pos-odeme-tipi-input');
            if (!input) return;
            setTimeout(() => input.focus(), 50);
        };
        const aktifCanliInputMu = () => {
            const el = document.activeElement;
            if (!el) return false;
            const tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
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
            if (!cmp || typeof cmp.call !== 'function') return;
            cmp.call(method);
        };
        const callWith = (method, ...args) => {
            const cmp = livewireBileseni();
            if (!cmp || typeof cmp.call !== 'function') return;
            cmp.call(method, ...args);
        };

        document.addEventListener('livewire:initialized', () => {
            focusBarkod();

            if (window.Livewire && typeof window.Livewire.on === 'function') {
                window.Livewire.on('barkod-odakla', () => focusBarkod());
                window.Livewire.on('satis-fisi-ac', (payload = {}) => {
                    const url = payload?.url ?? null;
                    if (!url) return;
                    window.open(url, '_blank', 'noopener,noreferrer');
                });
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'F2') {
                event.preventDefault();
                focusBarkod();
                return;
            }
            if (event.ctrlKey && (event.key === 'f' || event.key === 'F')) {
                event.preventDefault();
                focusHizliAra();
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
                focusOdemeTipi();
                return;
            }
            if (event.key === 'F6') {
                event.preventDefault();
                if (window.confirm('Mevcut sepeti iptal edip temizlemek istiyor musunuz?')) {
                    call('sepetiTemizle');
                }
                return;
            }
            if (event.key === 'F4') {
                event.preventDefault();
                call('sepetBeklet');
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
                if (iadeUrl) {
                    window.location.href = iadeUrl;
                }
                return;
            }
            if (event.key === 'Delete' && !aktifCanliInputMu()) {
                event.preventDefault();
                call('seciliKalemSil');
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                call('sepetiTemizle');
            }
        });
    })();
</script>
