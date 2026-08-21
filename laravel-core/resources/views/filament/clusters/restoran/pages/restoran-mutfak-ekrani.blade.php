@php
    $ozet = $this->durumOzeti();
    $guncelleyebilir = $this->guncellemeYetkisiVarMi();
    $aktifKolonGorunumu = $this->durumFiltresi === 'aktif';
    $kalemGruplari = $aktifKolonGorunumu ? $this->kalemGruplari() : [];
    $kalemler = $aktifKolonGorunumu ? collect() : $this->kalemler();
    $kolonlar = [
        \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI => ['baslik' => 'Yeni', 'renk' => 'border-blue-200 bg-blue-50/60 dark:border-blue-900 dark:bg-blue-950/20'],
        \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => ['baslik' => 'Hazirlaniyor', 'renk' => 'border-warning-200 bg-warning-50/60 dark:border-warning-900 dark:bg-warning-950/20'],
        \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIR => ['baslik' => 'Hazir', 'renk' => 'border-success-200 bg-success-50/60 dark:border-success-900 dark:bg-success-950/20'],
    ];
@endphp

<x-filament-panels::page>
    <div class="restoran-cork-screen restoran-mutfak-ekrani space-y-4">
    <div class="restoran-cork-kpi-grid grid gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Yeni</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet[\App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Hazirlaniyor</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet[\App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Hazir</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet[\App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIR] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Geciken</div>
            <div class="mt-1 text-2xl font-semibold text-danger-600 dark:text-danger-400">{{ $ozet['geciken'] ?? 0 }}</div>
        </div>
    </div>

    <div class="restoran-cork-toolbar grid gap-3 md:grid-cols-2">
        <x-filament::input.wrapper>
            <select wire:model.live="durumFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                <option value="aktif">Aktif kalemler</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI }}">Yeni</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR }}">Hazirlaniyor</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIR }}">Hazir</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI }}">Servis edildi</option>
            </select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <select wire:model.live="siparisTipiFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                <option value="tum">Tum siparisler</option>
                <option value="masa">Masa</option>
                <option value="qr">QR</option>
                <option value="paket">Paket</option>
                <option value="online">Online</option>
                <option value="gel-al">Gel-al</option>
            </select>
        </x-filament::input.wrapper>
    </div>

    @if($aktifKolonGorunumu)
        <div class="grid gap-4 xl:grid-cols-3">
            @foreach($kolonlar as $durum => $kolon)
                @php($kolonKalemleri = $kalemGruplari[$durum] ?? collect())
                <section class="rounded-lg border {{ $kolon['renk'] }} p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $kolon['baslik'] }}</h3>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700 shadow-sm dark:bg-gray-900 dark:text-gray-200">{{ $kolonKalemleri->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($kolonKalemleri as $kalem)
                            <article @class([
                                'rounded-lg border bg-white p-3 text-sm shadow-sm dark:bg-gray-900',
                                'border-danger-300 dark:border-danger-800' => (bool) $kalem->getAttribute('gecikti_mi'),
                                'border-gray-200 dark:border-gray-800' => ! (bool) $kalem->getAttribute('gecikti_mi'),
                            ])>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-gray-950 dark:text-gray-100">{{ $kalem->urun_adi }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $kalem->adisyon?->adisyon_no }} · {{ $kalem->adisyon?->masa?->ad ?? ucfirst((string) $kalem->adisyon?->siparis_tipi) }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-sm font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                                        x{{ rtrim(rtrim((string) $kalem->miktar, '0'), '.') }}
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                                    <span @class([
                                        'font-semibold text-danger-700 dark:text-danger-300' => (bool) $kalem->getAttribute('gecikti_mi'),
                                        'text-gray-500' => ! (bool) $kalem->getAttribute('gecikti_mi'),
                                    ])>
                                        {{ (int) $kalem->getAttribute('bekleme_dakika') }} dk
                                    </span>
                                    @if($kalem->mutfak_notu)
                                        <span class="truncate text-gray-500">{{ $kalem->mutfak_notu }}</span>
                                    @endif
                                </div>

                                @if($guncelleyebilir)
                                    <div class="mt-3 flex justify-end gap-2">
                                        @if($kalem->durum === \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI)
                                            <x-filament::button size="xs" color="gray" wire:click="hazirlamayaAl({{ $kalem->id }})">Al</x-filament::button>
                                        @endif
                                        @if(in_array($kalem->durum, [\App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI, \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR], true))
                                            <x-filament::button size="xs" color="warning" wire:click="hazirIsaretle({{ $kalem->id }})">Hazir</x-filament::button>
                                        @endif
                                        @if($kalem->durum === \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIR)
                                            <x-filament::button size="xs" color="success" wire:click="servisEdildiIsaretle({{ $kalem->id }})">Servis</x-filament::button>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white/70 px-3 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/60">
                                Bu kolonda bekleyen kalem yok.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="restoran-cork-table-wrap overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="restoran-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Adisyon</th>
                        <th class="px-4 py-3">Masa</th>
                        <th class="px-4 py-3">Ürün</th>
                        <th class="px-4 py-3">Miktar</th>
                        <th class="px-4 py-3">Bekleme</th>
                        <th class="px-4 py-3">Durum</th>
                        <th class="px-4 py-3">Not</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($kalemler as $kalem)
                        <tr @class(['bg-danger-50/70 dark:bg-danger-950/20' => (bool) $kalem->getAttribute('gecikti_mi')])>
                            <td class="px-4 py-3 font-medium">{{ $kalem->adisyon?->adisyon_no }}</td>
                            <td class="px-4 py-3">{{ $kalem->adisyon?->masa?->ad ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $kalem->urun_adi }}</td>
                            <td class="px-4 py-3">{{ rtrim(rtrim((string) $kalem->miktar, '0'), '.') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'font-semibold text-danger-700 dark:text-danger-300' => (bool) $kalem->getAttribute('gecikti_mi'),
                                ])>
                                    {{ (int) $kalem->getAttribute('bekleme_dakika') }} dk
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $kalem->durum) }}</td>
                            <td class="px-4 py-3">{{ $kalem->mutfak_notu ?: '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($guncelleyebilir)
                                    <div class="flex justify-end gap-2">
                                        @if($kalem->durum === \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI)
                                            <x-filament::button size="xs" color="gray" wire:click="hazirlamayaAl({{ $kalem->id }})">Al</x-filament::button>
                                        @endif
                                        @if(in_array($kalem->durum, [\App\Models\Restoran\RestoranAdisyonKalemi::DURUM_YENI, \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR], true))
                                            <x-filament::button size="xs" color="warning" wire:click="hazirIsaretle({{ $kalem->id }})">Hazır</x-filament::button>
                                        @endif
                                        @if($kalem->durum === \App\Models\Restoran\RestoranAdisyonKalemi::DURUM_HAZIR)
                                            <x-filament::button size="xs" color="success" wire:click="servisEdildiIsaretle({{ $kalem->id }})">Servis</x-filament::button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="restoran-cork-empty px-4 py-8 text-center text-gray-500">Bekleyen mutfak kalemi yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    @endif
    </div>
</x-filament-panels::page>
