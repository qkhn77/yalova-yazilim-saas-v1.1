<x-filament-panels::page>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #etiket-yazdirma-alani,
            #etiket-yazdirma-alani * {
                visibility: visible;
            }

            #etiket-yazdirma-alani {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .etiket-kontrol-alani {
                display: none !important;
            }
        }

        .etiket-barkod-yazisi-gizli svg text {
            display: none;
        }
    </style>

    <div class="space-y-6">
        <div class="etiket-kontrol-alani max-w-6xl">
            {{ $this->form }}

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button color="warning" icon="heroicon-o-plus" wire:click="stokSepeteEkle">
                    Sepete Ekle
                </x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-cube" wire:click="seciliUrunStokAdediniKullan">
                    Stok Adedini Kullan
                </x-filament::button>
                <x-filament::button color="success" icon="heroicon-o-tag" wire:click="etiketleriOlustur">
                    Etiketleri Olustur
                </x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-cog-6-tooth" wire:click="sablonYonetiminiDegistir">
                    {{ $sablonYonetimiAcik ? 'Sablon Yonetimini Gizle' : 'Sablon Yonetimi' }}
                </x-filament::button>
                @if($sablonYonetimiAcik)
                    <x-filament::button color="primary" icon="heroicon-o-check" wire:click="sablonKaydet">
                        Sablonu Kaydet
                    </x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="sablonYeni">
                        Yeni Sablon
                    </x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="seciliSablonuYenile">
                        Secili Sablonu Yukle
                    </x-filament::button>
                    <x-filament::button color="warning" icon="heroicon-o-plus" wire:click="hazirSablonlariYukle">
                        Hazir 6 Sablonu Yukle
                    </x-filament::button>
                    <x-filament::button color="danger" icon="heroicon-o-trash" wire:click="sablonSil">
                        Secili Sablonu Sil
                    </x-filament::button>
                @endif
            </div>

            <x-filament::section heading="Etiket sepeti" class="mt-4">
                @if(count($etiketSepeti) === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Sepet bos. Tek urun basmak icin stok secip "Etiketleri Olustur" kullanabilir veya birden fazla urun icin "Sepete Ekle" diyebilirsiniz.
                    </p>
                @else
                    <div class="mb-3 flex flex-wrap items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">{{ count($etiketSepeti) }} urun</span>
                        <span>{{ $this->etiketToplamAdedi() }} etiket</span>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-trash" wire:click="sepetiTemizle">
                            Sepeti Temizle
                        </x-filament::button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                <tr>
                                    <th class="px-3 py-2">Urun</th>
                                    <th class="px-3 py-2">Barkod</th>
                                    <th class="px-3 py-2 text-right">Stok</th>
                                    <th class="px-3 py-2 text-right">Fiyat</th>
                                    <th class="px-3 py-2 text-right">Etiket</th>
                                    <th class="px-3 py-2 text-right">Islem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($etiketSepeti as $index => $satir)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-950 dark:text-white">{{ $satir['stok_adi'] ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ ($satir['kod'] ?? '') !== '' ? $satir['kod'] : '-' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $satir['barkod'] ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format((float) ($satir['stok_miktari'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $satir['fiyat'] ?? '0,00' }} {{ $satir['para_birimi'] ?? 'TRY' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <input
                                                type="number"
                                                min="1"
                                                max="500"
                                                value="{{ (int) ($satir['adet'] ?? 1) }}"
                                                wire:change="sepetSatirAdediGuncelle({{ $index }}, $event.target.value)"
                                                class="w-20 rounded-md border-gray-300 text-right text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <x-filament::button size="sm" color="danger" icon="heroicon-o-x-mark" wire:click="sepetSatiriniSil({{ $index }})">
                                                Sil
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section heading="Etiket onizleme">
            @if(count($etiketler) === 0)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Onizleme henuz olusmadi. Stok ve adet secip "Etiketleri Olustur" butonunu kullanin.
                </p>
            @else
                <div class="etiket-kontrol-alani mb-4 flex flex-wrap items-center gap-3">
                    <x-filament::button color="success" icon="heroicon-o-printer" onclick="window.print()">
                        Yazdir
                    </x-filament::button>
                    <span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        @if($this->gercekBoyutModuAktifMi())
                            Baski sim.: Gercek Boyut
                        @else
                            Baski sim.: {{ $this->onizlemeOlcekYuzdesi() }}%
                        @endif
                    </span>
                    <span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ count($etiketler) }} etiket
                    </span>
                    <span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ strtoupper((string) ($seciliSablon['barkod_tipi'] ?? 'ean13')) }}
                    </span>
                </div>
                @if(count($etiketUyarilari) > 0)
                    <div class="etiket-kontrol-alani mb-3 rounded-lg border border-warning-300 bg-warning-50 px-3 py-2 text-xs text-warning-800 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-200">
                        @foreach($etiketUyarilari as $uyari)
                            <div>{{ $uyari }}</div>
                        @endforeach
                    </div>
                @endif
                @if($this->gercekBoyutModuAktifMi())
                    <div class="etiket-kontrol-alani mb-3 rounded-lg border border-warning-300 bg-warning-50 px-3 py-2 text-xs text-warning-800 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-200">
                        Gercek boyut onizleme icin tarayici zoom degerini %100 kullanin.
                    </div>
                @endif

                @php
                    $genislik = (int) ($seciliSablon['genislik_mm'] ?? 50);
                    $yukseklik = (int) ($seciliSablon['yukseklik_mm'] ?? 30);
                @endphp

                <div
                    id="etiket-yazdirma-alani"
                    class="{{ $this->etiketAlaniSinifi() }}"
                    style="background-image: linear-gradient(to right, rgba(148, 163, 184, 0.22) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.22) 1px, transparent 1px); {{ $this->onizlemeIzgaraStili() }} {{ $this->baskiAlaniStili() }}"
                >
                    @foreach($etiketler as $etiket)
                        @php($tasarim = (string) ($etiket['tasarim_tipi'] ?? 'standart'))
                        <div
                            class="overflow-hidden rounded-lg border border-gray-300 bg-white p-2 text-black {{ $this->alanGosterilsinMi('barkod_yazisi') ? '' : 'etiket-barkod-yazisi-gizli' }}"
                            style="{{ $this->onizlemeEtiketStili() }}"
                        >
                            @if($tasarim === 'mini')
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[10px] font-semibold leading-4">{{ $etiket['stok_adi'] }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('fiyat'))
                                    <div class="text-[12px] font-bold leading-4">{{ $etiket['fiyat'] }} {{ $etiket['para_birimi'] }}</div>
                                @endif
                                <div class="mt-1">{!! $etiket['svg'] !!}</div>
                            @elseif($tasarim === 'fiyat_odakli')
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[10px] leading-4">{{ $etiket['stok_adi'] }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('fiyat'))
                                    <div class="text-[16px] font-extrabold leading-5">{{ $etiket['fiyat'] }} {{ $etiket['para_birimi'] }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('kod'))
                                    <div class="text-[9px] leading-3">Kod: {{ $etiket['kod'] ?: '-' }}</div>
                                @endif
                                <div class="mt-1">{!! $etiket['svg'] !!}</div>
                            @elseif($tasarim === 'raf')
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[11px] font-semibold leading-4">{{ $etiket['stok_adi'] }}</div>
                                @endif
                                <div class="flex items-center justify-between text-[10px] leading-4">
                                    @if($this->alanGosterilsinMi('kod'))
                                        <span>Kod: {{ $etiket['kod'] ?: '-' }}</span>
                                    @endif
                                    @if($this->alanGosterilsinMi('fiyat'))
                                        <span class="font-bold">{{ $etiket['fiyat'] }} {{ $etiket['para_birimi'] }}</span>
                                    @endif
                                </div>
                                <div class="mt-1">{!! $etiket['svg'] !!}</div>
                            @elseif($tasarim === 'kargo')
                                <div class="text-[9px] uppercase tracking-wide">Kargo Etiketi</div>
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[11px] font-semibold leading-4">{{ $etiket['stok_adi'] }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('kod'))
                                    <div class="text-[10px] leading-4">Stok Kod: {{ $etiket['kod'] ?: '-' }}</div>
                                @endif
                                <div class="mt-1">{!! $etiket['svg'] !!}</div>
                            @elseif($tasarim === 'depo')
                                <div class="text-[9px] uppercase tracking-wide">Depo Takip</div>
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[11px] font-semibold leading-4">{{ $etiket['stok_adi'] }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('kod'))
                                    <div class="text-[10px] leading-4">Kod: {{ $etiket['kod'] ?: '-' }}</div>
                                @endif
                                @if($this->alanGosterilsinMi('fiyat'))
                                    <div class="text-[10px] leading-4">Fiyat: {{ $etiket['fiyat'] }} {{ $etiket['para_birimi'] }}</div>
                                @endif
                                <div class="mt-1">{!! $etiket['svg'] !!}</div>
                            @else
                                @if($this->alanGosterilsinMi('ad'))
                                    <div class="truncate text-[11px] font-semibold leading-4">
                                        {{ $etiket['stok_adi'] }}
                                    </div>
                                @endif
                                @if($this->alanGosterilsinMi('kod') && filled($etiket['kod']))
                                    <div class="text-[10px] leading-4">
                                        Kod: {{ $etiket['kod'] }}
                                    </div>
                                @endif
                                @if($this->alanGosterilsinMi('fiyat'))
                                    <div class="text-[11px] font-bold leading-4">
                                        {{ $etiket['fiyat'] }} {{ $etiket['para_birimi'] }}
                                    </div>
                                @endif
                                @if(filled($etiket['svg']))
                                    <div class="mt-1">{!! $etiket['svg'] !!}</div>
                                @else
                                    <div class="mt-2 rounded bg-gray-100 px-2 py-1 text-[10px]">
                                        Barkod gecersiz: {{ $etiket['barkod'] }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

<script>
    (() => {
        document.addEventListener('livewire:initialized', () => {
            if (window.Livewire && typeof window.Livewire.on === 'function') {
                window.Livewire.on('etiket-oto-yazdir', () => {
                    setTimeout(() => window.print(), 250);
                });
            }
        });
    })();
</script>
