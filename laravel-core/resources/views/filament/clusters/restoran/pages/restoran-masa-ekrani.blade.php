@php
    $masalar = $this->masalar();
    $sayilar = $this->durumSayilari();
    $ozet = $this->operasyonOzeti();
    $guncelleyebilir = $this->guncellemeYetkisiVarMi();
@endphp

<x-filament-panels::page>
    <div class="restoran-cork-screen restoran-masa-ekrani space-y-4">
    <div class="restoran-cork-kpi-grid grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Bos</div>
            <div class="mt-1 text-2xl font-semibold">{{ $sayilar[\App\Models\Restoran\RestoranMasasi::DURUM_BOS] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Dolu</div>
            <div class="mt-1 text-2xl font-semibold">{{ $sayilar[\App\Models\Restoran\RestoranMasasi::DURUM_DOLU] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Rezerve</div>
            <div class="mt-1 text-2xl font-semibold">{{ $sayilar[\App\Models\Restoran\RestoranMasasi::DURUM_REZERVE] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Toplam</div>
            <div class="mt-1 text-2xl font-semibold">{{ $masalar->count() }}</div>
        </div>
    </div>

    <div class="restoran-cork-toolbar grid gap-3 lg:grid-cols-4">
        <x-filament::input.wrapper>
            <select wire:model.live="subeFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                @foreach($this->subeSecenekleri() as $deger => $etiket)
                    <option value="{{ $deger }}">{{ $etiket }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <select wire:model.live="salonFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                @foreach($this->salonSecenekleri() as $deger => $etiket)
                    <option value="{{ $deger }}">{{ $etiket }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <select wire:model.live="durumFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                <option value="tum">Tüm durumlar</option>
                <option value="{{ \App\Models\Restoran\RestoranMasasi::DURUM_BOS }}">Boş</option>
                <option value="{{ \App\Models\Restoran\RestoranMasasi::DURUM_DOLU }}">Dolu</option>
                <option value="{{ \App\Models\Restoran\RestoranMasasi::DURUM_REZERVE }}">Rezerve</option>
                <option value="{{ \App\Models\Restoran\RestoranMasasi::DURUM_KAPALI }}">Kapalı</option>
            </select>
        </x-filament::input.wrapper>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Doluluk</span>
                <span class="font-semibold">{{ number_format((float) $ozet['doluluk_orani'], 2, ',', '.') }}%</span>
            </div>
            <div class="mt-1 flex justify-between gap-3">
                <span class="text-gray-500">Açık toplam</span>
                <span class="font-semibold">{{ number_format((float) $ozet['acik_adisyon_toplami'], 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @forelse($masalar as $masa)
            @php
                $adisyon = $masa->adisyonlar->first();
                $renk = match ((string) $masa->durum) {
                    \App\Models\Restoran\RestoranMasasi::DURUM_BOS => 'border-emerald-300 dark:border-emerald-700',
                    \App\Models\Restoran\RestoranMasasi::DURUM_DOLU => 'border-rose-300 dark:border-rose-700',
                    \App\Models\Restoran\RestoranMasasi::DURUM_REZERVE => 'border-amber-300 dark:border-amber-700',
                    default => 'border-gray-200 dark:border-gray-800',
                };
            @endphp

            <div class="rounded-lg border {{ $renk }} bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-gray-950 dark:text-white">{{ $masa->ad }}</div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $masa->salon?->ad ?? 'Salon yok' }} · {{ $masa->sube?->ad ?? 'Sube yok' }}
                        </div>
                    </div>
                    <span class="restoran-cork-badge rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ str_replace('_', ' ', $masa->durum) }}
                    </span>
                </div>

                @if($adisyon)
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Adisyon</span>
                            <span class="font-medium">{{ $adisyon->adisyon_no }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Kalem</span>
                            <span class="font-medium">{{ $adisyon->kalemler_count }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Toplam</span>
                            <span class="font-semibold">{{ number_format((float) $adisyon->genel_toplam, 2, ',', '.') }} {{ $adisyon->para_birimi }}</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button tag="a" size="xs" color="gray" href="{{ $this->adisyonUrl($adisyon->id) }}">
                            Ac
                        </x-filament::button>
                        @if($guncelleyebilir && in_array($adisyon->durum, [\App\Models\Restoran\RestoranAdisyonu::DURUM_ACIK, \App\Models\Restoran\RestoranAdisyonu::DURUM_ODEMEDE], true))
                            <a
                                href="{{ $this->urunEkleUrl((int) $adisyon->id) }}"
                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
                            >
                                Ürün ekle
                            </a>
                        @endif
                        @if($guncelleyebilir && $adisyon->durum === \App\Models\Restoran\RestoranAdisyonu::DURUM_ACIK)
                            <x-filament::button size="xs" color="warning" wire:click="odemeyeAl({{ $adisyon->id }})">
                                Odemeye al
                            </x-filament::button>
                        @endif
                    </div>

                    @if($guncelleyebilir && $urunEkleAdisyonId === (int) $adisyon->id)
                        @php($menuSecenekleri = $this->menuUrunuSecenekleri((int) $adisyon->id))
                        <form wire:submit="siparisKalemiEkle" class="mt-4 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950/40">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200">
                                Ürün
                                <select
                                    wire:model="siparisFormu.menu_urunu_id"
                                    required
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="">Ürün seçin</option>
                                    @foreach($menuSecenekleri as $urunId => $urunAdi)
                                        <option value="{{ $urunId }}">{{ $urunAdi }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200">
                                    Miktar
                                    <input
                                        type="number"
                                        min="0.0001"
                                        step="0.0001"
                                        wire:model="siparisFormu.miktar"
                                        required
                                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                                    />
                                </label>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200">
                                    Mutfak notu
                                    <input
                                        type="text"
                                        wire:model="siparisFormu.mutfak_notu"
                                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                                    />
                                </label>
                            </div>

                            @if($menuSecenekleri === [])
                                <div class="text-xs text-warning-600 dark:text-warning-400">
                                    Aktif menü ürünü bulunmuyor.
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <x-filament::button type="submit" size="xs" color="success" :disabled="$menuSecenekleri === []">
                                    Siparişi ekle
                                </x-filament::button>
                                <a
                                    href="{{ $this->urunEkleKapatUrl() }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-gray-500 px-2 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500/50"
                                >
                                    Vazgeç
                                </a>
                            </div>
                        </form>
                    @endif
                @else
                    <div class="mt-4 text-sm text-gray-500">Acik adisyon yok.</div>

                    @if($guncelleyebilir && $masa->durum !== \App\Models\Restoran\RestoranMasasi::DURUM_KAPALI)
                        <div class="mt-4">
                            <x-filament::button size="xs" color="success" wire:click="masaAdisyonuAc({{ $masa->id }})">
                                Adisyon ac
                            </x-filament::button>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="restoran-cork-empty rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:col-span-2 xl:col-span-4">
                Aktif masa bulunmuyor.
            </div>
        @endforelse
    </div>
    </div>
</x-filament-panels::page>
